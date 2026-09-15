<?php
include_once('../conexion.php');
$conn = conectar();

// Revisa si el id del contacto está definido y es un número
if (isset($_GET['idContacto']) && is_numeric($_GET['idContacto'])) {
    $idContacto = $_GET['idContacto'];
} else {
    // Si no hay un ID válido, muestra un mensaje de error y detén la ejecución
    die('No se ha seleccionado un contacto válido.');
}

/* Busco los datos del contacto */
// Preparamos la consulta para evitar inyecciones SQL
$sql = "SELECT id, apellido, nombre, telefono, correo, tipoFactura, cuit, fechaCarga, tipo, notas 
        FROM contactos 
        WHERE id = ? ";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $idContacto);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Si no se encuentra el contacto, muestra un mensaje
if (mysqli_num_rows($result) === 0) {
    die('Contacto no encontrado.');
}

$myrow = mysqli_fetch_assoc($result);

// Asignamos los datos a variables
$id = $myrow["id"];
$apellido = $myrow["apellido"];
$nombre = $myrow["nombre"];
$telefono = $myrow["telefono"];
$correo = $myrow["correo"];
$tipoFactura = $myrow["tipoFactura"];
$cuit = $myrow["cuit"];
$fechaCarga = $myrow["fechaCarga"];
$tipo = $myrow["tipo"];
$notas = $myrow["notas"];

mysqli_stmt_close($stmt);
?>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="modal-header bg-success text-white center"> 
    <h5 class="modal-title">Borrar Contacto <?php echo htmlspecialchars($apellido . " " . $nombre); ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <h5 class="text-center text-primary mb-3">
                        <?php echo htmlspecialchars($apellido . " " . $nombre); ?>
                    </h5>
                    <hr>
                </div>
            </div>

            <div class="row">
                <input type="hidden" id="idContacto" value="<?php echo $id; ?>">
                <div class="col-md-6 mb-2">
                    <strong>Teléfono:</strong> <?php echo htmlspecialchars($telefono); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Correo:</strong> <?php echo htmlspecialchars($correo); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Tipo de factura:</strong> <?php echo htmlspecialchars($tipoFactura); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>CUIT:</strong> <?php echo htmlspecialchars($cuit); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Fecha de carga:</strong> <?php echo htmlspecialchars(date('d/m/Y', strtotime($fechaCarga))); ?>
                </div>
                <div class="col-md-6 mb-2">
                    <strong>Tipo de contacto:</strong> <?php echo htmlspecialchars($tipo); ?>
                </div>
            </div>

            <?php if (!empty($notas)) : ?>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <strong>Notas:</strong> <?php echo nl2br(htmlspecialchars($notas)); ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
  

<!-- Busco los datos de los pedidos -->
<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0 text-center">Pedidos realizados</h5>
        </div>
        <div class="card-body"  style="max-height: 400px; overflow-y: scroll;">
            <?php
            /* Busco los datos de los pedidos */
            $sql_pedidos = "SELECT id, entrada, detalle, monto, montoPagado, estadoEntrega, estadoPago, estadoProduccion, idUsuario 
                            FROM pedidos 
                            WHERE idContacto = ? 
                            ORDER BY entrada DESC "; // Ordenamos por fecha de entrada para ver los más recientes primero
            //echo $sql_pedidos;
            $stmt_pedidos = mysqli_prepare($conn, $sql_pedidos);
            mysqli_stmt_bind_param($stmt_pedidos, "i", $idContacto);
            mysqli_stmt_execute($stmt_pedidos);
            $result_pedidos = mysqli_stmt_get_result($stmt_pedidos);
            ?>

            <?php if (mysqli_num_rows($result_pedidos) > 0) : ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover rounded">
                        <thead class="table-success">
                            <tr>
                                <th># Pedido</th>
                                <th>Entrada</th>
                                <th>Detalle</th>
                                <th>Monto</th>
                                <th>Estado de Pago</th>
                                <th>Estado de Entrega</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($pedido = mysqli_fetch_assoc($result_pedidos)) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pedido['idPedido']); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($pedido['entrada']))); ?></td>
                                    <td><?php echo htmlspecialchars($pedido['detalle']); ?></td>
                                    <td><?php echo '$' . number_format($pedido['monto'], 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($pedido['estadoPago']); ?></td>
                                    <td><?php echo htmlspecialchars($pedido['estadoEntrega']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else : ?>
                <p class="text-muted text-center">Este contacto no tiene pedidos registrados.</p>
            <?php endif; ?>

            <?php mysqli_stmt_close($stmt_pedidos); ?>
        </div>
    </div>
</div>

<?php mysqli_close($conn); ?>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
    <button type="button" class="btn btn-danger borrarContacto" id="guardarDatos">Borrar Contacto</button>
</div>

<script>
      $('.borrarContacto').click(function(){
          v_idContacto = $('#idContacto').val();
          v_url = 'contactos/ajaxContactos.php?opcion=borraContacto&idContacto=' + v_idContacto
          alert(v_url);
          $('#mensaje').load(v_url);
      });

</script>