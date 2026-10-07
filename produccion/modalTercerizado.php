<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
$conn = conectar();

// Alta o edición de un trabajo tercerizado. Con ?id= edita; con ?idPedido= arranca
// con el pedido ya cargado (botón de la pizarra).
$id = (int)($_GET['id'] ?? 0);
$trabajo = ['idPedido' => (int)($_GET['idPedido'] ?? 0) ?: '', 'idProveedor' => 0, 'descripcion' => '', 'precio' => '', 'fechaPrometida' => ''];
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM tercerizados WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $trabajo = $stmt->get_result()->fetch_assoc();
    if (!$trabajo) {
        echo '<div class="modal-body"><div class="alert alert-warning mb-0">No se encontró el trabajo.</div></div>';
        exit;
    }
}

// Proveedores: los contactos marcados "esTercerizado" (tilde en la edición del contacto)
// y los que ya tienen trabajos, con los más usados primero.
$proveedores = mysqli_query($conn, "SELECT c.id, TRIM(CONCAT(c.apellido, ' ', COALESCE(c.nombre, ''))) AS proveedor,
                                           (SELECT COUNT(*) FROM tercerizados t WHERE t.idProveedor = c.id) AS trabajos
                                    FROM contactos c
                                    WHERE c.esTercerizado = 1 OR c.id IN (SELECT idProveedor FROM tercerizados)
                                    HAVING proveedor <> ''
                                    ORDER BY trabajos DESC, c.apellido, c.nombre");
?>

<div class="modal-header bg-dark text-white">
    <h5 class="modal-title"><i class="bi bi-truck"></i> <?= $id ? 'Editar trabajo tercerizado' : 'Nuevo trabajo tercerizado' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">
    <div class="container-fluid p-3 bg-white rounded shadow-sm" id="formTercerizado">
        <div class="row mb-3">
            <div class="col-md-8">
                <label class="form-label fw-bold">Proveedor:</label>
                <select class="form-select" id="terProveedor" style="width: 100%">
                    <option value="">Elegir proveedor...</option>
                    <?php while ($p = mysqli_fetch_assoc($proveedores)): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= (int)$p['id'] === (int)$trabajo['idProveedor'] ? 'selected' : '' ?>><?= htmlspecialchars($p['proveedor']) ?></option>
                    <?php endwhile; ?>
                </select>
                <div class="form-text">Si no aparece, editá el contacto y tildá "Es un proveedor al que le mandamos trabajos".</div>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold">Pedido # <span class="fw-normal text-muted">(opcional)</span>:</label>
                <input type="number" class="form-control" id="terPedido" value="<?= htmlspecialchars((string)$trabajo['idPedido']) ?>">
            </div>
            <div class="col-12 small mt-1" id="terPedidoInfo"></div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Qué se manda:</label>
            <textarea class="form-control" id="terDescripcion" rows="2" maxlength="255" placeholder="Ej: plastificado mate de 200 tapas"><?= htmlspecialchars($trabajo['descripcion']) ?></textarea>
        </div>

        <div class="row mb-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Precio $ <span class="text-muted">(si se sabe)</span>:</label>
                <input type="number" class="form-control" id="terPrecio" min="0" step="0.01" value="<?= htmlspecialchars((string)$trabajo['precio']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Lo prometen para:</label>
                <input type="date" class="form-control" id="terPrometida" value="<?= htmlspecialchars((string)$trabajo['fechaPrometida']) ?>">
            </div>
            <?php if (!$id): ?>
            <div class="col-md-4">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="terEnviado">
                    <label class="form-check-label" for="terEnviado">Ya se llevó al proveedor</label>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <button type="button" id="btnGuardarTercerizado" class="btn btn-warning btn-lg w-100 fw-bold">
            <i class="bi bi-save"></i> GUARDAR
        </button>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#terProveedor').select2({ dropdownParent: $('#modalUniversal') });

    // Muestra de quién es el pedido, para no enganchar el trabajo a otro por un número mal tipeado.
    function verPedido() {
        var idPedido = $('#terPedido').val();
        if (!idPedido) { $('#terPedidoInfo').text(''); return; }
        $.post('produccion/ajaxTercerizados.php', { opcion: 'verPedido', idPedido: idPedido }, function(res) {
            var ok = res.trim().indexOf('✅') === 0;
            $('#terPedidoInfo').text(res.trim().substring(2)).toggleClass('text-danger', !ok).toggleClass('text-success', ok);
        });
    }
    $('#terPedido').on('change', verPedido);
    verPedido();

    $('#btnGuardarTercerizado').on('click', function() {
        $.post('produccion/ajaxTercerizados.php', {
            opcion: 'guardar',
            id: <?= $id ?>,
            idProveedor: $('#terProveedor').val(),
            idPedido: $('#terPedido').val(),
            descripcion: $('#terDescripcion').val(),
            precio: $('#terPrecio').val(),
            fechaPrometida: $('#terPrometida').val(),
            enviado: $('#terEnviado').is(':checked') ? 1 : ''
        }, function(res) {
            // Con un error, el modal queda abierto para no perder lo que se escribió.
            if (res.trim().indexOf('✅') !== 0) {
                alert(res);
                return;
            }
            $('#modalUniversal').modal('hide');
            $('#contenido').load(window.urlListaActual || 'produccion/tercerizados.php');
        });
    });
});
</script>
