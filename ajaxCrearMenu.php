<?php

    include_once('conexion.php');
	$conn=conectar();
    
    if(isset($_GET['opcion'])){
        $opcion = $_GET['opcion'];
    };
    
    
    switch ($opcion){

	case 'agregarMenu':	
	
				$nombre = $_GET['nombre'];
				$form = $_GET['form'];
				$padre = $_GET['padre'];
				
			$sql = "insert into menu (nombre,form,padre)
					values ('$nombre','$form',$padre)";
		echo $sql;
			$result = mysqli_query($conn,$sql);
      
    break;
       
    case 'borrarMenu':
 
            $id= $_GET['id'];
            
            $sql = "delete from menu 
                    where id=$id";
            $result = mysqli_query($conn,$sql);
             
    break;
    
    case 'actualizarMenu':	
	
                    $nombre = $_GET['nombre'];
                    $form = $_GET['form'];
                    $padre = $_GET['padre'];
                    $id = $_GET['id'];
                    
                $sql = "update menu set
                            nombre = '$nombre',
                            form = '$form',
                            padre = $padre
                        where id = $id";
            
                $result = mysqli_query($conn,$sql);
               
    
       break;
  
    };
?>
