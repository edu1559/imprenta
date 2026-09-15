<?php
include_once('../conexion.php');
$conn = conectar();

$idMedio = $_GET['idMedio'] ?? die("Falta ID");

// Traemos todos los medios para el <select> de cambio rápido
$resMedios = mysqli_query($conn, "SELECT id, medio FROM mediosPago WHERE visible=1");
$listaMedios = mysqli_fetch_all($resMedios, MYSQLI_ASSOC);

// Consulta mejorada: agregamos idTipoPedido
$sql = "SELECT 
            p.id as idPago,
            p.idPedido,
            p.monto,
            date_format(p.fecha, '%d-%H:%i hs') as hora,
            c.apellido,
            pe.detalle,
            pe.idTipoPedido, -- Agregado para lógica de colores y resta
            u.usuario
        FROM pagos p
        INNER JOIN pedidos pe ON p.idPedido = pe.id
        INNER JOIN contactos c ON pe.idContacto = c.id
        INNER JOIN usuarios u ON p.idUsuario = u.id
        INNER JOIN cierreMedios cm ON p.idMedioPago = cm.idMedio
        WHERE p.idMedioPago = $idMedio 
          AND p.fecha >= cm.fechaUC
        ORDER BY p.fecha DESC";

$result = mysqli_query($conn, $sql);
?>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle shadow-sm" style="font-size: 0.85rem;">
        <thead class="table-warning text-dark">
            <tr>
                <th>Día-hs</th>
                <th>Pedido</th>
                <th>Cliente / Detalle</th>
                <th class="text-end">Monto</th>
                <th>Mover a...</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalDetalle = 0;
            if(mysqli_num_rows($result) > 0):
                while($f = mysqli_fetch_assoc($result)): 
                    
                    // Lógica Contable: Si es Egreso (2), restamos. Si es Venta (1), sumamos.
                    if($f['idTipoPedido'] == 2) {
                        $totalDetalle -= $f['monto'];
                        $claseFila = 'border-start border-danger border-4 bg-danger bg-opacity-10'; // Rojo para gastos
                        $simbolo = '-';
                        $colorMonto = 'text-danger';
                    } else {
                        $totalDetalle += $f['monto'];
                        $claseFila = 'border-start border-success border-4'; // Verde para ventas
                        $simbolo = '+';
                        $colorMonto = 'text-success';
                    }
            ?>
            <tr class="<?php echo $claseFila; ?>">
                <td class="text-muted small"><?php echo $f['hora']; ?></td>
                <td>
                    <span class="fw-bold">#<?php echo $f['idPedido']; ?></span>
                    <span class="idPago d-none"><?php echo $f['idPago']; ?></span>
                </td>
                <td>
                    <div class="fw-bold text-truncate" style="max-width: 140px;"><?php echo $f['apellido']; ?></div>
                    <div class="text-muted small text-truncate" style="max-width: 140px;"><?php echo $f['detalle']; ?></div>
                </td>
                <td class="text-end fw-bold <?php echo $colorMonto; ?>">
                    <?php echo $simbolo; ?>$<?php echo number_format($f['monto'], 0, ',', '.'); ?>
                </td>
                <td>
                    <select class="form-select form-select-sm selMedioRapido" style="font-size: 0.7rem; padding: 0.1rem 0.3rem;">
                        <?php foreach($listaMedios as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo ($m['id'] == $idMedio) ? 'selected' : ''; ?>>
                                <?php echo $m['medio']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php else: ?>
            <tr>
                <td colspan="5" class="text-center py-4 text-muted">No hay movimientos nuevos.</td>
            </tr>
            <?php endif; ?>
        </tbody>
        <tfoot class="table-light">
            <tr class="fw-bold h6">
                <td colspan="3" class="text-end">BALANCE NETO:</td>
                <td class="text-end <?php echo ($totalDetalle < 0) ? 'text-danger' : 'text-primary'; ?>">
                    $<?php echo number_format($totalDetalle, 0, ',', '.'); ?>
                </td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
$('.selMedioRapido').change(function() {
    let idPago = $(this).closest('tr').find('.idPago').text();
    let idNuevoMedio = $(this).val();
    let fila = $(this).closest('tr');

    if(confirm('¿Seguro que quieres mover este pago?')) {
        $.post('finanzas/ajaxCierre.php', {
            opcion: 'cambiaMedio',
            idPago: idPago,
            idMedio: idNuevoMedio
        }, function() {
            fila.fadeOut(300, function() {
                // Actualizamos el tablero general para que los saldos coincidan
                $('#contenido').load('finanzas/finanzas.php');
            });
        });
    }
});
</script>