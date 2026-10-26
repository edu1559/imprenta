<?php
include_once(__DIR__ . '/../sesion.php');
exigirPermisoPagina('insumos/procesos.php');
include_once(__DIR__ . '/../conexion.php');
$conn = conectar();

// Niveles de complejidad o riesgo: cada uno multiplica el valor hora de la mano de obra.
$niveles = mysqli_fetch_all(mysqli_query($conn, "SELECT c.id, c.nombre, c.factor, (SELECT COUNT(*) FROM procesos p WHERE p.idComplejidad = c.id) AS procesos
                                                 FROM complejidades c ORDER BY c.orden, c.nombre"), MYSQLI_ASSOC);

function valor($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }
?>

<div class="modal-header bg-dark text-white">
    <h5 class="modal-title"><i class="bi bi-speedometer2"></i> Niveles de complejidad</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">
    <div class="container-fluid p-3 bg-white rounded shadow-sm">
        <div class="small text-muted mb-2">
            El factor multiplica el valor hora del proceso: 1 = normal, 1,5 = la mano de obra cuesta la mitad más.
            Cada proceso tiene un nivel por defecto; en los presupuestos se va a poder subir para un trabajo puntual.
        </div>
        <table class="table table-sm align-middle mb-2" id="tablaNiveles">
            <thead class="table-light"><tr><th>Nivel</th><th style="width: 120px">Factor</th><th class="text-center">Procesos</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($niveles as $n): ?>
                <tr data-id="<?= (int)$n['id'] ?>">
                    <td><input type="text" class="form-control form-control-sm nivNombre" maxlength="30" value="<?= valor($n['nombre']) ?>"></td>
                    <td><input type="number" class="form-control form-control-sm nivFactor" min="0.01" step="0.05" value="<?= valor((float)$n['factor']) ?>"></td>
                    <td class="text-center small"><?= (int)$n['procesos'] ?: '-' ?></td>
                    <td class="text-end"><?php if (!$n['procesos']): ?><i class="bi bi-trash text-danger btnBorrarNivel" style="cursor: pointer" title="Borrar"></i><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                <tr data-id="0">
                    <td><input type="text" class="form-control form-control-sm nivNombre" maxlength="30" placeholder="Nivel nuevo (opcional)"></td>
                    <td><input type="number" class="form-control form-control-sm nivFactor" min="0.01" step="0.05"></td>
                    <td></td><td></td>
                </tr>
            </tbody>
        </table>
        <button type="button" id="btnGuardarNiveles" class="btn btn-warning fw-bold w-100"><i class="bi bi-save"></i> GUARDAR</button>
    </div>
</div>

<script>
$(document).ready(function() {
    function respuesta(res) {
        if (res.trim().indexOf('✅') !== 0) { alert(res); return; }
        $('#modalUniversal .modal-content').load('insumos/modalComplejidades.php');
        window.complejidadesCambiadas = true;
    }

    // Al cerrar, la lista de procesos se recarga si cambió algo (los costos dependen del factor).
    $('#modalUniversal').off('hidden.bs.modal.complejidades').one('hidden.bs.modal.complejidades', function() {
        if (window.complejidadesCambiadas) $('#contenido').load(window.urlProcesos || 'insumos/procesos.php');
        window.complejidadesCambiadas = false;
    });

    $('#btnGuardarNiveles').on('click', function() {
        var niveles = [];
        $('#tablaNiveles tbody tr').each(function() {
            niveles.push({ id: $(this).data('id'), nombre: $(this).find('.nivNombre').val(), factor: $(this).find('.nivFactor').val() });
        });
        $.post('insumos/ajaxProcesos.php', { opcion: 'guardarComplejidades', niveles: niveles }, respuesta);
    });

    $('#tablaNiveles').on('click', '.btnBorrarNivel', function() {
        if (!confirm('¿Borrar este nivel?')) return;
        $.post('insumos/ajaxProcesos.php', { opcion: 'borrarComplejidad', id: $(this).closest('tr').data('id') }, respuesta);
    });
});
</script>
