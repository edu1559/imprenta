<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorAjax();
include_once(__DIR__ . '/../conexion.php');
$conn = conectar();

// Usamos REQUEST para capturar tanto GET como POST sin conflictos
$opcion = isset($_REQUEST['opcion']) ? $_REQUEST['opcion'] : '';

// --- 1. BUSCADOR ---
// Cada palabra tiene que aparecer en "apellido nombre" (así "Vargas Julieta" encuentra
// apellido=Vargas, nombre=Julieta); un número busca también por id de contacto.
if ($opcion == 'buscarContactosSinUsuario') {
    $condiciones = [];
    foreach (preg_split('/\s+/', trim($_REQUEST['cadena'])) as $palabra) {
        if ($palabra === '') continue;
        $p = mysqli_real_escape_string($conn, $palabra);
        $condiciones[] = ctype_digit($palabra)
            ? "(c.id = $p OR CONCAT_WS(' ', c.apellido, c.nombre) LIKE '%$p%')"
            : "CONCAT_WS(' ', c.apellido, c.nombre) LIKE '%$p%'";
    }
    $filtro = $condiciones ? implode(' AND ', $condiciones) : '1';
    $sql = "SELECT c.id, c.apellido, c.nombre
            FROM contactos c
            WHERE $filtro
            AND c.id NOT IN (SELECT u.id FROM usuarios u)
            ORDER BY c.apellido, c.nombre, c.id
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
    $user = $_POST['usuario'];
    $perfil = intval($_POST['idPerfil']);
    $clave = $_POST['clave'];

    // 1. Manejo de la FOTO: fotoUsuarios/Apellido-Nombre.jpg, el mismo nombre que arma
    // ingreso/ajaxIngreso.php para la barra del menú. La foto anterior no se pisa: queda
    // como Apellido-Nombre(1).jpg, (2), ... El modal ya la manda achicada y en JPG.
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        if ((new finfo(FILEINFO_MIME_TYPE))->file($_FILES['foto']['tmp_name']) !== 'image/jpeg') {
            die("Error: la foto tiene que ser una imagen JPG.");
        }

        $datNom = mysqli_fetch_assoc(mysqli_query($conn, "SELECT apellido, nombre FROM contactos WHERE id = $id"));
        $base = str_replace(['/', '\\'], '', $datNom['apellido'] . "-" . $datNom['nombre']);
        $carpeta = __DIR__ . "/../fotoUsuarios/";

        if (file_exists($carpeta . "$base.jpg")) {
            for ($n = 1; file_exists($carpeta . "$base($n).jpg"); $n++);
            rename($carpeta . "$base.jpg", $carpeta . "$base($n).jpg");
        }
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $carpeta . "$base.jpg")) {
            die("Error al guardar la foto en el servidor.");
        }
    } elseif (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        die("Error al subir la foto (código {$_FILES['foto']['error']}).");
    }

    // 2. Actualización de datos en DB. La clave solo cambia si se escribió una nueva.
    if ($clave !== '') {
        $stmt = $conn->prepare("UPDATE usuarios SET usuario = ?, idPerfil = ?, clave = ? WHERE id = ?");
        $stmt->bind_param('sisi', $user, $perfil, $clave, $id);
    } else {
        $stmt = $conn->prepare("UPDATE usuarios SET usuario = ?, idPerfil = ? WHERE id = ?");
        $stmt->bind_param('sii', $user, $perfil, $id);
    }
    if ($stmt->execute()) {
        echo "Usuario actualizado correctamente.";
    } else {
        echo "Error: " . $stmt->error;
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
