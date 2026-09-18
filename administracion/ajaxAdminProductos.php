<?php
 session_start();  
  
  include_once('../conexion.php');
	$conn=conectar();
    
    if(isset($_GET['opcion'])){
        $opcion = $_GET['opcion'];
    };
    
    
    switch ($opcion){

	case 'agregarProducto':	

                $nombre = $_GET['nombre'];
                $foto = $_GET['foto'];
                $color = $_GET['color'];
                $idCategoria = $_GET['idCategoria'];
                $caracteristicas = urldecode($_GET['caracteristicas']);
                $enlace = $_GET['enlace'];
                $orden = $_GET['orden'];
                

              /*  
                echo "nombre:". $nombre . "<br>";
                echo "foto:". $foto . "<br>";
                echo "color:". $color . "<br>";
                echo "idCategoria:". $idCategoria . "<br>";
              */      
                               
        $sql = "insert into productos (nombre,foto,color,idCategoria,caracteristicas,enlace,orden)
                values ('$nombre','$foto','$color',$idCategoria,'$caracteristicas','$enlace', $orden)";
        
        //echo $sql;

        $result = mysqli_query($conn,$sql);
     
        
			


			
     
    break;
       
    case 'borrarProducto':
 
            $id= $_GET['id'];
            
            $sql = "delete from productos 
                    where id=$id";
            $result = mysqli_query($conn,$sql);
           // echo $sql;
             
    break;
    
  
	
   case 'actualizarProducto':	
	
                    $nombre = urldecode($_GET['nombre']);
                    $foto = $_GET['foto'];
                    $color = $_GET['color'];
                    $orden = $_GET['orden'];
                    $enlace = $_GET['enlace'];
                    $caracteristicas = urldecode($_GET['caracteristicas']);
                    $idCategoria = $_GET['idCategoria'];
                    $idProducto = $_GET['idProducto'];
					
					
                $sql = "update productos set
                            nombre = '$nombre',
                            foto = '$foto',
                            color = '$color',
                            orden =  $orden,
                            enlace = '$enlace',
                            caracteristicas = '$caracteristicas',
                            idCategoria = $idCategoria
                        where id = $idProducto";
       //   echo $sql;
            
        $result = mysqli_query($conn,$sql);
     break;
  
    };
?>