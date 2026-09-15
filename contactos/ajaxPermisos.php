<?php
  
  include_once('../conexion.php');
  $conn = conectar();

  if(isset($_GET['opcion'])) {
    $opcion = $_GET['opcion'];
  } else {
    $opcion = 'ninguna';
  };

  
  
  switch($opcion) {

  
            case 'cambiarPermiso':
            
            $permiso = $_GET['permiso'];
            $idMenu = $_GET['idMenu'];
            $idUsuario = $_GET['idUsuario'];
            
            if ($permiso == 0){
                $sql = "insert into permisos (idMenu,idUsuario) values ($idMenu,$idUsuario)";
                
            }else{
                $sql = "delete from permisos where idMenu=$idMenu and idUsuario=$idUsuario;";
            };
             echo $sql;

            $result = mysqli_query($conn,$sql);
			      if ($result){
                echo "El permiso se modificó con éxito";
            }else{
                echo "El permiso no pudo modificarse";
            };
          
            
            break;
                 
          };
            ?>
                    
               
       
      
   
 
