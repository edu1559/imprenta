<?php
// Acciones sobre un cheque desde Finanzas → Cheques: entregar, depositar, rechazar,
// volver a cartera y corregir datos. Todo queda en el registro de cambios (entidad 'cheque').
session_start();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
include_once(__DIR__ . '/../cheques.php');
$conn = conectar();

exigirTrabajadorAjax();
$idUsuario = (int)$_SESSION['idUsuario'];
$opcion = $_POST['opcion'] ?? '';

$cheque = cargarCheque($conn, (int)($_POST['idCheque'] ?? 0));
if (!$cheque) {
    echo "❌ No se encontró el cheque.";
    exit;
}
$idCheque = (int)$cheque['id'];
$nota = textoCorto($_POST['nota'] ?? '', 255);
$motivo = textoCorto($_POST['motivo'] ?? '', 255);

// Para las acciones que solo puede hacer un administrador o un usuario habilitado, con motivo.
function exigirPermisoYMotivo($conn, $motivo) {
    if (!puedeModificar($conn)) {
        echo "❌ No tenés permiso. Tiene que ser un administrador o un usuario habilitado, logueado.";
        exit;
    }
    if ($motivo === '') {
        echo "❌ Falta el motivo.";
        exit;
    }
}

function exigirEnCartera($cheque) {
    if ($cheque['estado'] !== 'tenemos') {
        echo "❌ El cheque ya no está en cartera (" . ESTADOS_CHEQUE[$cheque['estado']] . ").";
        exit;
    }
}

mysqli_begin_transaction($conn);
try {
    switch ($opcion) {

        // Se le da a un contacto (sin una compra cargada: para eso está el pago de la compra en cheques)
        case 'entregar':
            exigirEnCartera($cheque);
            $idContacto = (int)($_POST['idContacto'] ?? 0);
            $nombre = nombreContacto($conn, $idContacto);
            if (!$nombre) {
                echo "❌ Elegí a quién se le entrega.";
                exit;
            }
            $stmt = $conn->prepare("UPDATE cheques SET estado = 'entregado', fechaEstado = NOW(), idContactoDestino = ?,
                                           nota = IF(? = '', nota, ?) WHERE id = ?");
            $stmt->bind_param('issi', $idContacto, $nota, $nota, $idCheque);
            $stmt->execute();
            registrarModificacion($conn, 'cheque', $idCheque, null, 'entregar', null, "a $nombre", $nota !== '' ? $nota : 'Entregado');
            $mensaje = "✅ Cheque entregado a $nombre.";
            break;

        // Se deposita en un medio propio: movimiento entre medios cheques → destino, por el monto del cheque
        case 'depositar':
            exigirEnCartera($cheque);
            $idMedio = (int)($_POST['idMedio'] ?? 0);
            $medio = mysqli_fetch_row(mysqli_query($conn, "SELECT mp.medio FROM mediosPago mp
                                                           INNER JOIN cierreMedios cm ON cm.idMedio = mp.id
                                                           WHERE mp.visible = 1 AND mp.id = $idMedio"));
            if (!$medio || esMedioCheques($conn, $idMedio)) {
                echo "❌ Elegí en qué medio se deposita.";
                exit;
            }
            $origen = idMedioCheques($conn);
            $monto = (float)$cheque['monto'];
            $notaMov = textoCorto("Cheque #$idCheque: depósito (" . describirCheque($cheque) . ')', 255);
            $stmt = $conn->prepare("INSERT INTO movimientosMedios (fecha, idMedioOrigen, idMedioDestino, monto, nota, idUsuario)
                                    VALUES (NOW(), ?, ?, ?, ?, ?)");
            $stmt->bind_param('iidsi', $origen, $idMedio, $monto, $notaMov, $idUsuario);
            $stmt->execute();
            $idMov = (int)$conn->insert_id;
            $stmt = $conn->prepare("UPDATE cheques SET estado = 'entregado', fechaEstado = NOW(), idMedioDestino = ?, idMovimiento = ?,
                                           nota = IF(? = '', nota, ?) WHERE id = ?");
            $stmt->bind_param('iissi', $idMedio, $idMov, $nota, $nota, $idCheque);
            $stmt->execute();
            registrarModificacion($conn, 'cheque', $idCheque, null, 'depositar', null, "en {$medio[0]}", $nota !== '' ? $nota : 'Depositado');
            $mensaje = "✅ Cheque depositado en {$medio[0]}.";
            break;

        // Rebotó: se revierten los pagos hechos con él (pagos negativos de hoy, en cheques) y, si
        // estaba depositado, el depósito. Los pedidos vuelven a quedar con deuda.
        case 'rechazar':
            exigirPermisoYMotivo($conn, $motivo);
            if ($cheque['estado'] === 'rechazado') {
                echo "❌ El cheque ya está rechazado.";
                exit;
            }
            $idMedioCh = idMedioCheques($conn);
            if ($cheque['idMovimiento']) {
                $mov = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM movimientosMedios WHERE id = " . (int)$cheque['idMovimiento']));
                if ($mov) {
                    $notaMov = "Cheque #$idCheque: rechazado, se revierte el depósito";
                    $stmt = $conn->prepare("INSERT INTO movimientosMedios (fecha, idMedioOrigen, idMedioDestino, monto, nota, idUsuario)
                                            VALUES (NOW(), ?, ?, ?, ?, ?)");
                    $stmt->bind_param('iidsi', $mov['idMedioDestino'], $idMedioCh, $mov['monto'], $notaMov, $idUsuario);
                    $stmt->execute();
                }
            }
            // un pago negativo por pedido, por lo que se pagó con el cheque en ese pedido
            $porPedido = [];
            foreach (pagosDelCheque($conn, $idCheque) as $pg) {
                $porPedido[$pg['idPedido']] = ($porPedido[$pg['idPedido']] ?? 0) + (float)$pg['monto'];
            }
            $revertidos = [];
            foreach ($porPedido as $idPedido => $monto) {
                if ($monto < 0.009) continue;
                $negativo = -round($monto, 2);
                $stmt = $conn->prepare("INSERT INTO pagos (idPedido, fecha, monto, idMedioPago, idUsuario, idCheque) VALUES (?, NOW(), ?, ?, ?, ?)");
                $stmt->bind_param('idiii', $idPedido, $negativo, $idMedioCh, $idUsuario, $idCheque);
                $stmt->execute();
                $idPagoNeg = (int)$conn->insert_id;
                recalcularPagosPedido($conn, $idPedido);
                registrarModificacion($conn, 'pago', $idPagoNeg, $idPedido, 'rechazoCheque', null,
                                      pesosCheque($negativo) . " por el cheque #$idCheque rechazado", $motivo);
                $revertidos[] = "#$idPedido";
            }
            $stmt = $conn->prepare("UPDATE cheques SET estado = 'rechazado', fechaEstado = NOW() WHERE id = ?");
            $stmt->bind_param('i', $idCheque);
            $stmt->execute();
            registrarModificacion($conn, 'cheque', $idCheque, null, 'rechazar', ESTADOS_CHEQUE[$cheque['estado']],
                                  'rechazado' . ($revertidos ? ' (se revirtieron los pagos de ' . implode(', ', $revertidos) . ')' : ''), $motivo);
            $mensaje = "✅ Cheque rechazado." . ($revertidos ? " Los pedidos " . implode(', ', $revertidos) . " vuelven a tener deuda." : '');
            break;

        // Deshacer una entrega o un depósito hechos por error (no los de una compra: ahí se borra el pago)
        case 'volverCartera':
            exigirPermisoYMotivo($conn, $motivo);
            if ($cheque['estado'] !== 'entregado') {
                echo "❌ Solo un cheque entregado puede volver a cartera.";
                exit;
            }
            foreach (pagosDelCheque($conn, $idCheque) as $pg) {
                if ((int)$pg['idTipoPedido'] === TIPO_PEDIDO_COMPRA) {
                    echo "❌ Se entregó con el pago de la compra #{$pg['idPedido']}: borrá ese pago y el cheque vuelve a cartera.";
                    exit;
                }
            }
            if ($cheque['idMovimiento']) {
                $mov = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM movimientosMedios WHERE id = " . (int)$cheque['idMovimiento']));
                if ($mov && (fechaEnCajaCerrada($conn, $mov['fecha'], $mov['idMedioOrigen']) || fechaEnCajaCerrada($conn, $mov['fecha'], $mov['idMedioDestino']))) {
                    echo "❌ El depósito ya quedó en un cierre de caja: no se puede deshacer.";
                    exit;
                }
                if ($mov) {
                    mysqli_query($conn, "DELETE FROM movimientosMedios WHERE id = " . (int)$mov['id']);
                    registrarModificacion($conn, 'movimiento', (int)$mov['id'], null, 'borrar', json_encode($mov), null, $motivo);
                }
            }
            $antes = $cheque['idMedioDestino'] ? 'depositado' : 'entregado a ' . (nombreContacto($conn, $cheque['idContactoDestino']) ?? '?');
            $stmt = $conn->prepare("UPDATE cheques SET estado = 'tenemos', fechaEstado = NOW(), idContactoDestino = NULL,
                                           idMedioDestino = NULL, idMovimiento = NULL WHERE id = ?");
            $stmt->bind_param('i', $idCheque);
            $stmt->execute();
            registrarModificacion($conn, 'cheque', $idCheque, null, 'volverCartera', $antes, 'en cartera', $motivo);
            $mensaje = "✅ El cheque volvió a cartera.";
            break;

        // Corregir los datos (no el monto: está atado a los pagos)
        case 'editar':
            exigirPermisoYMotivo($conn, $motivo);
            $datos = is_array($_POST['cheque'] ?? null) ? $_POST['cheque'] : [];
            [$d, $error] = validarDatosCheque(['monto' => $cheque['monto']] + $datos);
            if ($error) {
                echo "❌ $error";
                exit;
            }
            if ($id = chequeRepetido($conn, $d['numero'], $d['banco'], $idCheque)) {
                echo "❌ Ya hay otro cheque con ese número y banco (#$id).";
                exit;
            }
            $campos = ['tipo', 'numero', 'banco', 'titular', 'cuitTitular', 'fechaPago'];
            $cambios = [];
            foreach ($campos as $c) {
                if ((string)$cheque[$c] !== $d[$c]) $cambios[] = "$c: «{$cheque[$c]}» → «{$d[$c]}»";
            }
            $notaNueva = textoCorto($_POST['cheque']['nota'] ?? $cheque['nota'], 255);
            if ($notaNueva !== $cheque['nota']) $cambios[] = "nota: «{$cheque['nota']}» → «{$notaNueva}»";
            if (!$cambios) {
                echo "No hay cambios para guardar.";
                exit;
            }
            $stmt = $conn->prepare("UPDATE cheques SET tipo = ?, numero = ?, banco = ?, titular = ?, cuitTitular = ?, fechaPago = ?, nota = ? WHERE id = ?");
            $stmt->bind_param('sssssssi', $d['tipo'], $d['numero'], $d['banco'], $d['titular'], $d['cuitTitular'], $d['fechaPago'], $notaNueva, $idCheque);
            $stmt->execute();
            registrarModificacion($conn, 'cheque', $idCheque, null, 'editar', null, implode('; ', $cambios), $motivo);
            $mensaje = "✅ Datos del cheque corregidos.";
            break;

        default:
            echo "❌ Acción desconocida.";
            exit;
    }
    mysqli_commit($conn);
    echo $mensaje;
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo "❌ Error: " . $e->getMessage();
}
