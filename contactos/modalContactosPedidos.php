<?php
include_once('../conexion.php');
$conn = conectar();

$idContacto = intval($_GET['idContacto']);

// 1. Buscamos datos del contacto para el título
$resCliente = mysqli_query($conn, "SELECT apellido, nombre FROM contactos WHERE id = $idContacto");
$cliente = mysqli_fetch_assoc($resCliente);

// 2. Buscamos sus pedidos
$sql = "SELECT p.id,
               p.detalle,
               date_format(p.entrada, '%d/%m/%y') as fecha,
               p.monto,
               (p.monto - p.montoPagado) as saldo,
               p.estadoProduccion,
               p.estadoEntrega,
               p.estadoPago
        FROM pedidos p
        WHERE p.idContacto = $idContacto
        ORDER BY p.id DESC";
$resPedidos = mysqli_query($conn, $sql);
?>

<div class="modal-header bg-primary text-white">
    <h5 class="modal-title">
        <i class="bi bi-journal-text me-2"></i>
        Historial: <?php echo $cliente['apellido'] . ", " . $cliente['nombre']; ?>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body bg-light">
    <div class="container-fluid">
        <?php if (mysqli_num_rows($resPedidos) > 0): ?>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover bg-white shadow-sm rounded">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Detalle</th>
                            <th class="text-center">Estados (P | E | $)</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalDeuda = 0;
                        while ($p = mysqli_fetch_assoc($resPedidos)):
                            $totalDeuda += $p['saldo'];

                            // Lógica de colores para estados rápidos
                            $cProd = ($p['estadoProduccion'] == 3) ? 'success' : (($p['estadoProduccion'] == 2) ? 'warning' : 'danger');
                            $cEntr = ($p['estadoEntrega'] == 3) ? 'success' : (($p['estadoEntrega'] == 2) ? 'warning' : 'danger');
                            $cPago = ($p['estadoPago'] == 3) ? 'success' : (($p['estadoPago'] == 2) ? 'warning' : 'danger');
                        ?>
                            <tr>
                                <td class="fw-bold">#<?php echo $p['id']; ?></td>
                                <td class="small"><?php echo $p['fecha']; ?></td>
                                <td>
                                    <div class="small text-truncate" style="max-width: 200px;" title="<?php echo $p['detalle']; ?>">
                                        <?php echo $p['detalle']; ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?php echo $cProd; ?> p-1" title="Producción"><i class="bi bi-gear-fill"></i></span>
                                    <span class="badge bg-<?php echo $cEntr; ?> p-1" title="Entrega"><i class="bi bi-truck"></i></span>
                                    <span class="badge bg-<?php echo $cPago; ?> p-1" title="Pago"><i class="bi bi-cash"></i></span>
                                </td>
                                <td class="text-end small">$<?php echo number_format($p['monto'], 2); ?></td>
                                <td class="text-end fw-bold <?php echo ($p['saldo'] > 0) ? 'text-danger' : 'text-success'; ?>">
                                    $<?php echo number_format($p['saldo'], 2); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="row mt-3 p-2 bg-white rounded shadow-sm border">
                <div class="col-6">
                    <span class="text-muted">Total de pedidos realizados:</span>
                    <span class="fw-bold"><?php echo mysqli_num_rows($resPedidos); ?></span>
                </div>
                <div class="col-6 text-end">
                    <span class="h5">Saldo Pendiente: </span>
                    <span class="h5 <?php echo ($totalDeuda > 0) ? 'text-danger' : 'text-success'; ?>">
                        $<?php echo number_format($totalDeuda, 2); ?>
                    </span>
                </div>
            </div>

        <?php else: ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle me-2"></i> Este cliente aún no tiene pedidos registrados.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
    <button type="button" class="btn btn-success btnNuevoPedidoDesdeModal" data-id="<?php echo $idContacto; ?>">
        <i class="bi bi-plus-circle me-1"></i> Crear Nuevo Pedido
    </button>
</div>

<script>
    // Por si quieres saltar directo a crearle un pedido a este cliente
    $('.btnNuevoPedidoDesdeModal').click(function() {
        let idC = $(this).data('id');
        $('#modalUniversal').modal('hide');
        // Aquí podrías disparar la carga del modal de nuevo pedido con el ID de cliente ya seleccionado
        setTimeout(function(){
             $('#contenido').load('pedidos/pedidos.php', function(){
                 // Lógica para abrir el modal de pedido nuevo
             });
        }, 300);
    });
</script>
