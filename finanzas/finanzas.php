<?php
include_once('../conexion.php');
$conn = conectar();

// ---------------------------------------------------------------------
// Fechas: rango rápido (hoy/semana/mes/año) o desde-hasta manual.
// ---------------------------------------------------------------------
function calcularRangoRapido($rango) {
    $hoy = new DateTime();
    switch ($rango) {
        case 'semana':
            $desde = (clone $hoy)->modify('monday this week');
            $hasta = (clone $desde)->modify('+6 days');
            break;
        case 'mes':
            $desde = new DateTime($hoy->format('Y-m-01'));
            $hasta = (clone $desde)->modify('last day of this month');
            break;
        case 'anio':
            $desde = new DateTime($hoy->format('Y-01-01'));
            $hasta = new DateTime($hoy->format('Y-12-31'));
            break;
        case 'hoy':
        default:
            $desde = $hoy;
            $hasta = clone $hoy;
    }
    return [$desde->format('Y-m-d'), $hasta->format('Y-m-d')];
}

$rangoRapido = isset($_GET['rango']) ? $_GET['rango'] : null;
if ($rangoRapido && $rangoRapido !== 'custom') {
    [$fechaDesde, $fechaHasta] = calcularRangoRapido($rangoRapido);
} elseif (!empty($_GET['desde']) && !empty($_GET['hasta'])) {
    $fechaDesde = $_GET['desde'];
    $fechaHasta = $_GET['hasta'];
    $rangoRapido = 'custom';
} else {
    [$fechaDesde, $fechaHasta] = calcularRangoRapido('hoy');
    $rangoRapido = 'hoy';
}

$fechaDesdeSQL = $fechaDesde . ' 00:00:00';
$fechaHastaSQL = (new DateTime($fechaHasta))->modify('+1 day')->format('Y-m-d') . ' 00:00:00'; // límite exclusivo

// ---------------------------------------------------------------------
// Filtros de tipo (dos filtros independientes que se combinan):
// - tipoMov: qué movimiento mirar (todos/ingresos/egresos/nosDeben/debemos)
// - alcance: sobre qué universo de pedidos (pedidos/presupuestos/pagos/todo)
// ---------------------------------------------------------------------
$tipoMov = isset($_GET['tipoMov']) ? $_GET['tipoMov'] : 'todos';
$alcance = isset($_GET['alcance']) ? $_GET['alcance'] : 'pedidos';

// "Solo pagos concretados" no tiene noción de saldo pendiente: si venía
// Nos deben/Debemos seleccionado, lo llevamos a Todos.
if ($alcance === 'pagos' && in_array($tipoMov, ['nosDeben', 'debemos'])) {
    $tipoMov = 'todos';
}

// ---------------------------------------------------------------------
// Métricas: ingresos/egresos (pagos reales) y nos deben/debemos (saldo
// pendiente de pedidos ya concretados, venta=1 / compra=2). Ingresos y
// egresos NUNCA se suman entre sí ni con nos deben/debemos: son vistas
// separadas, no un único total.
// ---------------------------------------------------------------------
function metricaPagos($conn, $idTipo, $desdeSQL, $hastaSQL) {
    $stmt = $conn->prepare("SELECT COUNT(*) cant, COALESCE(SUM(pg.monto),0) total
                             FROM pagos pg INNER JOIN pedidos p ON p.id = pg.idPedido
                             WHERE p.idTipoPedido = ? AND pg.fecha >= ? AND pg.fecha < ?");
    $stmt->bind_param('iss', $idTipo, $desdeSQL, $hastaSQL);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function metricaSaldo($conn, $idTipo, $desdeSQL, $hastaSQL) {
    $stmt = $conn->prepare("SELECT COUNT(*) cant, COALESCE(SUM(p.monto - p.montoPagado),0) total
                             FROM pedidos p
                             WHERE p.idTipoPedido = ? AND (p.monto - p.montoPagado) > 0.01
                               AND p.entrada >= ? AND p.entrada < ?");
    $stmt->bind_param('iss', $idTipo, $desdeSQL, $hastaSQL);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$ingresos = metricaPagos($conn, 1, $fechaDesdeSQL, $fechaHastaSQL);
$egresos  = metricaPagos($conn, 2, $fechaDesdeSQL, $fechaHastaSQL);
$nosDeben = metricaSaldo($conn, 1, $fechaDesdeSQL, $fechaHastaSQL);
$debemos  = metricaSaldo($conn, 2, $fechaDesdeSQL, $fechaHastaSQL);

// Alcance "presupuestos": vista propia, no encaja en ingreso/egreso porque
// un presupuesto todavía no es venta ni compra concretada.
$presupuestos = null;
$cantPresupuestosInfo = null;
if ($alcance === 'presupuestos') {
    $stmt = $conn->prepare("SELECT COUNT(*) cant, COALESCE(SUM(monto),0) totalPresupuestado,
                                    COALESCE(SUM(montoPagado),0) totalSenado,
                                    COALESCE(SUM(monto-montoPagado),0) saldo
                             FROM pedidos WHERE idTipoPedido = 3 AND entrada >= ? AND entrada < ?");
    $stmt->bind_param('ss', $fechaDesdeSQL, $fechaHastaSQL);
    $stmt->execute();
    $presupuestos = $stmt->get_result()->fetch_assoc();
} elseif ($alcance === 'todo') {
    $stmt = $conn->prepare("SELECT COUNT(*) cant FROM pedidos WHERE idTipoPedido = 3 AND entrada >= ? AND entrada < ?");
    $stmt->bind_param('ss', $fechaDesdeSQL, $fechaHastaSQL);
    $stmt->execute();
    $cantPresupuestosInfo = $stmt->get_result()->fetch_assoc()['cant'];
}

// ---------------------------------------------------------------------
// Detalle: solo se arma cuando hay un tipoMov específico elegido (no
// "Todos"), o siempre que el alcance sea "presupuestos".
// ---------------------------------------------------------------------
function detallePagos($conn, $idTipo, $desdeSQL, $hastaSQL) {
    $stmt = $conn->prepare("SELECT pg.fecha, pg.idPedido, CONCAT(c.apellido,' ',c.nombre) cliente, p.detalle, mp.medio, pg.monto, u.usuario
                             FROM pagos pg
                             INNER JOIN pedidos p ON p.id = pg.idPedido
                             INNER JOIN contactos c ON c.id = p.idContacto
                             LEFT JOIN mediosPago mp ON mp.id = pg.idMedioPago
                             LEFT JOIN usuarios u ON u.id = pg.idUsuario
                             WHERE p.idTipoPedido = ? AND pg.fecha >= ? AND pg.fecha < ?
                             ORDER BY pg.fecha DESC LIMIT 200");
    $stmt->bind_param('iss', $idTipo, $desdeSQL, $hastaSQL);
    $stmt->execute();
    return $stmt->get_result();
}

function detalleSaldo($conn, $idTipo, $desdeSQL, $hastaSQL) {
    $stmt = $conn->prepare("SELECT p.id, p.entrada, CONCAT(c.apellido,' ',c.nombre) cliente, p.detalle, p.monto, p.montoPagado, (p.monto-p.montoPagado) saldo
                             FROM pedidos p INNER JOIN contactos c ON c.id = p.idContacto
                             WHERE p.idTipoPedido = ? AND (p.monto-p.montoPagado) > 0.01
                               AND p.entrada >= ? AND p.entrada < ?
                             ORDER BY saldo DESC LIMIT 200");
    $stmt->bind_param('iss', $idTipo, $desdeSQL, $hastaSQL);
    $stmt->execute();
    return $stmt->get_result();
}

function detallePresupuestos($conn, $desdeSQL, $hastaSQL) {
    $stmt = $conn->prepare("SELECT p.id, p.entrada, CONCAT(c.apellido,' ',c.nombre) cliente, p.detalle, p.monto, p.montoPagado, (p.monto-p.montoPagado) saldo
                             FROM pedidos p INNER JOIN contactos c ON c.id = p.idContacto
                             WHERE p.idTipoPedido = 3
                               AND p.entrada >= ? AND p.entrada < ?
                             ORDER BY p.entrada DESC LIMIT 200");
    $stmt->bind_param('ss', $desdeSQL, $hastaSQL);
    $stmt->execute();
    return $stmt->get_result();
}

$filaDetalle = null; // filas a listar
$columnasDetalle = null; // 'pagos' | 'saldo'
$tituloDetalle = '';

if ($alcance === 'presupuestos') {
    $filaDetalle = detallePresupuestos($conn, $fechaDesdeSQL, $fechaHastaSQL);
    $columnasDetalle = 'saldo';
    $tituloDetalle = 'Presupuestos del período';
} elseif ($tipoMov === 'ingresos') {
    $filaDetalle = detallePagos($conn, 1, $fechaDesdeSQL, $fechaHastaSQL);
    $columnasDetalle = 'pagos';
    $tituloDetalle = 'Ingresos (pagos de ventas)';
} elseif ($tipoMov === 'egresos') {
    $filaDetalle = detallePagos($conn, 2, $fechaDesdeSQL, $fechaHastaSQL);
    $columnasDetalle = 'pagos';
    $tituloDetalle = 'Egresos (pagos de compras)';
} elseif ($tipoMov === 'nosDeben') {
    $filaDetalle = detalleSaldo($conn, 1, $fechaDesdeSQL, $fechaHastaSQL);
    $columnasDetalle = 'saldo';
    $tituloDetalle = 'Nos deben (ventas con saldo pendiente)';
} elseif ($tipoMov === 'debemos') {
    $filaDetalle = detalleSaldo($conn, 2, $fechaDesdeSQL, $fechaHastaSQL);
    $columnasDetalle = 'saldo';
    $tituloDetalle = 'Debemos (compras con saldo pendiente)';
}
// tipoMov === 'todos' y alcance !== 'presupuestos': sin tabla de detalle,
// solo el panel de resumen con las 4 tarjetas.

function fmt($n) { return number_format((float)$n, 2, ',', '.'); }

// ---------------------------------------------------------------------
// Medios de pago, para el modal de ABM.
// ---------------------------------------------------------------------
$medios = [];
$resMedios = mysqli_query($conn, "SELECT id, medio, logo, visible FROM mediosPago ORDER BY id");
while ($m = mysqli_fetch_assoc($resMedios)) { $medios[] = $m; }
?>

<div class="container-fluid py-4 px-4">

    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <div>
            <h3 class="text-success mb-0 fw-bold">
                <i class="bi bi-graph-up-arrow me-2"></i> Finanzas
            </h3>
            <small class="text-muted">Ingresos, egresos, y saldos pendientes</small>
        </div>
        <button type="button" class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalMediosPago">
            <i class="bi bi-credit-card"></i> Medios de pago
        </button>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">

            <div class="row g-3 mb-3">
                <div class="col-12">
                    <label class="form-label small fw-bold text-muted mb-1">Fecha</label>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <button type="button" class="btn btn-sm rango-rapido <?php echo $rangoRapido === 'hoy' ? 'btn-success' : 'btn-outline-success'; ?>" data-rango="hoy">Hoy</button>
                        <button type="button" class="btn btn-sm rango-rapido <?php echo $rangoRapido === 'semana' ? 'btn-success' : 'btn-outline-success'; ?>" data-rango="semana">Esta semana</button>
                        <button type="button" class="btn btn-sm rango-rapido <?php echo $rangoRapido === 'mes' ? 'btn-success' : 'btn-outline-success'; ?>" data-rango="mes">Este mes</button>
                        <button type="button" class="btn btn-sm rango-rapido <?php echo $rangoRapido === 'anio' ? 'btn-success' : 'btn-outline-success'; ?>" data-rango="anio">Este año</button>
                        <span class="text-muted small mx-1">o rango manual:</span>
                        <input type="date" class="form-control form-control-sm" id="inpDesde" style="width:auto;" value="<?php echo $fechaDesde; ?>">
                        <span class="text-muted small">a</span>
                        <input type="date" class="form-control form-control-sm" id="inpHasta" style="width:auto;" value="<?php echo $fechaHasta; ?>">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnAplicarFechas">Aplicar</button>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1">Tipo de movimiento</label>
                    <div class="d-flex flex-wrap gap-3">
                        <?php
                        $opcionesMov = [
                            'todos'    => 'Todos',
                            'ingresos' => 'Ingresos',
                            'egresos'  => 'Egresos',
                            'nosDeben' => 'Nos deben',
                            'debemos'  => 'Debemos',
                        ];
                        foreach ($opcionesMov as $val => $label):
                            $deshabilitado = ($alcance === 'pagos' && in_array($val, ['nosDeben', 'debemos']));
                            $chk = ($tipoMov === $val) ? 'checked' : '';
                            $dis = $deshabilitado ? 'disabled' : '';
                        ?>
                        <div class="form-check">
                            <input class="form-check-input radio-tipo-mov" type="radio" name="tipoMov" id="mov_<?php echo $val; ?>" value="<?php echo $val; ?>" <?php echo "$chk $dis"; ?>>
                            <label class="form-check-label <?php echo $deshabilitado ? 'text-muted' : ''; ?>" for="mov_<?php echo $val; ?>"><?php echo $label; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1">Alcance</label>
                    <div class="d-flex flex-wrap gap-3">
                        <?php
                        $opcionesAlcance = [
                            'pedidos'      => 'Pedidos y Compras',
                            'presupuestos' => 'Presupuestos',
                            'pagos'        => 'Solo Pagos Concretados',
                            'todo'         => 'Todo',
                        ];
                        foreach ($opcionesAlcance as $val => $label):
                            $chk = ($alcance === $val) ? 'checked' : '';
                        ?>
                        <div class="form-check">
                            <input class="form-check-input radio-alcance" type="radio" name="alcance" id="alc_<?php echo $val; ?>" value="<?php echo $val; ?>" <?php echo $chk; ?>>
                            <label class="form-check-label" for="alc_<?php echo $val; ?>"><?php echo $label; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($alcance === 'presupuestos'): ?>
        <!-- Vista propia para presupuestos: no son ingreso ni egreso todavía -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body">
                        <div class="small text-muted fw-bold">CANTIDAD</div>
                        <div class="h3 mb-0"><?php echo $presupuestos['cant']; ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm text-center h-100">
                    <div class="card-body">
                        <div class="small text-muted fw-bold">PRESUPUESTADO</div>
                        <div class="h4 mb-0">$<?php echo fmt($presupuestos['totalPresupuestado']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm text-center h-100 bg-success bg-opacity-10">
                    <div class="card-body">
                        <div class="small text-muted fw-bold">SEÑADO / PAGADO</div>
                        <div class="h4 mb-0 text-success">$<?php echo fmt($presupuestos['totalSenado']); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm text-center h-100 bg-warning bg-opacity-10">
                    <div class="card-body">
                        <div class="small text-muted fw-bold">SALDO</div>
                        <div class="h4 mb-0 text-warning-emphasis">$<?php echo fmt($presupuestos['saldo']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Panel de resultados agrupados: siempre las 4 tarjetas, la elegida se resalta -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm text-center h-100 <?php echo $tipoMov === 'ingresos' ? 'bg-success bg-opacity-25 border border-success' : ''; ?>">
                    <div class="card-body">
                        <div class="small text-muted fw-bold"><i class="bi bi-arrow-down-circle text-success"></i> INGRESOS</div>
                        <div class="h4 mb-0 text-success">$<?php echo fmt($ingresos['total']); ?></div>
                        <div class="small text-muted"><?php echo $ingresos['cant']; ?> pagos</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm text-center h-100 <?php echo $tipoMov === 'egresos' ? 'bg-danger bg-opacity-25 border border-danger' : ''; ?>">
                    <div class="card-body">
                        <div class="small text-muted fw-bold"><i class="bi bi-arrow-up-circle text-danger"></i> EGRESOS</div>
                        <div class="h4 mb-0 text-danger">$<?php echo fmt($egresos['total']); ?></div>
                        <div class="small text-muted"><?php echo $egresos['cant']; ?> pagos</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm text-center h-100 <?php echo $tipoMov === 'nosDeben' ? 'bg-info bg-opacity-25 border border-info' : ''; ?>">
                    <div class="card-body">
                        <div class="small text-muted fw-bold"><i class="bi bi-hourglass-split text-info"></i> NOS DEBEN</div>
                        <div class="h4 mb-0 text-info">$<?php echo fmt($nosDeben['total']); ?></div>
                        <div class="small text-muted"><?php echo $nosDeben['cant']; ?> pedidos</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm text-center h-100 <?php echo $tipoMov === 'debemos' ? 'bg-warning bg-opacity-25 border border-warning' : ''; ?>">
                    <div class="card-body">
                        <div class="small text-muted fw-bold"><i class="bi bi-hourglass-bottom text-warning-emphasis"></i> DEBEMOS</div>
                        <div class="h4 mb-0 text-warning-emphasis">$<?php echo fmt($debemos['total']); ?></div>
                        <div class="small text-muted"><?php echo $debemos['cant']; ?> compras</div>
                    </div>
                </div>
            </div>
        </div>
        <?php if ($alcance === 'todo' && $cantPresupuestosInfo > 0): ?>
            <div class="alert alert-light border small text-muted mb-4">
                <i class="bi bi-info-circle me-1"></i>
                Además hubo <strong><?php echo $cantPresupuestosInfo; ?></strong> presupuesto(s) en el período (no incluidos en los montos de arriba). Elegí "Presupuestos" en Alcance para verlos.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($filaDetalle !== null): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-dark text-white py-2">
            <?php echo htmlspecialchars($tituloDetalle); ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <?php if ($columnasDetalle === 'pagos'): ?>
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Detalle</th>
                            <th>Medio de Pago</th>
                            <th class="text-end">Monto</th>
                            <th>Cargó</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($filaDetalle->num_rows === 0): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Sin registros en el período.</td></tr>
                        <?php else: while ($f = $filaDetalle->fetch_assoc()): ?>
                        <tr>
                            <td class="small"><?php echo date('d/m/y H:i', strtotime($f['fecha'])); ?></td>
                            <td class="fw-bold">#<?php echo $f['idPedido']; ?></td>
                            <td><?php echo htmlspecialchars($f['cliente']); ?></td>
                            <td class="small text-truncate" style="max-width:250px;" title="<?php echo htmlspecialchars($f['detalle']); ?>"><?php echo htmlspecialchars($f['detalle']); ?></td>
                            <td class="small"><?php echo htmlspecialchars($f['medio'] ?? '-'); ?></td>
                            <td class="text-end fw-bold">$<?php echo fmt($f['monto']); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars($f['usuario'] ?? '-'); ?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                    <?php else: ?>
                    <thead class="table-light">
                        <tr>
                            <th>Pedido</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Detalle</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-end">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($filaDetalle->num_rows === 0): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">Sin registros en el período.</td></tr>
                        <?php else: while ($f = $filaDetalle->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold">#<?php echo $f['id']; ?></td>
                            <td class="small"><?php echo date('d/m/y', strtotime($f['entrada'])); ?></td>
                            <td><?php echo htmlspecialchars($f['cliente']); ?></td>
                            <td class="small text-truncate" style="max-width:250px;" title="<?php echo htmlspecialchars($f['detalle']); ?>"><?php echo htmlspecialchars($f['detalle']); ?></td>
                            <td class="text-end">$<?php echo fmt($f['monto']); ?></td>
                            <td class="text-end">$<?php echo fmt($f['montoPagado']); ?></td>
                            <td class="text-end fw-bold <?php echo $f['saldo'] > 0 ? 'text-danger' : 'text-success'; ?>">$<?php echo fmt($f['saldo']); ?></td>
                        </tr>
                        <?php endwhile; endif; ?>
                    </tbody>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Modal: ABM de Medios de Pago -->
<div class="modal fade" id="modalMediosPago" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-credit-card text-success me-2"></i>Gestión de Medios de Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contenidoMediosPago">
                <table class="table table-striped table-hover align-middle" id="tablaMediosPago">
                    <thead>
                        <tr>
                            <th>id</th>
                            <th>Medio de pago</th>
                            <th>logo</th>
                            <th>Visible</th>
                            <th>editar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medios as $m): ?>
                        <tr>
                            <td class="clave"><?php echo $m['id']; ?></td>
                            <td class="medio"><?php echo htmlspecialchars($m['medio']); ?></td>
                            <td class="logo"><?php echo htmlspecialchars($m['logo']); ?></td>
                            <td class="visible"><?php echo $m['visible']; ?></td>
                            <td><i class="bi bi-pencil fs-4 text-danger btnEditarMedio" style="cursor:pointer"></i></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="nuevoRenglon">
                            <td></td>
                            <td><input type="text" id="nuevoMedio" class="form-control" placeholder="Nombre" style="display:none"></td>
                            <td><input type="text" id="nuevoLogo" class="form-control" placeholder="Logo" style="display:none"></td>
                            <td><input type="text" id="nuevoVisible" class="form-control" placeholder="1 o 0" style="display:none"></td>
                            <td><i class="bi bi-plus-square text-danger btnAgregarMedio fs-4" style="cursor:pointer"></i></td>
                        </tr>
                    </tbody>
                </table>
                <div id="mensajeMediosPago" class="small text-muted"></div>
            </div>
        </div>
    </div>
</div>

<script>
function cargarIndicadores(overrides) {
    overrides = overrides || {};
    var params = $.extend({
        desde: $('#inpDesde').val(),
        hasta: $('#inpHasta').val(),
        tipoMov: $('input[name="tipoMov"]:checked').val() || 'todos',
        alcance: $('input[name="alcance"]:checked').val() || 'pedidos'
    }, overrides);

    var qs = $.param(params);
    $('#contenido').load('finanzas/finanzas.php?' + qs);
}

$('.rango-rapido').on('click', function() {
    cargarIndicadores({ rango: $(this).data('rango'), desde: '', hasta: '' });
});

$('#btnAplicarFechas').on('click', function() {
    if (!$('#inpDesde').val() || !$('#inpHasta').val()) {
        alert('Elegí ambas fechas.');
        return;
    }
    cargarIndicadores({ rango: 'custom' });
});

$('.radio-tipo-mov').on('change', function() {
    cargarIndicadores({ rango: '<?php echo $rangoRapido; ?>' });
});

$('.radio-alcance').on('change', function() {
    cargarIndicadores({ rango: '<?php echo $rangoRapido; ?>' });
});

// --- ABM Medios de Pago (modal) ---
$('#tablaMediosPago').on('click', '.btnEditarMedio', function() {
    var $row = $(this).closest('tr');
    var v_logo = $row.find('td.logo').text();
    var v_visible = $row.find('td.visible').text();

    $row.find('td.logo').html('<input type="text" class="form-control form-control-sm nuevoLogo" value="' + v_logo + '">');
    $row.find('td.visible').html('<input type="text" class="form-control form-control-sm nuevoVisible" value="' + v_visible + '">');

    $(this).removeClass('btnEditarMedio bi-pencil').addClass('btnActualizarMedio bi-save');
});

function refrescarTablaMediosPago() {
    // Solo refresca el <tbody> de la tabla del modal, sin tocar el contenedor
    // del modal (si reemplazáramos #contenido con el modal abierto, Bootstrap
    // pierde la referencia al nodo y el modal queda roto).
    $('#tablaMediosPago tbody').load('finanzas/finanzas.php #tablaMediosPago tbody > *');
}

$('#tablaMediosPago').on('click', '.btnActualizarMedio', function() {
    var $row = $(this).closest('tr');
    var v_id = $row.find('td.clave').text();
    var nuevoLogo = $row.find('input.nuevoLogo').val();
    var nuevoVisible = $row.find('input.nuevoVisible').val();

    var v_url = 'finanzas/ajaxFinanzas.php?opcion=actualizarMedio&id=' + v_id + '&logo=' + encodeURIComponent(nuevoLogo) + '&visible=' + encodeURIComponent(nuevoVisible);
    $('#mensajeMediosPago').load(v_url, refrescarTablaMediosPago);
});

$('#tablaMediosPago').on('click', '.btnAgregarMedio', function() {
    $(this).toggleClass('bi-plus-square bi-save');
    $(this).closest('tr').find('input').toggle();
});

$('#tablaMediosPago').on('click', '.bi-save', function() {
    var nuevoMedio = $('#nuevoMedio').val();
    var nuevoLogo = $('#nuevoLogo').val();
    var nuevoVisible = $('#nuevoVisible').val();

    var v_url = 'finanzas/ajaxFinanzas.php?opcion=agregarMedio&medio=' + encodeURIComponent(nuevoMedio) + '&logo=' + encodeURIComponent(nuevoLogo) + '&visible=' + encodeURIComponent(nuevoVisible);
    $('#mensajeMediosPago').load(v_url, refrescarTablaMediosPago);
});
</script>
