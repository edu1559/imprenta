<?php
include_once('../conexion.php');
$conn = conectar();

// 1. Sanitización básica y obtención de variables
$cadena = isset($_GET['cadena']) ? mysqli_real_escape_string($conn, $_GET['cadena']) : '';
$idUsuario = isset($_GET['idUsuario']) ? intval($_GET['idUsuario']) : 1;
?>

<div class="container-fluid mt-3 px-4">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-shield-lock fs-1 text-success me-3"></i>
        <h2 class="mb-0">Gestión de Permisos</h2>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="input-group mb-3">
                        <span class="input-group-text bg-success text-white border-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="buscar" class="form-control shadow-none" placeholder="Buscar apellido..." value="<?php echo $cadena; ?>">
                    </div>

                    <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                        <table class="table table-hover align-middle">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th class="text-end">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $where = $cadena != '' ? "WHERE c.apellido LIKE '%$cadena%'" : "";
                                $sqlUsers = "SELECT u.id, c.apellido, c.nombre 
                                             FROM usuarios u 
                                             INNER JOIN contactos c ON u.id = c.id 
                                             $where LIMIT 20";
                                $resUsers = mysqli_query($conn, $sqlUsers);

                                while ($row = mysqli_fetch_assoc($resUsers)) {
                                    $activeClass = ($row['id'] == $idUsuario) ? 'table-success fw-bold' : '';
                                    echo "<tr class='$activeClass'>
                                            <td>{$row['id']}</td>
                                            <td>" . ucfirst($row['apellido']) . " {$row['nombre']}</td>
                                            <td class='text-end'>
                                                <button class='btn btn-sm btn-outline-success editarPermisos' data-id='{$row['id']}'>
                                                    <i class='bi bi-pencil-square'></i>
                                                </button>
                                            </td>
                                          </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <?php
            // Buscamos datos del usuario seleccionado
            $sqlInfo = "SELECT c.nombre, c.apellido FROM usuarios u 
                        INNER JOIN contactos c ON u.id = c.id WHERE u.id = $idUsuario";
            $resInfo = mysqli_query($conn, $sqlInfo);
            $userSelected = mysqli_fetch_assoc($resInfo);
            ?>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-secondary">
                        Permisos para: <span class="text-dark fw-bold"><?php echo $userSelected['apellido'] . " " . $userSelected['nombre']; ?></span>
                    </h5>
                    <input type="hidden" id="idUsuarioSeleccionado" value="<?php echo $idUsuario; ?>">
                </div>
                
                <div class="card-body bg-light">
                    <div class="row g-3"> <?php
                        // Menús principales (Padres)
                        $sqlPadres = "SELECT m.id, m.nombre, COALESCE(p.idMenu, 0) as tienePermiso
                                      FROM menu m 
                                      LEFT JOIN permisos p ON m.id = p.idMenu AND p.idUsuario = $idUsuario
                                      WHERE m.padre IS NULL AND m.activo = 1";
                        $resPadres = mysqli_query($conn, $sqlPadres);

                        while ($padre = mysqli_fetch_assoc($resPadres)) {
                            $checked = $padre['tienePermiso'] != 0 ? 'checked' : '';
                        ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 border-0 shadow-sm">
                                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                        <span class="small fw-bold text-uppercase"><?php echo $padre['nombre']; ?></span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input checkPadre" type="checkbox" 
                                                   value="<?php echo $padre['id']; ?>" <?php echo $checked; ?>>
                                        </div>
                                    </div>
                                    <ul class="list-group list-group-flush">
                                        <?php
                                        // Hijos de este padre
                                        $idPadre = $padre['id'];
                                        $sqlHijos = "SELECT m.id, m.nombre, COALESCE(p.idMenu, 0) as tienePermiso
                                                     FROM menu m 
                                                     LEFT JOIN permisos p ON m.id = p.idMenu AND p.idUsuario = $idUsuario
                                                     WHERE m.padre = $idPadre AND m.activo = 1";
                                        $resHijos = mysqli_query($conn, $sqlHijos);

                                        while ($hijo = mysqli_fetch_assoc($resHijos)) {
                                        ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                                                <small><?php echo $hijo['nombre']; ?></small>
                                                <select class="form-select form-select-sm selectPermiso w-auto border-0 bg-light" id="<?php echo $hijo['id']; ?>">
                                                    <option value="0" <?php echo ($hijo['tienePermiso'] != 0) ? 'selected' : ''; ?>>Permitido</option>
                                                    <option value="1" <?php echo ($hijo['tienePermiso'] == 0) ? 'selected' : ''; ?>>Denegado</option>
                                                </select>
                                            </li>
                                        <?php } ?>
                                    </ul>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Buscar con retraso para no saturar el servidor (Debounce)
    let timeout = null;
    $('#buscar').on('keyup', function() {
        clearTimeout(timeout);
        let v_cadena = $(this).val();
        timeout = setTimeout(function() {
            if (v_cadena.length >= 3 || v_cadena.length == 0) {
                $('#contenido').load('contactos/permisos.php?cadena=' + encodeURIComponent(v_cadena));
            }
        }, 500);
    });

    // Cargar permisos del usuario seleccionado
    $('.editarPermisos').click(function() {
        let id = $(this).data('id');
        $('#contenido').load('contactos/permisos.php?idUsuario=' + id);
    });

    // Cambiar permiso de un hijo
    $('.selectPermiso').change(function(){
        let v_permiso = $(this).val(); 
        let v_idMenu = $(this).attr('id');
        let v_idUsuario = $('#idUsuarioSeleccionado').val();
        
        // Usamos POST por seguridad
        $.get('contactos/ajaxPermisos.php', {
            opcion: 'cambiarPermiso',
            permiso: v_permiso,
            idMenu: v_idMenu,
            idUsuario: v_idUsuario
        }, function(data) {
             // Aquí podrías poner un toast de éxito
        });
    });

    // Cambiar permiso del padre
    $('.checkPadre').change(function() {
        let v_idMenuPadre = $(this).val();
        let v_idUsuario = $('#idUsuarioSeleccionado').val();
        let v_permisoPadre = $(this).is(':checked') ? 0 : 1;
        
        $.get('contactos/ajaxPermisos.php', {
            opcion: 'cambiarPermiso',
            permiso: v_permisoPadre,
            idMenu: v_idMenuPadre,
            idUsuario: v_idUsuario
        });
    });
});
</script>
