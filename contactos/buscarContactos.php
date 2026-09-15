<?php
include_once('../conexion.php');
$conn = conectar();

$q = mysqli_real_escape_string($conn, $_GET['q']);

$sql = "SELECT id, CONCAT(apellido, ' ', nombre) as text 
        FROM contactos 
        WHERE apellido LIKE '%$q%' OR nombre LIKE '%$q%' 
        LIMIT 20";

$res = mysqli_query($conn, $sql);
$data = [];
while($row = mysqli_fetch_assoc($res)) {
    $data[] = $row;
}
echo json_encode($data);