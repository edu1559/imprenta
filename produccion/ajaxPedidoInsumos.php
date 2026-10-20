<?php
include_once(__DIR__ . '/../sesion.php');
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../insumos/comunInsumos.php');

// Insumos gastados en un pedido de venta (produccion/modalPedidoInsumos.php, desde la pizarra).
exigirPermisoAjax('produccion/pizarra.php');
$idUsuario = (int)$_SESSION['idUsuario'];
$conn = conectar();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$opcion = $_POST['opcion'] ?? '';

switch ($opcion) {

// Si el insumo controla stock, también se descuenta (movimiento tipo 'pedido').
case 'agregar':
    $idPedido = (int)($_POST['idPedido'] ?? 0);
    $idInsumo = (int)($_POST['idInsumo'] ?? 0);

    $stmt = $conn->prepare("SELECT 1 FROM pedidos WHERE id = ? AND anulado = 0 AND idTipoPedido = 1");
    $stmt->bind_param('i', $idPedido);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_row()) { echo "❌ No hay un pedido de venta con ese número."; break; }

    $conn->begin_transaction();
    // Bloqueado hasta el final, así dos cargas a la vez no se pisan el stock.
    $stmt = $conn->prepare("SELECT i.id, i.unidadesPorCompra, i.controlaStock FROM insumos i
                            INNER JOIN insumosCategorias c ON c.id = i.idCategoria
                            WHERE i.id = ? AND i.activo = 1 AND c.ficha <> 'toner' FOR UPDATE");
    $stmt->bind_param('i', $idInsumo);
    $stmt->execute();
    $insumo = $stmt->get_result()->fetch_assoc();
    if (!$insumo) { $conn->rollback(); echo "❌ Falta elegir el insumo (el toner no se carga por pedido: sale del recuento)."; break; }
    $cantidad = cantidadDelFormulario($_POST, max(1, (int)$insumo['unidadesPorCompra']));
    if (!$cantidad) { $conn->rollback(); echo "❌ Falta la cantidad, o no es un número entero mayor que cero."; break; }

    $mejor = preciosVigentes($conn, $idInsumo)[$idInsumo][0] ?? null;
    $costoUnitario = $mejor ? round($mejor['precio'] / max(1, (int)$insumo['unidadesPorCompra']), 4) : null;

    $idMovimiento = null;
    if ($insumo['controlaStock']) {
        $salida = -$cantidad;
        $stmt = $conn->prepare("INSERT INTO insumosMovimientos (idInsumo, fecha, tipo, cantidad, idPedido, idUsuario)
                                VALUES (?, NOW(), 'pedido', ?, ?, ?)");
        $stmt->bind_param('iiii', $idInsumo, $salida, $idPedido, $idUsuario);
        $stmt->execute();
        $idMovimiento = $conn->insert_id;
        $stmt = $conn->prepare("UPDATE insumos SET stockActual = stockActual - ? WHERE id = ?");
        $stmt->bind_param('ii', $cantidad, $idInsumo);
        $stmt->execute();
    }
    $stmt = $conn->prepare("INSERT INTO pedidosInsumos (idPedido, idInsumo, cantidad, costoUnitario, idMovimiento, fecha, idUsuario)
                            VALUES (?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param('iiidii', $idPedido, $idInsumo, $cantidad, $costoUnitario, $idMovimiento, $idUsuario);
    $stmt->execute();
    $conn->commit();
    echo "✅ Listo.";
    break;

// Saca un renglón y devuelve lo descontado al stock. No se puede si después hubo un recuento
// de ese insumo: el recuento ya lo tuvo en cuenta y devolverlo dejaría mal el stock.
case 'sacar':
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("SELECT idInsumo FROM pedidosInsumos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $idInsumo = $stmt->get_result()->fetch_row()[0] ?? null;
    if (!$idInsumo) { echo "❌ No se encontró el renglón."; break; }

    $conn->begin_transaction();
    $stmt = $conn->prepare("SELECT id FROM insumos WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $idInsumo);
    $stmt->execute();
    $stmt->get_result()->free();
    $stmt = $conn->prepare("SELECT idMovimiento, cantidad FROM pedidosInsumos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $renglon = $stmt->get_result()->fetch_assoc();
    if ($renglon['idMovimiento']) {
        $stmt = $conn->prepare("SELECT 1 FROM insumosMovimientos WHERE idInsumo = ? AND tipo = 'inventario' AND id > ?");
        $stmt->bind_param('ii', $idInsumo, $renglon['idMovimiento']);
        $stmt->execute();
        if ($stmt->get_result()->fetch_row()) {
            $conn->rollback();
            echo "❌ Después de cargarlo se hizo un recuento de ese insumo, que ya lo tuvo en cuenta: no se puede sacar.";
            break;
        }
        $stmt = $conn->prepare("DELETE FROM insumosMovimientos WHERE id = ?");
        $stmt->bind_param('i', $renglon['idMovimiento']);
        $stmt->execute();
        $stmt = $conn->prepare("UPDATE insumos SET stockActual = stockActual + ? WHERE id = ?");
        $stmt->bind_param('ii', $renglon['cantidad'], $idInsumo);
        $stmt->execute();
    }
    $stmt = $conn->prepare("DELETE FROM pedidosInsumos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $conn->commit();
    echo "✅ Listo.";
    break;

default:
    echo "❌ Opción desconocida.";
}
