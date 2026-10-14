<?php
// Acciones de los clientes web. El cliente es siempre el de la sesión
// (usuarios.id = contactos.id); nada de lo que llega del navegador elige el contacto.
include_once(__DIR__ . '/../sesion.php');
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
$conn = conectar();

$opcion = $_POST['opcion'] ?? '';
$idContacto = (int)($_SESSION['idUsuario'] ?? 0);

// Para que nadie llene la lista de la imprenta: más de estos sin revisar, no se aceptan.
const MAX_WEB_PENDIENTES = 10;
const LARGO_PEDIDO_WEB = 900;   // + la cantidad entra en pedidos.detalle (1000)

switch ($opcion) {

case 'nuevoPedido':
    exigirClienteAjax('clientes/nuevoPedido.php');

    $detalle = trim(textoLatin1($_POST['detalle'] ?? ''));
    $cantidad = trim(textoLatin1($_POST['cantidad'] ?? ''));
    $deseado = trim($_POST['deseado'] ?? '');

    if ($detalle === '') {
        echo "❌ Contanos qué necesitás.";
        break;
    }
    if (preg_match_all('/./su', $detalle) > LARGO_PEDIDO_WEB || preg_match_all('/./su', $cantidad) > 50) {
        echo "❌ El texto es demasiado largo.";
        break;
    }
    if ($deseado !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $deseado) || $deseado < date('Y-m-d')) {
            echo "❌ La fecha para cuándo lo necesitás tiene que ser de hoy en adelante.";
            break;
        }
    }

    $stmt = $conn->prepare("SELECT COUNT(*) FROM pedidos WHERE idContacto = ? AND estadoWeb = ? AND anulado = 0");
    $pendiente = ESTADO_WEB_PENDIENTE;
    $stmt->bind_param('ii', $idContacto, $pendiente);
    $stmt->execute();
    if ($stmt->get_result()->fetch_row()[0] >= MAX_WEB_PENDIENTES) {
        echo "❌ Ya tenés " . MAX_WEB_PENDIENTES . " pedidos esperando aprobación. Cuando los revisemos vas a poder hacer más.";
        break;
    }

    // Venta con monto 0 y estados en 0 hasta que la imprenta la apruebe (ver sql/2026-10-14_pedidos_web.sql).
    // La fecha deseada va en prometido: al aprobar, la imprenta la confirma o la cambia.
    $texto = $detalle . ($cantidad !== '' ? "\nCantidad: $cantidad" : '');
    $prometido = $deseado !== '' ? $deseado : null;
    $idOrigenWeb = mysqli_fetch_row(mysqli_query($conn, "SELECT id FROM origen WHERE origen = 'web'"))[0] ?? 0;
    $stmt = $conn->prepare("INSERT INTO pedidos (idContacto, idTipoPedido, idOrigen, detalle, observaciones, entrada, prometido,
                                                 estadoEntrega, estadoProduccion, estadoPago, monto, montoPagado, idUsuario, estadoWeb)
                            VALUES (?, 1, ?, ?, '', NOW(), ?, 0, 0, 0, 0, 0, ?, ?)");
    $stmt->bind_param('iissii', $idContacto, $idOrigenWeb, $texto, $prometido, $idContacto, $pendiente);
    if ($stmt->execute()) {
        echo "✅ Recibimos tu pedido #" . $conn->insert_id . ". Lo vamos a revisar y te avisamos el precio y la fecha.";
    } else {
        echo "❌ No pudimos guardar el pedido. Probá de nuevo en un rato.";
    }
    break;

default:
    http_response_code(400);
    echo "Opción desconocida.";
}
