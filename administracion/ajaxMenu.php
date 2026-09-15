<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


include_once('../conexion.php');
$conn = conectar();

$opcion = isset($_REQUEST['opcion']) ? $_REQUEST['opcion'] : '';

switch ($opcion) {

    case 'agregarMenu':
        $nombre = mysqli_real_escape_string($conn, $_REQUEST['nombre']);
        $pagina = mysqli_real_escape_string($conn, $_REQUEST['pagina']);
        $padre  = intval($_REQUEST['padre']);
        // Aseguramos que si orden viene vacío, sea 0
        $orden  = isset($_REQUEST['orden']) && $_REQUEST['orden'] != '' ? intval($_REQUEST['orden']) : 0;

        $sql = "INSERT INTO menu (nombre, pagina, padre, orden, activo) 
                VALUES ('$nombre', '$pagina', $padre, $orden, 1)";
        
        if (mysqli_query($conn, $sql)) {
            $nuevoId = mysqli_insert_id($conn);
            $msg = $sql . "Se agregó el menú. Nuevo ID: $nuevoId ";
            
            // Validamos que exista el ID de usuario en la sesión antes de insertar permisos
            if (isset($_SESSION['idUsuario']) && !empty($_SESSION['idUsuario'])) {
                $idUser = $_SESSION['idUsuario'];
                $sqlPermiso = "INSERT INTO permisos (idUsuario, idMenu) VALUES ($idUser, $nuevoId)";
                if(mysqli_query($conn, $sqlPermiso)){
                    $msg .= "y se asignaron permisos al administrador.";
                }
            } else {
                $msg .= "(Nota: No se asignaron permisos automáticos porque la sesión expiró).";
            }
            echo $msg;
        } else {
            echo "Error en el insert de menú: " . mysqli_error($conn);
        }
        break;

    case 'actualizarMenu':
        $id     = intval($_REQUEST['id']);
        $nombre = mysqli_real_escape_string($conn, $_REQUEST['nombre']);
        $pagina = mysqli_real_escape_string($conn, $_REQUEST['pagina']);
        $padre  = intval($_REQUEST['padre']);
        $orden  = intval($_REQUEST['orden']);
       // die("Recibí ID: $id y Orden: $orden. Usuario en sesion: " . $_SESSION['idUsuario']);
        $sql = "UPDATE menu SET 
                    nombre = '$nombre', 
                    pagina = '$pagina', 
                    padre = $padre, 
                    orden = $orden  
                WHERE id = $id";
       
        if (mysqli_query($conn, $sql)) {
            echo "Menú ID $id actualizado correctamente (Orden: $orden).";
        } else {
            echo "Error al actualizar: " . mysqli_error($conn);
        }
        break;

    case 'borrarMenu':
        $id = intval($_REQUEST['id']);

        // 1. Limpieza de tablas dependientes (Integridad referencial)
        // Borramos el acceso de este ítem en todos los perfiles y usuarios
        mysqli_query($conn, "DELETE FROM perfilesMenu WHERE idMenu = $id");
        mysqli_query($conn, "DELETE FROM permisos WHERE idMenu = $id");

        // 2. Borramos el ítem del menú
        $sql = "DELETE FROM menu WHERE id = $id";
        
        if (mysqli_query($conn, $sql)) {
            echo "Menú y permisos asociados eliminados.";
        }
        break;
}
?>