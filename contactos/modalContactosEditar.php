<?php
include_once('../conexion.php');
$conn = conectar();

// Validación de seguridad para el ID
if (isset($_GET['idContacto']) && is_numeric($_GET['idContacto'])) {
    $idContacto = $_GET['idContacto'];
} else {
    die('<div class="alert alert-danger">No se ha seleccionado un contacto válido.</div>');
}

/* Busco los datos del contacto */
$sql = "select id, apellido, nombre, telefono, correo, tipoFactura, cuit, notas 
        from contactos 
        where id = ? ";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $idContacto);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    die('<div class="alert alert-danger">Contacto no encontrado.</div>');
}

$myrow = mysqli_fetch_assoc($result);

// Asignación de variables
$id = $myrow["id"];
$apellido = $myrow["apellido"];
$nombre = $myrow["nombre"];
$telefono = $myrow["telefono"];
$correo = $myrow["correo"];
$tipoFactura = $myrow["tipoFactura"];
$cuit = $myrow["cuit"];
$notas = $myrow["notas"];

mysqli_stmt_close($stmt);
?>

<div class="modal-header bg-primary text-white"> 
    <h5 class="modal-title">Editar: <?php echo htmlspecialchars($apellido . " " . $nombre); ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <div class="container-fluid">
        <form id="formEditarContacto">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="apellido1" class="form-label">Apellido:</label>
                    <input type="text" class="form-control" id="apellido1" value="<?php echo htmlspecialchars($apellido); ?>">
                </div>
                <div class="col-md-6">
                    <label for="nombre1" class="form-label">Nombre:</label>
                    <input type="text" class="form-control" id="nombre1" value="<?php echo htmlspecialchars($nombre); ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="telefono1" class="form-label">Teléfono:</label>
                    <input type="text" class="form-control" id="telefono1" value="<?php echo htmlspecialchars($telefono); ?>">
                </div>
                <div class="col-md-6">
                    <label for="correo1" class="form-label">Correo:</label>
                    <input type="email" class="form-control" id="correo1" value="<?php echo htmlspecialchars($correo); ?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <label for="notas1" class="form-label">Notas:</label>
                    <textarea class="form-control" id="notas1" rows="4"><?php echo htmlspecialchars($notas); ?></textarea>
                </div>
            </div>

            <input type="hidden" id="idContacto1" value="<?php echo $idContacto; ?>">
        </form>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
    <button type="button" class="btn btn-success" id="guardarDatos">Actualizar Datos</button>
</div>

<script>
    $('#guardarDatos').click(function(){
        // Captura de datos
        var v_apellido = encodeURI($('#apellido1').val());
        var v_nombre = encodeURI($('#nombre1').val());
        var v_telefono = encodeURI($('#telefono1').val());
        var v_correo = encodeURI($('#correo1').val());
        var v_notas = encodeURI($('#notas1').val()); // Agregué el campo notas
        var v_idContacto = encodeURI($('#idContacto1').val());
        
        // CONSTRUCCIÓN SEGURA DE LA URL
        // Usamos encodeURIComponent para evitar errores si hay espacios o caracteres especiales (&, ?, etc)
        var v_url = 'contactos/ajaxContactos.php?opcion=actualizarContacto' +
            '&id=' + v_idContacto +
            '&apellido=' + v_apellido +
            '&nombre=' + v_nombre +
            '&telefono=' + v_telefono +
            '&correo=' + v_correo +
            '&notas=' + v_notas;
           
            alert(v_url);
        
      $('#mensaje').load(v_url);
        
    });
</script>