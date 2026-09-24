<?php
session_start();
include_once('../conexion.php');
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

        $stmt = $conn->prepare("UPDATE cierreMedios SET montoUC = ?, diferenciaUC = ?, fechaUC = NOW() WHERE idMedio = ?");
        $stmt->bind_param('ddi', $montoReal, $dif, $idM);
        echo $stmt->execute() ? "Cierre parcial exitoso" : "Error";
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

        mysqli_begin_transaction($conn);

        try {
            $valoresCierre = array_fill_keys($columnasCierre, 0);

            $stmtUpd = $conn->prepare("UPDATE cierreMedios SET montoUC = ?, diferenciaUC = ?, fechaUC = NOW() WHERE idMedio = ?");
            $stmtNombre = $conn->prepare("SELECT medio FROM cierreMedios WHERE idMedio = ?");

            foreach ($medios as $m) {
                $id = (int)$m['id'];
                $real = (float)$m['real'];
                $dif = $real - (float)$m['calc'];

                $stmtUpd->bind_param('ddi', $real, $dif, $id);
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
                             VALUES (NOW(), $placeholders, ?, ?)";

            $stmtHist = $conn->prepare($sqlHistorial);
            $tipos = str_repeat('d', count($cols)) . 'di';
            $valores = array_values($valoresCierre);
            $valores[] = $totalDif;
            $valores[] = $idUsuario;
            $stmtHist->bind_param($tipos, ...$valores);
            $stmtHist->execute();

            mysqli_commit($conn);
            echo "✅ Cierre total completado con éxito. Historial actualizado.";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "❌ Error en el cierre: " . $e->getMessage();
        }
        break;

    case 'eliminarCierre':
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM cierre WHERE id = ?");
        $stmt->bind_param('i', $id);
        echo $stmt->execute() ? "OK" : "Error";
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

        $sqlCheck = $conn->prepare("SELECT monto, montoPagado FROM pedidos WHERE id = ?");
        $sqlCheck->bind_param('i', $idPedido);
        $sqlCheck->execute();
        $pedido = $sqlCheck->get_result()->fetch_assoc();

        $montoTotal          = (float)$pedido['monto'];
        $montoPagadoAnterior = (float)$pedido['montoPagado'];

        $sqlPago = $conn->prepare("INSERT INTO pagos (idPedido, fecha, monto, idMedioPago, idUsuario) VALUES (?, NOW(), ?, ?, ?)");
        $sqlPago->bind_param('idii', $idPedido, $monto, $idMedioPago, $idUsuario);

        if ($sqlPago->execute()) {
            $nuevoTotalPagado = $montoPagadoAnterior + $monto;

            if (abs($montoTotal - $nuevoTotalPagado) < 0.1) {
                $estadoPago = 3; // Pagado
                $nuevoTotalPagado = $montoTotal;
            } else {
                $estadoPago = 2; // Pago Parcial
            }

            $sqlUpd = $conn->prepare("UPDATE pedidos SET montoPagado = ?, estadoPago = ? WHERE id = ?");
            $sqlUpd->bind_param('dii', $nuevoTotalPagado, $estadoPago, $idPedido);

            echo $sqlUpd->execute() ? "✅ Pago registrado y pedido actualizado." : "❌ Error al actualizar pedido: " . mysqli_error($conn);
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

        $idPedido      = $pagoAnterior['idPedido'];
        $montoAnterior = (float)$pagoAnterior['monto'];
        $diferencia    = $nuevoMonto - $montoAnterior;

        $sqlUpdPago = $conn->prepare("UPDATE pagos SET monto = ?, idMedioPago = ? WHERE id = ?");
        $sqlUpdPago->bind_param('dii', $nuevoMonto, $idMedioPago, $idPago);

        if ($sqlUpdPago->execute()) {
            // Ajustamos el pedido por la diferencia entre el monto viejo y el nuevo
            $sqlUpdPedido = $conn->prepare("UPDATE pedidos SET
                                            montoPagado = montoPagado + ?,
                                            estadoPago = IF(monto - (montoPagado + ?) <= 0.1, 3, 2)
                                            WHERE id = ?");
            $sqlUpdPedido->bind_param('ddi', $diferencia, $diferencia, $idPedido);
            echo $sqlUpdPedido->execute() ? "✅ Pago actualizado." : "❌ Error al actualizar el pedido.";
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
            $idPed = $pago['idPedido'];
            $montoABorrar = $pago['monto'];

            $del = $conn->prepare("DELETE FROM pagos WHERE id = ?");
            $del->bind_param('i', $idPago);
            $del->execute();

            $upd = $conn->prepare("UPDATE pedidos SET
                                   montoPagado = montoPagado - ?,
                                   estadoPago = IF(montoPagado - ? <= 0, 1, 2)
                                   WHERE id = ?");
            $upd->bind_param('ddi', $montoABorrar, $montoABorrar, $idPed);
            $upd->execute();
            echo "✅ Pago eliminado.";
        }
        break;
}
