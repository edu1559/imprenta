<?php

    include_once('../conexion.php');
	$conn = conectar();
    
    if(isset($_GET['opcion'])){
        $opcion = $_GET['opcion'];
    };
 //  echo $opcion;
  
    switch ($opcion){

	case 'agregarPrueba':	
		  	
                    $idPadre = $_GET['idPadre'];
                    $idPagina = $_GET['idPagina'];
                    $funcionalidad = urldecode($_GET['funcionalidad']);
                    $prueba = urldecode($_GET['prueba']);
                    $resultado = urldecode($_GET['resultado']);
                    $mejora = urldecode($_GET['mejora']);
                 
                $sql = "insert into pruebas (fecha,idPadre,idPagina,funcionalidad,prueba,resultado,mejora)
                        values (now(),$idPadre, $idPagina,'$funcionalidad','$prueba','$resultado','$mejora')";
				
               //echo $sql;
                
               $result = mysqli_query($conn,$sql);
		
                if($result){
                   echo 'Se cargó con éxito';    
                }else{
                    echo 'No pudo cargarse';
                }; 
			
    
       break;
       
       case 'borrarPrueba':
        
            
            $id= $_GET['id'];
            
            $sql = "delete from pruebas 
                    where id=$id";
            echo $sql;       
     
            $result = mysqli_query($conn,$sql);
                if($result){
                    echo 'Se borró con éxito';    
                }else{
                     echo 'No pudo borrarse';
                }; 
        break;
    
     case 'actualizarPrueba':	
		  	
             
                   
                    $id = $_GET['id'];
                    $funcionalidad = urldecode($_GET['funcionalidad']);
                    $prueba = urldecode($_GET['prueba']);
                    $resultado = urldecode($_GET['resultado']);
                    $mejora = urldecode($_GET['mejora']);    

                    
                $sql = "update pruebas set 
                            funcionalidad = '$funcionalidad',
                            prueba = '$prueba',
                            resultado = '$resultado',
                            mejora = '$mejora'
                        where id = $id";
		// echo $sql;     

       $result = mysqli_query($conn,$sql);
                if ($result) {
                    echo "Se actualizo correctamene"; // Este mensaje será enviado al JavaScript
                } else {
                    echo "Error al actualizar prueba";
                };
               
    
       break;
    
    
    
    };


