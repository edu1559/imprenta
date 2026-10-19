<?php
include_once(__DIR__ . '/../sesion.php');
exigirPermisoPagina('insumos/insumos.php');
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/comunInsumos.php');
$conn = conectar();

// Catálogo de insumos por categoría, con el mejor precio vigente de cada uno.
$verInactivos = ($_GET['ver'] ?? '') === 'todos';

$categorias = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nombre FROM insumosCategorias ORDER BY orden, nombre"), MYSQLI_ASSOC);

$insumos = mysqli_fetch_all(mysqli_query($conn, "SELECT i.*, c.nombre AS categoria, c.ficha, m.nombre AS maquina
                                                 FROM insumos i
                                                 INNER JOIN insumosCategorias c ON c.id = i.idCategoria
                                                 LEFT JOIN maquinas m ON m.id = i.idMaquina
                                                 " . ($verInactivos ? '' : 'WHERE i.activo = 1') . "
                                                 ORDER BY c.orden, i.nombre"), MYSQLI_ASSOC);
$vigentes = preciosVigentes($conn);

$cantidades = array_count_values(array_column($insumos, 'idCategoria'));
?>

<div class="container-fluid py-3 px-4" id="insumos">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 bg-white p-3 shadow-sm rounded">
        <div>
            <h3 class="text-success mb-0 fw-bold"><i class="bi bi-box-seam me-2"></i> Insumos</h3>
            <small class="text-muted">Papeles, toner, encuadernado y demás materiales, con sus precios por proveedor</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnVerInsumos" data-ver="<?= $verInactivos ? '' : 'todos' ?>">
                <?= $verInactivos ? 'Ocultar los inactivos' : 'Ver también los inactivos' ?>
            </button>
            <button type="button" class="btn btn-success" id="btnNuevoInsumo"><i class="bi bi-plus-circle"></i> Nuevo insumo</button>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="btn-group flex-wrap" role="group" id="filtroCategoria">
            <button type="button" class="btn btn-outline-dark btn-sm active" data-categoria="">Todos <span class="badge bg-secondary"><?= count($insumos) ?></span></button>
            <?php foreach ($categorias as $c): ?>
            <button type="button" class="btn btn-outline-dark btn-sm" data-categoria="<?= (int)$c['id'] ?>">
                <?= htmlspecialchars($c['nombre']) ?> <span class="badge bg-secondary"><?= $cantidades[$c['id']] ?? 0 ?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <input type="search" class="form-control form-control-sm ms-auto" style="max-width: 260px" id="buscarInsumo" placeholder="Buscar insumo...">
    </div>

    <?php if (!$insumos): ?>
    <div class="alert alert-light border text-center text-muted">Todavía no hay insumos cargados.</div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Insumo</th>
                        <th>Categoría</th>
                        <th>Presentación</th>
                        <th class="text-end">Mejor precio</th>
                        <th class="text-end">Costo por unidad</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($insumos as $i):
                        $mejor = $vigentes[$i['id']][0] ?? null;
                        $viejo = $mejor && $mejor['fecha'] < date('Y-m-d', strtotime('-' . DIAS_PRECIO_VIEJO . ' days'));
                    ?>
                    <tr data-id="<?= (int)$i['id'] ?>" data-categoria="<?= (int)$i['idCategoria'] ?>" class="<?= $i['activo'] ? '' : 'text-muted' ?>" style="cursor: pointer">
                        <td>
                            <span class="fw-bold nombreInsumo"><?= htmlspecialchars($i['nombre']) ?></span>
                            <?php if (!$i['activo']): ?><span class="badge bg-light text-dark border">inactivo</span><?php endif; ?>
                            <div class="small text-muted"><?= htmlspecialchars(detalleTecnico($i)) ?></div>
                        </td>
                        <td class="small"><?= htmlspecialchars($i['categoria']) ?></td>
                        <td class="small"><?= htmlspecialchars(presentacion($i)) ?></td>
                        <td class="text-end">
                            <?php if ($mejor): ?>
                            <?= fmtPesos($mejor['precio']) ?>
                            <div class="small <?= $viejo ? 'text-danger' : 'text-muted' ?>"
                                 title="<?= $viejo ? 'Precio de hace más de ' . DIAS_PRECIO_VIEJO . ' días: conviene actualizarlo' : '' ?>">
                                <?= htmlspecialchars($mejor['proveedor']) ?> · <?= date('d/m/y', strtotime($mejor['fecha'])) ?><?= $viejo ? ' <i class="bi bi-exclamation-triangle"></i>' : '' ?>
                            </div>
                            <?php if (count($vigentes[$i['id']]) > 1): ?>
                            <div class="small text-muted"><?= count($vigentes[$i['id']]) ?> proveedores</div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="small text-muted">sin precio</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end small"><?= $mejor ? htmlspecialchars(costoPorUnidad($i, $mejor['precio'])) : '-' ?></td>
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
    // El formulario recarga "la lista actual" al cerrarse: que vuelva a esta página.
    var urlListaActual = 'insumos/insumos.php<?= $verInactivos ? '?ver=todos' : '' ?>';

    function abrirInsumo(id) {
        $('#modalUniversal .modal-content').load('insumos/modalInsumo.php?id=' + id, function() {
            $('#modalUniversal').modal('show');
        });
    }

    function filtrarInsumos() {
        var categoria = String($('#filtroCategoria .active').data('categoria'));
        var texto = $('#buscarInsumo').val().toLowerCase();
        $('#insumos tbody tr').each(function() {
            var $tr = $(this);
            var ok = (!categoria || String($tr.data('categoria')) === categoria)
                  && (!texto || $tr.text().toLowerCase().indexOf(texto) !== -1);
            $tr.toggle(ok);
        });
    }

    $('#filtroCategoria').on('click', 'button', function() {
        $('#filtroCategoria button').removeClass('active');
        $(this).addClass('active');
        filtrarInsumos();
    });
    $('#buscarInsumo').on('input', filtrarInsumos);

    $('#btnNuevoInsumo').on('click', function() { abrirInsumo(0); });
    $('#insumos').on('click', 'tbody tr', function() { abrirInsumo($(this).data('id')); });

    $('#btnVerInsumos').on('click', function() {
        var ver = $(this).data('ver');
        $('#contenido').load('insumos/insumos.php' + (ver ? '?ver=' + ver : ''));
    });
</script>
