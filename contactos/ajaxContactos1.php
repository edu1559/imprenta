<?php

    include_once('../conexion.php');
	$conn = conectar();
    
    if(isset($_GET['opcion'])){
        $opcion = $_GET['opcion'];
    };


   if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['opcion'])) {

    $opcion = $_GET['opcion'];
  
    switch ($opcion){

    case 'buscaContacto':
            $search_term = isset($_GET['q']) ? $_GET['q'] : '';
                
            $sql = "SELECT id, CONCAT(apellido, ', ', nombre) as text
                    FROM contactos
                    WHERE apellido LIKE ? OR nombre LIKE ? LIMIT 20";

            $stmt = $conn->prepare($sql);
            $search_param = "%" . $search_term . "%";
            $stmt->bind_param("ss", $search_param, $search_param);
            $stmt->execute();
            $result = $stmt->get_result();

            $clientes = [];
            while ($row = $result->fetch_assoc()) {
                $clientes[] = ['id' => (string)$row['id'], 'text' => $row['text']];
            }
            
              
            // Envía la respuesta JSON
            echo json_encode(['results' => $clientes]);
                
            $stmt->close();
            $conn->close();

            break;

	case 'agregarContacto':	
		  	
                    $apellido = urldecode($_GET['apellido']);
                    $nombre = urldecode($_GET['nombre']);
                    $telefono = $_GET['telefono'];
                    $correo = $_GET['correo'];
                    $cuit = $_GET['cuit'];
                    $tipoFactura = $_GET['tipoFactura'];
                    $notas = urldecode($_GET['notas']);
                 
                $sql = "insert into contactos (apellido,nombre,telefono,correo,cuit,tipoFactura,notas)
                        values ('$apellido','$nombre','$telefono','$correo','$cuit','$tipoFactura','$notas')";
				echo $sql;
                $result = mysqli_query($conn,$sql);
		
                if($result){
                   echo 'Se cargó con éxito';    
                }else{
                    echo 'No pudo cargarse';
                }; 
			
    
       break;
       
       case 'borrarContacto':
        
            
            $id= $_GET['id'];
            
            $sql = "delete from contactos 
                    where id=$id";
                    
     
            $result = mysqli_query($conn,$sql);
                if($result){
                    echo 'Se cargó con éxito';    
                }else{
                     echo 'No pudo cargarse';
                }; 
        break;
    
     case 'actualizarContacto':	
		  	       
                    $id = $_GET['id'];
                    $nombre = urldecode($_GET['nombre']);
                    $telefono = $_GET['telefono'];
                    $correo = $_GET['correo'];
                    $cuit = $_GET['cuit'];
                    $tipoFactura = $_GET['tipoFactura'];
                    $notas = urldecode($_GET['notas']);
                    
                $sql = "update contactos set 
                            nombre = '$nombre',
                            telefono = '$telefono',
                            correo = '$correo',
                            cuit = '$cuit', 
                            tipoFactura = '$tipoFactura',
                            notas = '$notas'  
                        where id = $id";
		//echo $sql;        
                $result = mysqli_query($conn,$sql);
                if ($result) {
                    echo "Se actualizo correctamene"; // Este mensaje será enviado al JavaScript
                } else {
                    echo "Error al agregar pedido";
                };
               
    
       break;
    
    
    
    };
};