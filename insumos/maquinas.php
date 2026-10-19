<?php
include_once(__DIR__ . '/../sesion.php');
exigirPermisoPagina('insumos/maquinas.php');
include_once(__DIR__ . '/../conexion.php');
$conn = conectar();

// Máquinas de la imprenta, agrupadas por tipo. El toner se engancha a la máquina en que se usa.
$maquinas = mysqli_fetch_all(mysqli_query($conn, "SELECT m.*, (SELECT COUNT(*) FROM insumos i WHERE i.idMaquina = m.id) AS insumos
                                                  FROM maquinas m
                                                  ORDER BY m.activo DESC, m.tipo, m.nombre"), MYSQLI_ASSOC);
?>

<div class="container-fluid py-3 px-4" id="maquinas">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 bg-white p-3 shadow-sm rounded">
        <div>
            <h3 class="text-success mb-0 fw-bold"><i class="bi bi-printer me-2"></i> Máquinas</h3>
            <small class="text-muted">Fotocopiadoras, impresoras, abrochadoras, intercaladoras...</small>
        </div>
        <button type="button" class="btn btn-success" id="btnNuevaMaquina"><i class="bi bi-plus-circle"></i> Nueva máquina</button>
    </div>

    <?php if (!$maquinas): ?>
    <div class="alert alert-light border text-center text-muted">Todavía no hay máquinas cargadas.</div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Tipo</th><th>Nombre</th><th>Marca y modelo</th><th>Observaciones</th><th class="text-center">Insumos</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($maquinas as $m): ?>
                    <tr data-id="<?= (int)$m['id'] ?>" class="<?= $m['activo'] ? '' : 'text-muted' ?>" style="cursor: pointer">
                        <td><?= htmlspecialchars($m['tipo']) ?></td>
                        <td class="fw-bold">
                            <?= htmlspecialchars($m['nombre']) ?>
                            <?php if (!$m['activo']): ?><span class="badge bg-light text-dark border">inactiva</span><?php endif; ?>
                        </td>
                        <td class="small"><?= htmlspecialchars(trim($m['marca'] . ' ' . $m['modelo'])) ?></td>
                        <td class="small"><?= htmlspecialchars($m['observaciones'] ?? '') ?></td>
                        <td class="text-center small"><?= (int)$m['insumos'] ?: '-' ?></td>
                        <td class="text-end"><button type="button" class="btn btn-outline-dark btn-sm" title="Editar"><i class="bi bi-pencil"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    function abrirMaquina(id) {
        $('#modalUniversal .modal-content').load('insumos/modalMaquina.php?id=' + id, function() {
            $('#modalUniversal').modal('show');
        });
    }
    $('#btnNuevaMaquina').on('click', function() { abrirMaquina(0); });
    $('#maquinas').on('click', 'tbody tr', function() { abrirMaquina($(this).data('id')); });
</script>
