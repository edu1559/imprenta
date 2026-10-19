<?php
include_once(__DIR__ . '/../sesion.php');
exigirPermisoPagina('insumos/maquinas.php');
include_once(__DIR__ . '/../conexion.php');
$conn = conectar();

// Alta o edición de una máquina (?id=).
$id = (int)($_GET['id'] ?? 0);
$maquina = ['tipo' => '', 'nombre' => '', 'marca' => '', 'modelo' => '', 'observaciones' => '', 'activo' => 1];
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM maquinas WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $maquina = $stmt->get_result()->fetch_assoc();
    if (!$maquina) {
        echo '<div class="modal-body"><div class="alert alert-warning mb-0">No se encontró la máquina.</div></div>';
        exit;
    }
}

// Sugerencias de tipo: los de siempre más los que ya se usaron.
$tipos = ['fotocopiadora', 'impresora', 'abrochadora', 'intercaladora', 'guillotina', 'anilladora', 'plastificadora'];
$res = mysqli_query($conn, "SELECT DISTINCT tipo FROM maquinas ORDER BY tipo");
while ($fila = mysqli_fetch_row($res)) $tipos[] = $fila[0];
$tipos = array_unique($tipos);

function valor($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }
?>

<div class="modal-header bg-dark text-white">
    <h5 class="modal-title"><i class="bi bi-printer"></i> <?= $id ? 'Editar máquina' : 'Nueva máquina' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">
    <div class="container-fluid p-3 bg-white rounded shadow-sm">
        <div class="row mb-3 g-2">
            <div class="col-md-4">
                <label class="form-label fw-bold">Tipo:</label>
                <input type="text" class="form-control" id="maqTipo" maxlength="30" list="maqTipos" value="<?= valor($maquina['tipo']) ?>">
                <datalist id="maqTipos">
                    <?php foreach ($tipos as $t): ?><option value="<?= valor($t) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-8">
                <label class="form-label fw-bold">Nombre:</label>
                <input type="text" class="form-control" id="maqNombre" maxlength="50" value="<?= valor($maquina['nombre']) ?>" placeholder="Como la llaman en la imprenta">
            </div>
            <div class="col-md-6">
                <label class="form-label">Marca:</label>
                <input type="text" class="form-control" id="maqMarca" maxlength="50" value="<?= valor($maquina['marca']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Modelo:</label>
                <input type="text" class="form-control" id="maqModelo" maxlength="50" value="<?= valor($maquina['modelo']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Observaciones:</label>
                <input type="text" class="form-control" id="maqObservaciones" maxlength="255" value="<?= valor($maquina['observaciones']) ?>">
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="maqActivo" <?= $maquina['activo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="maqActivo">Activa (se sigue usando)</label>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="button" id="btnGuardarMaquina" class="btn btn-warning fw-bold flex-grow-1"><i class="bi bi-save"></i> GUARDAR</button>
            <?php if ($id): ?>
            <button type="button" id="btnBorrarMaquina" class="btn btn-outline-danger" title="Borrar"><i class="bi bi-trash"></i></button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    function respuesta(res) {
        if (res.trim().indexOf('✅') !== 0) { alert(res); return; }
        $('#modalUniversal').modal('hide');
        $('#contenido').load('insumos/maquinas.php');
    }

    $('#btnGuardarMaquina').on('click', function() {
        $.post('insumos/ajaxInsumos.php', {
            opcion: 'guardarMaquina',
            id: <?= $id ?>,
            tipo: $('#maqTipo').val(),
            nombre: $('#maqNombre').val(),
            marca: $('#maqMarca').val(),
            modelo: $('#maqModelo').val(),
            observaciones: $('#maqObservaciones').val(),
            activo: $('#maqActivo').is(':checked') ? 1 : ''
        }, respuesta);
    });

    $('#btnBorrarMaquina').on('click', function() {
        if (confirm('¿Borrar esta máquina?')) {
            $.post('insumos/ajaxInsumos.php', { opcion: 'borrarMaquina', id: <?= $id ?> }, respuesta);
        }
    });
});
</script>
