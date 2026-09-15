<?php

    include_once('../conexion.php');
	$conn = conectar();
    
    if(isset($_GET['opcion'])){
        $opcion = $_GET['opcion'];
    };
   // echo $opcion;
  
    switch ($opcion){

	case 'agregarMedio':	
		  	
             
                   
                    $medio = $_GET['medio'];
                  
                    $logo = $_GET['logo'];
                    $visible = $_GET['visible'];
                    
                    
                $sql = "insert into mediosPago (medio,logo,visible)
                        values ('$medio','$logo',$visible)";
				
                $result = mysqli_query($conn,$sql);
		
			
 				echo $sql;
			
    
       break;
       
       case 'borrarMedio':
        
            
            $id= $_GET['id'];
            
            $sql = "delete from mediosPago 
                    where id=$id";
                    
     
            $result = mysqli_query($conn,$sql);
             
        break;
    
     case 'actualizarMedio':	
		  	
             
                   
                    $logo = $_GET['logo'];
                    $visible = $_GET['visible'];
                    $id = $_GET['id'];
                    
                $sql = "update mediosPago set
                            logo = '$logo',
                            visible = $visible
                        where id = $id";
		//echo $sql;        
                $result = mysqli_query($conn,$sql);
                if ($result) {
                    echo "Se agregó correctamene"; // Este mensaje será enviado al JavaScript
                } else {
                  //  echo "Error al agregar pedido";
                };
               
    
       break;
    
    
    
    };

