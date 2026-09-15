<?php
session_start();
include_once('../conexion.php');
$conn = conectar();

// Usamos $_POST para mayor seguridad
$opcion = $_POST['opcion'] ?? $_GET['opcion'] ?? die("Sin opción");
$idUsuario = $_SESSION['idUsuario'] ?? 1;

switch ($opcion) {

    case 'agregarPago':
        $idPedido    = (int)$_POST['idPedido'];
        $monto       = (float)$_POST['monto'];
        $idMedioPago = (int)$_POST['idMedioPago'];
        
        // Es mejor traer los valores actuales de la DB para evitar errores de desfase
        $sqlCheck = "SELECT monto, montoPagado FROM pedidos WHERE id = $idPedido";
        $resCheck = mysqli_query($conn, $sqlCheck);
        $pedido   = mysqli_fetch_assoc($resCheck);
        
        $montoTotal         = (float)$pedido['monto'];
        $montoPagadoAnterior = (float)$pedido['montoPagado'];

        // 1. Insertar el movimiento en la tabla PAGOS
        $sqlPago = "INSERT INTO pagos (idPedido, fecha, monto, idMedioPago, idUsuario) 
                    VALUES ($idPedido, NOW(), '$monto', $idMedioPago, $idUsuario)";
        
        if (mysqli_query($conn, $sqlPago)) {
            
            // 2. Calcular nuevo total pagado
            $nuevoTotalPagado = $montoPagadoAnterior + $monto;

            // 3. Lógica de ESTADO DE PAGO
            // Si el saldo pendiente es casi cero (usamos 0.1 por margen de decimales), es Pagado (3)
            if (abs($montoTotal - $nuevoTotalPagado) < 0.1) {
                $estadoPago = 3; // Pagado
                $nuevoTotalPagado = $montoTotal; // Ajuste fino para que no quede 999.99
            } else {
                $estadoPago = 2; // Pago Parcial
            }

            // 4. Actualizar el pedido
            $sqlUpd = "UPDATE pedidos SET 
                        montoPagado = '$nuevoTotalPagado', 
                        estadoPago = $estadoPago 
                       WHERE id = $idPedido";
            
            if (mysqli_query($conn, $sqlUpd)) {
                echo "✅ Pago registrado y pedido actualizado.";
            } else {
                echo "❌ Error al actualizar pedido: " . mysqli_error($conn);
            }
        } else {
            echo "❌ Error al guardar el pago.";
        }
        break;

    case 'borrarPago':
        // IMPORTANTE: Al borrar un pago, hay que RESTARLO del pedido
        $idPago = (int)$_POST['id'];
        
        // Buscamos datos del pago antes de borrarlo
        $resP = mysqli_query($conn, "SELECT idPedido, monto FROM pagos WHERE id = $idPago");
        if($pago = mysqli_fetch_assoc($resP)) {
            $idPed = $pago['idPedido'];
            $montoABorrar = $pago['monto'];
            
            // Borramos el pago
            mysqli_query($conn, "DELETE FROM pagos WHERE id = $idPago");
            
            // Actualizamos el pedido restando ese monto
            mysqli_query($conn, "UPDATE pedidos SET 
                                 montoPagado = montoPagado - $montoABorrar,
                                 estadoPago = IF(montoPagado - $montoABorrar <= 0, 1, 2)
                                 WHERE id = $idPed");
            echo "✅ Pago eliminado.";
        }
        break;
}
