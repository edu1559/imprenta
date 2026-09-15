<?php
include_once('../conexion.php');
$conn = conectar();
session_start();

// Validamos que lleguen datos y que el usuario esté logueado
$idUsuario = $_SESSION['idUsuario'] ?? 1; // Ajustar según tu variable de sesión
$billetes = $_POST['billetes'] ?? null;

if ($billetes && is_array($billetes)) {
    
    // 1. Iniciamos una transacción para guardar todo el bloque de billetes junto
    mysqli_begin_transaction($conn);

    try {
        foreach ($billetes as $denominacion => $cantidad) {
            // Limpiamos los valores para evitar inyecciones o errores de tipo
            $denominacion = (int)$denominacion;
            $cantidad = (int)$cantidad;

            // 2. Insertamos el registro. 
            // Usamos NOW() para tener la precisión del segundo exacto del conteo.
            $sql = "INSERT INTO arqueo_efectivo (denominacion, cantidad, idUsuario, fecha) 
                    VALUES ($denominacion, $cantidad, $idUsuario, NOW())";
            
            if (!mysqli_query($conn, $sql)) {
                throw new Exception("Error al insertar denominación $denominacion");
            }
        }

        // Si todo salió bien, confirmamos los cambios
        mysqli_commit($conn);
        echo "success";

    } catch (Exception $e) {
        // Si algo falló, deshacemos todo para no tener arqueos incompletos
        mysqli_rollback($conn);
        echo "error: " . $e->getMessage();
    }

} else {
    echo "No se recibieron datos válidos.";
}
?>