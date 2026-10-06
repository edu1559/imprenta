<?php include_once(__DIR__ . '/../sesion.php'); exigirTrabajadorPagina(); ?>
   <?php
include_once(__DIR__ . '/../conexion.php');
$conn = conectar();

$id = intval($_GET['id']);

// 1. Obtenemos los datos actuales del usuario y su nombre desde contactos
$sql = "SELECT u.usuario, u.idPerfil, c.apellido, c.nombre 
        FROM usuarios u 
        INNER JOIN contactos c ON u.id = c.id 
        WHERE u.id = $id";
$res = mysqli_query($conn, $sql);
$reg = mysqli_fetch_assoc($res);

// 2. Traemos los perfiles para el select
$resPerfiles = mysqli_query($conn, "SELECT id, perfil FROM perfiles ORDER BY perfil ASC");

// 3. Foto actual: mismo nombre que arma ingreso/ajaxIngreso.php para la barra del menú
$fotoActual = "fotoUsuarios/" . $reg['apellido'] . "-" . $reg['nombre'] . ".jpg";
$fotoSrc = file_exists(__DIR__ . "/../$fotoActual") ? rawurlencode_ruta($fotoActual) . "?t=" . time() : "fotoUsuarios/sinLoguear.png";
function rawurlencode_ruta($ruta) { return implode('/', array_map('rawurlencode', explode('/', $ruta))); }
?>

<div class="modal-header bg-primary text-white">
    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Usuario</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>

<div class="modal-body">
    <form id="formEditUsuario">
        <div class="alert alert-secondary py-2 mb-3">
            <small class="d-block text-muted text-uppercase fw-bold" style="font-size: 0.7rem;">Contacto Asociado:</small>
            <span class="fw-bold"><?php echo $reg['apellido'] . ", " . $reg['nombre']; ?></span>
            <input type="hidden" id="idEdit" value="<?php echo $id; ?>">
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Nombre de Usuario:</label>
            <input type="text" class="form-control" id="userEdit" value="<?php echo $reg['usuario']; ?>">
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Nueva Clave:</label>
            <div class="input-group">
                <input type="password" class="form-control" id="passEdit" placeholder="Dejar en blanco para no cambiar">
                <button class="btn btn-outline-secondary" type="button" id="togglePass">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            <div class="form-text">Solo escribe aquí si deseas resetear la contraseña del usuario.</div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Foto:</label>
            <div class="d-flex align-items-center gap-3">
                <img id="fotoPreview" src="<?php echo $fotoSrc; ?>" class="rounded-circle border"
                     width="64" height="64" style="object-fit: cover; background: #eee;">
                <input type="file" class="form-control" id="fotoEdit" accept="image/*">
            </div>
            <div class="form-text">Si ya tenía foto, la anterior se guarda como respaldo.</div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-bold">Perfil / Rol:</label>
            <select class="form-select" id="perfilEdit">
                <?php while($p = mysqli_fetch_assoc($resPerfiles)) { 
                    $selected = ($p['id'] == $reg['idPerfil']) ? 'selected' : '';
                    echo "<option value='{$p['id']}' $selected>{$p['perfil']}</option>";
                } ?>
            </select>
        </div>
    </form>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-primary px-4" id="btnActualizar">
        <i class="bi bi-save me-1"></i> Guardar Cambios
    </button>
</div>

<script>
// Ver/Ocultar clave
$('#togglePass').click(function() {
    let input = $('#passEdit');
    input.attr('type', input.attr('type') === 'password' ? 'text' : 'password');
});

// Todo dentro de una función: el modal se carga por ajax cada vez que se abre, y un
// 'let' suelto a nivel global falla al volver a abrirlo (la variable ya existe).
(function() {

// Foto elegida: se achica a 600px y se pasa a JPG en el navegador, así cualquier
// foto de celular entra (el servidor acepta hasta 2 MB) y queda como .jpg.
let fotoNueva = null;
$('#fotoEdit').change(function() {
    fotoNueva = null;
    let archivo = this.files[0];
    if (!archivo) return;
    let img = new Image();
    img.onload = function() {
        let escala = Math.min(1, 600 / Math.max(img.width, img.height));
        let canvas = document.createElement('canvas');
        canvas.width = Math.round(img.width * escala);
        canvas.height = Math.round(img.height * escala);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function(blob) {
            fotoNueva = blob;
            $('#fotoPreview').attr('src', URL.createObjectURL(blob));
        }, 'image/jpeg', 0.85);
        URL.revokeObjectURL(img.src);
    };
    img.onerror = function() {
        alert("No se pudo leer la imagen. Probá con una foto JPG o PNG.");
        $('#fotoEdit').val('');
    };
    img.src = URL.createObjectURL(archivo);
});

// Guardar Cambios
$('#btnActualizar').click(function() {
    let datos = new FormData();
    datos.append('opcion', 'actualizarUsuario');
    datos.append('id', $('#idEdit').val());
    datos.append('usuario', $('#userEdit').val());
    datos.append('clave', $('#passEdit').val()); // Si va vacío, la clave no se cambia
    datos.append('idPerfil', $('#perfilEdit').val());
    if (fotoNueva) datos.append('foto', fotoNueva, 'foto.jpg');

    if(!$('#userEdit').val()) {
        alert("El nombre de usuario no puede estar vacío.");
        return;
    }
    if ($('#fotoEdit').val() && !fotoNueva) {
        alert("La foto todavía se está preparando, probá de nuevo en un segundo.");
        return;
    }

    $.ajax({
        url: 'contactos/ajaxUsuarios.php',
        type: 'POST',
        data: datos,
        processData: false,
        contentType: false,
        success: function(res) {
            alert(res);
            $('#modalUsuario').modal('hide');
            // Recargamos la lista de usuarios para ver los cambios (ej. si cambió el perfil)
            $('#contenido').load('contactos/usuarios.php');
        }
    });
});

})();
</script>