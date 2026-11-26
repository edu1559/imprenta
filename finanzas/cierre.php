<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
include_once(__DIR__ . '/../dolares.php');
$conn = conectar();
// Dólares: se cuentan en US$ y se valúan con la cotización vigente (ver dolares.php)
$dol = estadoDolares($conn);
$idDolares = idMedioDolares($conn);
$historialCotiz = mysqli_fetch_all(mysqli_query($conn, "SELECT c.*, u.usuario FROM cotizacionesDolar c LEFT JOIN usuarios u ON u.id = c.idUsuario
                                                        ORDER BY c.fecha DESC, c.id DESC LIMIT 5"), MYSQLI_ASSOC);

// 1. Obtener Medios de Pago Activos
$medioPago = array();
$resMedios = mysqli_query($conn, "SELECT id, medio FROM mediosPago WHERE visible=1");
while($m = mysqli_fetch_assoc($resMedios)) { $medioPago[$m['id']] = $m['medio']; }

// 2. Procesar Datos de Cierre
$cierre = array();
$sqlCierre = "SELECT cm.idMedio, cm.medio, cm.fechaUC, cm.montoUC, cm.diferenciaUC
              FROM cierreMedios cm
              INNER JOIN mediosPago mp ON cm.idMedio = mp.id
              WHERE mp.visible = 1";
$resCierre = mysqli_query($conn, $sqlCierre);
// Movimientos entre medios (retiros, depósitos, dólares) de la caja abierta
$movMedios = movimientosCajaAbierta($conn);

while ($row = mysqli_fetch_assoc($resCierre)) {
    $idM = $row['idMedio'];
    $fechaUC = $row['fechaUC'];

    // Restamos si idTipoPedido es 2 (egreso/compra)
    $sqlSum = "SELECT
                COUNT(p.id) as cant,
                SUM(CASE WHEN pe.idTipoPedido = 2 THEN (p.monto * -1) ELSE p.monto END) as total
               FROM pagos p
               INNER JOIN pedidos pe ON p.idPedido = pe.id
               WHERE p.idMedioPago = $idM AND p.fecha >= '$fechaUC'";
    $resSum = mysqli_query($conn, $sqlSum);
    $datosNuevos = mysqli_fetch_assoc($resSum);
    $mov = $movMedios[$idM] ?? ['cant' => 0, 'neto' => 0];

    $cierre[$idM] = [
        'medio' => $row['medio'],
        'fechaUC' => $row['fechaUC'],
        'montoUC' => (float)$row['montoUC'],
        'difUC' => (float)$row['diferenciaUC'],
        'cantOp' => (int)$datosNuevos['cant'] + $mov['cant'],
        'netoMov' => $mov['neto'],
        'montoCalculado' => (float)($datosNuevos['total'] ?? 0) + $mov['neto']
    ];
}
?>

<style>
    #tablaCierre thead th, #tablaHistorialCierres thead th { background: #e7f1ff; }
    #tablaCierre td.colMedio { background: #f1f6fd; }
    #tablaCierre .dato { font-size: .9rem; color: #212529; }
    #tablaCierre .saldo { font-size: 1.1rem; font-weight: 700; }
    #tablaCierre .inpMontoReal { font-size: 1.1rem; }
</style>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-bank text-success me-2"></i>Cierre de Caja</h2>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-lg" data-bs-toggle="modal" data-bs-target="#modalMovimiento">
                <i class="bi bi-arrow-left-right"></i> Movimiento entre medios
            </button>
            <button id="btnCerrarTodos" class="btn btn-success btn-lg shadow">
                <i class="bi bi-check-all"></i> Cierre Total del Día
            </button>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-9">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-dark">Estado por Medios de Pago</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="tablaCierre">
                            <thead class="table-light">
                                <tr>
                                    <th>Medio</th>
                                    <th>Últ. Cierre</th>
                                    <th class="text-end">Monto U.C.</th>
                                    <th class="text-center">Ops.</th>
                                    <th class="text-end">Movimientos</th>
                                    <th class="text-end">Saldo Calculado</th>
                                    <th class="text-center">Saldo Real</th>
                                    <th class="text-end">Diferencia</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cierre as $id => $v):
                                    $deberiaHaber = $v['montoUC'] + $v['montoCalculado'];
                                    $esDolares = $dol && $id === $idDolares;
                                    if ($esDolares) $deberiaHaber = $dol['pesos'];
                                    $signo = ($v['montoCalculado'] >= 0) ? '+$' : '-$';
                                    $valorMostrar = abs($v['montoCalculado']);
                                    // cerrado hoy y sin movimientos desde entonces
                                    $cerradoHoy = date('Y-m-d', strtotime($v['fechaUC'])) === date('Y-m-d') && $v['cantOp'] === 0;
                                ?>
                                <tr>
                                    <td class="fw-bold colMedio">
                                        <?php echo $v['medio']; ?>
                                        <?php if ($cerradoHoy): ?>
                                            <i class="bi bi-check-circle-fill text-success ms-1" title="Cerrado parcial a las <?php echo date('H:i', strtotime($v['fechaUC'])); ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td class="dato"><?php echo date('d/m/y', strtotime($v['fechaUC'])); ?></td>
                                    <td class="text-end dato">$<?php echo number_format($v['montoUC'], 0, ',', '.'); ?>
                                        <?php if ($esDolares): ?><div class="small text-muted"><?php echo usdTexto($dol['usdUC']); ?> a <?php echo cotizacionTexto($dol['cotizacionUC']); ?></div><?php endif; ?>
                                    </td>
                                    <td class="text-center"><span class="badge bg-secondary rounded-pill"><?php echo $v['cantOp']; ?></span></td>
                                    <td class="text-end dato">
                                        <?php echo $signo . number_format($valorMostrar, 0, ',', '.'); ?>
                                        <?php if ($v['netoMov'] != 0): ?>
                                            <div class="small text-info" title="Retiros, depósitos y dólares: ya están sumados en Movimientos">
                                                <i class="bi bi-arrow-left-right"></i> <?php echo ($v['netoMov'] > 0 ? '+$' : '-$') . number_format(abs($v['netoMov']), 0, ',', '.'); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($esDolares && abs($dol['usdMov']) > 0.009): ?>
                                            <div class="small text-info"><?php echo ($dol['usdMov'] > 0 ? '+' : '-') . usdTexto(abs($dol['usdMov'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end saldo text-primary">$<?php echo number_format($deberiaHaber, 0, ',', '.'); ?>
                                        <?php if ($esDolares): ?>
                                            <div class="small text-muted fw-normal"><?php echo usdTexto($dol['usd']); ?> a <?php echo cotizacionTexto($dol['cotizacion']); ?></div>
                                            <?php if (abs($dol['difCotizacion']) >= 1): ?>
                                                <div class="small fw-normal <?php echo $dol['difCotizacion'] > 0 ? 'text-success' : 'text-danger'; ?>"
                                                     title="Lo que cambió el valor en pesos por la cotización nueva. No es faltante ni sobrante: no entra en la diferencia.">
                                                    por cotización <?php echo ($dol['difCotizacion'] > 0 ? '+$' : '-$') . number_format(abs($dol['difCotizacion']), 0, ',', '.'); ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="width: 190px;">
                                        <?php if ($esDolares): ?>
                                        <div class="input-group input-group-sm" title="Dólares contados">
                                            <span class="input-group-text">US$</span>
                                            <input type="text" inputmode="decimal" class="form-control inpMontoReal fw-bold text-success text-end"
                                                   data-id="<?php echo $id; ?>"
                                                   data-calculado="<?php echo $deberiaHaber; ?>"
                                                   data-cotizacion="<?php echo $dol['cotizacion']; ?>"
                                                   data-usd="<?php echo $dol['usd']; ?>"
                                                   value="<?php echo number_format($dol['usd'], 2, ',', '.'); ?>">
                                        </div>
                                        <?php else: ?>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="text" inputmode="numeric" class="form-control inpMontoReal fw-bold text-success text-end"
                                                   data-id="<?php echo $id; ?>"
                                                   data-calculado="<?php echo $deberiaHaber; ?>"
                                                   value="<?php echo number_format($deberiaHaber, 0, ',', '.'); ?>">
                                        </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold diferencia text-muted">$0</td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <button class="btn btn-outline-primary btn-sm btnVer" data-id="<?php echo $id; ?>" data-nombre="<?php echo $v['medio']; ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-success btn-sm btnCerrarParcial" data-id="<?php echo $id; ?>" title="Cierre parcial de este medio">
                                                <i class="bi bi-lock"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-4 shadow-sm border-0">
                <div class="card-header bg-white border-bottom py-2 fw-bold text-dark">Historial de Cierres Totales</div>
                <div class="card-body p-0 overflow-auto" style="max-height: 300px;">
                    <table class="table table-hover table-striped table-sm small align-middle mb-0" id="tablaHistorialCierres">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha / Hora</th>
                                <th class="text-end">Efectivo</th>
                                <th class="text-end">BcoMacro</th>
                                <th class="text-end">M. Pago</th>
                                <th class="text-end fw-bold">Total</th>
                                <th class="text-center">Dif.</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sqlH = "SELECT * FROM cierre ORDER BY fecha DESC LIMIT 3";
                            $resH = mysqli_query($conn, $sqlH);
                            while($h = mysqli_fetch_assoc($resH)):
                                $claseDif = ($h['diferencia'] < 0) ? 'text-danger' : (($h['diferencia'] > 0) ? 'text-success' : 'text-muted');
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo date('d/m/Y', strtotime($h['fecha'])); ?></div>
                                    <small class="text-muted"><?php echo date('H:i', strtotime($h['fecha'])); ?> hs</small>
                                </td>
                                <td class="text-end">$<?php echo number_format($h['efectivo'], 0, ',', '.'); ?></td>
                                <td class="text-end">$<?php echo number_format($h['BcoMacro'], 0, ',', '.'); ?></td>
                                <td class="text-end">$<?php echo number_format($h['mercadoPago'], 0, ',', '.'); ?></td>
                                <td class="text-end fw-bold">$<?php echo number_format($h['suma'], 0, ',', '.'); ?></td>
                                <td class="text-center <?php echo $claseDif; ?> fw-bold">$<?php echo number_format($h['diferencia'], 0, ',', '.'); ?></td>
                                <td class="text-center">
                                    <button class="btn btn-outline-danger btn-sm btnBorrarCierre" data-id="<?php echo $h['id']; ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card border-0 shadow-sm mb-4 bg-primary bg-opacity-10">
                <div class="card-body text-center py-2">
                    <h6 class="mb-2"><i class="bi bi-cash-stack text-primary me-1"></i>Recuento de Billetes</h6>
                    <button class="btn btn-primary btn-sm w-100" data-bs-toggle="modal" data-bs-target="#modalEfectivo">Abrir Planilla</button>
                </div>
            </div>
            <?php if ($dol): ?>
            <div class="card border-0 shadow-sm mb-4 bg-success bg-opacity-10">
                <div class="card-body py-2">
                    <h6 class="mb-2 text-center"><i class="bi bi-currency-dollar text-success me-1"></i>Dólares</h6>
                    <div class="d-flex justify-content-between small"><span>Tenemos</span><strong><?php echo usdTexto($dol['usd']); ?></strong></div>
                    <div class="d-flex justify-content-between small"><span>Cotización</span><strong><?php echo cotizacionTexto($dol['cotizacion']); ?></strong></div>
                    <div class="d-flex justify-content-between small"><span>En pesos</span><strong>$<?php echo number_format($dol['pesos'], 0, ',', '.'); ?></strong></div>
                    <?php if ($dol['cotizacionFila']): $cf = $dol['cotizacionFila']; ?>
                        <div class="small text-muted mt-1">
                            Cotización del <?php echo date('d/m/Y H:i', strtotime($cf['fecha'])); ?><?php echo $cf['usuario'] ? ' (' . htmlspecialchars($cf['usuario']) . ')' : ''; ?>
                            <?php if ($cf['compra'] !== null && $cf['venta'] !== null): ?><br>compra <?php echo cotizacionTexto($cf['compra']); ?> / venta <?php echo cotizacionTexto($cf['venta']); ?><?php endif; ?>
                            <?php if ($cf['nota'] !== ''): ?><br><?php echo htmlspecialchars($cf['nota']); ?><?php endif; ?>
                        </div>
                        <?php if (date('Y-m-d', strtotime($cf['fecha'])) < date('Y-m-d')): ?>
                            <div class="small text-warning fw-bold">No es de hoy: actualizala antes de cerrar.</div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <button class="btn btn-success btn-sm w-100 mt-2" data-bs-toggle="modal" data-bs-target="#modalCotizacion">Actualizar cotización</button>
                </div>
            </div>
            <?php endif; ?>
            <div id="panelDetalleMovimientos" class="card border-0 shadow-sm" style="display:none;">
                <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between">
                    <span>Detalle: <span id="nombreMedioDetalle"></span></span>
                    <button type="button" class="btn-close btn-sm" onclick="$('#panelDetalleMovimientos').hide()"></button>
                </div>
                <div class="card-body p-0" id="cuerpoDetalleMovimientos"></div>
            </div>
        </div>
        <div class="modal fade" id="modalMovimiento" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-left-right me-1"></i>Movimiento entre medios</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Plata que pasa de un medio a otro: retiro del banco, depósito, compra de dólares.
                    No es venta ni compra: resta al origen y suma al destino.</p>
                <div class="row g-2 mb-2">
                    <div class="col">
                        <label class="form-label small fw-bold mb-1">Sale de</label>
                        <select id="movOrigen" class="form-select">
                            <?php foreach ($cierre as $id => $v): ?><option value="<?php echo $id; ?>" <?php echo $v['medio'] === 'BcoMacro' ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['medio']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col">
                        <label class="form-label small fw-bold mb-1">Entra a</label>
                        <select id="movDestino" class="form-select">
                            <?php foreach ($cierre as $id => $v): ?><option value="<?php echo $id; ?>" <?php echo $v['medio'] === 'efectivo' ? 'selected' : ''; ?>><?php echo htmlspecialchars($v['medio']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <label class="form-label small fw-bold mb-1">Monto en pesos</label>
                <div class="input-group mb-2">
                    <span class="input-group-text">$</span>
                    <input type="text" inputmode="numeric" id="movMonto" class="form-control text-end fw-bold" placeholder="0">
                </div>
                <div id="movDolares" class="row g-2 mb-2" style="display:none;">
                    <div class="col">
                        <label class="form-label small fw-bold mb-1">Dólares (US$)</label>
                        <input type="text" inputmode="decimal" id="movUsd" class="form-control text-end" placeholder="obligatorio">
                    </div>
                    <div class="col">
                        <label class="form-label small mb-1">Cotización ($ por US$)</label>
                        <input type="text" inputmode="decimal" id="movCotiz" class="form-control text-end" placeholder="la de la operación">
                    </div>
                    <div class="col-12 small text-muted">Los dólares se llevan en US$: cargá cuántos se compraron o vendieron y a qué cotización (el monto en pesos se calcula solo).</div>
                </div>
                <label class="form-label small fw-bold mb-1">Nota</label>
                <input type="text" id="movNota" class="form-control" maxlength="255" placeholder="Ej: retiro cajero, compra de dólares">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarMovimiento">Registrar</button>
            </div>
        </div>
    </div>
</div>

        <div class="modal fade" id="modalCotizacion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-currency-dollar me-1"></i>Cotización del dólar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">Dólar oficial del día: se toma el promedio entre compra y venta.</p>
                <div class="row g-2 mb-2">
                    <div class="col">
                        <label class="form-label small fw-bold mb-1">Compra $</label>
                        <input type="text" inputmode="decimal" id="cotCompra" class="form-control text-end">
                    </div>
                    <div class="col">
                        <label class="form-label small fw-bold mb-1">Venta $</label>
                        <input type="text" inputmode="decimal" id="cotVenta" class="form-control text-end">
                    </div>
                    <div class="col">
                        <label class="form-label small fw-bold mb-1">Promedio $</label>
                        <input type="text" id="cotValor" class="form-control text-end fw-bold" readonly>
                    </div>
                </div>
                <label class="form-label small fw-bold mb-1">Nota</label>
                <input type="text" id="cotNota" class="form-control mb-2" maxlength="255" placeholder="Opcional (ej: Banco Nación)">
                <?php if ($dol): ?>
                    <div class="small" id="cotEfecto" data-usd="<?php echo $dol['usd']; ?>" data-pesos="<?php echo $dol['pesos']; ?>"></div>
                <?php endif; ?>
                <?php if ($historialCotiz): ?>
                    <table class="table table-sm small mt-2 mb-0">
                        <thead><tr><th>Fecha</th><th class="text-end">Compra</th><th class="text-end">Venta</th><th class="text-end">Cotización</th><th>Usuario</th></tr></thead>
                        <tbody>
                        <?php foreach ($historialCotiz as $c): ?>
                            <tr title="<?php echo htmlspecialchars($c['nota']); ?>">
                                <td><?php echo date('d/m/y H:i', strtotime($c['fecha'])); ?></td>
                                <td class="text-end"><?php echo $c['compra'] !== null ? cotizacionTexto($c['compra']) : '—'; ?></td>
                                <td class="text-end"><?php echo $c['venta'] !== null ? cotizacionTexto($c['venta']) : '—'; ?></td>
                                <td class="text-end fw-bold"><?php echo cotizacionTexto($c['valor']); ?></td>
                                <td><?php echo htmlspecialchars($c['usuario'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" id="btnGuardarCotizacion">Guardar</button>
            </div>
        </div>
    </div>
</div>

        <div class="modal fade" id="modalEfectivo" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Planilla de Recuento de Efectivo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light" id="contenidoEfectivo">
                <?php include('efectivo.php'); ?>
            </div>
        </div>
    </div>
</div>

    </div>
</div>


<script>
// Saldo Real se muestra como los demás montos (1.234.567). Mientras no lo cambien,
// vale lo calculado con sus centavos, así no aparece una diferencia que nadie contó.
function saldoReal($inp) {
    if ($inp.data('cotizacion')) return Math.round(usdReal($inp) * parseFloat($inp.data('cotizacion')) * 100) / 100;
    let texto = $inp.val().trim();
    if (texto === $inp.data('mostrado')) return parseFloat($inp.data('calculado'));
    return parseFloat(texto.replace(/\./g, '').replace(',', '.')) || 0;
}
// dólares: la fila de dolares se cuenta en US$ (mientras no lo cambien, valen los calculados)
function usdReal($inp) {
    let texto = $inp.val().trim();
    if (texto === $inp.data('mostrado')) return parseFloat($inp.data('usd'));
    return numeroAR(texto);
}
function mostrarSaldoReal($inp) {
    if ($inp.data('cotizacion')) {
        let usd = usdReal($inp);
        $inp.val(usd.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        if (Math.abs(usd - parseFloat($inp.data('usd'))) > 0.009) $inp.data('mostrado', null);
        mostrarDiferencia($inp);
        return;
    }
    let valor = saldoReal($inp);
    $inp.val(Math.round(valor).toLocaleString('es-AR'));
    if (Math.round(valor) !== Math.round(parseFloat($inp.data('calculado')))) $inp.data('mostrado', null);
    mostrarDiferencia($inp);
}
// Diferencia = Saldo Real - Saldo Calculado, en rojo si falta plata y en verde si sobra
function mostrarDiferencia($inp) {
    let dif = Math.round(saldoReal($inp) - parseFloat($inp.data('calculado')));
    $inp.closest('tr').find('.diferencia')
        .text((dif < 0 ? '-$' : '$') + Math.abs(dif).toLocaleString('es-AR'))
        .removeClass('text-muted text-danger text-success')
        .addClass(dif < 0 ? 'text-danger' : (dif > 0 ? 'text-success' : 'text-muted'));
}
$('.inpMontoReal').each(function() { $(this).data('mostrado', $(this).val()); });
$('#tablaCierre').on('change blur', '.inpMontoReal', function() { mostrarSaldoReal($(this)); });
$('#tablaCierre').on('input', '.inpMontoReal', function() { mostrarDiferencia($(this)); });

// Función para ver el detalle de un medio
$('.btnVer').click(function() {
    let idM = $(this).data('id');
    let nombre = $(this).data('nombre');
    $('#nombreMedioDetalle').text(nombre);
    $('#panelDetalleMovimientos').fadeIn();

    // Cargamos el detalle por AJAX
    $('#cuerpoDetalleMovimientos').html('<div class="p-4 text-center"><div class="spinner-border text-warning"></div></div>');
    $('#cuerpoDetalleMovimientos').load('finanzas/detalleMedio.php?idMedio=' + idM);
});

$('.btnCerrarParcial').click(function() {
    let $row = $(this).closest('tr');
    let v_idMedio = $(this).data('id');
    let v_montoCalculado = $row.find('.inpMontoReal').data('calculado'); // Lo que el sistema dice
    let v_montoReal = saldoReal($row.find('.inpMontoReal')); // Lo que el usuario contó

    let v_texto = '$' + Math.round(v_montoReal).toLocaleString('es-AR');
    if ($row.find('.inpMontoReal').data('cotizacion')) v_texto = 'US$ ' + usdReal($row.find('.inpMontoReal')).toLocaleString('es-AR') + ' (' + v_texto + ')';
    if(confirm('¿Confirmas el cierre PARCIAL de este medio con un saldo de ' + v_texto + '?')) {
        $.post('finanzas/ajaxCierre.php', {
            opcion: 'cierre',
            idMedio: v_idMedio,
            montoCalculado: v_montoCalculado,
            montoReal: v_montoReal,
            usdReal: $row.find('.inpMontoReal').data('cotizacion') ? usdReal($row.find('.inpMontoReal')) : ''
        }, function(respuesta) {
            alert(respuesta);
            // Recargamos el tablero para ver la nueva "Fecha Últ. Cierre" y los montos en cero
            $('#contenido').load('finanzas/cierre.php');
        });
    }
});

$('.btnBorrarCierre').click(function() {
    let idCierre = $(this).data('id');

    let motivo = prompt('Vas a borrar este cierre: los medios que dependen de él vuelven a su cierre anterior.\n\nMotivo (obligatorio):');
    if (motivo === null) return;
    if (motivo.trim() === '') {
        alert('Tenés que indicar el motivo.');
        return;
    }

    $.post('finanzas/ajaxCierre.php', {
        opcion: 'eliminarCierre',
        id: idCierre,
        motivo: motivo.trim()
    }, function(res) {
        if(res.trim() == "OK") {
            $('#contenido').load('finanzas/cierre.php');
        } else {
            alert("Error: " + res);
        }
    });
});

$('#btnCerrarTodos').click(function() {
    if (!confirm('¿Estás seguro de realizar el CIERRE TOTAL? Se archivarán los saldos actuales de todos los medios y se reiniciará el contador de movimientos.')) return;

    let datosCierre = [];
    let sumaTotalReal = 0;

    // Recorremos cada fila de la tabla de medios para recolectar datos
    $('#tablaCierre tbody tr').each(function() {
        let idMedio = $(this).find('.inpMontoReal').data('id');
        let calculado = parseFloat($(this).find('.inpMontoReal').data('calculado'));
        let real = saldoReal($(this).find('.inpMontoReal'));

        if (idMedio) {
            let $inp = $(this).find('.inpMontoReal');
            datosCierre.push({
                id: idMedio,
                calc: calculado,
                real: real,
                usd: $inp.data('cotizacion') ? usdReal($inp) : null
            });
            sumaTotalReal += real;
        }
    });

    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Procesando...');

    $.post('finanzas/ajaxCierre.php', {
        opcion: 'cerrarTodos',
        medios: JSON.stringify(datosCierre), // Enviamos el array como JSON
        totalSuma: sumaTotalReal
    }, function(res) {
        alert(res);
        $('#contenido').load('finanzas/cierre.php');
    });
});
// ---- Movimiento entre medios ----
// "1.234.567,50" -> 1234567.5
function numeroAR(texto) {
    return parseFloat(String(texto).trim().replace(/\./g, '').replace(',', '.')) || 0;
}
function mostrarCamposDolares() {
    let nombres = [$('#movOrigen option:selected').text(), $('#movDestino option:selected').text()];
    $('#movDolares').toggle(nombres.includes('dolares'));
}
$('#movOrigen, #movDestino').change(mostrarCamposDolares);
// con US$ y cotización completos, el monto en pesos se calcula solo
$('#movUsd, #movCotiz').on('input', function() {
    let usd = numeroAR($('#movUsd').val()), cotiz = numeroAR($('#movCotiz').val());
    if (usd > 0 && cotiz > 0) $('#movMonto').val(Math.round(usd * cotiz).toLocaleString('es-AR'));
});
$('#movMonto').on('blur', function() {
    let n = numeroAR($(this).val());
    if (n) $(this).val(n.toLocaleString('es-AR', {maximumFractionDigits: 2}));
});
$('#btnGuardarMovimiento').click(function() {
    let origen = $('#movOrigen').val(), destino = $('#movDestino').val();
    let monto = numeroAR($('#movMonto').val());
    if (origen === destino) { alert('El origen y el destino tienen que ser distintos.'); return; }
    if (monto <= 0) { alert('Falta el monto.'); return; }
    if ($('#movDolares').is(':visible') && !(numeroAR($('#movUsd').val()) > 0)) { alert('Faltan los dólares (US$).'); return; }
    let texto = 'Pasar $' + monto.toLocaleString('es-AR') + ' de ' + $('#movOrigen option:selected').text()
              + ' a ' + $('#movDestino option:selected').text() + '?';
    if (!confirm(texto)) return;

    let $btn = $(this).prop('disabled', true);
    $.post('finanzas/ajaxCierre.php', {
        opcion: 'movimientoMedios',
        idMedioOrigen: origen,
        idMedioDestino: destino,
        monto: monto,
        usd: $('#movDolares').is(':visible') && $('#movUsd').val().trim() !== '' ? numeroAR($('#movUsd').val()) : '',
        cotizacion: $('#movDolares').is(':visible') && $('#movCotiz').val().trim() !== '' ? numeroAR($('#movCotiz').val()) : '',
        nota: $('#movNota').val()
    }, function(res) {
        alert(res);
        if (res.indexOf('✅') === 0) {
            $('#modalMovimiento').modal('hide');
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open').css('overflow', '');
            $('#contenido').load('finanzas/cierre.php');
        } else {
            $btn.prop('disabled', false);
        }
    });
});

// ---- Cotización del dólar ----
function mostrarPromedioCotizacion() {
    let compra = numeroAR($('#cotCompra').val()), venta = numeroAR($('#cotVenta').val());
    let valor = compra > 0 && venta > 0 ? (compra + venta) / 2 : 0;
    $('#cotValor').val(valor ? valor.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : '');
    let $ef = $('#cotEfecto');
    if (!$ef.length) return;
    if (!valor) { $ef.text(''); return; }
    let pesos = parseFloat($ef.data('usd')) * valor, dif = pesos - parseFloat($ef.data('pesos'));
    $ef.html('Los US$ ' + parseFloat($ef.data('usd')).toLocaleString('es-AR') + ' pasan a valer <strong>$' + Math.round(pesos).toLocaleString('es-AR') +
             '</strong> (' + (dif >= 0 ? '+' : '-') + '$' + Math.abs(Math.round(dif)).toLocaleString('es-AR') + ' por cotización).');
}
$('#cotCompra, #cotVenta').on('input', mostrarPromedioCotizacion);
$('#btnGuardarCotizacion').click(function() {
    let compra = numeroAR($('#cotCompra').val()), venta = numeroAR($('#cotVenta').val());
    if (!(compra > 0) || !(venta > 0)) { alert('Cargá la compra y la venta.'); return; }
    if (compra > venta) { alert('La compra no puede ser mayor que la venta.'); return; }
    let $btn = $(this).prop('disabled', true);
    let datos = { opcion: 'cotizacion', compra: compra, venta: venta, nota: $('#cotNota').val() };
    let enviar = function() {
        $.post('finanzas/ajaxCierre.php', datos, function(res) {
            if (res.trim() === 'CONFIRMAR') {
                if (confirm('La cotización cambia más de un 30% respecto de la anterior. ¿Es correcta?')) {
                    datos.confirmado = 1;
                    enviar();
                } else {
                    $btn.prop('disabled', false);
                }
                return;
            }
            alert(res);
            if (res.indexOf('✅') === 0) {
                $('#modalCotizacion').modal('hide');
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css('overflow', '');
                $('#contenido').load('finanzas/cierre.php');
            } else {
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert('Error del servidor: no se guardó.');
            $btn.prop('disabled', false);
        });
    };
    enviar();
});
</script>
