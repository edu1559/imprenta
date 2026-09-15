<?php
// Intentamos conectar. Si falla, el error aparecerá en pantalla.
include_once('../conexion.php');
$conn = conectar();

if (!$conn) {
    die("Error de conexión: " . mysqli_connect_error());
}

$hoy = date('Y-m-d');

// KPIs: Pedidos de hoy (Corregido para ser rápido como el otro)
$sqlPed = "SELECT COUNT(id) as cant, SUM(monto) as total FROM pedidos 
           WHERE entrada >= '$hoy 00:00:00' AND entrada <= '$hoy 23:59:59'";
$resPed = mysqli_query($conn, $sqlPed);
$dataPed = mysqli_fetch_assoc($resPed);

// KPIs: Cobrado hoy
$sqlCobKPI = "SELECT COUNT(pa.id) as cant, SUM(pa.monto) as total 
              FROM pagos pa 
                INNER JOIN pedidos pe
                   ON  pa.idPedido = pe.id 
              WHERE pa.fecha >= '$hoy 00:00:00' 
                AND pa.fecha <= '$hoy 23:59:59' 
                AND pe.idTipoPedido = 1";
$resCob = mysqli_query($conn, $sqlCobKPI);
$dataCob = mysqli_fetch_assoc($resCob);

// KPIs: Pagado  hoy
$sqlPagKPI = "SELECT COUNT(pa.id) as cant, SUM(pa.monto) as total 
              FROM pagos pa 
                INNER JOIN pedidos pe
                   ON  pa.idPedido = pe.id 
              WHERE pa.fecha >= '$hoy 00:00:00' 
                AND pa.fecha <= '$hoy 23:59:59' 
                AND pe.idTipoPedido = 2";
$resPag = mysqli_query($conn, $sqlPagKPI);
$dataPag = mysqli_fetch_assoc($resPag);

// KPIs: Presupuestos  hoy
$sqlPresKPI = "SELECT COUNT(pa.id) as cant, SUM(pa.monto) as total 
              FROM pagos pa 
                INNER JOIN pedidos pe
                   ON  pa.idPedido = pe.id 
              WHERE pa.fecha >= '$hoy 00:00:00' 
                AND pa.fecha <= '$hoy 23:59:59' 
                AND pe.idTipoPedido = 3";
$resPres = mysqli_query($conn, $sqlPresKPI);
$dataPres = mysqli_fetch_assoc($resPres);
?>

<div class="row m-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white shadow">
            <div class="card-body text-center">
                <h6>Pedidos Hoy</h6>
                <h4><?php echo $dataPed['cant'] ?? 0; ?> | $<?php echo number_format($dataPed['total'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white shadow">
            <div class="card-body text-center">
                <h6>Cobrado Hoy</h6>
                <h4><?php echo $dataCob['cant'] ?? 0; ?> | $<?php echo number_format($dataCob['total'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white shadow">
            <div class="card-body text-center">
                <h6>Pagado Hoy</h6>
                <h4><?php echo $dataPag['cant'] ?? 0; ?> | $<?php echo number_format($dataPag['total'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-secondary text-white shadow">
            <div class="card-body text-center">
                <h6>Presupuesos Hoy</h6>
                <h4><?php echo $dataPres['cant'] ?? 0; ?> | $<?php echo number_format($dataPres['total'] ?? 0, 2); ?></h4>
            </div>
        </div>
    </div>
</div>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-11 bg-light p-3 rounded shadow-sm">
            <div class="h4 text-center mb-4">Pagos Registrados Hoy</div>
            
            <table class="table table-striped table-hover" id="tablaPagos">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Pedido</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Detalle</th>
                        <th>Monto</th>
                        <th>Medio</th>
                        <th>Usuario</th>
                        <th>Editar</th>
                        <th>Borrar</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $sql = "SELECT p.id, p.idPedido, DATE_FORMAT(p.fecha,'%H:%i hs') as hora,
                               CONCAT(c.apellido,' ',c.nombre) as cliente, pe.detalle, p.monto, 
                               mp.medio, u.usuario, pe.idTipoPedido 
                        FROM pagos p
                        INNER JOIN pedidos pe ON p.idPedido = pe.id 
                        INNER JOIN contactos c ON pe.idContacto = c.id
                        INNER JOIN mediosPago mp ON p.idMedioPago = mp.id
                        INNER JOIN usuarios u ON p.idUsuario = u.id
                        WHERE p.fecha >= '$hoy 00:00:00' AND p.fecha <= '$hoy 23:59:59'
                        ORDER BY p.fecha DESC";

                $result = mysqli_query($conn, $sql);

                if (mysqli_num_rows($result) > 0) {
                    while($myrow = mysqli_fetch_row($result)) {
                        $claseFila = "";
                        if ($myrow[8] == 2) $claseFila = "table-danger";
                        if ($myrow[8] == 1) $claseFila = "table-success";

                        echo "<tr class='$claseFila'>
                                <td>{$myrow[0]}</td>
                                <td>{$myrow[1]}</td>
                                <td>{$myrow[2]}</td>
                                <td>{$myrow[3]}</td>
                                <td><small>{$myrow[4]}</small></td>
                                <td class='fw-bold'>$".number_format($myrow[5], 2)."</td>
                                <td>{$myrow[6]}</td>
                                <td>{$myrow[7]}</td>
                                <td><button class='btn btn-sm btn-outline-primary btnEditarPago' data-id='{$myrow[0]}'><i class='bi bi-pencil'></i></button></td>
                                <td><button class='btn btn-sm btn-outline-danger btnBorraPago' data-id='{$myrow[0]}'><i class='bi bi-trash'></i></button></td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='10' class='text-center'>No se registraron pagos en el día de hoy.</td></tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Usamos delegación de eventos por si la tabla se recarga
// Desvincular eventos previos para no duplicar
   
        
   (function($) { // Encapsulamos para evitar conflictos
    $(document).ready(function() {
        
        // Desvincular eventos previos para no duplicar
        $(document).off('click', '.btnEditarPago');
        
        $(document).on('click', '.btnEditarPago', function(e) {
            e.preventDefault();
            var idPago = $(this).attr('data-id'); // Usamos attr para mayor seguridad
            
            if(!idPago) return;

            var v_url = 'finanzas/modalEditarPago.php?idPago=' + idPago;
            
            // Verificamos si el modal existe en el DOM
            if ($('#modalUniversal').length === 0) {
                console.error("No existe el div #modalUniversal en el index.");
                return;
            }

            $('.modal-content').load(v_url, function(response, status, xhr) {
                if (status === "error") {
                    console.error("Error al cargar modal: " + xhr.status);
                } else {
                    $('#modalUniversal').modal('show');
                }
            });
        });

    });
})(jQuery);

$(document).off('click', '.btnBorraPago').on('click', '.btnBorraPago', function(){
    var id = $(this).data('id');
    if(confirm("¿Seguro que deseas ELIMINAR el pago #" + id + "?")){
        $.post('finanzas/ajaxPagos.php', {opcion: 'borrarPago', id: id}, function(r){
            alert(r);
            $('#contenido').load('finanzas/pagos.php');
        });
    }
});
</script>