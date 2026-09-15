<?php
function conectar()
{
    // Variables de conexión
    $servername = "localhost";
    $username = "root";
    $password = "root";
    $database = "imprenta1";

    // Creación de la conexión
   
	$conn1 = new mysqli($servername, $username, $password, $database);

    // Comprobación de la conexión
    if ($conn1->connect_error) {
        die("Error de conexión: " . $conn1->connect_error);
    }

    return $conn1;
}


function cerrar($conn1) {
  // Cierra la conexión a la base de datos
  mysqli_close($conn1);
}
?>			
