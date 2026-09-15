<style>
.historial-scroll {
    max-height: 250px; /* Ajusta este alto a tu gusto */
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 5px;
}

/* Opcional: Estilizar la barrita de scroll para que sea fina y moderna */
.historial-scroll::-webkit-scrollbar {
    width: 6px;
}
.historial-scroll::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 10px;
}
</style>
<?php
include_once('../conexion.php');
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

// Buscamos las últimas 2 fechas únicas
$sqlFechas = "SELECT DISTINCT fecha, idUsuario FROM arqueo_efectivo 
              ORDER BY fecha DESC LIMIT 2";
$resFechas = mysqli_query($conn, $sqlFechas);

$arqueosAnteriores = [];
while ($f = mysqli_fetch_assoc($resFechas)) {
    $fecha = $f['fecha'];
    // Para cada fecha, traemos el desglose
    $sqlD = "SELECT denominacion, cantidad FROM arqueo_efectivo WHERE fecha = '$fecha'";
    $resD = mysqli_query($conn, $sqlD);
    
    $desglose = [];
    $total = 0;
    while ($d = mysqli_fetch_assoc($resD)) {
        if ($d['cantidad'] > 0) {
            $desglose[] = "$" . $d['denominacion'] . " x" . $d['cantidad'];
            $total += ($d['denominacion'] * $d['cantidad']);
        }
    }
    
    $arqueosAnteriores[] = [
        'fecha' => $fecha,
        'usuario' => $f['idUsuario'],
        'total' => $total,
        'cadena' => implode('; ', $desglose) // Tu idea de la cadena: "1000:45; 500:10"
    ];
}

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
<div class="col-md-10 border-end bg-white">
    <h6 class="text-muted fw-bold small p-2 border-bottom">
        <i class="bi bi-clock-history"></i> COMPARATIVA DE ARQUEOS
    </h6>
    
    <?php foreach ($arqueosAnteriores as $index => $arq): ?>
        <div class="card mb-3 border-0 shadow-sm <?php echo ($index == 0) ? 'bg-light' : ''; ?>">
            <div class="card-body p-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge bg-secondary"><?php echo date('H:i', strtotime($arq['fecha'])); ?> hs</span>
                    <span class="fw-bold text-success">$<?php echo number_format($arq['total'], 0, '', '.'); ?></span>
                </div>
                
                <div class="mt-2 pt-2 border-top">
                    <p class="text-muted mb-0" style="font-size: 0.75rem; line-height: 1.4;">
                        <i class="bi bi-cash me-1"></i> 
                        <strong>Detalle:</strong><br>
                        <?php 
                            // Reemplazamos los puntos y coma por pequeñas etiquetas o saltos
                            echo str_replace('; ', ' | ', $arq['cadena']); 
                        ?>
                    </p>
                </div>
                
                <div class="mt-2 text-end" style="font-size: 0.65rem; color: #aaa;">
                    Usuario: <?php echo $arq['usuario']; ?> | <?php echo date('d/m/y', strtotime($arq['fecha'])); ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($arqueosAnteriores)): ?>
        <p class="text-muted small p-3">No hay arqueos previos registrados.</p>
    <?php endif; ?>
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
        $.post('finanzas/ajaxEfectivo.php', $('#formEfectivo').serialize(), function(r) {
            // 2. Pasar el valor a la pantalla de finanzas (ID 1 suele ser Efectivo)
            $('#tablaCierre .inpMontoReal[data-id="1"]').val(total).trigger('change').addClass('is-valid');
            
            // Cerrar el modal
            bootstrap.Modal.getInstance(document.getElementById('modalEfectivo')).hide();
            alert("Arqueo guardado y saldo de caja actualizado.");
        });
    });
});
</script>