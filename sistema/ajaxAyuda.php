<?php
 session_start();  
    include_once('../conexion.php');
	$conn=conectar();
    
    if(isset($_GET['opcion'])){
        $opcion = $_GET['opcion'];
    };
    
    
    switch ($opcion){

	case 'guardar':	
	    
	
				$texto = urldecode($_GET['texto']);
				$idClase = $_GET['idClase'];
					
				$sql = "insert into ayuda (texto,idClase) values ('$texto',$idClase)";
                $result = mysqli_query($conn,$sql);
				echo $sql;
				

    break;
       
   
    
    case 'modificar':	
	
                    $texto = urldecode($_GET['texto']);
                    $idClase = $_GET['idClase'];
                    $id = $_GET['id'];
                    
                $sql = "update ayuda set
                            texto = '$texto',
                            idClase = $idClase
                            
                        where id = $id";
            echo $sql;
            
                $result = mysqli_query($conn,$sql);
                
                
    
       break;
  
    

    case 'borrar':
        $id = $_GET['id'];
        $sql = "delete 
                from ayuda
                where id = $id";
        echo $sql;

        $result = mysqli_query($conn,$sql);


    break;

};
?>