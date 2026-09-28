<?php
session_start();
include_once('../conexion.php');
include_once('../auditoria.php');
$conn = conectar();

$opcion = $_POST['opcion'] ?? $_GET['opcion'] ?? '';
$idUsuario = $_SESSION['idUsuario'] ?? 1;

switch ($opcion) {

    // -------------------------------------------------------------
    // Reasignar el medio de pago de un pago puntual.
    // -------------------------------------------------------------
    case 'cambiaMedio':
        $idPago = (int)$_POST['idPago'];
        $idMedio = (int)$_POST['idMedio'];
        $stmt = $conn->prepare("UPDATE pagos SET idMedioPago = ? WHERE id = ?");
        $stmt->bind_param('ii', $idMedio, $idPago);
        echo $stmt->execute() ? "OK" : "Error";
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

        // Columnas reales de la tabla histórica 'cierre' (además de
        // 'credito', que existe como columna pero queda fuera de la
        // suma generada por diseño y no se carga acá).
        $columnasCierre = ['efectivo', 'transferencia', 'mercadoPago', 'cheques', 'dolares', 'brubank', 'naranjaX'];

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

                if ($nombreMedio !== null && array_key_exists($nombreMedio, $valoresCierre)) {
                    $valoresCierre[$nombreMedio] = $real;
                }
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

        $sqlPago = $conn->prepare("INSERT INTO pagos (idPedido, fecha, monto, idMedioPago, idUsuario) VALUES (?, NOW(), ?, ?, ?)");
        $sqlPago->bind_param('idii', $idPedido, $monto, $idMedioPago, $idUsuario);

        if ($sqlPago->execute()) {
            echo recalcularPagosPedido($conn, $idPedido) ? "✅ Pago registrado y pedido actualizado." : "❌ Error al actualizar pedido: " . mysqli_error($conn);
        } else {
            echo "❌ Error al guardar el pago.";
        }
        break;

    case 'actualizarPago':
        $idPago      = (int)$_POST['id'];
        $nuevoMonto  = (float)$_POST['monto'];
        $idMedioPago = (int)$_POST['idMedioPago'];

        $sqlAnterior = $conn->prepare("SELECT idPedido, monto FROM pagos WHERE id = ?");
        $sqlAnterior->bind_param('i', $idPago);
        $sqlAnterior->execute();
        $pagoAnterior = $sqlAnterior->get_result()->fetch_assoc();

        if (!$pagoAnterior) {
            echo "❌ No se encontró el pago.";
            break;
        }

        $idPedido = (int)$pagoAnterior['idPedido'];

        $sqlUpdPago = $conn->prepare("UPDATE pagos SET monto = ?, idMedioPago = ? WHERE id = ?");
        $sqlUpdPago->bind_param('dii', $nuevoMonto, $idMedioPago, $idPago);

        if ($sqlUpdPago->execute()) {
            echo recalcularPagosPedido($conn, $idPedido) ? "✅ Pago actualizado." : "❌ Error al actualizar el pedido.";
        } else {
            echo "❌ Error al actualizar el pago.";
        }
        break;

    case 'borrarPago':
        $idPago = (int)$_POST['id'];

        $resP = $conn->prepare("SELECT idPedido, monto FROM pagos WHERE id = ?");
        $resP->bind_param('i', $idPago);
        $resP->execute();
        $pago = $resP->get_result()->fetch_assoc();

        if ($pago) {
            $del = $conn->prepare("DELETE FROM pagos WHERE id = ?");
            $del->bind_param('i', $idPago);
            $del->execute();

            recalcularPagosPedido($conn, (int)$pago['idPedido']);
            echo "✅ Pago eliminado.";
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
