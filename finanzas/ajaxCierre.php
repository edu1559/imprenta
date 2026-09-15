<?php
include_once('../conexion.php');
$conn = conectar();

$opcion = $_POST['opcion'] ?? $_GET['opcion'] ?? '';
$idUsuario = $_SESSION['idUsuario'] ?? 1;

switch ($opcion) {
    case 'cambiaMedio':
        $idPago = $_POST['idPago'];
        $idMedio = $_POST['idMedio'];
        $sql = "UPDATE pagos SET idMedioPago = $idMedio WHERE id = $idPago";
        echo mysqli_query($conn, $sql) ? "OK" : "Error";
        break;

    case 'cierre':
        // Aquí actualizamos la tabla cierreMedios para un solo medio
        $idM = $_POST['idMedio'];
        $montoReal = $_POST['montoReal'];
        $montoCalculado = $_POST['montoCalculado'];
        $dif = $montoReal - $montoCalculado;

        $sql = "UPDATE cierreMedios SET 
                montoUC = '$montoReal', 
                diferenciaUC = '$dif', 
                fechaUC = NOW() 
                WHERE idMedio = $idM";
        echo mysqli_query($conn, $sql) ? "Cierre individual exitoso" : "Error";
        break;

    case 'cerrarTodos':
    $medios = json_decode($_POST['medios'], true);
    $totalSuma = (float)$_POST['totalSuma'];
    $totalDif = (float)$_POST['totalDiferencia'];
    $idUsuario = $_SESSION['idUsuario'] ?? 1;

    // 1. Iniciamos una transacción para que si algo falla, no se rompa nada
    mysqli_begin_transaction($conn);

    try {
        // Variables para el registro histórico
        $valoresCierre = array_fill_keys(['efectivo', 'transferencia', 'mercadoPago', 'cheques', 'credito', 'dolares', 'brubank', 'naranjaX'], 0);

        foreach ($medios as $m) {
            $id = $m['id'];
            $real = $m['real'];
            $dif = $real - $m['calc'];

            // Actualizar el estado del medio (Cierre individual masivo)
            $sqlUpd = "UPDATE cierreMedios SET 
                        montoUC = '$real', 
                        diferenciaUC = '$dif', 
                        fechaUC = NOW() 
                       WHERE idMedio = $id";
            mysqli_query($conn, $sqlUpd);

            // Mapeo para la tabla 'cierre' (Ajustar nombres de medios según tu DB)
            // Aquí un ejemplo simple, podrías usar un switch según el ID del medio
            if ($id == 1) $valoresCierre['efectivo'] = $real;
            if ($id == 2) $valoresCierre['transferencia'] = $real;
            if ($id == 3) $valoresCierre['mercadoPago'] = $real;
            // ... agregar los demás según tus IDs ...
        }

        // 2. Insertar en el historial general 'cierre'
        $sqlHistorial = "INSERT INTO cierre (fecha, efectivo, transferencia, mercadoPago, suma, diferencia, idUsuarioCierre) 
                         VALUES (NOW(), '{$valoresCierre['efectivo']}', '{$valoresCierre['transferencia']}', 
                                 '{$valoresCierre['mercadoPago']}', '$totalSuma', '$totalDif', '$idUsuario')";
        
        mysqli_query($conn, $sqlHistorial);

        mysqli_commit($conn);
        echo "✅ Cierre general completado con éxito. Historial actualizado.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "❌ Error en el cierre: " . $e->getMessage();
    }
    break;
}
?>





























