<?php

    include_once('../conexion.php');
	$conn = conectar();
    
    if(isset($_GET['opcion'])){
        $opcion = $_GET['opcion'];
    };
   echo $opcion;
  
    switch ($opcion){

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