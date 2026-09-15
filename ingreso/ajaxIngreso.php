<?php
// Evitar que se imprima cualquier espacio antes del session_start
session_start();
include_once('../conexion.php');
$conn = conectar();

$opcion = $_GET['opcion'] ?? '';

switch ($opcion) {

case 'ingreso': 
    $usuario = mysqli_real_escape_string($conn, $_GET['usuario']);
    $clave = mysqli_real_escape_string($conn, $_GET['clave']);
      
    // Buscamos el usuario y su perfil (asumiendo que agregaste idPerfil a la tabla usuarios)
    $sql = "SELECT u.id, u.idPerfil, c.apellido, c.nombre
            FROM usuarios u 
            INNER JOIN contactos c ON u.id = c.id
            WHERE u.usuario = '$usuario' AND u.clave = '$clave'";
  
    $resultado = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($resultado) == 1) {
        $fila = mysqli_fetch_assoc($resultado);

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
 