<?php
include_once(__DIR__ . '/../sesion.php');
exigirPermisoPagina('insumos/stock.php');
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/comunInsumos.php');
$conn = conectar();

// Recuento de todos los insumos con stock de una vez. Los que se dejan vacíos no se tocan.
$insumos = mysqli_fetch_all(mysqli_query($conn, "SELECT i.*, c.nombre AS categoria
                                                 FROM insumos i INNER JOIN insumosCategorias c ON c.id = i.idCategoria
                                                 WHERE i.controlaStock = 1 AND i.activo = 1
                                                 ORDER BY c.orden, i.nombre"), MYSQLI_ASSOC);
?>

<div class="modal-header bg-dark text-white">
    <h5 class="modal-title"><i class="bi bi-clipboard-check"></i> Inventario: contar lo que hay</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">
    <div class="p-3 bg-white rounded shadow-sm">
        <p class="small text-muted">Cargá lo que contaste. Lo que dejes vacío no se cambia. El sistema registra la diferencia con lo que tenía.</p>
        <table class="table table-sm align-middle" id="tablaInventario">
            <thead class="table-light"><tr><th>Insumo</th><th>El sistema dice</th><th>Contado</th></tr></thead>
            <tbody>
                <?php $categoria = null; foreach ($insumos as $i):
                    $porCompra = max(1, (int)$i['unidadesPorCompra']);
                    if ($i['categoria'] !== $categoria): $categoria = $i['categoria']; ?>
                <tr class="table-secondary"><td colspan="3" class="fw-bold small"><?= htmlspecialchars($categoria) ?></td></tr>
                <?php endif; ?>
                <tr data-id="<?= (int)$i['id'] ?>">
                    <td><?= htmlspecialchars($i['nombre']) ?></td>
                    <td class="small text-muted"><?= cantidadEnUnidades($i, $i['stockActual']) ?></td>
                    <td>
                        <div class="input-group input-group-sm" style="max-width: 300px">
                            <input type="number" class="form-control invEnteras" min="0" step="1">
                            <span class="input-group-text"><?= htmlspecialchars(strtolower(plural($i['unidadCompra']))) ?></span>
                            <?php if ($porCompra > 1): ?>
                            <input type="number" class="form-control invSueltas" min="0" step="1">
                            <span class="input-group-text"><?= htmlspecialchars(strtolower(plural($i['unidadUso']))) ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" id="btnGuardarInventario" class="btn btn-warning fw-bold w-100"><i class="bi bi-save"></i> GRABAR INVENTARIO</button>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#btnGuardarInventario').on('click', function() {
        var datos = { opcion: 'inventarioGeneral' };
        $('#tablaInventario tbody tr[data-id]').each(function() {
            var enteras = $(this).find('.invEnteras').val();
            var sueltas = $(this).find('.invSueltas').val() || '';
            if (enteras === '' && sueltas === '') return;
            datos['conteos[' + $(this).data('id') + '][enteras]'] = enteras;
            datos['conteos[' + $(this).data('id') + '][sueltas]'] = sueltas;
        });
        $.post('insumos/ajaxStock.php', datos, function(res) {
            alert(res);
            if (res.trim().indexOf('✅') !== 0) return;
            $('#modalUniversal').modal('hide');
            $('#contenido').load('insumos/stock.php');
        });
    });
});
</script>
