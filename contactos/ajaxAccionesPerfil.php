<?php
include_once('../conexion.php');
$conn = conectar();

// Recibimos los datos por GET (o POST según prefieras)
$accion   = isset($_GET['accion']) ? $_GET['accion'] : '';
$idMenu   = isset($_GET['idMenu']) ? intval($_GET['idMenu']) : 0;
$idPerfil = isset($_GET['idPerfil']) ? intval($_GET['idPerfil']) : 0;

if ($idMenu > 0 && $idPerfil > 0) {

    if ($accion == 'insertar') {
        // Usamos IGNORE por si acaso ya existe, para no generar error de duplicado
        $sql = "INSERT IGNORE INTO perfilesMenu (idPerfil, idMenu) 
                VALUES ($idPerfil, $idMenu)";
        
        if(mysqli_query($conn, $sql)) {
            echo "<span class='text-success small'><i class='bi bi-check'></i> Agregado al perfil</span>";
        }

    } elseif ($accion == 'eliminar') {
        $sql = "DELETE FROM perfilesMenu 
                WHERE idPerfil = $idPerfil AND idMenu = $idMenu";
        
        if(mysqli_query($conn, $sql)) {
            echo "<span class='text-danger small'><i class='bi bi-trash'></i> Quitado del perfil</span>";
        }
    }
} else {
    echo "Error: Datos insuficientes.";
};
// Agregar este caso al switch o if anterior para que se pueda actualizar todos los usuarios de un perfil
if ($accion == 'sincronizarTodo') {
    // 1. Borramos los permisos actuales de TODOS los usuarios que tengan este perfil
    // (Cuidado: esto pisa personalizaciones manuales)
    $sqlDelete = "DELETE p FROM permisos p 
                  INNER JOIN usuarios u ON p.idUsuario = u.id 
                  WHERE u.idPerfil = $idPerfil";
    mysqli_query($conn, $sqlDelete);

    // 2. Insertamos la nueva configuración del molde para todos esos usuarios
    $sqlSync = "INSERT INTO permisos (idUsuario, idMenu)
                SELECT u.id, pm.idMenu 
                FROM usuarios u
                CROSS JOIN perfilesMenu pm
                WHERE u.idPerfil = $idPerfil AND pm.idPerfil = $idPerfil";
    
    if(mysqli_query($conn, $sqlSync)) {
        echo "Perfil sincronizado con éxito para todos los usuarios.";
    }
}
?>