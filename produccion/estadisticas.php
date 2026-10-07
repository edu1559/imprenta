<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
$conn = conectar();

// Cantidades por empleado en un período: pedidos que cargó, pedidos que terminó como
// responsable de producción y pagos que cobró. Solo ventas, sin anulados.

// Fechas: rango rápido o desde-hasta manual, igual que en finanzas/finanzas.php.
$rangos = [
    'hoy'    => ['Hoy',         'today',                      'today'],
    'semana' => ['Esta semana', 'monday this week',           'sunday this week'],
    'mes'    => ['Este mes',    'first day of this month',    'last day of this month'],
    'anio'   => ['Este año',    'first day of january',       'last day of december'],
];
$rango = $_GET['rango'] ?? 'mes';
$esFecha = fn($f) => is_string($f) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f);
if ($rango === 'custom' && $esFecha($_GET['desde'] ?? null) && $esFecha($_GET['hasta'] ?? null)) {
    $fechaDesde = $_GET['desde'];
    $fechaHasta = $_GET['hasta'];
} else {
    if (!isset($rangos[$rango])) $rango = 'mes';
    $fechaDesde = date('Y-m-d', strtotime($rangos[$rango][1]));
    $fechaHasta = date('Y-m-d', strtotime($rangos[$rango][2]));
}
$desdeSQL = $fechaDesde . ' 00:00:00';
$hastaSQL = date('Y-m-d', strtotime($fechaHasta . ' +1 day')) . ' 00:00:00'; // límite exclusivo

// Cada consulta devuelve (idUsuario, cantidad, monto) y se vuelca en su columna.
$filas = [];
function sumarColumna($conn, &$filas, $columna, $sql, $params = []) {
    $stmt = $conn->prepare($sql);
    if ($params) $stmt->bind_param(str_repeat('s', count($params)), ...$params);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_NUM) as [$idUsuario, $cant, $monto]) {
        $filas[(int)$idUsuario][$columna] = ['cant' => (int)$cant, 'monto' => (float)$monto];
    }
}

sumarColumna($conn, $filas, 'cargados',
    "SELECT idUsuario, COUNT(*), COALESCE(SUM(monto), 0) FROM pedidos
     WHERE anulado = 0 AND idTipoPedido = 1 AND entrada >= ? AND entrada < ? GROUP BY idUsuario",
    [$desdeSQL, $hastaSQL]);

sumarColumna($conn, $filas, 'terminados',
    "SELECT idResponsable, COUNT(*), COALESCE(SUM(monto), 0) FROM pedidos
     WHERE anulado = 0 AND idTipoPedido = 1 AND idResponsable IS NOT NULL
       AND fechaTerminado >= ? AND fechaTerminado < ? GROUP BY idResponsable",
    [$desdeSQL, $hastaSQL]);

// Lo que cada uno tiene en su columna de la pizarra ahora mismo (no depende del período).
sumarColumna($conn, $filas, 'enCurso',
    "SELECT idResponsable, COUNT(*), COALESCE(SUM(monto), 0) FROM pedidos
     WHERE anulado = 0 AND idTipoPedido = 1 AND idResponsable IS NOT NULL
       AND estadoProduccion IN (1, 2) GROUP BY idResponsable");

// Los pagos 'sinRegistro' no son plata cobrada ese día (ver PAGOS_REALES en finanzas.php).
sumarColumna($conn, $filas, 'cobros',
    "SELECT pg.idUsuario, COUNT(*), COALESCE(SUM(pg.monto), 0)
     FROM pagos pg INNER JOIN pedidos p ON p.id = pg.idPedido
     WHERE p.idTipoPedido = 1 AND pg.fecha >= ? AND pg.fecha < ?
       AND pg.idMedioPago NOT IN (SELECT id FROM mediosPago WHERE medio = 'sinRegistro')
     GROUP BY pg.idUsuario",
    [$desdeSQL, $hastaSQL]);

$usuarios = [];
$res = mysqli_query($conn, "SELECT id, usuario FROM usuarios");
while ($u = mysqli_fetch_assoc($res)) $usuarios[(int)$u['id']] = $u['usuario'];

$nombreUsuario = fn($id) => $usuarios[$id] ?? ($id ? "usuario $id" : 'sin usuario');
uksort($filas, fn($a, $b) => strcasecmp($nombreUsuario($a), $nombreUsuario($b)));

$columnas = ['cargados', 'terminados', 'enCurso', 'cobros'];
$totales = [];
$maximos = [];
foreach ($columnas as $col) {
    $cantidades = array_map(fn($f) => $f[$col]['cant'] ?? 0, $filas);
    $totales[$col] = ['cant' => array_sum($cantidades), 'monto' => array_sum(array_map(fn($f) => $f[$col]['monto'] ?? 0, $filas))];
    $maximos[$col] = max($cantidades ?: [0]);
}

function fmtMonto($n) { return '$' . number_format((float)$n, 0, ',', '.'); }

// Celda con la cantidad y una barra proporcional al mayor de la columna.
function celdaCantidad($fila, $col, $maximo, $color, $conMonto = false) {
    $cant = $fila[$col]['cant'] ?? 0;
    if (!$cant) return '<td class="text-muted">-</td>';
    $ancho = $maximo ? max(2, round(100 * $cant / $maximo)) : 0;
    $monto = $conMonto ? ' <span class="small text-muted">' . fmtMonto($fila[$col]['monto']) . '</span>' : '';
    return "<td><span class='fw-bold'>$cant</span>$monto
              <div class='progress mt-1' style='height: 5px;'><div class='progress-bar $color' style='width: $ancho%'></div></div></td>";
}
?>

<div class="container-fluid py-4 px-4" id="estadisticasProduccion">

    <div class="mb-4 bg-white p-3 shadow-sm rounded">
        <h3 class="text-success mb-0 fw-bold"><i class="bi bi-people me-2"></i> Estadísticas por empleado</h3>
        <small class="text-muted">Pedidos cargados, producción y cobros de cada uno</small>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <label class="form-label small fw-bold text-muted mb-1">Fecha</label>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php foreach ($rangos as $clave => [$etiqueta]): ?>
                <button type="button" class="btn btn-sm rango-rapido <?= $rango === $clave ? 'btn-success' : 'btn-outline-success' ?>" data-rango="<?= $clave ?>"><?= $etiqueta ?></button>
                <?php endforeach; ?>
                <span class="text-muted small mx-1">o rango manual:</span>
                <input type="date" class="form-control form-control-sm" id="inpDesde" style="width:auto;" value="<?= $fechaDesde ?>">
                <span class="text-muted small">a</span>
                <input type="date" class="form-control form-control-sm" id="inpHasta" style="width:auto;" value="<?= $fechaHasta ?>">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAplicarFechas">Aplicar</button>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-dark text-white py-2">
            Del <?= date('d/m/Y', strtotime($fechaDesde)) ?> al <?= date('d/m/Y', strtotime($fechaHasta)) ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Empleado</th>
                            <th style="width: 22%" title="Ventas que cargó en el período">Cargó pedidos</th>
                            <th style="width: 22%" title="Pedidos que terminó en el período como responsable de producción">Producción terminada</th>
                            <th style="width: 16%" title="Pedidos que tiene ahora en su columna de la pizarra">En su pizarra hoy</th>
                            <th style="width: 22%" title="Pagos de ventas que registró en el período">Cobró</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$filas): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Sin registros en el período.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($filas as $idUsuario => $fila): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars(ucfirst($nombreUsuario($idUsuario))) ?></td>
                            <?= celdaCantidad($fila, 'cargados', $maximos['cargados'], 'bg-success') ?>
                            <?= celdaCantidad($fila, 'terminados', $maximos['terminados'], 'bg-primary') ?>
                            <?= celdaCantidad($fila, 'enCurso', $maximos['enCurso'], 'bg-warning') ?>
                            <?= celdaCantidad($fila, 'cobros', $maximos['cobros'], 'bg-info', true) ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if ($filas): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td>Total</td>
                            <td><?= $totales['cargados']['cant'] ?></td>
                            <td><?= $totales['terminados']['cant'] ?></td>
                            <td><?= $totales['enCurso']['cant'] ?></td>
                            <td><?= $totales['cobros']['cant'] ?> <span class="small text-muted fw-normal"><?= fmtMonto($totales['cobros']['monto']) ?></span></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <div class="card-footer small text-muted">
            <i class="bi bi-info-circle me-1"></i>
            "Producción terminada" cuenta los pedidos que tienen responsable en la pizarra. Los que se cargan ya
            terminados en el mostrador no tienen responsable y figuran solo en "Cargó pedidos".
        </div>
    </div>
</div>

<script>
    function cargarEstadisticas(params) {
        $('#contenido').load('produccion/estadisticas.php?' + $.param(params));
    }
    $('#estadisticasProduccion .rango-rapido').on('click', function() {
        cargarEstadisticas({ rango: $(this).data('rango') });
    });
    $('#estadisticasProduccion #btnAplicarFechas').on('click', function() {
        if (!$('#inpDesde').val() || !$('#inpHasta').val()) {
            alert('Elegí ambas fechas.');
            return;
        }
        cargarEstadisticas({ rango: 'custom', desde: $('#inpDesde').val(), hasta: $('#inpHasta').val() });
    });
</script>
