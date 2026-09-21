<?php
include_once('../conexion.php');
$conn = conectarPDO();

?>

<div class="modal-header bg-secondary text-white"> 
    <h5 class="modal-title">Agregar un Contacto</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <div class="container-fluid">
        <form id="formEditarContacto">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="apellido1" class="form-label">Apellido:</label>
                    <input type="text" class="form-control" id="apellido1" value="<?php if(isset($apellido)){ echo $apellido;}?>">
                </div>
                <div class="col-md-6">
                    <label for="nombre1" class="form-label">Nombre:</label>
                    <input type="text" class="form-control" id="nombre1" value="">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="telefono1" class="form-label">Teléfono:</label>
                    <input type="text" class="form-control" id="telefono1" value="<?php if(isset($email)){ echo $email;}?>">
                </div>
                <div class="col-md-6">
                    <label for="correo1" class="form-label">Correo:</label>
                    <input type="email" class="form-control" id="correo1" value="<?php if(isset($telefono)){ echo $telefono;}?>">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <label for="notas1" class="form-label">Notas:</label>
                    <textarea class="form-control" id="notas1" rows="4"></textarea>
                </div>
            </div>

            <input type="hidden" id="idContacto1" value="<?php echo $idContacto; ?>">
        </form>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
    <button type="button" class="btn btn-success" id="guardarDatos">Guardar</button>
</div>

<script>
    $('#guardarDatos').click(function(){
        // Captura de datos
        var v_apellido = encodeURI($('#apellido1').val());
        var v_nombre = encodeURI($('#nombre1').val());
        var v_telefono = encodeURI($('#telefono1').val());
        var v_correo = encodeURI($('#correo1').val());
        var v_notas = encodeURI($('#notas1').val()); // Agregué el campo notas
       
        
        // CONSTRUCCIÓN SEGURA DE LA URL
        // Usamos encodeURIComponent para evitar errores si hay espacios o caracteres especiales (&, ?, etc)
        var v_url = 'contactos/ajaxContactos.php?opcion=agregarContacto' +
            '&apellido=' + v_apellido +
            '&nombre=' + v_nombre +
            '&telefono=' + v_telefono +
            '&correo=' + v_correo +
            '&notas=' + v_notas;
           
            alert(v_url);
        
      $('#mensaje').load(v_url);
        
    });
</script>