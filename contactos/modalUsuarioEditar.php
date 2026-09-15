   <?php
include_once('../conexion.php');
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

// Guardar Cambios
$('#btnActualizar').click(function() {
    let datos = {
        opcion: 'actualizarUsuario',
        id: $('#idEdit').val(),
        usuario: $('#userEdit').val(),
        clave: $('#passEdit').val(), // Si va vacío, el ajaxUsuarios.php ya sabe qué hacer
        idPerfil: $('#perfilEdit').val()
    };

    if(!datos.usuario) {
        alert("El nombre de usuario no puede estar vacío.");
        return;
    }

    $.post('contactos/ajaxUsuarios.php', datos, function(res) {
        alert(res);
        $('#modalUsuario').modal('hide');
        // Recargamos la lista de usuarios para ver los cambios (ej. si cambió el perfil)
        $('#contenido').load('contactos/usuarios.php'); 
    });
});
</script>