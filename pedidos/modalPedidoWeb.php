<?php
// Revisar un pedido web (lo hizo un cliente desde clientes/nuevoPedido.php): aprobarlo con
// monto y fecha prometida, o rechazarlo con un motivo. La nota la ve el cliente.
session_start();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
exigirTrabajadorPagina();
$conn = conectar();

$idPedido = (int)$_GET['idPedido'];
$stmt = $conn->prepare("SELECT p.id, p.detalle, p.entrada, DATE(p.prometido) AS deseado, p.estadoWeb, p.anulado,
                               c.apellido, c.nombre, c.celular, c.telefono, c.correo
                        FROM pedidos p INNER JOIN contactos c ON c.id = p.idContacto
                        WHERE p.id = ?");
$stmt->bind_param('i', $idPedido);
$stmt->execute();
$reg = $stmt->get_result()->fetch_assoc();
$h = fn($t) => htmlspecialchars((string)$t);
?>

<div class="modal-header bg-warning">
    <h5 class="modal-title"><i class="bi bi-globe me-2"></i>Pedido web Nº <?= $idPedido ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<?php if (!$reg || $reg['anulado'] || (int)$reg['estadoWeb'] !== ESTADO_WEB_PENDIENTE): ?>
    <div class="alert alert-info mb-0">Este pedido ya no está pendiente de revisión.</div>
<?php else: ?>
    <div class="mb-3">
        <div class="fw-bold"><?= $h(trim($reg['apellido'] . ' ' . $reg['nombre'])) ?></div>
        <div class="small text-muted">
            <?= $h(implode(' · ', array_filter([$reg['celular'], $reg['telefono'], $reg['correo']]))) ?>
        </div>
        <div class="small text-muted">Lo pidió el <?= date('d/m/Y H:i', strtotime($reg['entrada'])) ?>
            <?= $reg['deseado'] ? ' · Lo quiere para el ' . date('d/m/Y', strtotime($reg['deseado'])) : '' ?></div>
    </div>
    <div class="border rounded bg-light p-2 mb-3" style="white-space: pre-line;"><?= $h($reg['detalle']) ?></div>

    <div class="row g-2">
        <div class="col-6">
            <label class="form-label small mb-0" for="webMonto">Monto</label>
            <input type="number" step="0.01" min="0" class="form-control" id="webMonto">
        </div>
        <div class="col-6">
            <label class="form-label small mb-0" for="webPrometido">Fecha prometida</label>
            <input type="date" class="form-control" id="webPrometido" value="<?= $h($reg['deseado']) ?>">
        </div>
        <div class="col-12">
            <label class="form-label small mb-0" for="webNota">Nota para el cliente <span class="text-muted">(la ve el cliente; para rechazar es obligatoria)</span></label>
            <textarea class="form-control" id="webNota" rows="2" maxlength="255"></textarea>
        </div>
    </div>
    <p class="small text-muted mt-2 mb-0">Al aprobarlo pasa a producción como una venta más. Si hay que cambiar el detalle, editalo después de aprobarlo.</p>
<?php endif; ?>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
    <?php if ($reg && !$reg['anulado'] && (int)$reg['estadoWeb'] === ESTADO_WEB_PENDIENTE): ?>
        <button type="button" class="btn btn-outline-danger btnRevisionWeb" data-opcion="rechazarWeb">Rechazar</button>
        <button type="button" class="btn btn-success btnRevisionWeb" data-opcion="aprobarWeb">Aprobar</button>
    <?php endif; ?>
</div>

<script>
$('.btnRevisionWeb').on('click', function() {
    var opcion = $(this).data('opcion');
    if (opcion === 'rechazarWeb' && !$('#webNota').val().trim()) {
        alert('Escribí el motivo del rechazo en la nota para el cliente.');
        return;
    }
    if (opcion === 'rechazarWeb' && !confirm('¿Rechazar el pedido? El cliente va a ver el motivo.')) return;
    var boton = $('.btnRevisionWeb').prop('disabled', true);
    $.post('pedidos/ajaxPedidos.php', {
        opcion: opcion,
        idPedido: <?= $idPedido ?>,
        monto: $('#webMonto').val(),
        prometido: $('#webPrometido').val(),
        notaCliente: $('#webNota').val()
    }, function(res) {
        alert(res);
        if (res.indexOf('✅') === 0) {
            $('#modalUniversal').modal('hide');
            $('#contenido').load(window.urlListaActual || 'pedidos/pedidos.php');
        } else {
            boton.prop('disabled', false);
        }
    });
});
</script>
