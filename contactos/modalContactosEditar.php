<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../celular.php');
$conn = conectarPDO();

// Validación de seguridad para el ID
if (isset($_GET['idContacto']) && is_numeric($_GET['idContacto'])) {
    $idContacto = $_GET['idContacto'];
} else {
    die('<div class="alert alert-danger">No se ha seleccionado un contacto válido.</div>');
}

/* Busco los datos del contacto */
$sql = "select id, apellido, nombre, telefono, celular, correo, tipoFactura, cuit, notas, esEmpresa
        from contactos
        where id = :idContacto ";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':idContacto', $idContacto, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->rowCount() === 0) {
    die('<div class="alert alert-danger">Contacto no encontrado.</div>');
}

$myrow = $stmt->fetch(PDO::FETCH_ASSOC);

// Asignación de variables
$id = $myrow["id"];
$apellido = $myrow["apellido"];
$nombre = $myrow["nombre"];
$telefono = $myrow["telefono"];
$celular = $myrow["celular"] ? mostrarCelular($myrow["celular"]) : '';
$correo = $myrow["correo"];
$tipoFactura = $myrow["tipoFactura"];
$cuit = $myrow["cuit"];
$notas = $myrow["notas"];
$esEmpresa = $myrow["esEmpresa"];

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

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="esEmpresa1" <?php echo $esEmpresa ? 'checked' : ''; ?>>
                <label class="form-check-label" for="esEmpresa1">Es una empresa o institución (el nombre es la persona de contacto)</label>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="celular1" class="form-label"><i class="bi bi-whatsapp text-success"></i> Celular:</label>
                    <input type="text" class="form-control" id="celular1" placeholder="351 532-9898" value="<?php echo htmlspecialchars($celular); ?>">
                </div>
                <div class="col-md-4">
                    <label for="telefono1" class="form-label">Teléfono fijo / otro:</label>
                    <input type="text" class="form-control" id="telefono1" maxlength="50" value="<?php echo htmlspecialchars($telefono); ?>">
                </div>
                <div class="col-md-4">
                    <label for="correo1" class="form-label">Correo:</label>
                    <input type="email" class="form-control" id="correo1" maxlength="100" value="<?php echo htmlspecialchars($correo); ?>">
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
        // $.get codifica cada valor (con encodeURI se rompían nombres con & o #)
        $.get('contactos/ajaxContactos.php', {
            opcion: 'actualizarContacto',
            id: $('#idContacto1').val(),
            apellido: $('#apellido1').val(),
            nombre: $('#nombre1').val(),
            esEmpresa: $('#esEmpresa1').is(':checked') ? 1 : 0,
            celular: $('#celular1').val().trim(),
            telefono: $('#telefono1').val(),
            correo: $('#correo1').val(),
            notas: $('#notas1').val()
        }, function(res) {
            // Con error (por ejemplo un celular que no se entiende) el modal queda abierto.
            if (res.indexOf('Se actualizo') === -1) {
                alert(res);
                return;
            }
            $('#modalUniversal').modal('hide');
        });
    });
</script>