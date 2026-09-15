<?php
include_once('../conexion.php');
$conn = conectar();

// Consulta para las estadísticas que pediste
$sqlStats = "SELECT p.perfil, COUNT(u.id) as total 
             FROM perfiles p 
             LEFT JOIN usuarios u ON p.id = u.idPerfil 
             GROUP BY p.id";
$resStats = mysqli_query($conn, $sqlStats);
?>

<div class="container-fluid mt-3 px-4">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-person-badge fs-1 text-primary me-3"></i>
        <h2 class="mb-0">Configuración de Perfiles</h2>
    </div>

    <div class="row mb-4">
        <?php while($stat = mysqli_fetch_assoc($resStats)) { ?>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 border-start border-primary border-4">
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase small"><?php echo $stat['perfil']; ?>s</h6>
                        <h3 class="mb-0"><?php echo $stat['total']; ?> <small class="fs-6 text-muted">activos</small></h3>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold">Roles del Sistema</div>
                <div class="list-group list-group-flush">
                    <?php
                    $resP = mysqli_query($conn, "SELECT * FROM perfiles");
                    while($p = mysqli_fetch_assoc($resP)) {
                        echo "<a href='#' class='list-group-item list-group-item-action d-flex justify-content-between align-items-center btnVerPerfil' data-id='{$p['id']}' data-nombre='{$p['perfil']}'>
                                {$p['perfil']}
                                <i class='bi bi-chevron-right'></i>
                              </a>";
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div id="contenedorPermisosPerfil" class="card shadow-sm border-0">
                <div class="card-body text-center py-5 text-muted">
                    <i class="bi bi-arrow-left-circle fs-1 d-block mb-3"></i>
                    Seleccione un perfil para editar sus permisos base.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$('.btnVerPerfil').click(function(e) {
    e.preventDefault();
    let idPerfil = $(this).data('id');
    let nombre = $(this).data('nombre');
    
    // Cargamos una página que crearemos llamada 'ajaxPerfilesMenu.php'
    $('#contenedorPermisosPerfil').load('contactos/ajaxPerfilesMenu.php?idPerfil=' + idPerfil);
});
</script>