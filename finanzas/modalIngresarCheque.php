<?php
// Ingresar un cheque sin pedido (desde Finanzas → Cheques): por canje con un contacto, o carga
// inicial de uno que ya estaba en la imprenta (solo administradores). Lo graba ajaxCheques.php
// (opcion 'ingresar'). Los cheques que entran cobrando un pedido se cargan en el pago del pedido.
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
include_once(__DIR__ . '/../cheques.php');
$conn = conectar();

$esAdmin = esAdministrador($conn);
$medios = mediosParaCheque($conn);
$pagosViejos = $esAdmin ? pagosSinCheque($conn) : [];
$h = fn($t) => htmlspecialchars((string)$t);
?>
<div class="modal-header bg-dark text-white">
    <h5 class="modal-title"><i class="bi bi-box-arrow-in-down me-2"></i>Ingresar cheque</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">
    <div class="small text-muted mb-2">Si el cheque es el pago de un pedido, cargalo en el cobro del pedido con medio cheques.</div>

    <div class="mb-3">
        <div class="form-check">
            <input class="form-check-input" type="radio" name="ingOrigen" id="ingCanje" value="canje" checked>
            <label class="form-check-label" for="ingCanje"><strong>Canje</strong>: alguien nos da el cheque y le damos el mismo monto en efectivo o transferencia</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="ingOrigen" id="ingInicial" value="inicial" <?php echo $esAdmin ? '' : 'disabled'; ?>>
            <label class="form-check-label" for="ingInicial"><strong>Carga inicial</strong>: ya estaba en la imprenta y ya cuenta en el saldo de cheques (no mueve plata)
                <?php if (!$esAdmin): ?><span class="text-muted">(solo administradores)</span><?php endif; ?></label>
        </div>
    </div>

    <div class="row g-2 small">
        <div class="col-md-7">
            <label class="fw-bold" id="ingLblContacto">Quién nos da el cheque</label>
            <select id="ingContacto" class="form-select form-select-sm" style="width:100%"></select>
        </div>
        <div class="col-md-5 ingSoloCanje">
            <label class="fw-bold">Le damos la plata en</label>
            <select id="ingMedio" class="form-select form-select-sm">
                <?php foreach ($medios as $id => $m): ?><option value="<?php echo $id; ?>"><?php echo $h($m); ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php if ($pagosViejos): ?>
        <div class="col-12 ingSoloInicial" style="display:none">
            <label>Es el cheque de un pago viejo en cheques (sin datos del cheque)</label>
            <select id="ingPago" class="form-select form-select-sm">
                <option value="">No, no es de ningún pedido</option>
                <?php foreach ($pagosViejos as $p): ?>
                    <option value="<?php echo $p['id']; ?>" data-monto="<?php echo $p['monto']; ?>">
                        Pedido #<?php echo $p['idPedido']; ?> — <?php echo $h($p['contacto']); ?> — <?php echo pesosCheque($p['monto']); ?> del <?php echo date('d/m/Y', strtotime($p['fecha'])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <div class="col-md-2"><label class="fw-bold">Tipo</label>
            <select class="form-select form-select-sm" id="ingTipo">
                <?php foreach (TIPOS_CHEQUE as $v => $t): ?><option value="<?php echo $v; ?>"><?php echo $t; ?></option><?php endforeach; ?>
            </select></div>
        <div class="col-md-3"><label class="fw-bold">Número</label><input class="form-control form-control-sm" id="ingNumero" maxlength="30"></div>
        <div class="col-md-3"><label class="fw-bold">Banco</label><input class="form-control form-control-sm" id="ingBanco" maxlength="60"></div>
        <div class="col-md-4"><label class="fw-bold">Fecha de pago</label><input type="date" class="form-control form-control-sm" id="ingFechaPago" value="<?php echo date('Y-m-d'); ?>"></div>
        <div class="col-md-5"><label>Titular (quien lo firma)</label><input class="form-control form-control-sm" id="ingTitular" maxlength="100" placeholder="Opcional"></div>
        <div class="col-md-3"><label>CUIT titular</label><input class="form-control form-control-sm" id="ingCuit" maxlength="20" placeholder="Opcional"></div>
        <div class="col-md-4"><label class="fw-bold">Monto $</label><input type="number" step="0.01" class="form-control form-control-sm" id="ingMonto"></div>
        <div class="col-12"><label>Nota</label><input class="form-control form-control-sm" id="ingNota" maxlength="255" placeholder="Opcional"></div>
    </div>
</div>

<div class="modal-footer">
    <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button class="btn btn-success" id="btnIngGuardar">Ingresar</button>
</div>

<script>
(function() {
    function origen() { return $('input[name="ingOrigen"]:checked').val(); }
    function acomodar() {
        let canje = origen() === 'canje';
        $('.ingSoloCanje').toggle(canje);
        $('.ingSoloInicial').toggle(!canje);
        $('#ingLblContacto').text(canje ? 'Quién nos da el cheque' : 'Quién nos lo dio (opcional)');
    }
    $('input[name="ingOrigen"]').on('change', acomodar);
    $('#ingPago').on('change', function() {
        let m = $(this).find(':selected').data('monto');
        if (m && !$('#ingMonto').val()) $('#ingMonto').val(m);
    });

    $('#ingContacto').select2({
        dropdownParent: $('#modalUniversal'),
        placeholder: 'Buscar contacto (3 letras)...',
        allowClear: true,
        minimumInputLength: 3,
        ajax: {
            url: 'contactos/buscarContactos.php', dataType: 'json', delay: 250,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return { results: data.filter(function(c) { return c.id !== 'NEW'; }) }; }
        }
    });

    $('#btnIngGuardar').click(function() {
        let o = origen();
        let ch = {
            tipo: $('#ingTipo').val(), numero: $('#ingNumero').val().trim(), banco: $('#ingBanco').val().trim(),
            fechaPago: $('#ingFechaPago').val(), titular: $('#ingTitular').val().trim(),
            cuitTitular: $('#ingCuit').val().trim(), monto: $('#ingMonto').val()
        };
        if (!ch.numero) { alert('Falta el número del cheque.'); return; }
        if (!ch.banco) { alert('Falta el banco del cheque.'); return; }
        if (!ch.fechaPago) { alert('Falta la fecha de pago del cheque.'); return; }
        if (!(parseFloat(ch.monto) > 0)) { alert('Falta el monto del cheque.'); return; }
        let idContacto = $('#ingContacto').val() || '';
        if (o === 'canje' && !idContacto) { alert('Elegí quién nos da el cheque.'); return; }

        let monto = '$' + parseFloat(ch.monto).toLocaleString('es-AR');
        let msj = o === 'canje'
            ? '¿Ingresar el cheque por ' + monto + '?\n\nSalen ' + monto + ' de ' + $('#ingMedio option:selected').text() +
              ' para ' + $('#ingContacto option:selected').text() + ' (movimiento entre medios).'
            : '¿Cargar el cheque por ' + monto + ' como carga inicial? No mueve plata.';
        if (!confirm(msj)) return;

        let $btn = $(this).prop('disabled', true);
        $.post('finanzas/ajaxCheques.php', {
            opcion: 'ingresar', origen: o, cheque: ch, idContacto: idContacto,
            idMedio: o === 'canje' ? $('#ingMedio').val() : '',
            idPago: o === 'inicial' ? ($('#ingPago').val() || '') : '',
            nota: $('#ingNota').val()
        }, function(r) {
            alert($('<div>').html(r).text().trim());
            $btn.prop('disabled', false);
            if (r.trim().indexOf('✅') === 0) {
                $('#modalUniversal').modal('hide');
                if (window.recargarCheques) window.recargarCheques();
            }
        }).fail(function() {
            alert('Error del servidor: no se guardó.');
            $btn.prop('disabled', false);
        });
    });
})();
</script>
