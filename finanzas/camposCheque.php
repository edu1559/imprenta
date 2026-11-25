<?php
// Campos del cheque para un formulario de cobro o pago. Se muestran solo si el medio es "cheques".
//   Venta: cheque nuevo, o uno que ya tenemos (si un cheque paga varios pedidos).
//   Compra: uno de la cartera, que se entrega entero al proveedor.
// Uso: $prefijoCheque = 'nvo'; include __DIR__ . '/../finanzas/camposCheque.php';
// y en el JS: ChequeForm.vincular('nvo', '#selectMedio', '#inputMonto', function() { return esCompra; });
//             let ch = ChequeForm.datos('nvo');  // null (no es cheque), texto (error) u objeto
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../cheques.php');

$connCheque = conectar();
$pc = preg_replace('/\W/', '', $prefijoCheque ?? 'ch');
$cartera = chequesEnCartera($connCheque);
$conDisponible = array_filter($cartera, fn($c) => $c['disponible'] > 0.009);
?>
<div id="<?php echo $pc; ?>Cheque" class="border border-info rounded p-2 mt-2 bg-white text-start" style="display:none"
     data-medio="<?php echo idMedioCheques($connCheque); ?>">

    <div class="<?php echo $pc; ?>ChModoCompra" style="display:none">
        <?php if ($cartera): ?>
            <label class="small fw-bold">Cheque que se le entrega al proveedor</label>
            <select class="form-select form-select-sm" id="<?php echo $pc; ?>ChIdChequeCompra">
                <?php foreach ($cartera as $c): ?>
                    <option value="<?php echo $c['id']; ?>" data-monto="<?php echo $c['monto']; ?>">
                        <?php echo htmlspecialchars(describirCheque($c)); ?> — <?php echo pesosCheque($c['monto']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="small text-muted mt-1">El cheque se entrega entero: el pago queda por su monto y el cheque pasa a "entregado".</div>
        <?php else: ?>
            <div class="small text-danger">No hay cheques en cartera para entregar.</div>
        <?php endif; ?>
    </div>

    <div class="<?php echo $pc; ?>ChModoVenta">
        <?php if ($conDisponible): ?>
        <div class="mb-2 small">
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="<?php echo $pc; ?>ChOrigen" id="<?php echo $pc; ?>ChNuevo" value="nuevo" checked>
                <label class="form-check-label" for="<?php echo $pc; ?>ChNuevo">Cheque nuevo</label>
            </div>
            <div class="form-check form-check-inline">
                <input class="form-check-input" type="radio" name="<?php echo $pc; ?>ChOrigen" id="<?php echo $pc; ?>ChExistente" value="existente">
                <label class="form-check-label" for="<?php echo $pc; ?>ChExistente">Un cheque que ya tenemos</label>
            </div>
        </div>
        <div class="<?php echo $pc; ?>ChPanelExistente mb-1" style="display:none">
            <select class="form-select form-select-sm" id="<?php echo $pc; ?>ChIdCheque">
                <?php foreach ($conDisponible as $c): ?>
                    <option value="<?php echo $c['id']; ?>">
                        <?php echo htmlspecialchars(describirCheque($c)); ?> — quedan <?php echo pesosCheque($c['disponible']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="row g-2 <?php echo $pc; ?>ChPanelNuevo">
            <div class="col-md-2">
                <label class="small fw-bold">Tipo</label>
                <select class="form-select form-select-sm" id="<?php echo $pc; ?>ChTipo">
                    <?php foreach (TIPOS_CHEQUE as $v => $t): ?><option value="<?php echo $v; ?>"><?php echo $t; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="small fw-bold">Número</label>
                <input type="text" class="form-control form-control-sm" id="<?php echo $pc; ?>ChNumero" maxlength="30">
            </div>
            <div class="col-md-3">
                <label class="small fw-bold">Banco</label>
                <input type="text" class="form-control form-control-sm" id="<?php echo $pc; ?>ChBanco" maxlength="60">
            </div>
            <div class="col-md-4">
                <label class="small fw-bold">Fecha de pago</label>
                <input type="date" class="form-control form-control-sm" id="<?php echo $pc; ?>ChFechaPago" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-5">
                <label class="small">Titular (quien lo firma)</label>
                <input type="text" class="form-control form-control-sm" id="<?php echo $pc; ?>ChTitular" maxlength="100" placeholder="Opcional">
            </div>
            <div class="col-md-3">
                <label class="small">CUIT titular</label>
                <input type="text" class="form-control form-control-sm" id="<?php echo $pc; ?>ChCuit" maxlength="20" placeholder="Opcional">
            </div>
            <div class="col-md-4">
                <label class="small fw-bold">Monto del cheque $</label>
                <input type="number" class="form-control form-control-sm" id="<?php echo $pc; ?>ChMonto" step="0.01">
            </div>
            <div class="col-12 small text-muted">Si el cheque es por más que este pago, lo que sobra queda para asignarlo a otro pedido.</div>
        </div>
    </div>
</div>
<script>
window.ChequeForm = {
    vincular: function(p, selMedio, selMonto, esCompra) {
        let $panel = $('#' + p + 'Cheque');
        let $monto = $('#' + p + 'ChMonto');
        let $compra = $('#' + p + 'ChIdChequeCompra');
        esCompra = esCompra || function() { return false; };
        $panel.data('esCompra', esCompra);

        let actualizar = function() {
            let compra = esCompra();
            $panel.toggle($(selMedio).val() == $panel.data('medio'));
            $panel.find('.' + p + 'ChModoCompra').toggle(compra);
            $panel.find('.' + p + 'ChModoVenta').toggle(!compra);
            // en una compra el pago es por el monto del cheque elegido
            if (compra && $panel.is(':visible') && $compra.length) $(selMonto).val($compra.find(':selected').data('monto'));
        };
        $(selMedio).on('change', actualizar);
        $compra.on('change', actualizar);
        $panel.data('actualizar', actualizar);
        actualizar();

        // el monto del cheque nuevo sigue al del pago mientras no lo cambien a mano
        $monto.val($(selMonto).val()).on('input', function() { $(this).data('tocado', 1); });
        $(selMonto).on('input change', function() { if (!$monto.data('tocado')) $monto.val($(this).val()); });
        $panel.find('input[name="' + p + 'ChOrigen"]').on('change', function() {
            let existente = this.value === 'existente' && this.checked;
            $panel.find('.' + p + 'ChPanelExistente').toggle(existente);
            $panel.find('.' + p + 'ChPanelNuevo').toggle(!existente);
        });
    },
    // volver a acomodar el panel (por ejemplo, si cambió el tipo de pedido)
    refrescar: function(p) {
        let f = $('#' + p + 'Cheque').data('actualizar');
        if (f) f();
    },
    // null si no es cheque, un texto si falta algo, o el objeto para mandar como "cheque"
    datos: function(p) {
        let $panel = $('#' + p + 'Cheque');
        if (!$panel.is(':visible')) return null;
        if ($panel.data('esCompra')()) {
            let id = $('#' + p + 'ChIdChequeCompra').val();
            return id ? { idCheque: id } : 'No hay cheques en cartera para entregar.';
        }
        if ($panel.find('input[name="' + p + 'ChOrigen"]:checked').val() === 'existente') {
            return { idCheque: $('#' + p + 'ChIdCheque').val() };
        }
        let ch = {
            tipo: $('#' + p + 'ChTipo').val(),
            numero: $('#' + p + 'ChNumero').val().trim(),
            banco: $('#' + p + 'ChBanco').val().trim(),
            fechaPago: $('#' + p + 'ChFechaPago').val(),
            titular: $('#' + p + 'ChTitular').val().trim(),
            cuitTitular: $('#' + p + 'ChCuit').val().trim(),
            monto: $('#' + p + 'ChMonto').val()
        };
        if (!ch.numero) return 'Falta el número del cheque.';
        if (!ch.banco) return 'Falta el banco del cheque.';
        if (!ch.fechaPago) return 'Falta la fecha de pago del cheque.';
        if (!(parseFloat(ch.monto) > 0)) return 'Falta el monto del cheque.';
        return ch;
    }
};
</script>
