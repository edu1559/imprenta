<?php
session_start();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
$conn = conectar();

$opcion = $_POST['opcion'] ?? $_GET['opcion'] ?? '';
exigirTrabajadorAjax();
$idUsuario = (int)$_SESSION['idUsuario'];

switch ($opcion) {

    // -------------------------------------------------------------
    // Reasignar el medio de pago de un pago puntual.
    // -------------------------------------------------------------
    case 'cambiaMedio':
        $pago = validarModificacionPago($conn, (int)$_POST['idPago']);
        if (!$pago) break;
        $idMedio = (int)$_POST['idMedio'];
        if ($idMedio === (int)$pago['idMedioPago']) {
            echo "OK";
            break;
        }
        if (fechaEnCajaCerrada($conn, $pago['fecha'], $idMedio)) {
            echo "No se puede mover: la caja de destino ya se cerró después de la fecha de este pago.";
            break;
        }

        mysqli_begin_transaction($conn);
        try {
            $stmt = $conn->prepare("UPDATE pagos SET idMedioPago = ? WHERE id = ?");
            $stmt->bind_param('ii', $idMedio, $pago['id']);
            $stmt->execute();
            registrarModificacion($conn, 'pago', $pago['id'], $pago['idPedido'], 'cambiarMedio',
                                  $pago['medio'], nombreMedio($conn, $idMedio), $_POST['motivo']);
            mysqli_commit($conn);
            echo "OK";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "Error: " . $e->getMessage();
        }
        break;

    // -------------------------------------------------------------
    // Cierre PARCIAL: cierra un solo medio de pago.
    // -------------------------------------------------------------
    case 'cierre':
        $idM = (int)$_POST['idMedio'];
        $montoReal = (float)$_POST['montoReal'];
        $montoCalculado = (float)$_POST['montoCalculado'];
        $dif = $montoReal - $montoCalculado;

        $anterior = estadoCierreMedios($conn, $idM);

        $stmt = $conn->prepare("UPDATE cierreMedios SET montoUC = ?, diferenciaUC = ?, fechaUC = NOW() WHERE idMedio = ?");
        $stmt->bind_param('ddi', $montoReal, $dif, $idM);
        if ($stmt->execute()) {
            registrarModificacion($conn, 'cierre', $idM, null, 'cierreParcial', json_encode($anterior), (string)$montoReal, 'Cierre parcial');
            echo "Cierre parcial exitoso";
        } else {
            echo "Error";
        }
        break;

    // -------------------------------------------------------------
    // Cierre TOTAL: cierra todos los medios de pago a la vez y
    // registra el resultado en el historial (tabla cierre).
    // -------------------------------------------------------------
    case 'cerrarTodos':
        $medios = json_decode($_POST['medios'], true);
        $totalDif = (float)$_POST['totalDiferencia'];

        // Columnas de la tabla histórica 'cierre': se llaman igual que el
        // medio en cierreMedios.
        $columnasCierre = ['efectivo', 'BcoMacro', 'mercadoPago', 'cheques', 'invMacro', 'dolares', 'brubank', 'naranjaX'];

        // Una sola fecha para el historial y para cada medio: así, al borrar
        // el cierre, se sabe qué medios siguen apuntando a él.
        $ahora = date('Y-m-d H:i:s');

        mysqli_begin_transaction($conn);

        try {
            $valoresCierre = array_fill_keys($columnasCierre, 0);
            $estadoAnterior = estadoCierreMedios($conn);

            $stmtUpd = $conn->prepare("UPDATE cierreMedios SET montoUC = ?, diferenciaUC = ?, fechaUC = ? WHERE idMedio = ?");
            $stmtNombre = $conn->prepare("SELECT medio FROM cierreMedios WHERE idMedio = ?");

            foreach ($medios as $m) {
                $id = (int)$m['id'];
                $real = (float)$m['real'];
                $dif = $real - (float)$m['calc'];

                $stmtUpd->bind_param('ddsi', $real, $dif, $ahora, $id);
                $stmtUpd->execute();

                $stmtNombre->bind_param('i', $id);
                $stmtNombre->execute();
                $nombreMedio = $stmtNombre->get_result()->fetch_assoc()['medio'] ?? null;

                // Un medio sin columna en 'cierre' perdería su monto en el
                // historial: mejor cortar el cierre y avisar.
                if (!array_key_exists($nombreMedio ?? '', $valoresCierre)) {
                    throw new Exception("el medio '" . ($nombreMedio ?? $id) . "' no tiene columna en el historial de cierres.");
                }
                $valoresCierre[$nombreMedio] = $real;
            }

            // 'suma' es columna GENERADA (STORED) en la tabla 'cierre': no se
            // puede insertar un valor explícito ahí, MySQL calcula sola.
            $cols = array_keys($valoresCierre);
            $placeholders = implode(',', array_fill(0, count($cols), '?'));
            $sqlHistorial = "INSERT INTO cierre (fecha, " . implode(',', $cols) . ", diferencia, idUsuarioCierre)
                             VALUES (?, $placeholders, ?, ?)";

            $stmtHist = $conn->prepare($sqlHistorial);
            $tipos = 's' . str_repeat('d', count($cols)) . 'di';
            $valores = array_merge([$ahora], array_values($valoresCierre));
            $valores[] = $totalDif;
            $valores[] = $idUsuario;
            $stmtHist->bind_param($tipos, ...$valores);
            $stmtHist->execute();
            $idCierre = $conn->insert_id;

            // Guardamos cómo estaban los medios antes de cerrar: es lo que
            // permite deshacer el cierre si se borra del historial.
            registrarModificacion($conn, 'cierre', $idCierre, null, 'cierreTotal', json_encode($estadoAnterior), null, 'Cierre total');

            mysqli_commit($conn);
            echo "✅ Cierre total completado con éxito. Historial actualizado.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "❌ Error en el cierre: " . $e->getMessage();
        }
        break;

    // -------------------------------------------------------------
    // Borrar un cierre del historial y deshacerlo: los medios que todavía
    // apuntan a ese cierre vuelven a su fecha y saldo anteriores.
    // Solo administradores, con motivo.
    // -------------------------------------------------------------
    case 'eliminarCierre':
        $id = (int)$_POST['id'];
        $motivo = trim($_POST['motivo'] ?? '');

        if (!esAdministrador($conn)) {
            echo "Solo un administrador logueado puede borrar un cierre.";
            break;
        }
        if ($motivo === '') {
            echo "Falta el motivo.";
            break;
        }

        $stmt = $conn->prepare("SELECT fecha FROM cierre WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $cierre = $stmt->get_result()->fetch_assoc();
        if (!$cierre) {
            echo "No se encontró el cierre.";
            break;
        }

        // Medios cuyo último cierre es este (si después hubo otro cierre,
        // ese medio ya no depende de este y no se toca).
        $stmt = $conn->prepare("SELECT idMedio FROM cierreMedios WHERE fechaUC = ?");
        $stmt->bind_param('s', $cierre['fecha']);
        $stmt->execute();
        $mediosAfectados = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'idMedio');

        $anterior = null;
        if ($mediosAfectados) {
            $stmt = $conn->prepare("SELECT valorAnterior FROM modificaciones
                                    WHERE entidad = 'cierre' AND accion = 'cierreTotal' AND idEntidad = ?
                                    ORDER BY id DESC LIMIT 1");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();
            $anterior = $fila ? json_decode($fila['valorAnterior'], true) : null;

            if (!$anterior) {
                echo "Este cierre es anterior al registro de cambios y no se puede deshacer automáticamente.";
                break;
            }
        }

        mysqli_begin_transaction($conn);
        try {
            $upd = $conn->prepare("UPDATE cierreMedios SET fechaUC = ?, montoUC = ?, diferenciaUC = ? WHERE idMedio = ?");
            foreach ($mediosAfectados as $idM) {
                $previo = $anterior[$idM] ?? null;
                if (!$previo) continue;
                $upd->bind_param('sddi', $previo['fechaUC'], $previo['montoUC'], $previo['diferenciaUC'], $idM);
                $upd->execute();
            }

            $del = $conn->prepare("DELETE FROM cierre WHERE id = ?");
            $del->bind_param('i', $id);
            $del->execute();

            registrarModificacion($conn, 'cierre', $id, null, 'borrar', $cierre['fecha'], null, $motivo);

            mysqli_commit($conn);
            echo "OK";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "Error: " . $e->getMessage();
        }
        break;

    // -------------------------------------------------------------
    // Arqueo de efectivo (recuento de billetes).
    // -------------------------------------------------------------
    case 'guardarArqueo':
        $billetes = $_POST['billetes'] ?? null;

        if (!$billetes || !is_array($billetes)) {
            echo "No se recibieron datos válidos.";
            break;
        }

        mysqli_begin_transaction($conn);
        try {
            $stmt = $conn->prepare("INSERT INTO arqueo_efectivo (denominacion, cantidad, idUsuario, fecha) VALUES (?, ?, ?, NOW())");
            foreach ($billetes as $denominacion => $cantidad) {
                $denominacion = (int)$denominacion;
                $cantidad = (int)$cantidad;
                $stmt->bind_param('iii', $denominacion, $cantidad, $idUsuario);
                $stmt->execute();
            }
            mysqli_commit($conn);
            echo "success";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "error: " . $e->getMessage();
        }
        break;

    // -------------------------------------------------------------
    // ABM de pagos puntuales (alta / baja / edición).
    // -------------------------------------------------------------
    case 'agregarPago':
        $idPedido    = (int)$_POST['idPedido'];
        $monto       = (float)$_POST['monto'];
        $idMedioPago = (int)$_POST['idMedioPago'];

        $pedido = cargarPedido($conn, $idPedido);
        if (!$pedido || $pedido['anulado']) {
            echo "❌ El pedido está anulado o no existe.";
            break;
        }

        $sqlPago = $conn->prepare("INSERT INTO pagos (idPedido, fecha, monto, idMedioPago, idUsuario) VALUES (?, NOW(), ?, ?, ?)");
        $sqlPago->bind_param('idii', $idPedido, $monto, $idMedioPago, $idUsuario);

        if ($sqlPago->execute()) {
            echo recalcularPagosPedido($conn, $idPedido) ? "✅ Pago registrado y pedido actualizado." : "❌ Error al actualizar pedido: " . mysqli_error($conn);
        } else {
            echo "❌ Error al guardar el pago.";
        }
        break;

    case 'actualizarPago':
        $pago = validarModificacionPago($conn, (int)$_POST['id']);
        if (!$pago) break;
        $nuevoMonto  = round((float)$_POST['monto'], 2);
        $idMedioPago = (int)$_POST['idMedioPago'];
        $cambiaMonto = abs($nuevoMonto - (float)$pago['monto']) > 0.001;
        $cambiaMedio = $idMedioPago !== (int)$pago['idMedioPago'];

        if (!$cambiaMonto && !$cambiaMedio) {
            echo "No hay cambios para guardar.";
            break;
        }
        if ($cambiaMedio && fechaEnCajaCerrada($conn, $pago['fecha'], $idMedioPago)) {
            echo "❌ No se puede pasar a ese medio: su caja ya se cerró después de la fecha de este pago.";
            break;
        }

        mysqli_begin_transaction($conn);
        try {
            $stmt = $conn->prepare("UPDATE pagos SET monto = ?, idMedioPago = ? WHERE id = ?");
            $stmt->bind_param('dii', $nuevoMonto, $idMedioPago, $pago['id']);
            $stmt->execute();
            recalcularPagosPedido($conn, (int)$pago['idPedido']);

            if ($cambiaMonto) {
                registrarModificacion($conn, 'pago', $pago['id'], $pago['idPedido'], 'modificarMonto',
                                      $pago['monto'], $nuevoMonto, $_POST['motivo']);
            }
            if ($cambiaMedio) {
                registrarModificacion($conn, 'pago', $pago['id'], $pago['idPedido'], 'cambiarMedio',
                                      $pago['medio'], nombreMedio($conn, $idMedioPago), $_POST['motivo']);
            }
            mysqli_commit($conn);
            echo "✅ Pago actualizado.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "❌ Error al actualizar el pago: " . $e->getMessage();
        }
        break;

    case 'borrarPago':
        $pago = validarModificacionPago($conn, (int)$_POST['id']);
        if (!$pago) break;

        mysqli_begin_transaction($conn);
        try {
            $del = $conn->prepare("DELETE FROM pagos WHERE id = ?");
            $del->bind_param('i', $pago['id']);
            $del->execute();
            recalcularPagosPedido($conn, (int)$pago['idPedido']);
            registrarModificacion($conn, 'pago', $pago['id'], $pago['idPedido'], 'borrar',
                                  describirPago($pago), null, $_POST['motivo']);
            mysqli_commit($conn);
            echo "✅ Pago eliminado.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "❌ Error al borrar el pago: " . $e->getMessage();
        }
        break;

    // -------------------------------------------------------------
    // Pago de ajuste: corrige un pago que ya no se puede tocar (caja o
    // pedido cerrados). Es un pago nuevo, de hoy, que puede ser negativo,
    // y entra en la caja del día.
    // -------------------------------------------------------------
    case 'ajustePago':
        $idPedido    = (int)$_POST['idPedido'];
        $monto       = round((float)$_POST['monto'], 2);
        $idMedioPago = (int)$_POST['idMedioPago'];
        $motivo      = trim($_POST['motivo'] ?? '');

        if (!puedeModificar($conn)) {
            echo "No tenés permiso para cargar ajustes. Tiene que ser un administrador o un usuario habilitado, logueado.";
            break;
        }
        if ($motivo === '') {
            echo "Falta el motivo.";
            break;
        }
        if (abs($monto) < 0.01) {
            echo "El monto del ajuste no puede ser cero.";
            break;
        }
        $pedido = cargarPedido($conn, $idPedido);
        if (!$pedido || $pedido['anulado']) {
            echo "El pedido está anulado o no existe.";
            break;
        }

        mysqli_begin_transaction($conn);
        try {
            $stmt = $conn->prepare("INSERT INTO pagos (idPedido, fecha, monto, idMedioPago, idUsuario) VALUES (?, NOW(), ?, ?, ?)");
            $stmt->bind_param('idii', $idPedido, $monto, $idMedioPago, $idUsuario);
            $stmt->execute();
            $idAjuste = $conn->insert_id;
            recalcularPagosPedido($conn, $idPedido);
            registrarModificacion($conn, 'pago', $idAjuste, $idPedido, 'ajuste',
                                  null, $monto . ' en ' . nombreMedio($conn, $idMedioPago), $motivo);
            mysqli_commit($conn);
            echo "✅ Ajuste registrado.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "❌ Error al registrar el ajuste: " . $e->getMessage();
        }
        break;
}

// Foto de cierreMedios (uno o todos), indexada por idMedio.
function estadoCierreMedios($conn, $idMedio = null) {
    $sql = "SELECT idMedio, fechaUC, montoUC, diferenciaUC FROM cierreMedios";
    if ($idMedio !== null) $sql .= " WHERE idMedio = " . (int)$idMedio;
    $res = mysqli_query($conn, $sql);
    $estado = [];
    while ($f = mysqli_fetch_assoc($res)) {
        $estado[$f['idMedio']] = $f;
    }
    return $estado;
}

// Controles comunes antes de borrar, modificar o mover un pago: usuario con
// permiso, motivo, y que ni el pedido ni la caja del pago estén cerrados.
// Devuelve el pago, o null después de mostrar el error.
function validarModificacionPago($conn, $idPago) {
    if (!puedeModificar($conn)) {
        echo "No tenés permiso para modificar pagos. Tiene que ser un administrador o un usuario habilitado, logueado.";
        return null;
    }
    $_POST['motivo'] = trim($_POST['motivo'] ?? '');
    if ($_POST['motivo'] === '') {
        echo "Falta el motivo.";
        return null;
    }
    $pago = cargarPago($conn, $idPago);
    if (!$pago) {
        echo "No se encontró el pago.";
        return null;
    }
    $bloqueo = bloqueoPago($pago);
    if ($bloqueo) {
        echo $bloqueo;
        return null;
    }
    return $pago;
}

function nombreMedio($conn, $idMedio) {
    $stmt = $conn->prepare("SELECT medio FROM mediosPago WHERE id = ?");
    $stmt->bind_param('i', $idMedio);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['medio'] ?? (string)$idMedio;
}
