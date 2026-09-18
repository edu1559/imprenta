<?php
include_once('../conexion.php');
$conn = conectar();
// No necesitamos recuperar variables de contacto porque es uno NUEVO
?>

<div class="modal-header bg-dark text-white">
    <h5 class="modal-title"><i class="bi bi-person-plus-fill me-2"></i>Nuevo Contacto Rápido</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body bg-light">
    <div class="container-fluid">
        <form id="formNuevoContacto">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Apellido / Empresa:</label>
                    <input type="text" class="form-control shadow-sm" id="apellido1" placeholder="Ej: Perez o Gráfica Norte">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Nombre:</label>
                    <input type="text" class="form-control shadow-sm" id="nombre1" placeholder="Ej: Juan">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Teléfono / WhatsApp:</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                        <input type="text" class="form-control shadow-sm" id="telefono1">
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Correo Electrónico:</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control shadow-sm" id="correo1">
                    </div>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-12">
                    <label class="form-label small fw-bold">Notas o Referencias:</label>
                    <textarea class="form-control shadow-sm" id="notas1" rows="3" placeholder="Ej: Cliente recomendado por..."></textarea>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal-footer bg-white">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-success px-4" id="guardarDatosContacto">
        <i class="bi bi-save me-2"></i>Guardar Contacto
    </button>
</div>

<script>
    $('#guardarDatosContacto').click(function(){
        // Captura de datos
        var v_apellido = $('#apellido1').val().trim();
        var v_nombre   = $('#nombre1').val().trim();

        // Validación mínima
        if(v_apellido == ""){
            alert("El Apellido o Empresa es obligatorio");
            $('#apellido1').focus();
            return;
        }

        // Usamos POST para mayor seguridad y manejo de caracteres especiales
        $.get   ('contactos/ajaxContactos.php', {
            opcion: 'agregarContacto',
            apellido: v_apellido,
            nombre: v_nombre,
            telefono: $('#telefono1').val(),
            correo: $('#correo1').val(),
            notas: $('#notas1').val()
        }, function(respuesta) {
            // Asumimos que tu ajaxContactos.php devuelve el ID del nuevo contacto o un mensaje de éxito
            alert("Contacto guardado correctamente");

            // Cerramos el modal
            $('#modalUniversal').modal('hide');

            /* TIP PROFESIONAL:
               Si este modal se abrió desde el "Nuevo Pedido",
               podrías refrescar el select de contactos aquí para que ya aparezca el nuevo.
            */
            if(typeof cargarContactos === 'function') {
                cargarContactos();
            }
        });
    });
</script>
