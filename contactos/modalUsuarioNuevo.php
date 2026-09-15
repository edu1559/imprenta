    <?php
include_once('../conexion.php');
$conn = conectar();

// Traemos los perfiles (son pocos, el array está bien)
$resPerfiles = mysqli_query($conn, "SELECT id, perfil FROM perfiles ORDER BY perfil ASC");
?>

<div class="modal-header bg-dark text-white"> 
    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Nuevo Acceso de Usuario</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body bg-light">
    <div class="card mb-3 shadow-sm">
        <div class="card-body">
            <label class="form-label fw-bold text-primary">1. Buscar Contacto existente:</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="text" id="txtBuscaContacto" class="form-control" placeholder="Escriba apellido o nombre...">
            </div>
            <div id="resultadosBusqueda" class="list-group mt-2" style="max-height: 200px; overflow-y: auto;"></div>
            
            <input type="hidden" id="idContactoSeleccionado" value="0">
            <div id="contactoElegidoAlert" class="alert alert-info mt-2 d-none py-2">
                <small>Seleccionado: <strong id="nombreContactoElegido"></strong></small>
            </div>
        </div>
    </div>

    <form id="formNuevoUsuario" class="d-none"> <div class="card shadow-sm">
            <div class="card-body">
                <label class="form-label fw-bold text-primary">2. Datos de la cuenta:</label>
                <div class="mb-3">
                    <label class="small text-muted">Nombre de Usuario:</label>
                    <input type="text" class="form-control" id="nombreUsuario" placeholder="Ej: jperez">
                </div>
                <div class="mb-3">
                    <label class="small text-muted">Contraseña:</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="clave">
                        <button class="btn btn-outline-secondary" type="button" id="verClave">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="small text-muted">Asignar Perfil:</label>
                    <select class="form-select" id="selPerfil">
                        <option value="" selected disabled>Seleccione un perfil...</option>
                        <?php while($p = mysqli_fetch_assoc($resPerfiles)){
                            echo "<option value='{$p['id']}'>{$p['perfil']}</option>";
                        } ?>
                    </select>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-link text-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-success px-4" id="guardarDatos" disabled>
        <i class="bi bi-check-circle me-1"></i> Crear Usuario
    </button>
</div>

<script>
// --- Lógica de Búsqueda de Contactos ---
$('#txtBuscaContacto').keyup(function() {
    let query = $(this).val();
    if (query.length >= 3) {
        $.get('contactos/ajaxUsuarios.php', { opcion: 'buscarContactosSinUsuario', cadena: query }, function(data) {
            $('#resultadosBusqueda').html(data);
        });
    } else {
        $('#resultadosBusqueda').empty();
    }
});

// Al hacer clic en un resultado de la búsqueda
$(document).on('click', '.item-contacto', function() {
    let id = $(this).data('id');
    let nombre = $(this).data('nombre');
    
    $('#idContactoSeleccionado').val(id);
    $('#nombreContactoElegido').text(nombre);
    
    // UI: Mostrar selección y habilitar formulario
    $('#contactoElegidoAlert').removeClass('d-none');
    $('#formNuevoUsuario').removeClass('d-none');
    $('#resultadosBusqueda').empty();
    $('#txtBuscaContacto').val('');
    $('#guardarDatos').prop('disabled', false);
});

// Ver/Ocultar clave
$('#verClave').click(function() {
    let input = $('#clave');
    input.attr('type', input.attr('type') === 'password' ? 'text' : 'password');
});

// --- Guardar ---
$('#guardarDatos').click(function() {
    let datos = {
        opcion: 'agregarUsuario',
        idContacto: $('#idContactoSeleccionado').val(),
        usuario: $('#nombreUsuario').val(),
        clave: $('#clave').val(),
        idPerfil: $('#selPerfil').val()
    };

    if(!datos.usuario || !datos.clave || !datos.idPerfil) {
        alert("Complete todos los campos de la cuenta.");
        return;
    }

    // Enviamos por POST para que la clave no viaje en la URL (más seguro)
    $.post('contactos/ajaxUsuarios.php', datos, function(response) {
        $('#modalUsuario').modal('hide');
        $('#contenido').load('contactos/usuarios.php'); // Recargar lista
        // Aquí podrías disparar un mensaje de éxito
    });
});
</script>