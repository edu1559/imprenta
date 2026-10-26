<?php
include_once(__DIR__ . '/../sesion.php');
exigirPermisoPagina('insumos/procesos.php');
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/comunProcesos.php');
$conn = conectar();

// Catálogo de procesos (impresión, corte, plastificado, diseño...) con su precio por cantidad y su costo.
$verInactivos = ($_GET['ver'] ?? '') === 'todos';

$procesos = mysqli_fetch_all(mysqli_query($conn, "SELECT p.*, m.nombre AS maquina, m.costoPorUso, c.nombre AS complejidad, c.factor
                                                  FROM procesos p
                                                  INNER JOIN complejidades c ON c.id = p.idComplejidad
                                                  LEFT JOIN maquinas m ON m.id = p.idMaquina
                                                  " . ($verInactivos ? '' : 'WHERE p.activo = 1') . "
                                                  ORDER BY p.tipo IS NULL, p.tipo, p.nombre"), MYSQLI_ASSOC);
$tramos = $insumos = [];
foreach (mysqli_fetch_all(mysqli_query($conn, "SELECT * FROM procesosPrecios ORDER BY idProceso, desde"), MYSQLI_ASSOC) as $t) {
    $tramos[$t['idProceso']][] = $t;
}
foreach (mysqli_fetch_all(mysqli_query($conn, "SELECT pi.idProceso, pi.idInsumo, pi.cantidad, i.nombre
                                               FROM procesosInsumos pi INNER JOIN insumos i ON i.id = pi.idInsumo"), MYSQLI_ASSOC) as $i) {
    $insumos[$i['idProceso']][] = $i;
}
$costos = datosDeCosto($conn);

$tipos = array_count_values(array_map(fn($p) => (string)$p['tipo'], $procesos));
?>

<div class="container-fluid py-3 px-4" id="procesos">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 bg-white p-3 shadow-sm rounded">
        <div>
            <h3 class="text-success mb-0 fw-bold"><i class="bi bi-gear-wide-connected me-2"></i> Procesos</h3>
            <small class="text-muted">Impresión, corte, plastificado, diseño... con su precio por cantidad y lo que cuestan</small>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnVerProcesos" data-ver="<?= $verInactivos ? '' : 'todos' ?>">
                <?= $verInactivos ? 'Ocultar los inactivos' : 'Ver también los inactivos' ?>
            </button>
            <button type="button" class="btn btn-outline-dark btn-sm" id="btnComplejidades"><i class="bi bi-speedometer2"></i> Niveles de complejidad</button>
            <button type="button" class="btn btn-success" id="btnNuevoProceso"><i class="bi bi-plus-circle"></i> Nuevo proceso</button>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="btn-group flex-wrap" role="group" id="filtroTipo">
            <button type="button" class="btn btn-outline-dark btn-sm active" data-tipo="*">Todos <span class="badge bg-secondary"><?= count($procesos) ?></span></button>
            <?php foreach ($tipos as $tipo => $n): ?>
            <button type="button" class="btn btn-outline-dark btn-sm" data-tipo="<?= htmlspecialchars($tipo, ENT_QUOTES) ?>">
                <?= htmlspecialchars($tipo ?: 'Sin tipo') ?> <span class="badge bg-secondary"><?= $n ?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <input type="search" class="form-control form-control-sm ms-auto" style="max-width: 260px" id="buscarProceso" placeholder="Buscar proceso...">
    </div>

    <?php if (!$procesos): ?>
    <div class="alert alert-light border text-center text-muted">Todavía no hay procesos cargados.</div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Proceso</th>
                        <th>Se cobra por</th>
                        <th>Precio de venta</th>
                        <th>Costo</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($procesos as $p):
                        $costo = costoProceso($p, $insumos[$p['id']] ?? [], $costos);
                        $viejo = $p['fechaPrecios'] && $p['fechaPrecios'] < date('Y-m-d', strtotime('-' . DIAS_PRECIO_VIEJO . ' days'));
                        $detalle = array_filter([$p['tipo'], $p['maquina'] ? 'en ' . $p['maquina'] : null,
                                                 $p['complejidad'] !== 'Normal' ? $p['complejidad'] : null]);
                    ?>
                    <tr data-id="<?= (int)$p['id'] ?>" data-tipo="<?= htmlspecialchars((string)$p['tipo'], ENT_QUOTES) ?>" class="<?= $p['activo'] ? '' : 'text-muted' ?>" style="cursor: pointer">
                        <td>
                            <span class="fw-bold"><?= htmlspecialchars($p['nombre']) ?></span>
                            <?php if (!$p['activo']): ?><span class="badge bg-light text-dark border">inactivo</span><?php endif; ?>
                            <?php if (!$p['ventaSuelta']): ?><span class="badge bg-light text-dark border" title="Se usa solo dentro de productos">no se vende suelto</span><?php endif; ?>
                            <div class="small text-muted"><?= htmlspecialchars(implode(' · ', $detalle)) ?></div>
                        </td>
                        <td class="small"><?= htmlspecialchars($p['unidad']) ?></td>
                        <td class="small">
                            <?php foreach ($tramos[$p['id']] ?? [] as $n => $t): ?>
                            <div>
                                <?php if (count($tramos[$p['id']]) > 1): ?><span class="text-muted">desde <?= number_format($t['desde'], 0, ',', '.') ?>:</span><?php endif; ?>
                                <?= $t['arranque'] > 0 ? fmtPesos($t['arranque']) . ' + ' : '' ?><?= fmtPrecioUnidad($t['porUnidad']) ?> por <?= htmlspecialchars($p['unidad']) ?>
                            </div>
                            <?php endforeach; ?>
                            <?php if ($viejo): ?>
                            <div class="text-danger" title="Precios de hace más de <?= DIAS_PRECIO_VIEJO ?> días: conviene revisarlos">
                                <i class="bi bi-exclamation-triangle"></i> desde <?= date('d/m/y', strtotime($p['fechaPrecios'])) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?= $costo['fijo'] > 0 ? fmtPesos($costo['fijo']) . ' + ' : '' ?><?= fmtPrecioUnidad($costo['porUnidad']) ?> por <?= htmlspecialchars($p['unidad']) ?>
                            <?php if ($costo['faltan']): ?>
                            <div class="text-warning-emphasis" title="Falta <?= htmlspecialchars(implode(', ', $costo['faltan']), ENT_QUOTES) ?>">
                                <i class="bi bi-exclamation-circle"></i> incompleto
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end"><button type="button" class="btn btn-outline-dark btn-sm" title="Ver y editar"><i class="bi bi-pencil"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    var urlProcesos = 'insumos/procesos.php<?= $verInactivos ? '?ver=todos' : '' ?>';

    function abrirModalProcesos(url) {
        $('#modalUniversal .modal-content').load(url, function() {
            $('#modalUniversal').modal('show');
        });
    }

    function filtrarProcesos() {
        var tipo = String($('#filtroTipo .active').data('tipo'));
        var texto = $('#buscarProceso').val().toLowerCase();
        $('#procesos tbody tr').each(function() {
            var $tr = $(this);
            var ok = (tipo === '*' || String($tr.data('tipo')) === tipo)
                  && (!texto || $tr.text().toLowerCase().indexOf(texto) !== -1);
            $tr.toggle(ok);
        });
    }

    $('#filtroTipo').on('click', 'button', function() {
        $('#filtroTipo button').removeClass('active');
        $(this).addClass('active');
        filtrarProcesos();
    });
    $('#buscarProceso').on('input', filtrarProcesos);

    $('#btnNuevoProceso').on('click', function() { abrirModalProcesos('insumos/modalProceso.php?id=0'); });
    $('#btnComplejidades').on('click', function() { abrirModalProcesos('insumos/modalComplejidades.php'); });
    $('#procesos').on('click', 'tbody tr', function() { abrirModalProcesos('insumos/modalProceso.php?id=' + $(this).data('id')); });

    $('#btnVerProcesos').on('click', function() {
        var ver = $(this).data('ver');
        $('#contenido').load('insumos/procesos.php' + (ver ? '?ver=' + ver : ''));
    });
</script>
