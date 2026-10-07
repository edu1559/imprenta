<?php
session_start();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../sesion.php');
$conn = conectar();

exigirTrabajadorAjax();
$idUsuario = (int)$_SESSION['idUsuario'];

$opcion = $_POST['opcion'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

const LARGO_DESCRIPCION = 255;

// Pasos del trabajo, en orden. El estado es el último que tiene fecha.
const PASOS = ['fechaEnviado' => 'enviado', 'fechaListo' => 'listo', 'fechaRecibido' => 'recibido'];

function cargarTercerizado($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM tercerizados WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Venta sin anular con su cliente, o null.
function pedidoParaTercerizar($conn, $idPedido) {
    $stmt = $conn->prepare("SELECT p.id, CONCAT(c.apellido, ' ', COALESCE(c.nombre, '')) AS contacto, p.detalle
                            FROM pedidos p INNER JOIN contactos c ON c.id = p.idContacto
                            WHERE p.id = ? AND p.anulado = 0 AND p.idTipoPedido = 1");
    $stmt->bind_param('i', $idPedido);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

switch ($opcion) {

// Para el formulario: muestra de quién es el pedido antes de guardar.
case 'verPedido':
    $pedido = pedidoParaTercerizar($conn, (int)($_POST['idPedido'] ?? 0));
    echo $pedido ? "✅ " . trim($pedido['contacto']) . ": " . $pedido['detalle'] : "❌ No hay un pedido de venta con ese número.";
    break;

// Alta (id 0) o edición.
case 'guardar':
    $idProveedor = (int)($_POST['idProveedor'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $idPedido    = trim($_POST['idPedido'] ?? '') === '' ? null : (int)$_POST['idPedido'];
    $precio      = trim($_POST['precio'] ?? '') === '' ? null : (float)$_POST['precio'];
    $prometida   = trim($_POST['fechaPrometida'] ?? '') === '' ? null : $_POST['fechaPrometida'];

    if ($descripcion === '') { echo "❌ Falta describir qué se manda."; break; }
    if (preg_match_all('/./su', $descripcion) > LARGO_DESCRIPCION) { echo "❌ La descripción no puede tener más de " . LARGO_DESCRIPCION . " caracteres."; break; }
    if ($prometida !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $prometida)) { echo "❌ La fecha prometida no es válida."; break; }
    if ($precio !== null && $precio < 0) { echo "❌ El precio no puede ser negativo."; break; }
    if ($idPedido !== null && !pedidoParaTercerizar($conn, $idPedido)) { echo "❌ No hay un pedido de venta con el número $idPedido."; break; }

    $stmt = $conn->prepare("SELECT id FROM contactos WHERE id = ?");
    $stmt->bind_param('i', $idProveedor);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_row()) { echo "❌ Falta elegir el proveedor."; break; }

    if ($id === 0) {
        // "Ya se llevó": se carga directamente como enviado.
        $stmt = $conn->prepare("INSERT INTO tercerizados (idPedido, idProveedor, descripcion, precio, fechaPrometida, fechaCarga, idUsuario, fechaEnviado)
                                VALUES (?, ?, ?, ?, ?, NOW(), ?, IF(?, NOW(), NULL))");
        $enviado = empty($_POST['enviado']) ? 0 : 1;
        $stmt->bind_param('iisdsii', $idPedido, $idProveedor, $descripcion, $precio, $prometida, $idUsuario, $enviado);
        echo $stmt->execute() ? "✅ Trabajo cargado." : "❌ Error al guardar.";
    } else {
        if (!cargarTercerizado($conn, $id)) { echo "❌ No se encontró el trabajo."; break; }
        $stmt = $conn->prepare("UPDATE tercerizados SET idPedido = ?, idProveedor = ?, descripcion = ?, precio = ?, fechaPrometida = ? WHERE id = ?");
        $stmt->bind_param('iisdsi', $idPedido, $idProveedor, $descripcion, $precio, $prometida, $id);
        echo $stmt->execute() ? "✅ Trabajo actualizado." : "❌ Error al guardar.";
    }
    break;

// Pasa al paso siguiente (enviado → listo → recibido) o deshace el último.
case 'avanzar':
case 'deshacer':
    $trabajo = cargarTercerizado($conn, $id);
    if (!$trabajo) { echo "❌ No se encontró el trabajo."; break; }
    $hechos = array_keys(array_filter(array_intersect_key($trabajo, PASOS)));
    $columnas = array_keys(PASOS);
    if ($opcion === 'avanzar') {
        $columna = $columnas[$hechos ? array_search(end($hechos), $columnas) + 1 : 0] ?? null;
        if (!$columna) { echo "❌ El trabajo ya está recibido."; break; }
        $sql = "UPDATE tercerizados SET $columna = NOW() WHERE id = ?";
    } else {
        if (!$hechos) { echo "❌ El trabajo todavía no se envió."; break; }
        $columna = end($hechos);
        $sql = "UPDATE tercerizados SET $columna = NULL WHERE id = ?";
    }
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    echo $stmt->execute() ? "✅ Listo." : "❌ Error al guardar.";
    break;

case 'pagado':
    $pagado = empty($_POST['valor']) ? 0 : 1;
    $stmt = $conn->prepare("UPDATE tercerizados SET pagado = ?, fechaPagado = IF(?, NOW(), NULL) WHERE id = ?");
    $stmt->bind_param('iii', $pagado, $pagado, $id);
    echo $stmt->execute() && $stmt->affected_rows === 1 ? "✅ Listo." : "❌ No se encontró el trabajo.";
    break;

// Se retiran varios trabajos juntos y se pagan en un solo pago: tilda todos los
// recibidos sin pagar de ese proveedor.
case 'pagarProveedor':
    $idProveedor = (int)($_POST['idProveedor'] ?? 0);
    $stmt = $conn->prepare("UPDATE tercerizados SET pagado = 1, fechaPagado = NOW()
                            WHERE idProveedor = ? AND pagado = 0 AND fechaRecibido IS NOT NULL");
    $stmt->bind_param('i', $idProveedor);
    $stmt->execute();
    echo "✅ " . $stmt->affected_rows . " trabajos marcados como pagados.";
    break;

case 'borrar':
    $trabajo = cargarTercerizado($conn, $id);
    if (!$trabajo) { echo "❌ No se encontró el trabajo."; break; }
    if ($trabajo['pagado']) { echo "❌ El trabajo está marcado como pagado. Si igual hay que borrarlo, primero sacale el tilde."; break; }
    $stmt = $conn->prepare("DELETE FROM tercerizados WHERE id = ?");
    $stmt->bind_param('i', $id);
    echo $stmt->execute() ? "✅ Trabajo borrado." : "❌ Error al borrar.";
    break;

default:
    echo "❌ Opción desconocida.";
}
