<?php
// --- INICIO: Script administracion/subirArchivo.php ---

// Verificar que la petición sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo "Acceso no permitido.";
    exit;
}

// Incluir conexión a la base de datos
include_once('../conexion.php'); // Ajusta la ruta si es necesario
$conn = conectar();

// --- Variables ---
$idProducto = null;
$directorio_destino_base = $_SERVER['DOCUMENT_ROOT'] . '/productos/imagenes/'; // RUTA ABSOLUTA en el servidor
$ruta_web_base = '/productos/imagenes/'; // RUTA WEB para guardar en la BD
$nombre_archivo_final_db = null; // Nombre/Ruta a guardar en la BD
$mensaje_usuario = "";

// --- Validaciones ---

// 1. Verificar que se recibió el ID del producto
if (isset($_POST['idProducto']) && !empty($_POST['idProducto'])) {
    $idProducto = $_POST['idProducto'];
} else {
    $mensaje_usuario = "Error: No se especificó el producto.";
    // Podrías redirigir con el mensaje o mostrarlo aquí y salir
    header('Location: ../gestionar_imagenes.php?error=' . urlencode($mensaje_usuario)); // Redirige a la página anterior
    exit;
}

// 2. Verificar que se recibió un archivo y no hubo errores graves
if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {

    $nombre_archivo_temporal = $_FILES['archivo']['tmp_name'];
    $nombre_archivo_original = basename($_FILES['archivo']['name']); // Nombre original seguro
    $extension = strtolower(pathinfo($nombre_archivo_original, PATHINFO_EXTENSION)); // Obtener extensión

    // Opcional: Validar tipo de archivo (ej. solo jpg, png, gif)
    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($extension, $extensiones_permitidas)) {
        $mensaje_usuario = "Error: Tipo de archivo no permitido. Solo se aceptan: " . implode(', ', $extensiones_permitidas);
        header('Location: ../gestionar_imagenes.php?idProducto=' . $idProducto . '&error=' . urlencode($mensaje_usuario));
        exit;
    }

    // Opcional: Crear un nombre de archivo único para evitar sobreescrituras y caracteres extraños
    // Podría ser: idProducto . '-' . time() . '.' . $extension
    $nombre_archivo_final_servidor = uniqid('prod_' . $idProducto . '_') . '.' . $extension;
    $ruta_destino_final_servidor = $directorio_destino_base . $nombre_archivo_final_servidor;

    // Crear el directorio de destino si no existe
    if (!file_exists($directorio_destino_base)) {
        mkdir($directorio_destino_base, 0775, true); // Permisos 775, recursivo
    }

    // Mover el archivo subido
    if (move_uploaded_file($nombre_archivo_temporal, $ruta_destino_final_servidor)) {
        // ¡Éxito al mover! Ahora actualizar la base de datos

        // Construir la ruta que se guardará en la BD (la ruta web relativa)
        $nombre_archivo_final_db = $ruta_web_base . $nombre_archivo_final_servidor;

        // Preparar la consulta SQL para actualizar la foto del producto
        $sql_update = "UPDATE productos SET foto = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql_update);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "si", $nombre_archivo_final_db, $idProducto);

            if (mysqli_stmt_execute($stmt)) {
                $mensaje_usuario = "¡Imagen actualizada correctamente para el producto ID " . $idProducto . "!";
                // Opcional: Podrías querer eliminar la foto ANTERIOR del servidor aquí
            } else {
                $mensaje_usuario = "Error al actualizar la base de datos: " . mysqli_stmt_error($stmt);
                // Considerar eliminar el archivo recién subido si la BD falla
                unlink($ruta_destino_final_servidor);
            }
            mysqli_stmt_close($stmt);
        } else {
            $mensaje_usuario = "Error al preparar la consulta de actualización: " . mysqli_error($conn);
             // Considerar eliminar el archivo recién subido si la BD falla
            unlink($ruta_destino_final_servidor);
        }

    } else {
        $mensaje_usuario = "Error: No se pudo mover el archivo subido al directorio de destino.";
    }

} else {
    // Manejar errores de subida específicos (como en tu código original)
    $error_code = isset($_FILES['archivo']['error']) ? $_FILES['archivo']['error'] : UPLOAD_ERR_NO_FILE;
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $mensaje_usuario = "Error: El archivo es demasiado grande.";
            break;
        case UPLOAD_ERR_NO_FILE:
            $mensaje_usuario = "Error: No se seleccionó ningún archivo.";
            break;
        default:
            $mensaje_usuario = "Error desconocido al subir el archivo. Código: " . $error_code;
            break;
    }
}

mysqli_close($conn);

// Redirigir de vuelta a la página del formulario con un mensaje
$param_redir = $idProducto ? '?idProducto=' . $idProducto : ''; // Mantener el producto seleccionado
$tipo_mensaje = strpos(strtolower($mensaje_usuario), 'error') === false ? 'success' : 'error'; // Determinar si es éxito o error
$param_redir .= ($param_redir ? '&' : '?') . $tipo_mensaje . '=' . urlencode($mensaje_usuario);

header('Location: ../administracion/subirArchivo.php' . $param_redir); // Ajusta la ruta si es necesario
exit;

// --- FIN: Script administracion/subirArchivo.php ---
?>