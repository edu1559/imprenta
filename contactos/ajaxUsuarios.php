<?php
include_once('../conexion.php');
$conn = conectar();

// Usamos REQUEST para capturar tanto GET como POST sin conflictos
$opcion = isset($_REQUEST['opcion']) ? $_REQUEST['opcion'] : '';

// --- 1. BUSCADOR (Se mantiene igual, funciona perfecto) ---
if ($opcion == 'buscarContactosSinUsuario') {
    $cadena = mysqli_real_escape_string($conn, $_REQUEST['cadena']);
    $sql = "SELECT c.id, c.apellido, c.nombre
            FROM contactos c
            WHERE (c.apellido LIKE '%$cadena%' OR c.nombre LIKE '%$cadena%')
            AND c.id NOT IN (SELECT u.id FROM usuarios u)
            LIMIT 20";
    $res = mysqli_query($conn, $sql);

    if (mysqli_num_rows($res) > 0) {
        while ($row = mysqli_fetch_assoc($res)) {
            echo "<button type='button' class='list-group-item list-group-item-action item-contacto py-2'
                    data-id='{$row['id']}' data-nombre='{$row['apellido']} {$row['nombre']}'>
                    <i class='bi bi-person me-2'></i>{$row['apellido']}, {$row['nombre']}
                  </button>";
        }
    } else {
        echo "<div class='list-group-item disabled text-muted'>No se encontraron contactos disponibles</div>";
    }
    exit;
}

// --- 2. ACCIONES DE MANTENIMIENTO ---
switch ($opcion) {

    case 'agregarUsuario':
        // El ID del contacto que elegimos en el buscador es el ID del nuevo usuario
        $id = intval($_REQUEST['idContacto']);
        $usuario = mysqli_real_escape_string($conn, $_REQUEST['usuario']);
        $clave = mysqli_real_escape_string($conn, $_REQUEST['clave']);
        $idPerfil = intval($_REQUEST['idPerfil']);

        // Insertamos el usuario
        $sqlUser = "INSERT INTO usuarios (id, usuario, clave, idPerfil)
                    VALUES ($id, '$usuario', '$clave', $idPerfil)";

        if (mysqli_query($conn, $sqlUser)) {
            // Al crear el usuario, le heredamos los permisos del perfil elegido
            $sqlPermisos = "INSERT INTO permisos (idUsuario, idMenu)
                            SELECT $id, idMenu
                            FROM perfilesMenu
                            WHERE idPerfil = $idPerfil";
            mysqli_query($conn, $sqlPermisos);
            echo "Usuario y permisos creados con éxito.";
        } else {
            echo "Error al crear usuario: " . mysqli_error($conn);
        }
        break;

    case 'actualizarUsuario':

    $id = intval($_POST['id']);
    $user = mysqli_real_escape_string($conn, $_POST['usuario']);
    $perfil = intval($_POST['idPerfil']);
    $clave = $_POST['clave'];

    // 1. Manejo de la FOTO
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {

        // Obtenemos nombres del contacto para el nombre del archivo
        $resNom = mysqli_query($conn, "SELECT apellido, nombre FROM contactos WHERE id = $id");
        $datNom = mysqli_fetch_assoc($resNom);

        // Limpiamos espacios y caracteres raros para el nombre del archivo
        $nombreArchivo = $datNom['apellido'] . "-" . $datNom['nombre'] . ".jpg";
        $nombreArchivo = str_replace(' ', '', $nombreArchivo); // Quita espacios

        $directorioDestino = "../fotoUsuarios/"; // Asegúrate que la ruta sea correcta desde ajax/
        $rutaFinal = $directorioDestino . $nombreArchivo;

        // Requisitos de Seguridad
        $permitidos = ['image/jpeg', 'image/jpg'];
        $limite_kb = 2000; // 2MB

        if (!in_array($_FILES['foto']['type'], $permitidos)) {
            die("Error: Solo se permiten archivos JPG.");
        }
        if ($_FILES['foto']['size'] > $limite_kb * 1024) {
            die("Error: El archivo es muy pesado (Máximo 2MB).");
        }

        // Si todo está bien, lo movemos (sobreescribe si ya existe)
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $rutaFinal)) {
            die("Error al guardar la foto en el servidor.");
        }
    }

    // 2. Actualización de datos en DB
    $sql = "UPDATE usuarios SET usuario = '$user', idPerfil = $perfil, clave='$clave' where id = $id    ";


   /*
    if (!empty($clave)) {
        $passHash = password_hash($clave, PASSWORD_DEFAULT); // ¡Siempre usa hash!
        $sql .= ", clave = '$passHash'";
    }
    $sql .= " WHERE id = $id";
   */
    if (mysqli_query($conn, $sql)) {
        echo "Usuario actualizado correctamente.";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
    break;

    case 'borrarUsuario':
        $id = intval($_REQUEST['id']);

        // 1. Borramos primero sus permisos (integridad referencial manual)
        mysqli_query($conn, "DELETE FROM permisos WHERE idUsuario = $id");

        // 2. Borramos el usuario (el contacto permanece intacto)
        $sql = "DELETE FROM usuarios WHERE id = $id";

        if (mysqli_query($conn, $sql)) {
            echo "Acceso de usuario eliminado.";
        }
        break;
}
?>
