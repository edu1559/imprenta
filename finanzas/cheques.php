<?php
// Finanzas → Cheques: la cartera (lo que tenemos) y los entregados / rechazados.
// Clic en un cheque abre su ficha (modalCheque.php) con las acciones; "Ingresar cheque" carga uno
// sin pedido (modalIngresarCheque.php). Con ?al=AAAA-MM-DD muestra la cartera que había ese día.
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
include_once(__DIR__ . '/../cheques.php');
$conn = conectar();

$filtro = $_GET['estado'] ?? 'tenemos';
if (!in_array($filtro, ['tenemos', 'entregado', 'rechazado', 'todos'], true)) $filtro = 'tenemos';
$LIMITE = 300;

// Cartera a una fecha: cargados hasta ese día y que seguían en cartera al terminar el día
$al = $_GET['al'] ?? '';
$fechaAl = DateTime::createFromFormat('Y-m-d', $al);
if (!$fechaAl || $fechaAl->format('Y-m-d') !== $al || $al >= date('Y-m-d')) $al = '';
if ($al !== '') $filtro = 'tenemos';

$donde = $filtro === 'todos' ? '1' : "c.estado = '$filtro'";
if ($al !== '') $donde = "c.fechaCarga < '$al' + INTERVAL 1 DAY AND (c.estado = 'tenemos' OR c.fechaEstado >= '$al' + INTERVAL 1 DAY)";
$orden = $filtro === 'tenemos' ? 'c.fechaPago, c.id' : 'COALESCE(c.fechaEstado, c.fechaCarga) DESC, c.id DESC';
$sql = "SELECT c.*,
               CONCAT_WS(' ', ce.apellido, ce.nombre) AS entrego,
               CONCAT_WS(' ', cd.apellido, cd.nombre) AS destino,
               md.medio AS medioDestino,
               (SELECT GROUP_CONCAT(DISTINCT pg.idPedido ORDER BY pg.idPedido) FROM pagos pg WHERE pg.idCheque = c.id) AS pedidos,
               IF(c.origen <> 'pago', 0, c.monto) - COALESCE((SELECT SUM(pg.monto) FROM pagos pg INNER JOIN pedidos pe ON pe.id = pg.idPedido
                                   WHERE pg.idCheque = c.id AND pg.monto > 0 AND pe.idTipoPedido <> " . TIPO_PEDIDO_COMPRA . "), 0) AS disponible
        FROM cheques c
        LEFT JOIN contactos ce ON ce.id = c.idContactoEntrega
        LEFT JOIN contactos cd ON cd.id = c.idContactoDestino
        LEFT JOIN mediosPago md ON md.id = c.idMedioDestino
        WHERE $donde
        ORDER BY $orden
        LIMIT $LIMITE";
$cheques = mysqli_fetch_all(mysqli_query($conn, $sql), MYSQLI_ASSOC);

// Resumen de la cartera (siempre, aunque el filtro sea otro)
$hoy = date('Y-m-d');
$en7 = date('Y-m-d', strtotime('+7 days'));
$limiteVence = date('Y-m-d', strtotime('-' . (DIAS_VALIDEZ_CHEQUE - 5) . ' days')); // les quedan 5 días o menos
$r = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cant, COALESCE(SUM(monto), 0) AS total,
        COALESCE(SUM(CASE WHEN fechaPago <= '$hoy' THEN monto END), 0) AS cobrable,
        SUM(fechaPago <= '$hoy') AS cantCobrable,
        COALESCE(SUM(CASE WHEN fechaPago > '$hoy' AND fechaPago <= '$en7' THEN monto END), 0) AS proximos,
        SUM(fechaPago > '$hoy' AND fechaPago <= '$en7') AS cantProximos,
        SUM(fechaPago <= '$limiteVence') AS cantVencen
    FROM cheques WHERE estado = 'tenemos'"));

$caja = saldoCajaCheques($conn);
$totalAl = $al !== '' ? array_sum(array_column($cheques, 'monto')) : 0;

// Estado de la fecha de pago de un cheque en cartera: [clase del badge, texto]
function estadoFecha($fechaPago) {
    $dias = (int)floor((strtotime($fechaPago) - strtotime(date('Y-m-d'))) / 86400);
    $quedan = DIAS_VALIDEZ_CHEQUE + $dias; // días que le quedan para presentarlo
    if ($quedan < 0)  return ['bg-dark', 'vencido'];
    if ($quedan <= 5) return ['bg-danger', 'vence en ' . $quedan . ($quedan === 1 ? ' día' : ' días')];
    if ($dias <= 0)   return ['bg-success', 'se puede cobrar'];
    if ($dias <= 7)   return ['bg-warning text-dark', 'en ' . $dias . ($dias === 1 ? ' día' : ' días')];
    return ['bg-light text-dark border', 'en ' . $dias . ' días'];
}

function plata($n) {
    return '$' . number_format((float)$n, 0, ',', '.');
}
?>

<style>
    #tablaCheques thead th { background: #e7f1ff; white-space: nowrap; }
    #tablaCheques tbody tr { cursor: pointer; }
    .tarjetaCheque .valor { font-size: 1.15rem; font-weight: 700; }
</style>

<div class="container-fluid p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="fw-bold mb-0"><i class="bi bi-bank text-success me-2"></i>Cheques</h2>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group" role="group">
                <?php foreach (['tenemos' => 'En cartera', 'entregado' => 'Entregados', 'rechazado' => 'Rechazados', 'todos' => 'Todos'] as $v => $t): ?>
                    <button class="btn btn-sm btnEstadoCheque <?php echo $filtro === $v && $al === '' ? 'btn-primary' : 'btn-outline-primary'; ?>" data-estado="<?php echo $v; ?>"><?php echo $t; ?></button>
                <?php endforeach; ?>
            </div>
            <div class="input-group input-group-sm" style="width: auto;" title="Ver qué cheques teníamos en cartera ese día">
                <span class="input-group-text">Cartera al</span>
                <input type="date" id="inpChequesAl" class="form-control" max="<?php echo date('Y-m-d', strtotime('-1 day')); ?>" value="<?php echo $al; ?>">
            </div>
            <input type="search" id="inpBuscarCheque" class="form-control form-control-sm" style="width: 220px;" placeholder="Número, banco, titular, cliente...">
            <button class="btn btn-success btn-sm" id="btnIngresarCheque"><i class="bi bi-plus-lg"></i> Ingresar cheque</button>
        </div>
    </div>

    <?php if ($al !== ''): ?>
        <div class="alert alert-info py-2">
            Cartera al <strong><?php echo date('d/m/Y', strtotime($al)); ?></strong>: <?php echo count($cheques); ?> cheques por <strong><?php echo pesosCheque($totalAl); ?></strong>.
            <span class="small">(Se arma con la fecha en que se cargó cada cheque y la de su último cambio de estado; el detalle exacto está en la historia de cada cheque.)</span>
            <button class="btn btn-sm btn-outline-secondary ms-2" id="btnChequesHoy">Volver a hoy</button>
        </div>
    <?php endif; ?>

    <div class="row g-2 mb-3">
        <div class="col-6 col-md">
            <div class="card tarjetaCheque h-100"><div class="card-body p-2">
                <div class="small text-muted">En cartera</div>
                <div class="valor text-primary"><?php echo plata($r['total']); ?></div>
                <div class="small text-muted"><?php echo (int)$r['cant']; ?> cheques</div>
            </div></div>
        </div>
        <div class="col-6 col-md">
            <div class="card tarjetaCheque h-100"><div class="card-body p-2">
                <div class="small text-muted">Ya se pueden cobrar</div>
                <div class="valor text-success"><?php echo plata($r['cobrable']); ?></div>
                <div class="small text-muted"><?php echo (int)$r['cantCobrable']; ?> cheques</div>
            </div></div>
        </div>
        <div class="col-6 col-md">
            <div class="card tarjetaCheque h-100"><div class="card-body p-2">
                <div class="small text-muted">Próximos 7 días</div>
                <div class="valor text-warning"><?php echo plata($r['proximos']); ?></div>
                <div class="small text-muted"><?php echo (int)$r['cantProximos']; ?> cheques</div>
            </div></div>
        </div>
        <div class="col-6 col-md">
            <div class="card tarjetaCheque h-100 <?php echo $r['cantVencen'] ? 'border-danger' : ''; ?>"><div class="card-body p-2">
                <div class="small text-muted">Por vencer o vencidos</div>
                <div class="valor <?php echo $r['cantVencen'] ? 'text-danger' : 'text-muted'; ?>"><?php echo (int)$r['cantVencen']; ?></div>
                <div class="small text-muted">se presentan hasta <?php echo DIAS_VALIDEZ_CHEQUE; ?> días después de su fecha</div>
            </div></div>
        </div>
        <?php if ($caja): $difCaja = round($caja['saldo'] - (float)$r['total'], 2); ?>
        <div class="col-6 col-md">
            <div class="card tarjetaCheque h-100 <?php echo abs($difCaja) > 0.009 ? 'border-warning' : ''; ?>"><div class="card-body p-2"
                 title="Saldo calculado del medio cheques en Cierre de Caja: lo contado en el último cierre (<?php echo date('d/m/Y H:i', strtotime($caja['fechaUC'])); ?>) más los cobros, pagos y movimientos desde entonces">
                <div class="small text-muted">Saldo de cheques en la caja</div>
                <div class="valor"><?php echo plata($caja['saldo']); ?></div>
                <?php if (abs($difCaja) > 0.009): ?>
                    <div class="small text-warning fw-bold"><?php echo $difCaja > 0 ? 'sobran ' : 'faltan '; ?><?php echo plata(abs($difCaja)); ?> respecto de la cartera</div>
                <?php else: ?>
                    <div class="small text-success">coincide con la cartera</div>
                <?php endif; ?>
            </div></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="tablaCheques">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cheque</th>
                            <th>Titular</th>
                            <th>Lo entregó</th>
                            <th class="text-end">Monto</th>
                            <th>Fecha de pago</th>
                            <th>Estado</th>
                            <th>Pedidos</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$cheques): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No hay cheques <?php echo $filtro === 'todos' ? 'cargados' : 'en este estado'; ?>.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($cheques as $c):
                        [$claseFecha, $textoFecha] = estadoFecha($c['fechaPago']);
                    ?>
                        <tr data-id="<?php echo $c['id']; ?>"
                            data-buscar="<?php echo htmlspecialchars(strtolower($c['numero'] . ' ' . $c['banco'] . ' ' . $c['titular'] . ' ' . $c['cuitTitular'] . ' ' . $c['entrego'] . ' ' . $c['destino'] . ' ' . $c['pedidos'])); ?>">
                            <td class="text-muted small"><?php echo $c['id']; ?></td>
                            <td>
                                <span class="badge bg-secondary"><?php echo TIPOS_CHEQUE[$c['tipo']]; ?></span>
                                <strong><?php echo htmlspecialchars($c['banco']); ?></strong> N° <?php echo htmlspecialchars($c['numero']); ?>
                            </td>
                            <td class="small"><?php echo htmlspecialchars($c['titular']); ?></td>
                            <td class="small"><?php echo htmlspecialchars($c['entrego'] ?? ''); ?>
                                <?php if ($c['origen'] !== 'pago'): ?><span class="badge bg-light text-dark border"><?php echo ORIGENES_CHEQUE[$c['origen']]; ?></span><?php endif; ?>
                            </td>
                            <td class="text-end fw-bold">
                                <?php echo pesosCheque($c['monto']); ?>
                                <?php if ($c['estado'] === 'tenemos' && $c['disponible'] > 0.009): ?>
                                    <div class="small text-danger fw-normal" title="Parte del cheque que no está asignada a ningún pedido">sin asignar <?php echo pesosCheque($c['disponible']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($c['fechaPago'])); ?>
                                <?php if ($c['estado'] === 'tenemos'): ?><span class="badge <?php echo $claseFecha; ?>"><?php echo $textoFecha; ?></span><?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if ($c['estado'] === 'tenemos' || $al !== ''): ?>
                                    <span class="badge bg-primary">En cartera</span>
                                <?php elseif ($c['estado'] === 'entregado'): [$salida, $detalle] = describirSalida($c, $c['destino'], $c['medioDestino']); ?>
                                    <span class="badge bg-success"><?php echo $salida; ?></span>
                                    <?php echo htmlspecialchars($detalle); ?>
                                <?php else: ?>
                                    <span class="badge bg-danger">Rechazado</span>
                                <?php endif; ?>
                                <?php if ($c['estado'] !== 'tenemos' && $c['fechaEstado'] && $al === ''): ?>
                                    <div class="text-muted"><?php echo date('d/m/Y', strtotime($c['fechaEstado'])); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="small"><?php echo $c['pedidos'] ? '#' . str_replace(',', ', #', $c['pedidos']) : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if (count($cheques) === $LIMITE): ?>
        <div class="small text-muted mt-2">Se muestran los últimos <?php echo $LIMITE; ?>.</div>
    <?php endif; ?>
</div>

<script>
window.recargarCheques = function() {
    $('#contenido').load('finanzas/cheques.php?estado=<?php echo $filtro; ?>');
};

$('.btnEstadoCheque').click(function() {
    $('#contenido').load('finanzas/cheques.php?estado=' + $(this).data('estado'));
});

$('#inpChequesAl').on('change', function() {
    if (this.value) $('#contenido').load('finanzas/cheques.php?al=' + this.value);
});
$('#btnChequesHoy').click(function() {
    $('#contenido').load('finanzas/cheques.php');
});

$('#btnIngresarCheque').click(function() {
    $('#modalUniversal .modal-content').load('finanzas/modalIngresarCheque.php', function(response, status) {
        if (status !== 'error') $('#modalUniversal').modal('show');
    });
});

$('#inpBuscarCheque').on('input', function() {
    let q = this.value.trim().toLowerCase();
    $('#tablaCheques tbody tr[data-id]').each(function() {
        $(this).toggle(q === '' || String($(this).data('buscar')).indexOf(q) !== -1);
    });
});

$('#tablaCheques tbody tr[data-id]').click(function() {
    $('#modalUniversal .modal-content').load('finanzas/modalCheque.php?idCheque=' + $(this).data('id'), function(response, status) {
        if (status !== 'error') $('#modalUniversal').modal('show');
    });
});
</script>
