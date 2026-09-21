<?php
// Evitar que se imprima cualquier espacio antes del session_start
session_start();
include_once('../conexion.php');
$conn = conectarPDO();

$opcion = $_GET['opcion'] ?? '';

switch ($opcion) {

case 'ingreso':
    $usuario = $_GET['usuario'];
    $clave = $_GET['clave'];

    // Buscamos el usuario y su perfil (asumiendo que agregaste idPerfil a la tabla usuarios)
    $sql = "SELECT u.id, u.idPerfil, c.apellido, c.nombre
            FROM usuarios u
            INNER JOIN contactos c ON u.id = c.id
            WHERE u.usuario = :usuario AND u.clave = :clave";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->bindParam(':clave', $clave, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() == 1) {
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        // Guardamos todo lo necesario en la sesión
        $_SESSION['idUsuario'] = $fila['id'];
        $_SESSION['idPerfil']  = $fila['idPerfil']; // 1: Admin, 2: Trabajador, etc.
        $_SESSION['apellido']  = $fila['apellido'];
        $_SESSION['nombre']    = $fila['nombre'];
        $_SESSION['foto']      = "fotoUsuarios/" . $fila['apellido'] . "-" . $fila['nombre'] . ".jpg";
        $_SESSION['ultimo_acceso'] = time();

        echo "success|Bienvenido " . $fila['nombre'];
    } else {
        echo "error|Usuario o clave incorrectos";
    }
break;

case 'cerrarSesion':
    $_SESSION = array();
    session_destroy();
    echo "success|Sesión terminada";
break;
}
?>
 