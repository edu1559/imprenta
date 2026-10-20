<?php
include_once(__DIR__ . '/../sesion.php');
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/comunInsumos.php');

exigirPermisoAjax('insumos/stock.php');
$idUsuario = (int)$_SESSION['idUsuario'];
$conn = conectar();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$opcion = $_POST['opcion'] ?? '';

const TIPOS_MOVIMIENTO = ['compra', 'salida', 'inventario'];

// Pedido de compra (tipo 2) sin anular, con su proveedor, o null.
function pedidoDeCompra($conn, $idPedido) {
    $stmt = $conn->prepare("SELECT p.id, p.idContacto, TRIM(CONCAT(c.apellido, ' ', COALESCE(c.nombre, ''))) AS proveedor, p.detalle
                            FROM pedidos p LEFT JOIN contactos c ON c.id = p.idContacto
                            WHERE p.id = ? AND p.anulado = 0 AND p.idTipoPedido = 2");
    $stmt->bind_param('i', $idPedido);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Bloquea el insumo hasta el final de la transacción, así dos cargas a la vez no se pisan el stock.
function insumoParaMovimiento($conn, $idInsumo) {
    $stmt = $conn->prepare("SELECT id, nombre, stockActual, unidadesPorCompra FROM insumos WHERE id = ? AND controlaStock = 1 FOR UPDATE");
    $stmt->bind_param('i', $idInsumo);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Graba el movimiento y lo suma al stock. En inventario, $cantidad es lo contado y se graba la diferencia.
function registrarMovimiento($conn, $insumo, $tipo, $cantidad, $idPedido, $idProveedor, $observaciones, $idUsuario) {
    $contado = null;
    if ($tipo === 'inventario') {
        $contado = $cantidad;
        $cantidad = $contado - (int)$insumo['stockActual'];
    } elseif ($tipo === 'salida') {
        $cantidad = -$cantidad;
    }
    $stmt = $conn->prepare("INSERT INTO insumosMovimientos (idInsumo, fecha, tipo, cantidad, contado, idPedido, idProveedor, observaciones, idUsuario)
                            VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isiiiisi', $insumo['id'], $tipo, $cantidad, $contado, $idPedido, $idProveedor, $observaciones, $idUsuario);
    $stmt->execute();
    $stmt = $conn->prepare("UPDATE insumos SET stockActual = stockActual + ? WHERE id = ?");
    $stmt->bind_param('ii', $cantidad, $insumo['id']);
    $stmt->execute();
}

switch ($opcion) {

// Para el formulario de compra: muestra de quién es el pedido antes de guardar.
case 'verPedido':
    $pedido = pedidoDeCompra($conn, (int)($_POST['idPedido'] ?? 0));
    echo $pedido ? "✅ " . ($pedido['proveedor'] ?: 'sin proveedor') . ": " . $pedido['detalle'] : "❌ No hay un pedido de compra con ese número.";
    break;

case 'movimiento':
    $idInsumo = (int)($_POST['idInsumo'] ?? 0);
    $tipo = $_POST['tipo'] ?? '';
    $observaciones = trim($_POST['observaciones'] ?? '') ?: null;
    $idPedido = trim($_POST['idPedido'] ?? '') === '' ? null : (int)$_POST['idPedido'];
    $idProveedor = (int)($_POST['idProveedor'] ?? 0) ?: null;

    if (!in_array($tipo, TIPOS_MOVIMIENTO, true)) { echo "❌ Tipo de movimiento desconocido."; break; }
    if ($observaciones !== null && preg_match_all('/./su', $observaciones) > 255) { echo "❌ Las observaciones no pueden tener más de 255 caracteres."; break; }
    if ($tipo !== 'compra') $idPedido = $idProveedor = null;
    if ($idPedido !== null) {
        $pedido = pedidoDeCompra($conn, $idPedido);
        if (!$pedido) { echo "❌ No hay un pedido de compra con el número $idPedido."; break; }
        $idProveedor ??= (int)$pedido['idContacto'] ?: null;   // sin proveedor elegido, el del pedido
    }

    $conn->begin_transaction();
    $insumo = insumoParaMovimiento($conn, $idInsumo);
    if (!$insumo) { $conn->rollback(); echo "❌ No se encontró el insumo, o no tiene el control de stock activado."; break; }
    $cantidad = cantidadDelFormulario($_POST, max(1, (int)$insumo['unidadesPorCompra']));
    if ($cantidad === false || $cantidad === null) { $conn->rollback(); echo "❌ Falta la cantidad, o no es un número entero."; break; }
    if ($tipo !== 'inventario' && $cantidad === 0) { $conn->rollback(); echo "❌ La cantidad tiene que ser mayor que cero."; break; }

    registrarMovimiento($conn, $insumo, $tipo, $cantidad, $idPedido, $idProveedor, $observaciones, $idUsuario);
    $conn->commit();
    echo "✅ Listo.";
    break;

// Recuento de todo de una vez: conteos[idInsumo][enteras|sueltas]. Los que quedan vacíos no se tocan.
case 'inventarioGeneral':
    $conteos = is_array($_POST['conteos'] ?? null) ? $_POST['conteos'] : [];
    $conn->begin_transaction();
    $hechos = 0;
    $error = null;
    foreach ($conteos as $idInsumo => $datos) {
        $insumo = insumoParaMovimiento($conn, (int)$idInsumo);
        if (!$insumo) continue;
        $cantidad = cantidadDelFormulario(is_array($datos) ? $datos : [], max(1, (int)$insumo['unidadesPorCompra']));
        if ($cantidad === null) continue;
        if ($cantidad === false) { $error = "❌ La cantidad de \"{$insumo['nombre']}\" no es un número entero. No se grabó nada."; break; }
        registrarMovimiento($conn, $insumo, 'inventario', $cantidad, null, null, 'Inventario general', $idUsuario);
        $hechos++;
    }
    if ($error) { $conn->rollback(); echo $error; break; }
    $conn->commit();
    echo $hechos ? "✅ Inventario grabado: $hechos insumos contados." : "❌ No se cargó ninguna cantidad.";
    break;

// Para corregir un error: solo el último movimiento del insumo (los anteriores ya los
// tuvo en cuenta un inventario posterior, y deshacerlos dejaría mal el stock).
case 'deshacer':
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $conn->prepare("SELECT idInsumo FROM insumosMovimientos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $idInsumo = $stmt->get_result()->fetch_row()[0] ?? null;
    if (!$idInsumo) { echo "❌ No se encontró el movimiento."; break; }

    $conn->begin_transaction();
    insumoParaMovimiento($conn, $idInsumo);
    $stmt = $conn->prepare("SELECT id, cantidad FROM insumosMovimientos WHERE idInsumo = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('i', $idInsumo);
    $stmt->execute();
    $ultimo = $stmt->get_result()->fetch_assoc();
    if ((int)$ultimo['id'] !== $id) { $conn->rollback(); echo "❌ Solo se puede deshacer el último movimiento del insumo."; break; }
    $stmt = $conn->prepare("SELECT 1 FROM pedidosInsumos WHERE idMovimiento = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()) {
        $conn->rollback(); echo "❌ Este movimiento es de un pedido: se saca desde el pedido, en la pizarra."; break;
    }
    $stmt = $conn->prepare("DELETE FROM insumosMovimientos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt = $conn->prepare("UPDATE insumos SET stockActual = stockActual - ? WHERE id = ?");
    $stmt->bind_param('ii', $ultimo['cantidad'], $idInsumo);
    $stmt->execute();
    $conn->commit();
    echo "✅ Movimiento deshecho.";
    break;

default:
    echo "❌ Opción desconocida.";
}
