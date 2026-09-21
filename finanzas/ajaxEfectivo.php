<?php
include_once('../conexion.php');
$conn = conectarPDO();
session_start();

// Validamos que lleguen datos y que el usuario esté logueado
$idUsuario = $_SESSION['idUsuario'] ?? 1; // Ajustar según tu variable de sesión
$billetes = $_POST['billetes'] ?? null;

if ($billetes && is_array($billetes)) {
    
    // 1. Iniciamos una transacción para guardar todo el bloque de billetes junto
    $conn->beginTransaction();

    try {
        $sql = "INSERT INTO arqueo_efectivo (denominacion, cantidad, idUsuario, fecha)
                VALUES (:denominacion, :cantidad, :idUsuario, NOW())";
        $stmt = $conn->prepare($sql);

        foreach ($billetes as $denominacion => $cantidad) {
            // Limpiamos los valores para evitar inyecciones o errores de tipo
            $denominacion = (int)$denominacion;
            $cantidad = (int)$cantidad;

            // 2. Insertamos el registro.
            // Usamos NOW() para tener la precisión del segundo exacto del conteo.
            $stmt->bindValue(':denominacion', $denominacion, PDO::PARAM_INT);
            $stmt->bindValue(':cantidad', $cantidad, PDO::PARAM_INT);
            $stmt->bindValue(':idUsuario', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();
        }

        // Si todo salió bien, confirmamos los cambios
        $conn->commit();
        echo "success";

    } catch (Exception $e) {
        // Si algo falló, deshacemos todo para no tener arqueos incompletos
        $conn->rollBack();
        echo "error: " . $e->getMessage();
    }

} else {
    echo "No se recibieron datos válidos.";
}
?>