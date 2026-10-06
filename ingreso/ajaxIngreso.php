<?php
// Evitar que se imprima cualquier espacio antes del session_start
session_start();
include_once(__DIR__ . '/../conexion.php');
$conn = conectarPDO();

// El ingreso llega por POST (la clave no tiene que quedar en la URL ni en el log de
// Apache); cerrarSesion sigue llegando por GET.
$opcion = $_POST['opcion'] ?? $_GET['opcion'] ?? '';

switch ($opcion) {

case 'ingreso':
    $usuario = $_POST['usuario'] ?? '';
    $clave = $_POST['clave'] ?? '';

    // Buscamos el usuario y su perfil; la clave se comprueba contra el hash guardado.
    $sql = "SELECT u.id, u.idPerfil, u.clave, c.apellido, c.nombre
            FROM usuarios u
            INNER JOIN contactos c ON u.id = c.id
            WHERE u.usuario = :usuario";

    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->execute();

    $fila = null;
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $candidato) {
        $guardada = (string)$candidato['clave'];
        $cifrada = password_get_info($guardada)['algo'] !== null;
        // Una clave que todavía está sin cifrar (anterior a sql/2026-10-06_usuarios_cifrarClaves.php)
        // se compara tal cual y, si coincide, se cifra en ese momento.
        if ($cifrada ? password_verify($clave, $guardada) : ($clave !== '' && hash_equals($guardada, $clave))) {
            if (!$cifrada || password_needs_rehash($guardada, PASSWORD_DEFAULT)) {
                $conn->prepare("UPDATE usuarios SET clave = ? WHERE id = ?")
                     ->execute([password_hash($clave, PASSWORD_DEFAULT), $candidato['id']]);
            }
            $fila = $candidato;
            break;
        }
    }

    if ($fila) {
        session_regenerate_id(true);

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
 