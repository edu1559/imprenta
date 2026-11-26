<?php
// Acciones sobre un cheque desde Finanzas → Cheques: ingresar uno sin pedido (canje o carga
// inicial), canjear, depositar, rechazar, volver a cartera y corregir datos. Todo queda en el
// registro de cambios (entidad 'cheque'). Ver el resumen al principio de cheques.php.
session_start();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
include_once(__DIR__ . '/../cheques.php');
$conn = conectar();

exigirTrabajadorAjax();
$idUsuario = (int)$_SESSION['idUsuario'];
$opcion = $_POST['opcion'] ?? '';

if ($opcion === 'ingresar') {
    ingresarCheque($conn, $idUsuario);
    exit;
}

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

        // Se le da a un contacto que nos da el mismo monto en otro medio: movimiento cheques → medio.
        // (Para pagarle una compra cargada está el pago de la compra en cheques.)
        case 'canjear':
            exigirEnCartera($cheque);
            $idContacto = (int)($_POST['idContacto'] ?? 0);
            $nombre = nombreContacto($conn, $idContacto);
            if (!$nombre) {
                echo "❌ Elegí a quién se le entrega el cheque.";
                exit;
            }
            $idMedio = (int)($_POST['idMedio'] ?? 0);
            $medios = mediosParaCheque($conn);
            if (!isset($medios[$idMedio])) {
                echo "❌ Elegí en qué medio nos dan la plata.";
                exit;
            }
            $idMov = movimientoCheque($conn, idMedioCheques($conn), $idMedio, (float)$cheque['monto'], $idCheque,
                                      "canje con $nombre (" . describirCheque($cheque) . ')', $idUsuario);
            $stmt = $conn->prepare("UPDATE cheques SET estado = 'entregado', fechaEstado = NOW(), idContactoDestino = ?,
                                           idMedioDestino = ?, idMovimiento = ?, nota = IF(? = '', nota, ?) WHERE id = ?");
            $stmt->bind_param('iiissi', $idContacto, $idMedio, $idMov, $nota, $nota, $idCheque);
            $stmt->execute();
            registrarModificacion($conn, 'cheque', $idCheque, null, 'canjear', null,
                                  "a $nombre por " . pesosCheque($cheque['monto']) . " en {$medios[$idMedio]}", $nota !== '' ? $nota : 'Canje');
            $mensaje = "✅ Cheque canjeado con $nombre: entran " . pesosCheque($cheque['monto']) . " en {$medios[$idMedio]}.";
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
            $idMov = movimientoCheque($conn, idMedioCheques($conn), $idMedio, (float)$cheque['monto'], $idCheque,
                                      'depósito (' . describirCheque($cheque) . ')', $idUsuario);
            $stmt = $conn->prepare("UPDATE cheques SET estado = 'entregado', fechaEstado = NOW(), idMedioDestino = ?, idMovimiento = ?,
                                           nota = IF(? = '', nota, ?) WHERE id = ?");
            $stmt->bind_param('iissi', $idMedio, $idMov, $nota, $nota, $idCheque);
            $stmt->execute();
            registrarModificacion($conn, 'cheque', $idCheque, null, 'depositar', null, "en {$medio[0]}", $nota !== '' ? $nota : 'Depositado');
            $mensaje = "✅ Cheque depositado en {$medio[0]}.";
            break;

        // Rebotó: se revierten los pagos hechos con él (pagos negativos de hoy, en cheques) y, si
        // salió por depósito o canje, ese movimiento. Los pedidos vuelven a quedar con deuda.
        // Si entró por canje o carga inicial, idMedioDevolucion: dónde nos devolvieron la plata.
        case 'rechazar':
            exigirPermisoYMotivo($conn, $motivo);
            if ($cheque['estado'] === 'rechazado') {
                echo "❌ El cheque ya está rechazado.";
                exit;
            }
            $idMedioCh = idMedioCheques($conn);
            $idDevolucion = (int)($_POST['idMedioDevolucion'] ?? 0);
            $medios = mediosParaCheque($conn);
            if ($idDevolucion && ($cheque['origen'] === 'pago' || !isset($medios[$idDevolucion]))) {
                echo "❌ Medio de devolución inválido.";
                exit;
            }
            if ($cheque['idMovimiento']) {
                $mov = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM movimientosMedios WHERE id = " . (int)$cheque['idMovimiento']));
                if ($mov) {
                    movimientoCheque($conn, $mov['idMedioDestino'], $idMedioCh, (float)$mov['monto'], $idCheque,
                                     'rechazado, se revierte ' . ($cheque['idContactoDestino'] ? 'el canje' : 'el depósito'), $idUsuario);
                }
            }
            $devuelto = '';
            if ($idDevolucion) {
                movimientoCheque($conn, $idMedioCh, $idDevolucion, (float)$cheque['monto'], $idCheque,
                                 'rechazado, nos devolvieron la plata', $idUsuario);
                $devuelto = " (nos devolvieron la plata en {$medios[$idDevolucion]})";
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
                                  'rechazado' . ($revertidos ? ' (se revirtieron los pagos de ' . implode(', ', $revertidos) . ')' : '') . $devuelto, $motivo);
            $mensaje = "✅ Cheque rechazado." . ($revertidos ? " Los pedidos " . implode(', ', $revertidos) . " vuelven a tener deuda." : '') . $devuelto;
            break;

        // Deshacer una entrega, un canje o un depósito hechos por error (no los de una compra: ahí se borra el pago)
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
                    echo "❌ El movimiento ya quedó en un cierre de caja: no se puede deshacer.";
                    exit;
                }
                if ($mov) {
                    mysqli_query($conn, "DELETE FROM movimientosMedios WHERE id = " . (int)$mov['id']);
                    registrarModificacion($conn, 'movimiento', (int)$mov['id'], null, 'borrar', json_encode($mov), null, $motivo);
                }
            }
            $medioAntes = $cheque['idMedioDestino'] ? (mediosParaCheque($conn)[(int)$cheque['idMedioDestino']] ?? '?') : null;
            [$salida, $detalle] = describirSalida($cheque, nombreContacto($conn, $cheque['idContactoDestino']), $medioAntes);
            $antes = strtolower($salida) . ' ' . $detalle;
            $stmt = $conn->prepare("UPDATE cheques SET estado = 'tenemos', fechaEstado = NOW(), idContactoDestino = NULL,
                                           idMedioDestino = NULL, idMovimiento = NULL WHERE id = ?");
            $stmt->bind_param('i', $idCheque);
            $stmt->execute();
            registrarModificacion($conn, 'cheque', $idCheque, null, 'volverCartera', $antes, 'en cartera', $motivo);
            $mensaje = "✅ El cheque volvió a cartera.";
            break;

        // Borrar un cheque ingresado por error (canje o carga inicial, en cartera). Si fue canje, se
        // borra el movimiento de entrada, mientras la caja siga abierta.
        case 'borrar':
            exigirPermisoYMotivo($conn, $motivo);
            exigirEnCartera($cheque);
            if ($cheque['origen'] === 'pago' || pagosDelCheque($conn, $idCheque)) {
                echo "❌ Este cheque entró con un pago: se borra borrando el pago del pedido.";
                exit;
            }
            if ($cheque['idMovimientoEntrada']) {
                $mov = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM movimientosMedios WHERE id = " . (int)$cheque['idMovimientoEntrada']));
                if ($mov && (fechaEnCajaCerrada($conn, $mov['fecha'], $mov['idMedioOrigen']) || fechaEnCajaCerrada($conn, $mov['fecha'], $mov['idMedioDestino']))) {
                    echo "❌ El canje ya quedó en un cierre de caja: no se puede borrar. Canjealo al revés o corregí los datos.";
                    exit;
                }
                if ($mov) {
                    mysqli_query($conn, "DELETE FROM movimientosMedios WHERE id = " . (int)$mov['id']);
                    registrarModificacion($conn, 'movimiento', (int)$mov['id'], null, 'borrar', json_encode($mov), null, $motivo);
                }
            }
            mysqli_query($conn, "DELETE FROM cheques WHERE id = $idCheque");
            registrarModificacion($conn, 'cheque', $idCheque, null, 'borrar', describirCheque($cheque) . ' por ' . pesosCheque($cheque['monto']), null, $motivo);
            $mensaje = "✅ Cheque borrado.";
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


// Cheque que entra sin un pedido. origen 'canje': un contacto nos lo da y le damos el mismo monto
// en idMedio (movimiento medio → cheques). origen 'inicial' (solo administradores): ya estaba en la
// imprenta y no mueve plata; con idPago se asocia a un pago viejo en cheques que no tiene cheque.
function ingresarCheque($conn, $idUsuario) {
    $origen = $_POST['origen'] ?? '';
    if (!in_array($origen, ['canje', 'inicial'], true)) {
        echo "❌ Elegí cómo entra el cheque.";
        return;
    }
    if ($origen === 'inicial' && !esAdministrador($conn)) {
        echo "❌ La carga inicial la hace un administrador.";
        return;
    }
    [$d, $error] = validarDatosCheque(is_array($_POST['cheque'] ?? null) ? $_POST['cheque'] : []);
    if ($error) {
        echo "❌ $error";
        return;
    }
    if ($id = chequeRepetido($conn, $d['numero'], $d['banco'])) {
        echo "❌ Ese cheque ya está cargado (cheque #$id).";
        return;
    }
    $nota = textoCorto($_POST['nota'] ?? '', 255);
    $idContacto = (int)($_POST['idContacto'] ?? 0);
    $nombre = $idContacto ? nombreContacto($conn, $idContacto) : null;
    if ($idContacto && !$nombre) {
        echo "❌ No se encontró el contacto.";
        return;
    }
    $medios = mediosParaCheque($conn);
    $idMedio = (int)($_POST['idMedio'] ?? 0);
    $pago = null;

    if ($origen === 'canje') {
        if (!$nombre) {
            echo "❌ Elegí quién nos da el cheque.";
            return;
        }
        if (!isset($medios[$idMedio])) {
            echo "❌ Elegí en qué medio le damos la plata.";
            return;
        }
    } elseif (!empty($_POST['idPago'])) {
        $pago = pagoSinCheque($conn, (int)$_POST['idPago']);
        if (!$pago) {
            echo "❌ Ese pago no es un pago en cheques sin cheque cargado.";
            return;
        }
        if ((float)$pago['monto'] > $d['monto'] + 0.009) {
            echo "❌ El pago (" . pesosCheque($pago['monto']) . ") es mayor que el monto del cheque.";
            return;
        }
        if (!$idContacto) $idContacto = (int)$pago['idContacto'] ?: 0;
    }

    mysqli_begin_transaction($conn);
    try {
        $origenGuardado = $pago ? 'pago' : $origen;
        $idEntrega = $idContacto ?: null;
        $stmt = $conn->prepare("INSERT INTO cheques (tipo, origen, numero, banco, titular, cuitTitular, monto, fechaPago,
                                                     idContactoEntrega, nota, fechaCarga, idUsuario)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param('ssssssdsisi', $d['tipo'], $origenGuardado, $d['numero'], $d['banco'], $d['titular'], $d['cuitTitular'],
                          $d['monto'], $d['fechaPago'], $idEntrega, $nota, $idUsuario);
        $stmt->execute();
        $idCheque = (int)$conn->insert_id;

        if ($origen === 'canje') {
            $idMov = movimientoCheque($conn, $idMedio, idMedioCheques($conn), $d['monto'], $idCheque,
                                      "canje con $nombre (" . describirCheque($d) . ')', $idUsuario);
            mysqli_query($conn, "UPDATE cheques SET idMovimientoEntrada = $idMov WHERE id = $idCheque");
            $texto = "por canje con $nombre: le dimos " . pesosCheque($d['monto']) . " en {$medios[$idMedio]}";
            $mensaje = "✅ Cheque #$idCheque ingresado. Salen " . pesosCheque($d['monto']) . " de {$medios[$idMedio]}.";
        } elseif ($pago) {
            mysqli_query($conn, "UPDATE pagos SET idCheque = $idCheque WHERE id = " . (int)$pago['id']);
            $texto = "carga inicial, asociado al pago #{$pago['id']} del pedido #{$pago['idPedido']}";
            $mensaje = "✅ Cheque #$idCheque cargado y asociado al pago del pedido #{$pago['idPedido']}.";
        } else {
            $texto = 'carga inicial (ya estaba en la imprenta)' . ($nombre ? ", lo dio $nombre" : '');
            $mensaje = "✅ Cheque #$idCheque cargado.";
        }
        registrarModificacion($conn, 'cheque', $idCheque, $pago ? (int)$pago['idPedido'] : null, 'ingresar', null,
                              describirCheque($d) . ' por ' . pesosCheque($d['monto']) . ", $texto", $nota !== '' ? $nota : ORIGENES_CHEQUE[$origen]);
        mysqli_commit($conn);
        echo $mensaje;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "❌ Error: " . $e->getMessage();
    }
}
