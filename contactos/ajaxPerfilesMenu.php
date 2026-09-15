<?php
include_once('../conexion.php');
$conn = conectar();
$idPerfil = intval($_GET['idPerfil']);

// Obtenemos los menús padres
$sqlPadres = "SELECT m.id, m.nombre, 
              (SELECT COUNT(*) FROM perfilesMenu pm WHERE pm.idMenu = m.id AND pm.idPerfil = $idPerfil) as tiene
              FROM menu m WHERE m.padre IS NULL AND m.activo = 1";
$resPadres = mysqli_query($conn, $sqlPadres);
?>

<div class="card-header bg-primary text-white d-flex justify-content-between">
    <span>Configurando Molde de Perfil</span>
    <button class="btn btn-sm btn-light" id="btnSincronizar" data-id="<?php echo $idPerfil; ?>">
        <i class="bi bi-sync"></i> Aplicar a todos los usuarios de este perfil
    </button>
</div>
<div class="card-body bg-light">
    <div class="row g-3">
        <?php while ($padre = mysqli_fetch_assoc($resPadres)) { ?>
            <div class="col-md-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-dark text-white d-flex justify-content-between">
                        <small class="fw-bold"><?php echo $padre['nombre']; ?></small>
                        <input class="form-check-input checkPerfil" type="checkbox" 
                               data-menu="<?php echo $padre['id']; ?>" 
                               data-perfil="<?php echo $idPerfil; ?>"
                               <?php echo $padre['tiene'] > 0 ? 'checked' : ''; ?>>
                    </div>
                    <ul class="list-group list-group-flush">
                        <?php
                        $idP = $padre['id'];
                        $sqlHijos = "SELECT m.id, m.nombre, 
                                     (SELECT COUNT(*) FROM perfilesMenu pm WHERE pm.idMenu = m.id AND pm.idPerfil = $idPerfil) as tiene
                                     FROM menu m WHERE m.padre = $idP AND m.activo = 1";
                        $resHijos = mysqli_query($conn, $sqlHijos);
                        while ($hijo = mysqli_fetch_assoc($resHijos)) {
                        ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                                <small><?php echo $hijo['nombre']; ?></small>
                                <div class="form-check form-switch">
                                    <input class="form-check-input checkPerfil" type="checkbox" 
                                           data-menu="<?php echo $hijo['id']; ?>" 
                                           data-perfil="<?php echo $idPerfil; ?>"
                                           <?php echo $hijo['tiene'] > 0 ? 'checked' : ''; ?>>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<script>
$('.checkPerfil').change(function() {
    let v_idMenu = $(this).data('menu');
    let v_idPerfil = $(this).data('perfil');
    let v_estado = $(this).is(':checked') ? 'insertar' : 'eliminar';

    $.get('contactos/ajaxAccionesPerfil.php', {
        accion: v_estado,
        idMenu: v_idMenu,
        idPerfil: v_idPerfil
    });
});
</script>