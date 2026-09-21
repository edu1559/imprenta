<?php
  
  include_once('../conexion.php');
  $conn = conectarPDO();

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
                $sql = "insert into permisos (idMenu,idUsuario) values (:idMenu,:idUsuario)";

            }else{
                $sql = "delete from permisos where idMenu=:idMenu and idUsuario=:idUsuario";
            };

            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':idMenu', $idMenu, PDO::PARAM_INT);
            $stmt->bindValue(':idUsuario', $idUsuario, PDO::PARAM_INT);

            try {
                $stmt->execute();
                echo "El permiso se modificó con éxito";
            } catch (PDOException $e) {
                echo "El permiso no pudo modificarse";
            };
          
            
            break;
                 
          };
            ?>
                    
               
       
      
   
 
