<?php	
session_start();
$idUsuario = $_SESSION['idUsuario'];

// Se incluye el archivo de conexión
include_once('../conexion.php');
$conn = conectar();
/*
	viene desde nuevoPedido
pedidos/ajaxNuevoPedido.php?
	opcion=agregarPedido&
	idContacto=8179&
	idTipoPedido=1&
	idOrigen=1&
	detalle=1000%20volantes%20color&
	observaciones=&
	idEstadoEntrega=1&
	idEstadoProduccion=1&
	idEstadoPago=1&
	prometido=2026-02-13&
	montoPagado=10000&
	monto=38000&
	idMedioPago=1

*/

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['opcion'])) {

    $opcion = $_GET['opcion'];

    switch ($opcion) {

	case 'agregarPedido':	
	
	
		$idContacto = $_GET['idContacto'];
		$idTipoPedido = $_GET['idTipoPedido'];
		$idOrigen = $_GET['idOrigen'];
		$detalle = urldecode($_GET['detalle']);
		$observaciones = urldecode($_GET['observaciones']);
		$idEstadoEntrega = $_GET['idEstadoEntrega'];
		$idEstadoProduccion = $_GET['idEstadoProduccion'];
		$idEstadoPago = $_GET['idEstadoPago'];
		$prometido=$_GET['prometido'];
		$montoPagado=$_GET['montoPagado'];
		$monto=$_GET['monto'];
		$idMedioPago=$_GET['idMedioPago'];

		// comprobación del estado de pago

		  
      	// 1. Normalizamos el monto pagado si viene vacío o nulo
			if ($montoPagado == '' || $montoPagado == null) {
				$montoPagado = 0;
			}

		// 2. Calculamos la diferencia
		$diferencia = $monto - $montoPagado;

		if ($montoPagado == 0) {
			$idEstadoPago = 1; // Sin pagar
		} else if ($diferencia <= 0.01) { 
			// Usamos un margen pequeño por precisión de decimales
			$idEstadoPago = 3; // Pagado Total (Monto pagado alcanza o supera al total)
		} else {
			$idEstadoPago = 2; // Pagado Parcial (Se pagó algo, pero no todo)
		}
		
		$sql = "insert into pedidos (idContacto,idTipoPedido,idOrigen,detalle,observaciones,entrada,estadoEntrega,estadoProduccion,estadoPago,prometido,montoPagado,monto,idMedioPago,idUsuario)          
		values ($idContacto,$idTipoPedido,$idOrigen,'$detalle','$observaciones',now(),$idEstadoEntrega,$idEstadoProduccion,$idEstadoPago,'$prometido','$montoPagado','$monto',$idMedioPago,$idUsuario)";
 		// echo $sql . "<hr>";     
         
		$result = mysqli_query($conn,$sql);
               
        
		    if ($result) {
				$mensaje = "Se cargó el pedido - " . $montoPagado; // Este mensaje será enviado al JavaScript
			} else {
				$mensaje = "Error al agregar pedido ". $sql;
			};
			
		 $ultimo_id_pedido = mysqli_insert_id($conn);
		 echo '<hr> el ultimo id:'. $ultimo_id_pedido. "<hr>";
		


		 if($montoPagado <> '0'){
			$sql = "insert into pagos (fecha,monto,idPedido,idUsuario,idMedioPago) 
					values (now(),'$montoPagado',$ultimo_id_pedido,$idUsuario,$idMedioPago)";
		echo $sql; 		
			$result = mysqli_query($conn,$sql);
			if ($result) {
				$mensaje .= " pago cargado "; // Este mensaje será enviado al JavaScript
			} else {
				$mensaje .= " error al cargar el pago: ". $sql;
			};
		 };
			
		// si se paga el monto completo al cargar el pedido
            
		if($idEstadoPago == 3){
			$sql = "insert into pagos (fecha,monto,idPedido,idUsuario,idMedioPago,idContacto) 
						values (now(),'$monto',$ultimo_id_pedido,1,$idMedioPago,$idContacto)";
			$result = mysqli_query($conn,$sql);
			if ($result) {
				$mensaje .= " pago completo cargado "; // Este mensaje será enviado al JavaScript
			} else {
				$mensaje .= " error al cargar el pago completo: ". $sql;
			};
		};
	
				
        echo $mensaje;
                   
       break;
	   
	};	   
	   
};
    
?>