<?php
// Mis pedidos: lo que ve un cliente que ingresó por la web (perfil 3).
// Todo se filtra por el id de la sesión (usuarios.id = contactos.id), nunca por un
// id que venga del navegador. Las observaciones son internas y no se muestran.
include_once(__DIR__ . '/../sesion.php');
exigirClientePagina(__FILE__);
include_once(__DIR__ . '/../conexion.php');
$conn = conectar();

const MAX_PEDIDOS_CLIENTE = 100;

$idContacto = (int)$_SESSION['idUsuario'];

// Primero los que siguen abiertos, después los más recientes.
$stmt = $conn->prepare("SELECT p.id, p.idTipoPedido, p.detalle, p.entrada, p.prometido, p.monto, p.montoPagado,
                               p.estadoProduccion, p.estadoEntrega, p.estadoPago,
                               (p.estadoProduccion = 3 AND p.estadoEntrega = 3 AND p.estadoPago = 3) AS cerrado
                        FROM pedidos p
                        WHERE p.idContacto = ? AND p.anulado = 0 AND p.idTipoPedido IN (1, 3)
                        ORDER BY cerrado ASC, p.id DESC
                        LIMIT " . MAX_PEDIDOS_CLIENTE);
$stmt->bind_param('i', $idContacto);
$stmt->execute();
$pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Pagos de esos pedidos, agrupados por pedido.
$pagos = [];
if ($pedidos) {
    $ids = implode(',', array_map(fn($p) => (int)$p['id'], $pedidos));
    $res = mysqli_query($conn, "SELECT pg.idPedido, pg.fecha, pg.monto, mp.medio
                                FROM pagos pg
                                LEFT JOIN mediosPago mp ON mp.id = pg.idMedioPago
                                WHERE pg.idPedido IN ($ids)
                                ORDER BY pg.fecha, pg.id");
    while ($pg = mysqli_fetch_assoc($res)) $pagos[$pg['idPedido']][] = $pg;
}

function plata($monto) {
    $m = round((float)$monto, 2);
    return '$' . number_format($m, floor($m) == $m ? 0 : 2, ',', '.');
}

function fecha($valor) {
    return $valor ? date('d/m/Y', strtotime($valor)) : '';
}

// Un solo estado, en palabras del cliente: [texto, color de Bootstrap].
function estadoCliente($p) {
    if ($p['idTipoPedido'] == 3)       return ['Presupuesto', 'secondary'];
    if ($p['estadoEntrega'] == 3)      return ['Entregado', 'success'];
    if ($p['estadoEntrega'] == 2)      return ['Enviado', 'success'];
    if ($p['estadoProduccion'] == 3)   return ['Listo para retirar', 'primary'];
    if ($p['estadoProduccion'] == 2)   return ['En producción', 'warning'];
    return ['Recibido', 'info'];
}

// El saldo es de todas sus ventas, no solo de las que entran en la lista.
$stmt = $conn->prepare("SELECT COALESCE(SUM(GREATEST(0, monto - montoPagado)), 0)
                        FROM pedidos WHERE idContacto = ? AND anulado = 0 AND idTipoPedido = 1");
$stmt->bind_param('i', $idContacto);
$stmt->execute();
$saldoTotal = (float)$stmt->get_result()->fetch_row()[0];
?>

<div class="container my-3" style="max-width: 900px;">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h3 class="mb-0"><i class="bi bi-bag-check me-2 text-success"></i>Mis pedidos</h3>
        <?php if ($saldoTotal > 0.01): ?>
            <span class="badge bg-danger fs-6">Saldo pendiente: <?= plata($saldoTotal) ?></span>
        <?php endif; ?>
    </div>

    <?php if (!$pedidos): ?>
        <div class="alert alert-light border text-center p-4">Todavía no tenés pedidos cargados.</div>
    <?php endif; ?>

    <?php foreach ($pedidos as $p):
        [$estado, $color] = estadoCliente($p);
        $saldo = $p['monto'] - $p['montoPagado'];
        $pagosPedido = $pagos[$p['id']] ?? [];
    ?>
    <div class="card shadow-sm mb-3 <?= $p['cerrado'] ? 'opacity-75' : '' ?>">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <span class="fw-bold">Pedido #<?= (int)$p['id'] ?></span>
                    <span class="text-muted small ms-2"><?= fecha($p['entrada']) ?></span>
                </div>
                <span class="badge bg-<?= $color ?>"><?= $estado ?></span>
            </div>

            <div class="my-2" style="white-space: pre-line;"><?= htmlspecialchars((string)$p['detalle']) ?></div>

            <?php if ($p['prometido'] && $p['estadoEntrega'] != 3 && $p['idTipoPedido'] == 1): ?>
                <div class="small text-muted mb-2"><i class="bi bi-calendar-event me-1"></i>Fecha prometida: <?= fecha($p['prometido']) ?></div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-3 small">
                <span>Total: <strong><?= plata($p['monto']) ?></strong></span>
                <span>Pagado: <strong><?= plata($p['montoPagado']) ?></strong></span>
                <?php if ($p['idTipoPedido'] == 1): ?>
                    <?php if ($saldo > 0.01): ?>
                        <span class="text-danger">Saldo: <strong><?= plata($saldo) ?></strong></span>
                    <?php else: ?>
                        <span class="text-success"><i class="bi bi-check-circle me-1"></i>Pagado</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($pagosPedido): ?>
                <a class="small d-inline-block mt-2" data-bs-toggle="collapse" href="#pagos<?= (int)$p['id'] ?>">
                    Ver pagos (<?= count($pagosPedido) ?>)
                </a>
                <div class="collapse" id="pagos<?= (int)$p['id'] ?>">
                    <table class="table table-sm small mt-2 mb-0">
                        <tbody>
                        <?php foreach ($pagosPedido as $pg): ?>
                            <tr>
                                <td><?= fecha($pg['fecha']) ?></td>
                                <td><?= $pg['medio'] === 'sinRegistro' ? '' : htmlspecialchars(ucfirst((string)$pg['medio'])) ?></td>
                                <td class="text-end"><?= plata($pg['monto']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (count($pedidos) == MAX_PEDIDOS_CLIENTE): ?>
        <p class="text-muted small text-center">Se muestran los pedidos abiertos y los más recientes (hasta <?= MAX_PEDIDOS_CLIENTE ?>).</p>
    <?php endif; ?>

    <p class="text-muted small text-center">¿Alguna consulta sobre un pedido? Escribinos a corintios@imprentacorintios.com.ar</p>
</div>
