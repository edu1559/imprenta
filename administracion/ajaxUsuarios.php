<?php
    session_start();
    include_once('../conexion.php');
    include_once('../auditoria.php');
	$conn = conectar();
    
    $opcion = $_GET['opcion'] ?? $_POST['opcion'] ?? '';
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

     case 'permisoModificar':
            if (!esAdministrador($conn)) {
                echo "Solo un administrador logueado puede cambiar este permiso.";
                break;
            }
            $id = (int)$_POST['id'];
            $valor = ((int)$_POST['valor'] === 1) ? 1 : 0;
            $stmt = $conn->prepare("UPDATE usuarios SET puedeModificar = ? WHERE id = ?");
            $stmt->bind_param('ii', $valor, $id);
            echo $stmt->execute() ? "OK" : "Error al guardar el permiso.";
       break;
    
    };

?>