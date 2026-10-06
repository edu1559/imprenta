<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
$conn = conectar();

// 1. Traemos el último recuento guardado para pre-cargar el formulario
$sqlUltimo = "SELECT denominacion, cantidad FROM arqueo_efectivo 
              WHERE id IN (SELECT MAX(id) FROM arqueo_efectivo GROUP BY denominacion)";
$resUltimo = mysqli_query($conn, $sqlUltimo);
$cantidadesPrevia = [];
while($r = mysqli_fetch_assoc($resUltimo)){
    $cantidadesPrevia[$r['denominacion']] = $r['cantidad'];
}

$denominaciones = [20000, 10000, 2000, 1000, 500, 200, 100, 50, 20, 10];
?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-3">
        <h6 class="text-uppercase fw-bold text-muted mb-3 small"><i class="bi bi-clock-history me-2"></i>Último Arqueo Registrado</h6>
        <form id="formEfectivo">
            <table class="table table-sm align-middle">
                <tbody id="tablaBilletes">
                    <?php foreach ($denominaciones as $valor): 
                        $cant = $cantidadesPrevia[$valor] ?? 0; // Si no hay registro, es 0
                        $subtotal = $cant * $valor;
                    ?>
                    <tr>
                        <td class="fw-bold text-end pe-3" style="width: 30%;">$ <?php echo number_format($valor, 0, '', '.'); ?></td>
                        <td>
                            <input type="number" 
                                   name="billetes[<?php echo $valor; ?>]"
                                   class="form-control form-control-sm text-center cant-billete" 
                                   data-valor="<?php echo $valor; ?>" 
                                   value="<?php echo $cant; ?>" 
                                   min="0">
                        </td>
                        <td class="text-end ps-3 fw-bold text-primary subtotal-billete" style="width: 40%;">
                            $ <?php echo number_format($subtotal, 0, '', '.'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="border-top table-dark">
                    <tr>
                        <td colspan="2" class="text-end fw-bold">TOTAL BILLETES:</td>
                        <td class="text-end fw-bold" id="totalEfectivo" data-valor-puro="0">$ 0</td>
                    </tr>
                </tfoot>
            </table>
           
            <button type="button" class="btn btn-primary w-100 shadow-sm mt-2" id="btnGuardarYPasar">
                <i class="bi bi-save me-2"></i>Guardar Arqueo y Actualizar Cierre
            </button>
        </form>
    </div>
</div>
<script>
$(document).ready(function() {
    // Calculamos el total apenas carga para mostrar los valores recuperados
    actualizarTotales();

    $('.cant-billete').on('input', function() {
        actualizarTotales();
    });

    function actualizarTotales() {
        let granTotal = 0;
        $('.cant-billete').each(function() {
            let cant = parseInt($(this).val()) || 0;
            let valor = parseInt($(this).data('valor'));
            let sub = cant * valor;
            $(this).closest('tr').find('.subtotal-billete').text('$ ' + sub.toLocaleString('es-AR'));
            granTotal += sub;
        });
        $('#totalEfectivo').text('$ ' + granTotal.toLocaleString('es-AR')).data('valor-puro', granTotal);
    }

    $('#btnGuardarYPasar').click(function() {
        let total = $('#totalEfectivo').data('valor-puro');
        
        // 1. Guardar en la DB vía AJAX
        $.post('finanzas/ajaxCierre.php', $('#formEfectivo').serialize() + '&opcion=guardarArqueo', function(r) {
            // 2. Pasar el valor a la pantalla de finanzas (ID 1 suele ser Efectivo)
            $('#tablaCierre .inpMontoReal[data-id="1"]').val(total).trigger('change').addClass('is-valid');
            
            // Cerrar el modal
            bootstrap.Modal.getInstance(document.getElementById('modalEfectivo')).hide();
            alert("Arqueo guardado y saldo de caja actualizado.");
        });
    });
});
</script>