<?php
include_once('../conexion.php');
$conn = conectarPDO();

$q = $_GET['q'];
$busqueda = "%$q%";

$sql = "SELECT id, CONCAT(apellido, ' ', nombre) as text
        FROM contactos
        WHERE apellido LIKE :busqueda OR nombre LIKE :busqueda
        LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':busqueda', $busqueda, PDO::PARAM_STR);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data);