<?php
// Funciones compartidas para modificar pedidos y pagos de forma controlada:
// quién puede hacerlo, qué está bloqueado (caja cerrada, pedido cerrado)
// y el registro en la tabla 'modificaciones'.
// Requiere session_start() y una conexión mysqli ya abierta.

const ID_USUARIO_VISITANTE = 1;
const ID_PERFIL_ADMINISTRADOR = 1;

// index.php asigna el usuario 1 ("visitante") cuando no hay sesión;
// eso no cuenta como usuario logueado.
function usuarioLogueado() {
    $id = (int)($_SESSION['idUsuario'] ?? 0);
    return $id > 0 && $id !== ID_USUARIO_VISITANTE;
}

function datosUsuarioActual($conn) {
    if (!usuarioLogueado()) return null;
    $id = (int)$_SESSION['idUsuario'];
    $stmt = $conn->prepare("SELECT idPerfil, puedeModificar FROM usuarios WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Se lee de la base (no de la sesión) para que un cambio de permiso rija al instante.
function esAdministrador($conn) {
    $u = datosUsuarioActual($conn);
    return $u && (int)$u['idPerfil'] === ID_PERFIL_ADMINISTRADOR;
}

function puedeModificar($conn) {
    $u = datosUsuarioActual($conn);
    return $u && ((int)$u['idPerfil'] === ID_PERFIL_ADMINISTRADOR || (int)$u['puedeModificar'] === 1);
}

function registrarModificacion($conn, $entidad, $idEntidad, $idPedido, $accion, $valorAnterior, $valorNuevo, $motivo) {
    $idUsuario = (int)$_SESSION['idUsuario'];
    $stmt = $conn->prepare("INSERT INTO modificaciones
                              (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
                            VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isiissss', $idUsuario, $entidad, $idEntidad, $idPedido, $accion, $valorAnterior, $valorNuevo, $motivo);
    return $stmt->execute();
}

// Terminado + entregado + pagado.
function pedidoCerrado($pedido) {
    return (int)$pedido['estadoProduccion'] === 3
        && (int)$pedido['estadoEntrega'] === 3
        && (int)$pedido['estadoPago'] === 3;
}

// Un pago queda "en caja cerrada" si es anterior al último cierre de su medio.
function pagoEnCajaCerrada($conn, $idPago) {
    $stmt = $conn->prepare("SELECT p.fecha < cm.fechaUC AS cerrado
                            FROM pagos p
                            INNER JOIN cierreMedios cm ON cm.idMedio = p.idMedioPago
                            WHERE p.id = ?");
    $stmt->bind_param('i', $idPago);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    return $fila && (int)$fila['cerrado'] === 1;
}

function calcularEstadoPago($monto, $montoPagado) {
    if ($montoPagado <= 0.1) return 1;            // Sin pagar
    if ($monto - $montoPagado <= 0.1) return 3;   // Pagado
    return 2;                                     // Pago parcial
}

// Recalcula montoPagado y estadoPago del pedido a partir de sus pagos reales,
// que es lo que usan Finanzas y Cierre.
function recalcularPagosPedido($conn, $idPedido) {
    $stmt = $conn->prepare("SELECT p.monto, COALESCE(SUM(pg.monto), 0) AS pagado
                            FROM pedidos p
                            LEFT JOIN pagos pg ON pg.idPedido = p.id
                            WHERE p.id = ?
                            GROUP BY p.id");
    $stmt->bind_param('i', $idPedido);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    if (!$fila) return false;

    $pagado = (float)$fila['pagado'];
    $estado = calcularEstadoPago((float)$fila['monto'], $pagado);

    $upd = $conn->prepare("UPDATE pedidos SET montoPagado = ?, estadoPago = ? WHERE id = ?");
    $upd->bind_param('dii', $pagado, $estado, $idPedido);
    return $upd->execute();
}

// Una fecha cae en caja cerrada si es anterior al último cierre de ese medio.
function fechaEnCajaCerrada($conn, $fecha, $idMedio) {
    $stmt = $conn->prepare("SELECT ? < fechaUC AS cerrado FROM cierreMedios WHERE idMedio = ?");
    $stmt->bind_param('si', $fecha, $idMedio);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    return $fila && (int)$fila['cerrado'] === 1;
}

// Pago con los datos de su pedido y de su caja, para decidir si se puede tocar.
function cargarPago($conn, $idPago) {
    $stmt = $conn->prepare("SELECT pg.id, pg.fecha, pg.monto, pg.idMedioPago, pg.idPedido,
                                   mp.medio,
                                   p.estadoProduccion, p.estadoEntrega, p.estadoPago
                            FROM pagos pg
                            INNER JOIN pedidos p ON p.id = pg.idPedido
                            LEFT JOIN mediosPago mp ON mp.id = pg.idMedioPago
                            WHERE pg.id = ?");
    $stmt->bind_param('i', $idPago);
    $stmt->execute();
    $pago = $stmt->get_result()->fetch_assoc();
    if ($pago) {
        $pago['cajaCerrada'] = fechaEnCajaCerrada($conn, $pago['fecha'], (int)$pago['idMedioPago']);
    }
    return $pago;
}

// Motivo por el que un pago no se puede borrar ni modificar (null si se puede).
// En esos casos, la corrección se hace con un pago de ajuste.
function bloqueoPago($pago) {
    if (pedidoCerrado($pago)) return "El pedido está cerrado (terminado, entregado y pagado). Usá un pago de ajuste.";
    if ($pago['cajaCerrada'])  return "El pago ya está en una caja cerrada. Usá un pago de ajuste.";
    return null;
}

function describirPago($pago) {
    return '$' . number_format((float)$pago['monto'], 2, ',', '.') . ' en ' . ($pago['medio'] ?? $pago['idMedioPago'])
         . ' del ' . date('d/m/Y H:i', strtotime($pago['fecha']));
}

// Pedido con sus estados, si está anulado y cuántos pagos tiene.
function cargarPedido($conn, $idPedido) {
    $stmt = $conn->prepare("SELECT p.id, p.monto, p.montoPagado, p.anulado,
                                   p.estadoProduccion, p.estadoEntrega, p.estadoPago,
                                   (SELECT COUNT(*) FROM pagos pg WHERE pg.idPedido = p.id) AS cantPagos
                            FROM pedidos p
                            WHERE p.id = ?");
    $stmt->bind_param('i', $idPedido);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function describirEstados($pedido) {
    return 'producción ' . $pedido['estadoProduccion'] . ', entrega ' . $pedido['estadoEntrega'] . ', pago ' . $pedido['estadoPago'];
}
