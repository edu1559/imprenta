<?php
include_once(__DIR__ . '/../sesion.php');
exigirPermisoPagina('insumos/stock.php');
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/comunInsumos.php');
$conn = conectar();

// Stock de los insumos que lo controlan (tilde en la ficha del insumo). Arriba, lo que quedó
// por debajo del mínimo, agrupado por el proveedor más barato para armar los pedidos.
const DIAS_GASTADO = 90;

$insumos = mysqli_fetch_all(mysqli_query($conn, "SELECT i.*, c.nombre AS categoria,
                                   (SELECT MAX(fecha) FROM insumosMovimientos WHERE idInsumo = i.id AND tipo = 'inventario') AS ultimoInventario,
                                   (SELECT MAX(fecha) FROM insumosMovimientos WHERE idInsumo = i.id AND tipo = 'compra') AS ultimaCompra,
                                   (SELECT -SUM(cantidad) FROM insumosMovimientos
                                    WHERE idInsumo = i.id AND tipo IN ('salida', 'inventario', 'pedido') AND fecha >= NOW() - INTERVAL " . DIAS_GASTADO . " DAY) AS gastado
                            FROM insumos i
                            INNER JOIN insumosCategorias c ON c.id = i.idCategoria
                            WHERE i.controlaStock = 1 AND i.activo = 1
                            ORDER BY c.orden, i.nombre"), MYSQLI_ASSOC);
$vigentes = preciosVigentes($conn);

$aReponer = [];
foreach ($insumos as $i) {
    if ($i['stockMinimo'] === null || (int)$i['stockActual'] >= (int)$i['stockMinimo']) continue;
    $mejor = $vigentes[$i['id']][0] ?? null;
    $aReponer[$mejor ? $mejor['proveedor'] : ''][] = $i;
}
// Los que no tienen precio cargado (sin proveedor) van al final.
uksort($aReponer, fn($a, $b) => [$a === '', $a] <=> [$b === '', $b]);

function fechaCorta($f) { return $f ? date('d/m/y', strtotime($f)) : '-'; }
?>

<div class="container-fluid py-3 px-4" id="stock">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 bg-white p-3 shadow-sm rounded">
        <div>
            <h3 class="text-success mb-0 fw-bold"><i class="bi bi-boxes me-2"></i> Stock de insumos</h3>
            <small class="text-muted">Se cargan las compras y cada tanto se cuenta lo que hay: la diferencia es lo que se gastó</small>
        </div>
        <button type="button" class="btn btn-success" id="btnInventarioGeneral"><i class="bi bi-clipboard-check"></i> Contar todo (inventario)</button>
    </div>

    <?php if ($aReponer): ?>
    <div class="card border-danger shadow-sm mb-4">
        <div class="card-header bg-danger text-white fw-bold"><i class="bi bi-exclamation-triangle"></i> A reponer</div>
        <div class="card-body py-2">
            <?php foreach ($aReponer as $proveedor => $lista): ?>
            <div class="mb-2">
                <div class="fw-bold small"><?= $proveedor !== '' ? htmlspecialchars($proveedor) . ' <span class="fw-normal text-muted">(el más barato)</span>' : '<span class="text-muted">Sin precio cargado</span>' ?></div>
                <ul class="mb-0 small">
                    <?php foreach ($lista as $i): ?>
                    <li>
                        <strong><?= htmlspecialchars($i['nombre']) ?></strong>:
                        hay <?= cantidadEnUnidades($i, $i['stockActual']) ?>, mínimo <?= cantidadEnUnidades($i, $i['stockMinimo']) ?>
                        → faltan <?= cantidadEnUnidades($i, (int)$i['stockMinimo'] - (int)$i['stockActual']) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$insumos): ?>
    <div class="alert alert-light border text-center text-muted">
        Ningún insumo tiene el control de stock activado. Se activa con el tilde "Controlar stock" en la ficha del insumo.
    </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Insumo</th>
                        <th>Hay</th>
                        <th>Mínimo</th>
                        <th title="Salidas, pedidos y diferencias de inventario">Gastado (<?= DIAS_GASTADO ?> días)</th>
                        <th>Última compra</th>
                        <th>Último recuento</th>
                        <th class="text-end">Cargar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($insumos as $i):
                        $bajo = $i['stockMinimo'] !== null && (int)$i['stockActual'] < (int)$i['stockMinimo'];
                    ?>
                    <tr data-id="<?= (int)$i['id'] ?>">
                        <td>
                            <a href="#" class="fw-bold text-decoration-none text-dark lnkMovimientos"><?= htmlspecialchars($i['nombre']) ?></a>
                            <div class="small text-muted"><?= htmlspecialchars($i['categoria']) ?></div>
                        </td>
                        <td class="<?= $bajo || $i['stockActual'] < 0 ? 'text-danger fw-bold' : '' ?>"><?= cantidadEnUnidades($i, $i['stockActual']) ?></td>
                        <td class="small"><?= $i['stockMinimo'] !== null ? cantidadEnUnidades($i, $i['stockMinimo']) : '<span class="text-muted">sin aviso</span>' ?></td>
                        <td class="small"><?= $i['gastado'] !== null ? cantidadEnUnidades($i, $i['gastado']) : '-' ?></td>
                        <td class="small"><?= fechaCorta($i['ultimaCompra']) ?></td>
                        <td class="small"><?= fechaCorta($i['ultimoInventario']) ?></td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-outline-success btn-sm btnMovimiento" data-tipo="compra" title="Compra: entra"><i class="bi bi-plus-lg"></i> Compra</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm btnMovimiento" data-tipo="salida" title="Salida suelta"><i class="bi bi-dash-lg"></i></button>
                            <button type="button" class="btn btn-outline-primary btn-sm btnMovimiento" data-tipo="inventario" title="Contar lo que hay"><i class="bi bi-clipboard-check"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
    function abrirStock(url) {
        $('#modalUniversal .modal-content').load(url, function() {
            $('#modalUniversal').modal('show');
        });
    }

    $('#stock').on('click', '.btnMovimiento', function() {
        abrirStock('insumos/modalStock.php?idInsumo=' + $(this).closest('tr').data('id') + '&tipo=' + $(this).data('tipo'));
    });
    $('#stock').on('click', '.lnkMovimientos', function(e) {
        e.preventDefault();
        abrirStock('insumos/modalStock.php?idInsumo=' + $(this).closest('tr').data('id'));
    });
    $('#btnInventarioGeneral').on('click', function() { abrirStock('insumos/modalInventario.php'); });
</script>
