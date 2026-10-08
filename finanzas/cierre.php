<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
$conn = conectar();

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

    $cierre[$idM] = [
        'medio' => $row['medio'],
        'fechaUC' => $row['fechaUC'],
        'montoUC' => (float)$row['montoUC'],
        'difUC' => (float)$row['diferenciaUC'],
        'cantOp' => (int)$datosNuevos['cant'],
        'montoCalculado' => (float)($datosNuevos['total'] ?? 0)
    ];
}
?>

<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold"><i class="bi bi-bank text-success me-2"></i>Cierre de Caja</h2>
        <button id="btnCerrarTodos" class="btn btn-success btn-lg shadow">
            <i class="bi bi-check-all"></i> Cierre Total del Día
        </button>
    </div>

    <div class="row g-4">
        <div class="col-lg-9">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="mb-0">Estado por Medios de Pago</h5>
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
                                    <th class="text-end bg-light">Debería Haber</th>
                                    <th class="text-center">Saldo Real (Auditoría)</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cierre as $id => $v):
                                    $deberiaHaber = $v['montoUC'] + $v['montoCalculado'];
                                    // Color dinámico: si el movimiento neto es negativo, usamos rojo
                                    $colorMovimiento = ($v['montoCalculado'] < 0) ? 'text-danger' : 'text-primary';
                                    $signo = ($v['montoCalculado'] >= 0) ? '+$' : '-$';
                                    $valorMostrar = abs($v['montoCalculado']);
                                ?>
                                <tr>
                                    <td class="fw-bold"><?php echo $v['medio']; ?></td>
                                    <td><small class="text-muted"><?php echo date('d/m/y', strtotime($v['fechaUC'])); ?></small></td>
                                    <td class="text-end">$<?php echo number_format($v['montoUC'], 0, ',', '.'); ?></td>
                                    <td class="text-center"><span class="badge bg-secondary rounded-pill"><?php echo $v['cantOp']; ?></span></td>
                                    <td class="text-end <?php echo $colorMovimiento; ?> fw-bold">
                                        <?php echo $signo . number_format($valorMostrar, 0, ',', '.'); ?>
                                    </td>
                                    <td class="text-end bg-light fw-bold">$<?php echo number_format($deberiaHaber, 0, ',', '.'); ?></td>
                                    <td style="width: 190px;">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="text" inputmode="numeric" class="form-control inpMontoReal fw-bold text-success text-end"
                                                   data-id="<?php echo $id; ?>"
                                                   data-calculado="<?php echo $deberiaHaber; ?>"
                                                   value="<?php echo number_format($deberiaHaber, 0, ',', '.'); ?>">
                                        </div>
                                    </td>
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
                <div class="card-header bg-secondary text-white py-2 small">Historial de Cierres Totales</div>
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
            <div id="panelDetalleMovimientos" class="card border-0 shadow-sm" style="display:none;">
                <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between">
                    <span>Detalle: <span id="nombreMedioDetalle"></span></span>
                    <button type="button" class="btn-close btn-sm" onclick="$('#panelDetalleMovimientos').hide()"></button>
                </div>
                <div class="card-body p-0" id="cuerpoDetalleMovimientos"></div>
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
    let texto = $inp.val().trim();
    if (texto === $inp.data('mostrado')) return parseFloat($inp.data('calculado'));
    return parseFloat(texto.replace(/\./g, '').replace(',', '.')) || 0;
}
function mostrarSaldoReal($inp) {
    let valor = saldoReal($inp);
    $inp.val(Math.round(valor).toLocaleString('es-AR'));
    if (Math.round(valor) !== Math.round(parseFloat($inp.data('calculado')))) $inp.data('mostrado', null);
}
$('.inpMontoReal').each(function() { $(this).data('mostrado', $(this).val()); });
$('#tablaCierre').on('change blur', '.inpMontoReal', function() { mostrarSaldoReal($(this)); });

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

    if(confirm('¿Confirmas el cierre PARCIAL de este medio con un saldo de $' + Math.round(v_montoReal).toLocaleString('es-AR') + '?')) {
        $.post('finanzas/ajaxCierre.php', {
            opcion: 'cierre',
            idMedio: v_idMedio,
            montoCalculado: v_montoCalculado,
            montoReal: v_montoReal
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
    let sumaTotalCalculado = 0;

    // Recorremos cada fila de la tabla de medios para recolectar datos
    $('#tablaCierre tbody tr').each(function() {
        let idMedio = $(this).find('.inpMontoReal').data('id');
        let calculado = parseFloat($(this).find('.inpMontoReal').data('calculado'));
        let real = saldoReal($(this).find('.inpMontoReal'));

        if (idMedio) {
            datosCierre.push({
                id: idMedio,
                calc: calculado,
                real: real
            });
            sumaTotalReal += real;
            sumaTotalCalculado += calculado;
        }
    });

    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Procesando...');

    $.post('finanzas/ajaxCierre.php', {
        opcion: 'cerrarTodos',
        medios: JSON.stringify(datosCierre), // Enviamos el array como JSON
        totalSuma: sumaTotalReal,
        totalDiferencia: (sumaTotalReal - sumaTotalCalculado)
    }, function(res) {
        alert(res);
        $('#contenido').load('finanzas/cierre.php');
    });
});
</script>
