<?php
include_once('../conexion.php');
$conn = conectar();

$opcion = $_GET['opcion'] ?? $_POST['opcion'] ?? '';

switch ($opcion) {

    case 'agregarMedio':
        $medio   = $_GET['medio'] ?? $_POST['medio'];
        $logo    = $_GET['logo'] ?? $_POST['logo'];
        $visible = (int)($_GET['visible'] ?? $_POST['visible']);

        $stmt = $conn->prepare("INSERT INTO mediosPago (medio, logo, visible) VALUES (?, ?, ?)");
        $stmt->bind_param('ssi', $medio, $logo, $visible);
        echo $stmt->execute() ? "Se agregó correctamente" : "Error al agregar medio";
        break;

    case 'actualizarMedio':
        $id      = (int)($_GET['id'] ?? $_POST['id']);
        $logo    = $_GET['logo'] ?? $_POST['logo'];
        $visible = (int)($_GET['visible'] ?? $_POST['visible']);

        $stmt = $conn->prepare("UPDATE mediosPago SET logo = ?, visible = ? WHERE id = ?");
        $stmt->bind_param('sii', $logo, $visible, $id);
        echo $stmt->execute() ? "Se actualizó correctamente" : "Error al actualizar medio";
        break;

    case 'borrarMedio':
        $id = (int)($_GET['id'] ?? $_POST['id']);

        $stmt = $conn->prepare("DELETE FROM mediosPago WHERE id = ?");
        $stmt->bind_param('i', $id);
        echo $stmt->execute() ? "Se borró correctamente" : "Error al borrar medio";
        break;
}
