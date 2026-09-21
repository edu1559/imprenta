<?php
function conectar()
{
    // Variables de conexión
    $servername = "localhost";
    $username = "root";
    $password = "root";
    $database = "imprenta";

    // Creación de la conexión
   
	$conn = new mysqli($servername, $username, $password, $database);
	$mysqli = new mysqli($servername, $username, $password, $database);
    
    // Comprobación de la conexión
    if ($mysqli->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    
    // configura caracteres
    
    $mysqli->set_charset("utf8");
    return $conn;
}


function cerrar($conn) {
  // Cierra la conexión a la base de datos
  mysqli_close($conn);
}

function conectarPDO()
{
    // Variables de conexión (mismas credenciales que conectar())
    $host = "localhost";
    $user = "root";
    $pass = "root";
    $db   = "imprenta";

    $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
    $conn = new PDO($dsn, $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    return $conn;
}
?>
