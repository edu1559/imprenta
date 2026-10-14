<?php
// Pendientes de aprobación: los pedidos web del cliente que la imprenta todavía no revisó,
// y los rechazados de los últimos días con el motivo. Los aprobados pasan a Mis pedidos.
include_once(__DIR__ . '/../sesion.php');
exigirClientePagina(__FILE__);
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
include_once(__DIR__ . '/comunClientes.php');
$conn = conectar();

const DIAS_RECHAZADOS = 60;

$idContacto = (int)$_SESSION['idUsuario'];
$stmt = $conn->prepare("SELECT id, detalle, entrada, prometido, estadoWeb, notaCliente
                        FROM pedidos
                        WHERE idContacto = ?
                          AND ((estadoWeb = ? AND anulado = 0)
                               OR (estadoWeb = ? AND entrada >= NOW() - INTERVAL " . DIAS_RECHAZADOS . " DAY))
                        ORDER BY estadoWeb, id DESC");
$pendiente = ESTADO_WEB_PENDIENTE;
$rechazado = ESTADO_WEB_RECHAZADO;
$stmt->bind_param('iii', $idContacto, $pendiente, $rechazado);
$stmt->execute();
$pedidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container my-3" style="max-width: 900px;">
    <h3 class="mb-3"><i class="bi bi-hourglass-split me-2 text-warning"></i>Pendientes de aprobación</h3>

    <?php if (!$pedidos): ?>
        <div class="alert alert-light border text-center p-4">No tenés pedidos esperando aprobación.</div>
    <?php endif; ?>

    <?php foreach ($pedidos as $p):
        $esPendiente = (int)$p['estadoWeb'] === ESTADO_WEB_PENDIENTE;
    ?>
    <div class="card shadow-sm mb-3 <?= $esPendiente ? '' : 'opacity-75' ?>">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                <div>
                    <span class="fw-bold">Pedido #<?= (int)$p['id'] ?></span>
                    <span class="text-muted small ms-2"><?= fecha($p['entrada']) ?></span>
                </div>
                <?php if ($esPendiente): ?>
                    <span class="badge bg-warning text-dark">Esperando aprobación</span>
                <?php else: ?>
                    <span class="badge bg-danger">Rechazado</span>
                <?php endif; ?>
            </div>

            <div class="my-2" style="white-space: pre-line;"><?= htmlspecialchars((string)$p['detalle']) ?></div>

            <?php if ($esPendiente && $p['prometido']): ?>
                <div class="small text-muted"><i class="bi bi-calendar-event me-1"></i>Lo pediste para el <?= fecha($p['prometido']) ?></div>
            <?php endif; ?>
            <?php if ($p['notaCliente']): ?>
                <div class="alert alert-<?= $esPendiente ? 'info' : 'danger' ?> py-1 px-2 small mt-2 mb-0">
                    <i class="bi bi-chat-left-text me-1"></i><?= htmlspecialchars($p['notaCliente']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <p class="text-muted small text-center">Cuando aprobemos un pedido, lo vas a ver en <strong>Pedidos hechos</strong> con su precio y fecha de entrega.</p>
</div>
