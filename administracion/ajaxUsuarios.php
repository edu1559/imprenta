<?php

    include_once('../conexion.php');
	$conn = conectar();
    
    if(isset($_GET['opcion'])){
        $opcion = $_GET['opcion'];
    };
   // echo $opcion;
  
    switch ($opcion){

	case 'agregarUsuario':	
		  	
             
                    $id = $_GET['id'];
                    $usuario = $_GET['usuario'];
                    $clave = $_GET['clave'];
                    $idPerfil = $_GET['idPerfil'];
                    
                    
                $sql = "insert into usuarios (id,usuario,clave,idPerfil)
                        values ($id,'$usuario','$clave',$idPerfil)";
				
                $result = mysqli_query($conn,$sql);
                echo $sql;
			//se le agregan los permisos 
			
				$sql = "insert into permisos (idUsuario,idMenu) 
						select $id,idMenu from perfilesMenu where idPerfil = $idPerfil";
				$result = mysqli_query($conn,$sql);
				
 				echo $sql;
			
    
       break;
       
       case 'borrarUsuario':
        
            
            $id= $_GET['id'];
            
            $sql = "delete from usuarios 
                    where id=$id";
                    
     
            $result = mysqli_query($conn,$sql);
             
        break;
    
     case 'actualizarUsuario':	
		  	
             
                    $usuario = urldecode($_GET['usuario']);
                    $clave = urldecode($_GET['clave']);
                   
                    $id = $_GET['id'];
                    
                $sql = "update usuarios set
                           usuario = '$usuario',
                            clave = '$clave'
                         where id = $id";
		echo $sql;        
                $result = mysqli_query($conn,$sql);
               
    
       break;
    
    
    
    };

?>