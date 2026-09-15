<?php
include_once('../conexion.php');
$conn = conectar();

$idPedido = $_GET['idPedido'] ?? die("Error: ID no recibido");

// 1. Cargamos medios de pago
$resMedios = mysqli_query($conn, "SELECT id, medio FROM mediosPago");
$medios = mysqli_fetch_all($resMedios, MYSQLI_ASSOC);



// 2. Datos del pedido (Simplificado para mejor lectura)
$sqlPedido = "SELECT p.*, c.apellido, c.nombre, tp.tipo as tipoPedido 
              FROM pedidos p 
              INNER JOIN contactos c ON p.idContacto = c.id 
              INNER JOIN tipoPedido tp ON p.idTipoPedido = tp.id
              WHERE p.id = $idPedido";

$resPedido = mysqli_query($conn, $sqlPedido);
$p = mysqli_fetch_assoc($resPedido);

$montoTotal = $p['monto'];
$montoPagadoActual = $p['montoPagado'];
$saldo = $montoTotal - $montoPagadoActual;


// 3. Historial de pagos
$sqlPagos = "SELECT pa.*, mp.medio, u.usuario 
             FROM pagos pa 
             INNER JOIN mediosPago mp ON pa.idMedioPago = mp.id
             INNER JOIN usuarios u ON pa.idUsuario = u.id 
             WHERE idPedido = $idPedido ORDER BY pa.fecha DESC";
$resHistorial = mysqli_query($conn, $sqlPagos);

?>

<div class="modal-header bg-dark text-white">
    <h5 class="modal-title">Pagos: <?php echo $p['apellido']." ".$p['nombre']; ?> (Pedido #<?php echo $idPedido; ?>)</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">
    <div class="row g-3 mb-4 text-center">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted d-block">TOTAL PEDIDO</small>
                    <span class="h4 fw-bold">$<?php echo number_format($montoTotal, 2); ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <small class="text-muted d-block text-success">TOTAL COBRADO</small>
                    <span class="h4 fw-bold text-success">$<?php echo number_format($montoPagadoActual, 2); ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm <?php echo ($saldo > 0) ? 'bg-warning bg-opacity-10' : 'bg-success bg-opacity-10'; ?>">
                <div class="card-body">
                    <small class="text-muted d-block">SALDO PENDIENTE</small>
                    <span class="h4 fw-bold <?php echo ($saldo > 0) ? 'text-danger' : 'text-success'; ?>">
                        $<?php echo number_format($saldo, 2); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($saldo > 0.01): ?>
    <div class="card border-primary mb-4 shadow-sm">
        <div class="card-header bg-primary text-white py-1 small">REGISTRAR NUEVO PAGO</div>
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="small fw-bold">Monto a cobrar:</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text">$</span>
                        <input type="number" id="montoNuevo" class="form-control" step="0.01" max="<?php echo $saldo; ?>" value="<?php echo $saldo; ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="small fw-bold">Medio de Pago:</label>
                    <select id="idMedioPago" class="form-select form-select-sm">
                        <?php foreach($medios as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo $m['medio']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary btn-sm w-100" id="btnNvoPago">
                        <i class="bi bi-plus-circle"></i> Guardar Pago
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-success text-center py-2">
            <i class="bi bi-check-circle-fill"></i> Este pedido está totalmente pagado.
        </div>
    <?php endif; ?>

    <h6 class="border-bottom pb-2 mb-3">Historial de Movimientos</h6>
    <div class="table-responsive">
        <table class="table table-sm table-hover small">
            <thead class="table-dark">
                <tr>
                    <th>Fecha</th>
                    <th>Medio</th>
                    <th>Usuario</th>
                    <th class="text-end">Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php while($h = mysqli_fetch_assoc($resHistorial)): ?>
                <tr>
                    <td><?php echo date('d/m/Y H:i', strtotime($h['fecha'])); ?></td>
                    <td><?php echo $h['medio']; ?></td>
                    <td><?php echo $h['usuario']; ?></td>
                    <td class="text-end fw-bold">$<?php echo number_format($h['monto'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$('#btnNvoPago').click(function(){
    const v_monto = parseFloat($('#montoNuevo').val());
    const v_saldoMax = parseFloat('<?php echo $saldo; ?>');
    
    if(!v_monto || v_monto <= 0) {
        alert("Ingresa un monto válido.");
        return;
    }
    
    if(v_monto > (v_saldoMax + 0.01)) {
        alert("El pago no puede ser mayor al saldo pendiente ($" + v_saldoMax + ")");
        return;
    }

    $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

    $.post('finanzas/ajaxPagos.php', {
        opcion: 'agregarPago',
        idPedido: '<?php echo $idPedido; ?>',
        monto: v_monto,
        idMedioPago: $('#idMedioPago').val(),
        montoTotal: '<?php echo $montoTotal; ?>',
        montoPagadoAnterior: '<?php echo $montoPagadoActual; ?>'
    }, function(data) {
        $('#modalUniversal').modal('hide');
        // Recargamos el tablero de pedidos para ver los cambios de colores/montos
        $('#contenido').load('pedidos/pedidos.php');
        alert("Pago registrado correctamente");
    });
});
</script>
