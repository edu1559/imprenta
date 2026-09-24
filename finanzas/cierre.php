<?php
include_once('../conexion.php');
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
        <div class="col-lg-8">
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
                                    <td style="width: 150px;">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control inpMontoReal fw-bold text-success"
                                                   data-id="<?php echo $id; ?>"
                                                   data-calculado="<?php echo $deberiaHaber; ?>"
                                                   value="<?php echo $deberiaHaber; ?>">
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
                                <th class="text-end">Transf.</th>
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
                                <td class="text-end">$<?php echo number_format($h['efectivo'], 2); ?></td>
                                <td class="text-end">$<?php echo number_format($h['transferencia'], 2); ?></td>
                                <td class="text-end">$<?php echo number_format($h['mercadoPago'], 2); ?></td>
                                <td class="text-end fw-bold">$<?php echo number_format($h['suma'], 2); ?></td>
                                <td class="text-center <?php echo $claseDif; ?> fw-bold">$<?php echo number_format($h['diferencia'], 2); ?></td>
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
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4 bg-primary bg-opacity-10">
                <div class="card-body text-center py-4">
                    <i class="bi bi-cash-stack h1 text-primary"></i>
                    <h5>Recuento de Billetes</h5>
                    <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#modalEfectivo">Abrir Planilla</button>
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

// Al cambiar el medio en la tabla de detalles
$(document).on('change', '.selMedio', function() {
    let idPago = $(this).closest('tr').find('.idPago').text();
    let idNuevoMedio = $(this).val();

    $.post('finanzas/ajaxCierre.php', {
        opcion: 'cambiaMedio',
        idPago: idPago,
        idMedio: idNuevoMedio
    }, function() {
        // Recargar el tablero completo para actualizar los saldos calculados
        $('#contenido').load('finanzas/cierre.php');
    });
});

$('.btnCerrarParcial').click(function() {
    let $row = $(this).closest('tr');
    let v_idMedio = $(this).data('id');
    let v_montoCalculado = $row.find('.inpMontoReal').data('calculado'); // Lo que el sistema dice
    let v_montoReal = $row.find('.inpMontoReal').val(); // Lo que el usuario contó

    if(confirm('¿Confirmas el cierre PARCIAL de este medio con un saldo de $' + v_montoReal + '?')) {
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

    if(confirm('¿Estás seguro de eliminar este registro de cierre? Esta acción no se puede deshacer de forma automática.')) {
        $.post('finanzas/ajaxCierre.php', {
            opcion: 'eliminarCierre',
            id: idCierre
        }, function(res) {
            if(res.trim() == "OK") {
                $('#contenido').load('finanzas/cierre.php');
            } else {
                alert("Error: " + res);
            }
        });
    }
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
        let real = parseFloat($(this).find('.inpMontoReal').val());

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
