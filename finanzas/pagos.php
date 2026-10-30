<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
$conn = conectar();
$puedeModificar = puedeModificar($conn);

// Filtro: caja abierta (por defecto), un día, o un cierre total.
$filtro = $_GET['filtro'] ?? 'abierta';
if (!in_array($filtro, ['abierta', 'dia', 'cierre'], true)) $filtro = 'abierta';
$fechaDia = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDia)) $fechaDia = date('Y-m-d');
$idCierre = (int)($_GET['idCierre'] ?? 0);

// Medios para el <select> de cambio de medio
$resMedios = mysqli_query($conn, "SELECT id, medio FROM mediosPago WHERE visible=1");
$listaMedios = mysqli_fetch_all($resMedios, MYSQLI_ASSOC);

// Estado de cada caja (último cierre); sirve para el saldo y para saber si un pago está en caja cerrada
$cajas = array();
$res = mysqli_query($conn, "SELECT idMedio, medio, fechaUC, montoUC FROM cierreMedios");
while ($c = mysqli_fetch_assoc($res)) { $cajas[$c['idMedio']] = $c; }

// Últimos cierres totales para el combo, cada uno con la fecha del anterior (desde dónde arranca)
$cierres = array();
$res = mysqli_query($conn, "SELECT c.*, (SELECT MAX(c2.fecha) FROM cierre c2 WHERE c2.fecha < c.fecha) AS desde
                            FROM cierre c ORDER BY c.fecha DESC LIMIT 30");
while ($c = mysqli_fetch_assoc($res)) { $cierres[$c['id']] = $c; }
if ($filtro === 'cierre' && !isset($cierres[$idCierre])) $idCierre = (int)key($cierres);
$cierreSel = $cierres[$idCierre] ?? null;

// Condición de fechas según el filtro. Un pago es de la caja abierta si es posterior al
// último cierre de su medio (igual que en cierre.php); el de un cierre total va desde el
// cierre anterior hasta ese.
if ($filtro === 'abierta') {
    $donde = "p.fecha >= cm.fechaUC";
} elseif ($filtro === 'dia') {
    $donde = "DATE(p.fecha) = '$fechaDia'";
} else {
    $hasta = $cierreSel['fecha'] ?? '1900-01-01';
    $desde = $cierreSel['desde'] ?? '1900-01-01';
    $donde = "p.fecha >= '$desde' AND p.fecha < '$hasta'";
}

$sql = "SELECT p.id AS idPago, p.idPedido, p.monto, p.fecha, p.idMedioPago,
               mp.medio,
               CONCAT_WS(' ', c.apellido, c.nombre) AS contacto,
               pe.detalle, pe.idTipoPedido,
               pe.estadoProduccion, pe.estadoEntrega, pe.estadoPago,
               u.usuario,
               (p.fecha < cm.fechaUC) AS cajaCerrada
        FROM pagos p
        INNER JOIN pedidos pe ON p.idPedido = pe.id
        INNER JOIN cierreMedios cm ON p.idMedioPago = cm.idMedio
        LEFT JOIN mediosPago mp ON p.idMedioPago = mp.id
        LEFT JOIN contactos c ON pe.idContacto = c.id
        LEFT JOIN usuarios u ON p.idUsuario = u.id
        WHERE $donde
        ORDER BY p.monto DESC, p.fecha DESC";
$result = mysqli_query($conn, $sql);
$pagos = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Tarjetas: un medio por caja. En la caja abierta parten del monto del último cierre;
// en un cierre total muestran lo que se contó en ese cierre (columna con el nombre del medio).
$tarjetas = array();
foreach ($cajas as $idM => $c) {
    $tarjetas[$idM] = [
        'medio' => $c['medio'],
        'base' => ($filtro === 'abierta') ? (float)$c['montoUC'] : null,
        'contado' => ($cierreSel && isset($cierreSel[$c['medio']])) ? (float)$cierreSel[$c['medio']] : null,
    ];
}

function textoFiltro($filtro, $fechaDia, $cierreSel) {
    if ($filtro === 'abierta') return 'Caja abierta (desde el último cierre de cada medio)';
    if ($filtro === 'dia') return 'Pagos del ' . date('d/m/Y', strtotime($fechaDia));
    if (!$cierreSel) return 'No hay cierres';
    return 'Cierre del ' . date('d/m/Y H:i', strtotime($cierreSel['fecha']))
         . ($cierreSel['desde'] ? ' (desde ' . date('d/m/Y H:i', strtotime($cierreSel['desde'])) . ')' : '');
}
?>

<style>
    #tablaPagos thead th { background: #e7f1ff; cursor: pointer; white-space: nowrap; user-select: none; }
    #tablaPagos thead th.sinOrden { cursor: default; }
    #tablaPagos tr.egreso td { background: #fdf0f0; }
    #tablaPagos .selMedio { font-size: .8rem; min-width: 120px; }
    .tarjetaMedio { cursor: pointer; transition: box-shadow .15s; }
    .tarjetaMedio.activa { outline: 2px solid #0d6efd; }
    .tarjetaMedio.sinMov { opacity: .55; }
    .tarjetaMedio .saldo { font-size: 1.15rem; font-weight: 700; }
</style>

<div class="container-fluid p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="fw-bold mb-0"><i class="bi bi-list-check text-success me-2"></i>Pagos</h2>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <div class="btn-group" role="group">
                <button class="btn btn-sm btnFiltro <?php echo $filtro === 'abierta' ? 'btn-primary' : 'btn-outline-primary'; ?>" data-filtro="abierta">Caja abierta</button>
                <button class="btn btn-sm btnFiltro <?php echo $filtro === 'dia' ? 'btn-primary' : 'btn-outline-primary'; ?>" data-filtro="dia">Por día</button>
                <button class="btn btn-sm btnFiltro <?php echo $filtro === 'cierre' ? 'btn-primary' : 'btn-outline-primary'; ?>" data-filtro="cierre">Por cierre</button>
            </div>
            <input type="date" id="inpFecha" class="form-control form-control-sm" style="width: 150px; <?php echo $filtro === 'dia' ? '' : 'display:none;'; ?>" value="<?php echo $fechaDia; ?>">
            <select id="selCierre" class="form-select form-select-sm" style="width: 200px; <?php echo $filtro === 'cierre' ? '' : 'display:none;'; ?>">
                <?php foreach ($cierres as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $idCierre ? 'selected' : ''; ?>>
                        <?php echo date('d/m/Y H:i', strtotime($c['fecha'])); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="search" id="inpBuscar" class="form-control form-control-sm" style="width: 200px;" placeholder="Buscar contacto, pedido...">
        </div>
    </div>

    <div class="text-muted small mb-2"><?php echo textoFiltro($filtro, $fechaDia, $cierreSel); ?> · clic en un medio para filtrar</div>

    <!-- Tarjetas por medio: se recalculan en el navegador a partir de las filas -->
    <div class="row g-2 mb-3" id="tarjetas">
        <div class="col-6 col-md-3 col-xl-2">
            <div class="card tarjetaMedio activa h-100" data-medio="">
                <div class="card-body p-2">
                    <div class="fw-bold">Todos</div>
                    <div class="small text-muted"><span class="cant">0</span> ops.</div>
                    <div class="saldo text-primary mov">$0</div>
                </div>
            </div>
        </div>
        <?php foreach ($tarjetas as $idM => $t): ?>
        <div class="col-6 col-md-3 col-xl-2">
            <div class="card tarjetaMedio h-100" data-medio="<?php echo $idM; ?>"
                 data-base="<?php echo $t['base'] ?? ''; ?>" data-contado="<?php echo $t['contado'] ?? ''; ?>">
                <div class="card-body p-2">
                    <div class="fw-bold"><?php echo htmlspecialchars($t['medio']); ?></div>
                    <div class="small text-muted"><span class="cant">0</span> ops. · <span class="mov">$0</span></div>
                    <?php if ($t['base'] !== null): ?>
                        <div class="small text-muted">Debería haber</div>
                        <div class="saldo text-primary deberia">$0</div>
                    <?php elseif ($t['contado'] !== null): ?>
                        <div class="small text-muted">Contado en el cierre</div>
                        <div class="saldo text-secondary">$<?php echo number_format($t['contado'], 0, ',', '.'); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="tablaPagos">
                    <thead>
                        <tr>
                            <th data-col="id" data-tipo="num">Pago</th>
                            <th data-col="fecha" data-tipo="txt">Fecha</th>
                            <th data-col="contacto" data-tipo="txt">Contacto / Detalle</th>
                            <th data-col="monto" data-tipo="num" class="text-end">Monto <i class="bi bi-sort-down"></i></th>
                            <th data-col="medio" data-tipo="txt">Medio</th>
                            <th data-col="usuario" data-tipo="txt">Usuario</th>
                            <th class="sinOrden text-center">Pedido</th>
                            <th class="sinOrden text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pagos as $f):
                        $egreso = (int)$f['idTipoPedido'] === 2;
                        $montoSigno = $egreso ? -(float)$f['monto'] : (float)$f['monto'];
                        if (!$puedeModificar) {
                            $bloqueo = 'No tenés permiso para modificar pagos.';
                        } elseif ((int)$f['cajaCerrada'] === 1) {
                            $bloqueo = 'El pago ya está en una caja cerrada. Usá un pago de ajuste desde Pagos del pedido.';
                        } elseif (pedidoCerrado($f)) {
                            $bloqueo = 'El pedido está cerrado. Usá un pago de ajuste desde Pagos del pedido.';
                        } else {
                            $bloqueo = null;
                        }
                    ?>
                        <tr class="<?php echo $egreso ? 'egreso' : ''; ?>"
                            data-id="<?php echo $f['idPago']; ?>"
                            data-fecha="<?php echo $f['fecha']; ?>"
                            data-contacto="<?php echo htmlspecialchars($f['contacto'] ?? ''); ?>"
                            data-monto="<?php echo $montoSigno; ?>"
                            data-medio="<?php echo $f['idMedioPago']; ?>"
                            data-usuario="<?php echo htmlspecialchars($f['usuario'] ?? ''); ?>"
                            data-buscar="<?php echo htmlspecialchars($f['idPago'] . ' ' . $f['idPedido'] . ' ' . $f['contacto'] . ' ' . $f['detalle']); ?>">
                            <td class="text-muted small">#<?php echo $f['idPago']; ?></td>
                            <td class="small text-nowrap"><?php echo date('d/m H:i', strtotime($f['fecha'])); ?></td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($f['contacto'] ?? ''); ?></div>
                                <div class="text-muted small text-truncate" style="max-width: 320px;"><?php echo htmlspecialchars($f['detalle'] ?? ''); ?></div>
                            </td>
                            <td class="text-end fw-bold text-nowrap <?php echo $egreso ? 'text-danger' : 'text-success'; ?>">
                                <?php echo ($egreso ? '-' : '') . '$' . number_format($f['monto'], 0, ',', '.'); ?>
                            </td>
                            <td>
                                <select class="form-select form-select-sm selMedio" data-original="<?php echo $f['idMedioPago']; ?>"
                                    <?php echo $bloqueo ? 'disabled title="' . htmlspecialchars($bloqueo) . '"' : ''; ?>>
                                    <?php foreach ($listaMedios as $m): ?>
                                        <option value="<?php echo $m['id']; ?>" <?php echo $m['id'] == $f['idMedioPago'] ? 'selected' : ''; ?>><?php echo $m['medio']; ?></option>
                                    <?php endforeach; ?>
                                    <?php if (!in_array($f['idMedioPago'], array_column($listaMedios, 'id'))): ?>
                                        <option value="<?php echo $f['idMedioPago']; ?>" selected><?php echo htmlspecialchars($f['medio'] ?? $f['idMedioPago']); ?></option>
                                    <?php endif; ?>
                                </select>
                            </td>
                            <td class="small text-muted"><?php echo htmlspecialchars($f['usuario'] ?? ''); ?></td>
                            <td class="text-center">
                                <button class="btn btn-outline-secondary btn-sm btnVerPedido" data-id="<?php echo $f['idPedido']; ?>" title="Ver orden de pedido">
                                    #<?php echo $f['idPedido']; ?>
                                </button>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($bloqueo): ?>
                                    <i class="bi bi-lock text-muted" title="<?php echo htmlspecialchars($bloqueo); ?>"></i>
                                <?php else: ?>
                                    <button class="btn btn-outline-primary btn-sm btnEditarPago" title="Modificar monto o medio"><i class="bi bi-pencil"></i></button>
                                    <button class="btn btn-outline-danger btn-sm btnBorrarPago" title="Eliminar pago"><i class="bi bi-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr id="filaVacia" style="<?php echo $pagos ? 'display:none;' : ''; ?>">
                        <td colspan="8" class="text-center py-4 text-muted">No hay pagos para mostrar.</td>
                    </tr>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="3" class="text-end">TOTAL (<span id="cantVisibles">0</span> pagos):</td>
                            <td class="text-end text-nowrap" id="totalVisibles">$0</td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Dirección de esta vista con su filtro, para volver a ella después de editar un pago
window.urlPagos = 'finanzas/pagos.php?' + $.param({
    filtro: '<?php echo $filtro; ?>', fecha: '<?php echo $fechaDia; ?>', idCierre: '<?php echo $idCierre; ?>'
});

function pesos(n) {
    n = Math.round(n);
    return (n < 0 ? '-$' : '$') + Math.abs(n).toLocaleString('es-AR');
}

// Totales por medio a partir de las filas (todas, sin importar el filtro de pantalla),
// y total de lo que se ve.
function recalcular() {
    let porMedio = {}, cantTodos = 0, totalTodos = 0;
    $('#tablaPagos tbody tr[data-id]').each(function() {
        let m = $(this).data('medio'), monto = parseFloat($(this).data('monto'));
        porMedio[m] = porMedio[m] || { cant: 0, total: 0 };
        porMedio[m].cant++;
        porMedio[m].total += monto;
        cantTodos++;
        totalTodos += monto;
    });
    $('.tarjetaMedio').each(function() {
        let m = $(this).data('medio');
        let d = (m === '') ? { cant: cantTodos, total: totalTodos } : (porMedio[m] || { cant: 0, total: 0 });
        $(this).find('.cant').text(d.cant);
        $(this).find('.mov').text((d.total > 0 && m !== '' ? '+' : '') + pesos(d.total));
        $(this).toggleClass('sinMov', d.cant === 0 && m !== '');
        let base = $(this).data('base');
        if (base !== '' && base !== undefined) $(this).find('.deberia').text(pesos(parseFloat(base) + d.total));
    });

    let cant = 0, total = 0;
    $('#tablaPagos tbody tr[data-id]:visible').each(function() {
        cant++;
        total += parseFloat($(this).data('monto'));
    });
    $('#cantVisibles').text(cant);
    $('#totalVisibles').text(pesos(total)).toggleClass('text-danger', total < 0);
    $('#filaVacia').toggle(cant === 0);
}

// Filtro en pantalla: medio elegido en las tarjetas + texto buscado
function filtrar() {
    let medio = $('.tarjetaMedio.activa').data('medio');
    let texto = $('#inpBuscar').val().trim().toLowerCase();
    $('#tablaPagos tbody tr[data-id]').each(function() {
        let ok = (medio === '' || $(this).data('medio') == medio)
              && (texto === '' || String($(this).data('buscar')).toLowerCase().indexOf(texto) !== -1);
        $(this).toggle(ok);
    });
    recalcular();
}

$('.tarjetaMedio').click(function() {
    $('.tarjetaMedio').removeClass('activa');
    $(this).addClass('activa');
    filtrar();
});
$('#inpBuscar').on('input', filtrar);

// Orden por columna: primer clic de mayor a menor (montos) o A-Z (textos); el segundo lo invierte
$('#tablaPagos thead th[data-col]').click(function() {
    let col = $(this).data('col'), num = $(this).data('tipo') === 'num';
    let desc = $(this).data('desc') === undefined ? num : !$(this).data('desc');
    $('#tablaPagos thead th').removeData('desc').find('i').remove();
    $(this).data('desc', desc).append(' <i class="bi bi-sort-' + (desc ? 'down' : 'up') + '"></i>');

    let filas = $('#tablaPagos tbody tr[data-id]').get();
    filas.sort(function(a, b) {
        let x = $(a).data(col), y = $(b).data(col);
        if (col === 'medio') { x = $(a).find('.selMedio option:selected').text(); y = $(b).find('.selMedio option:selected').text(); }
        let r = num ? (parseFloat(x) - parseFloat(y)) : String(x).localeCompare(String(y));
        return desc ? -r : r;
    });
    $('#tablaPagos tbody').prepend(filas);
});
$('#tablaPagos thead th[data-col="monto"]').data('desc', true);

// Cambio de filtro de fechas: se recarga la página
function cargarFiltro(filtro) {
    $('#contenido').load('finanzas/pagos.php?' + $.param({
        filtro: filtro, fecha: $('#inpFecha').val(), idCierre: $('#selCierre').val() || ''
    }));
}
$('.btnFiltro').click(function() { cargarFiltro($(this).data('filtro')); });
$('#inpFecha').change(function() { cargarFiltro('dia'); });
$('#selCierre').change(function() { cargarFiltro('cierre'); });

// Pide el motivo (obligatorio). Devuelve null si se cancela.
function pedirMotivo(mensaje, sugerido) {
    let motivo = prompt(mensaje + '\n\nMotivo (obligatorio):', sugerido || '');
    if (motivo === null) return null;
    if (motivo.trim() === '') {
        alert('Tenés que indicar el motivo.');
        return null;
    }
    return motivo.trim();
}

// Cambio rápido de medio: no recarga, mueve el pago de tarjeta
$('.selMedio').change(function() {
    let $sel = $(this), $fila = $sel.closest('tr'), idPago = $fila.data('id');
    let motivo = pedirMotivo('Vas a mover el pago #' + idPago + ' a ' + $sel.find('option:selected').text().trim() + '.',
                             'Medio confirmado al cierre');
    if (motivo === null) {
        $sel.val($sel.data('original'));
        return;
    }
    $sel.prop('disabled', true);
    $.post('finanzas/ajaxCierre.php', {
        opcion: 'cambiaMedio', idPago: idPago, idMedio: $sel.val(), motivo: motivo
    }, function(r) {
        $sel.prop('disabled', false);
        if (r.trim() !== 'OK') {
            alert(r);
            $sel.val($sel.data('original'));
            return;
        }
        $sel.data('original', $sel.val());
        $fila.data('medio', $sel.val()).attr('data-medio', $sel.val());
        filtrar();
    });
});

$('.btnVerPedido').click(function() {
    window.open('pedidos/modalOrden.php?idPedido=' + $(this).data('id') + '&imprimir=true', '_blank', 'width=900,height=800');
});

$('.btnEditarPago').click(function() {
    let idPago = $(this).closest('tr').data('id');
    $('#modalUniversal .modal-content').load('finanzas/modalEditarPago.php?volver=pagos&idPago=' + idPago, function(response, status) {
        if (status !== 'error') $('#modalUniversal').modal('show');
    });
});

$('.btnBorrarPago').click(function() {
    let $fila = $(this).closest('tr'), idPago = $fila.data('id');
    let motivo = pedirMotivo('Vas a ELIMINAR el pago #' + idPago + ' de ' + pesos($fila.data('monto')) + '.');
    if (motivo === null) return;
    $.post('finanzas/ajaxCierre.php', { opcion: 'borrarPago', id: idPago, motivo: motivo }, function(r) {
        if (r.trim().indexOf('✅') !== 0) {
            alert(r);
            return;
        }
        $fila.remove();
        filtrar();
    });
});

filtrar();
</script>
