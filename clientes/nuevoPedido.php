<?php
// Nuevo pedido web: el cliente cuenta qué necesita; la imprenta lo revisa y le pone precio
// y fecha (pedidos/modalPedidoWeb.php). Mientras tanto aparece en "Pendientes de aprobación".
include_once(__DIR__ . '/../sesion.php');
exigirClientePagina(__FILE__);
?>

<div class="container my-3" style="max-width: 700px;">
    <h3 class="mb-3"><i class="bi bi-plus-circle me-2 text-success"></i>Nuevo pedido</h3>

    <div class="card shadow-sm">
        <div class="card-body">
            <form id="formPedidoWeb">
                <div class="mb-3">
                    <label class="form-label fw-semibold" for="webDetalle">¿Qué necesitás?</label>
                    <textarea class="form-control" id="webDetalle" rows="5" maxlength="900" required
                              placeholder="Por ejemplo: 500 volantes A5 a color, papel ilustración, de un solo lado."></textarea>
                    <div class="form-text text-end" id="webDetalleContador">0 / 900</div>
                </div>
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold" for="webCantidad">Cantidad <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="text" class="form-control" id="webCantidad" maxlength="50">
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label fw-semibold" for="webDeseado">¿Para cuándo lo necesitás? <span class="text-muted fw-normal">(opcional)</span></label>
                        <input type="date" class="form-control" id="webDeseado" min="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <p class="small text-muted">Te vamos a confirmar el precio y la fecha de entrega. Hasta entonces el pedido queda en
                    <strong>Pendientes de aprobación</strong>.</p>
                <div id="webMensaje"></div>
                <button type="submit" class="btn btn-success w-100" id="btnEnviarPedidoWeb">
                    <i class="bi bi-send me-1"></i>Enviar pedido
                </button>
            </form>
        </div>
    </div>
</div>

<script>
$('#webDetalle').on('input', function() {
    $('#webDetalleContador').text(this.value.length + ' / ' + this.maxLength);
});

$('#formPedidoWeb').on('submit', function(e) {
    e.preventDefault();
    if (!$('#webDetalle').val().trim()) {
        $('#webMensaje').html('<div class="alert alert-warning py-2">Contanos qué necesitás.</div>');
        return;
    }
    var boton = $('#btnEnviarPedidoWeb').prop('disabled', true);
    $.post('clientes/ajaxClientes.php', {
        opcion: 'nuevoPedido',
        detalle: $('#webDetalle').val(),
        cantidad: $('#webCantidad').val(),
        deseado: $('#webDeseado').val()
    }, function(res) {
        var ok = res.indexOf('✅') === 0;
        $('#webMensaje').html($('<div class="alert py-2">').addClass(ok ? 'alert-success' : 'alert-danger').text(res));
        if (ok) {
            $('#formPedidoWeb')[0].reset();
            $('#webDetalleContador').text('0 / 900');
        }
        boton.prop('disabled', false);
    });
});
</script>
