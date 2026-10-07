<?php
session_start();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
$conn = conectar();

exigirTrabajadorAjax();
$idUsuario = (int)$_SESSION['idUsuario'];

$opcion   = $_POST['opcion'] ?? '';
$idPedido = (int)($_POST['idPedido'] ?? 0);

// Pedido de la pizarra: una venta sin anular. Devuelve null si no existe.
function cargarPedidoPizarra($conn, $idPedido) {
    $stmt = $conn->prepare("SELECT p.id, p.estadoProduccion, p.idResponsable, u.usuario AS responsable
                            FROM pedidos p LEFT JOIN usuarios u ON u.id = p.idResponsable
                            WHERE p.id = ? AND p.anulado = 0");
    $stmt->bind_param('i', $idPedido);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$pedido = cargarPedidoPizarra($conn, $idPedido);
if (!$pedido) {
    echo "❌ No se encontró el pedido.";
    exit;
}
if ((int)$pedido['estadoProduccion'] === 3) {
    echo "❌ El pedido #$idPedido ya está terminado.";
    exit;
}

switch ($opcion) {

// "Lo tomo": solo si nadie lo tomó mientras tanto (dos personas mirando la misma pizarra).
case 'tomar':
    $stmt = $conn->prepare("UPDATE pedidos SET idResponsable = ?, estadoProduccion = 2
                            WHERE id = ? AND idResponsable IS NULL");
    $stmt->bind_param('ii', $idUsuario, $idPedido);
    $stmt->execute();
    if ($stmt->affected_rows === 1) {
        echo "✅ Tomaste el pedido #$idPedido.";
    } elseif ((int)$pedido['idResponsable'] === $idUsuario) {
        echo "✅ El pedido #$idPedido ya era tuyo.";
    } else {
        echo "❌ El pedido #$idPedido ya lo tomó " . $pedido['responsable'] . ".";
    }
    break;

// Asignar a cualquier trabajador (o a nadie, con idResponsable 0). Cualquiera puede hacerlo.
case 'asignar':
    $idResponsable = (int)($_POST['idResponsable'] ?? 0);
    if ($idResponsable === 0) {
        $stmt = $conn->prepare("UPDATE pedidos SET idResponsable = NULL WHERE id = ?");
        $stmt->bind_param('i', $idPedido);
        echo $stmt->execute() ? "✅ El pedido #$idPedido quedó sin asignar." : "❌ Error al guardar.";
        break;
    }
    $stmt = $conn->prepare("SELECT usuario FROM usuarios WHERE id = ? AND id <> " . ID_USUARIO_VISITANTE . " AND idPerfil <> " . ID_PERFIL_VISITANTE);
    $stmt->bind_param('i', $idResponsable);
    $stmt->execute();
    $responsable = $stmt->get_result()->fetch_assoc();
    if (!$responsable) {
        echo "❌ No se encontró el trabajador.";
        break;
    }
    $stmt = $conn->prepare("UPDATE pedidos SET idResponsable = ?, estadoProduccion = 2 WHERE id = ?");
    $stmt->bind_param('ii', $idResponsable, $idPedido);
    echo $stmt->execute() ? "✅ Pedido #$idPedido asignado a " . $responsable['usuario'] . "." : "❌ Error al guardar.";
    break;

// Terminado: si nadie lo había tomado, el responsable es quien lo marca.
case 'terminar':
    $stmt = $conn->prepare("UPDATE pedidos SET estadoProduccion = 3, idResponsable = COALESCE(idResponsable, ?)
                            WHERE id = ?");
    $stmt->bind_param('ii', $idUsuario, $idPedido);
    if ($stmt->execute()) {
        marcarFechaTerminado($conn, $idPedido);
        echo "✅ Pedido #$idPedido terminado.";
    } else {
        echo "❌ Error al guardar.";
    }
    break;

default:
    echo "❌ Opción desconocida.";
}
