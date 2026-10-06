<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
$conn = conectarPDO();

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

            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="esEmpresa1">
                <label class="form-check-label small" for="esEmpresa1">Es una empresa o institución (el nombre es la persona de contacto)</label>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Celular (WhatsApp):</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-whatsapp text-success"></i></span>
                        <input type="text" class="form-control shadow-sm" id="celular1" placeholder="351 532-9898">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Teléfono fijo / otro:</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                        <input type="text" class="form-control shadow-sm" id="telefono1" maxlength="50">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Correo Electrónico:</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control shadow-sm" id="correo1" maxlength="100">
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

        var boton = $(this).prop('disabled', true);
        $.get('contactos/ajaxContactos.php', {
            opcion: 'agregarContacto',
            apellido: v_apellido,
            nombre: v_nombre,
            esEmpresa: $('#esEmpresa1').is(':checked') ? 1 : 0,
            celular: $('#celular1').val().trim(),
            telefono: $('#telefono1').val().trim(),
            correo: $('#correo1').val().trim(),
            notas: $('#notas1').val()
        }, function(resp) {
            boton.prop('disabled', false);
            if (!resp || !resp.ok) {
                alert(resp && resp.error ? resp.error : 'No se pudo guardar el contacto.');
                return;
            }
            alert("Contacto guardado correctamente");
            $('#modalUniversal').modal('hide');

            // Si la pantalla que abrió el modal tiene un listado, lo refrescamos.
            if(typeof cargarContactos === 'function') {
                cargarContactos();
            }
        }, 'json').fail(function() {
            boton.prop('disabled', false);
            alert('No se pudo guardar el contacto: el servidor no respondió bien.');
        });
    });
</script>
