<?php
session_start();
include_once('../conexion.php');
$conn = conectar();

// Usamos $_POST en lugar de $_GET
if(isset($_POST['opcion'])){
    $opcion = $_POST['opcion'];
} else {
    // Si entran directo al archivo sin datos, detenemos todo
    die("No se recibieron datos");
}


// Manejo de sesión (igual que lo tenías)
if(isset($_SESSION['idUsuario'])){
    $idUsuario = $_SESSION['idUsuario'];
} else {
    $idUsuario = 1;
}

switch ($opcion){

    case 'agregarPedido':   
        
        // 1. MEJORA DE SEGURIDAD:
        // Usamos mysqli_real_escape_string para evitar que comillas o símbolos rompan la base de datos
        $idContacto = mysqli_real_escape_string($conn, $_POST['idContacto']);
        $idTipoPedido = mysqli_real_escape_string($conn, $_POST['idTipoPedido']);
        $idOrigen = mysqli_real_escape_string($conn, $_POST['idOrigen']);
        $detalle = mysqli_real_escape_string($conn, $_POST['detalle']); // Ya no hace falta urldecode con POST usualmente
        $observaciones = mysqli_real_escape_string($conn, $_POST['observaciones']);
        $idEstadoEntrega = mysqli_real_escape_string($conn, $_POST['idEstadoEntrega']);
        $idEstadoProduccion = mysqli_real_escape_string($conn, $_POST['idEstadoProduccion']);
        $idEstadoPago = mysqli_real_escape_string($conn, $_POST['idEstadoPago']);
        $prometido = mysqli_real_escape_string($conn, $_POST['prometido']);
        $montoPagado = mysqli_real_escape_string($conn, $_POST['montoPagado']);
        $monto = mysqli_real_escape_string($conn, $_POST['monto']);
        $idMedioPago = mysqli_real_escape_string($conn, $_POST['idMedioPago']);

        // Corrección de montos vacíos
        if ($montoPagado == '' || $montoPagado == null ){
            $montoPagado = 0;
        }

        // LÓGICA DE PAGO: Si dice "Pagado" (3), forzamos que lo pagado sea igual al total.
        if($idEstadoPago == 3){
            $montoPagado = $monto;
        }
        
        // Insertamos el PEDIDO
        $sql = "INSERT INTO pedidos (idContacto, idTipoPedido, idOrigen, detalle, observaciones, entrada, estadoEntrega, estadoProduccion, estadoPago, prometido, montoPagado, monto, idMedioPago, idUsuario)          
                VALUES ($idContacto, $idTipoPedido, $idOrigen, '$detalle', '$observaciones', NOW(), $idEstadoEntrega, $idEstadoProduccion, $idEstadoPago, '$prometido', '$montoPagado', '$monto', $idMedioPago, $idUsuario)";
        
        // Comenté los 'echo' de SQL para que no ensucien la pantalla del usuario, 
        // pero puedes descomentarlos si necesitas depurar.
        // echo $sql;     
        
        $result = mysqli_query($conn, $sql);
        
        $mensaje = ""; // Inicializamos la variable

        if ($result) {
            $mensaje .= "<div class='alert alert-success'>Se cargó el pedido correctamente.</div>";
            
            // 2. CORRECCIÓN DE TIPEO:
            // Antes decías $ulitimo_id_pedido (con una i extra)
            $ultimo_id_pedido = mysqli_insert_id($conn);
            
            // 3. LÓGICA DE PAGOS UNIFICADA:
            // Solo insertamos en la tabla pagos SI hay dinero de por medio.
            // Esto cubre tanto si puso un adelanto parcial O si pagó el total (estado 3).
            // (Tu código anterior insertaba dos veces si era estado 3).
            
            if($montoPagado > 0){
                $sqlPago = "INSERT INTO pagos (fecha, monto, idPedido, idUsuario, idMedioPago) 
                            VALUES (NOW(), '$montoPagado', $ultimo_id_pedido, $idUsuario, $idMedioPago)";
                
                $resultPago = mysqli_query($conn, $sqlPago);
                
                if ($resultPago) {
                    $mensaje .= "<br><small>Pago de $$montoPagado registrado.</small>";
                } else {
                    $mensaje .= "<br><small style='color:red'>Error al registrar el pago.</small>";
                }
            }

        } else {
            // Usamos mysqli_error para saber qué pasó si falla
            $mensaje = "<div class='alert alert-danger'>Error al agregar pedido: " . mysqli_error($conn) . "</div>";
        }
        
        echo $mensaje;
    
    break;


	   case 'actualizarPedido':
    // Recibimos por POST
    $idPedido           = $_POST['idPedido'];
    $idContacto         = $_POST['idContacto'];
    $idTipoPedido       = $_POST['idTipoPedido'];
    $detalle            = mysqli_real_escape_string($conn, $_POST['detalle']);
    $observaciones      = mysqli_real_escape_string($conn, $_POST['observaciones']);
    $prometido          = $_POST['prometido'];
    $idEstadoEntrega    = $_POST['idEstadoEntrega'];
    $idEstadoProduccion = $_POST['idEstadoProduccion'];
    $idEstadoPago       = $_POST['idEstadoPago'];
    $montoPagado        = $_POST['montoPagado'];
    $monto              = $_POST['monto'];
    $montoPagadoOriginal= $_POST['montoPagadoOriginal'];
    $idMedioPago        = $_POST['idMedioPago'];

    // Lógica para registrar un nuevo pago si el montoPagado aumentó
    // Nota: Para que esto funcione exacto, deberías enviar 'montoPagadoOriginal' desde el JS
  
        $diferencia = $montoPagado - $montoPagadoOriginal;

        if ($diferencia > 0.01) {
            // Nota: la tabla 'pagos' no tiene columna idContacto (bug detectado: la insert fallaba
            // en silencio y el pago aumentado nunca quedaba en el historial de pagos).
            $sqlPago = "INSERT INTO pagos (fecha, idPedido, idUsuario, monto, idMedioPago)
                        VALUES (NOW(), $idPedido, $idUsuario, '$diferencia', $idMedioPago)";
            mysqli_query($conn, $sqlPago);
        }
  

    // Actualización del pedido
    $sql = "UPDATE pedidos SET 
                idContacto = $idContacto,
                idTipoPedido = $idTipoPedido,
                detalle = '$detalle',
                observaciones = '$observaciones',
                prometido = '$prometido',    
                estadoEntrega = $idEstadoEntrega,
                estadoProduccion = $idEstadoProduccion,
                estadoPago = $idEstadoPago,
                montoPagado = '$montoPagado',
                monto = '$monto',
                idMedioPago = $idMedioPago
            WHERE id = $idPedido";

    $result = mysqli_query($conn, $sql);

    if ($result) {
        echo "✅ Pedido #$idPedido actualizado correctamente.";
    } else {
        echo "❌ Error al actualizar: " . mysqli_error($conn);
    }
    break;

case 'cerrarPedido':
    // También cambiamos a POST para mantener consistencia
    $id      = $_POST['idPedido'];
    $monto   = $_POST['monto'];
    $montoPagado = $_POST['montoPagado'];
    $idMedio = $_POST['idMedio'];
    

     //   calculo el pago 

        if  (abs($monto-$montoPagado)>0.1){

            $pagoActual = $monto - $montoPagado;

            $sqlPago = "insert into pagos
                        (fecha,idPedido,monto,idMedioPago,idUsuario)
                        values (now(),$id,$pagoActual,$idMedio,$idUsuario)";
                if (mysqli_query($conn, $sqlPago)) {
                        echo "✅ Pago Cargado.";
                } else {
                        echo "❌ Error.";
                };
        };

   


    // Al cerrar, asumimos que el pago se completa
    $sqlCerrar = "UPDATE pedidos SET 
                    estadoEntrega = 3,
                    estadoProduccion = 3,
                    estadoPago = 3,
                    montoPagado = '$monto',
                    idMedioPago = $idMedio
                  WHERE id = $id";
    
    if (mysqli_query($conn, $sqlCerrar)) {
        echo "✅ Pedido cerrado y pagado.";
    } else {
        echo "❌ Error al cerrar pedido.";
    };
    break;


case 'modificarEstado':
        $idPedido    = $_POST['idPedido'];
        $tipo        = $_POST['tipo']; // 'entrega' o 'Produccion'
        $nuevoEstado = $_POST['nuevoEstado'];

    // Mapeamos el nombre del botón con el nombre real de la columna en la DB
    // Si en el JS dice 'entrega', la columna es 'estadoEntrega'
    // Si en el JS dice 'Produccion', la columna es 'estadoProduccion'
    $columna = ($tipo == 'entrega') ? 'estadoEntrega' : 'estadoProduccion';

    $sql = "UPDATE pedidos SET $columna = $nuevoEstado WHERE id = $idPedido";
    
    if(mysqli_query($conn, $sql)) {
        echo "Estado actualizado";
    } else {
        echo "Error: " . mysqli_error($conn);
    }

    break;
};



	   
       
    
    

