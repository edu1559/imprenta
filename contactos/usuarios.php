
<?php
include_once('../conexion.php');
$conn = conectar();
?>

<div class="container-fluid mt-3 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <i class="bi bi-people-fill fs-1 text-primary me-3"></i>
            <h2 class="mb-0">Gestión de Cuentas de Usuario</h2>
        </div>
        <button class="btn btn-success" id="btnNuevoUsuario">
            <i class="bi bi-person-plus-fill"></i> Crear Usuario desde Contacto
        </button>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <table class="table table-hover align-middle" id="tblUsuarios">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Apellido y Nombre</th>
                        <th>Nombre de Usuario</th>
                        <th>Perfil</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql = "SELECT u.id, c.apellido, c.nombre, u.usuario, p.perfil, u.idPerfil
                            FROM usuarios u
                            INNER JOIN contactos c ON u.id = c.id
                            INNER JOIN perfiles p ON u.idPerfil = p.id
                            ORDER BY c.apellido ASC";
                    $res = mysqli_query($conn, $sql);
                    while($row = mysqli_fetch_assoc($res)){
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo "<strong>" . strtoupper($row['apellido']) . "</strong>, " . $row['nombre']; ?></td>
                        <td><span class="badge bg-light text-dark border"><?php echo $row['usuario']; ?></span></td>
                        <td><?php echo $row['perfil']; ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary btnEditarUsuario" data-id="<?php echo $row['id']; ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger btnBorrarUsuario" data-id="<?php echo $row['id']; ?>">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content" id="contenedorModalUsuario">
            </div>
    </div>
</div>

<script>
// Abrir modal para buscar contacto y crear usuario
$('#btnNuevoUsuario').click(function(){
    $('#contenedorModalUsuario').load('contactos/modalUsuarioNuevo.php', function(){
        $('#modalUsuario').modal('show');
    });
});

// Editar usuario (cambiar clave o perfil)
$('.btnEditarUsuario').click(function(){
    var id = $(this).data('id');
    $('#contenedorModalUsuario').load('contactos/modalUsuarioEditar.php?id=' + id, function(){
        $('#modalUsuario').modal('show');
    });
});

// --- Borrar usuario ---
$('.btnBorrarUsuario').click(function(){
    // Obtenemos el ID desde el atributo data-id del botón
    var v_id = $(this).data('id');
    
    // Es importante pedir confirmación antes de borrar
    if(confirm("¿Estás seguro de que deseas eliminar el acceso de este usuario? \n(El contacto no se borrará, solo su permiso de entrada)")){
        
        var v_url = 'contactos/ajaxUsuarios.php?opcion=borrarUsuario&id=' + v_id;
        
        // Ejecutamos el borrado
        $.get(v_url, function(respuesta){
            // Mostramos la respuesta del servidor (el "echo" del PHP)
            alert(respuesta);
            
            // Recargamos el listado de usuarios para que desaparezca de la tabla
            $('#contenido').load('contactos/usuarios.php');
        });
    }
});

</script>