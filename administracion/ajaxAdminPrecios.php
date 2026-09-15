<?php
 session_start();  
  
  include_once('../conexion.php');
	$conn=conectar();
    
    if(isset($_GET['opcion'])){
        $opcion = $_GET['opcion'];
    };
    
    
    switch ($opcion){

	case 'agregarPrecio':	

                $idProducto = $_GET['idProducto'];
                $descripcion = urldecode($_GET['descripcion']);
                $cantidad= urldecode($_GET['cantidad']);
                $gramaje= urldecode($_GET['gramaje']);
                $tamanio =urldecode( $_GET['tamanio']);
                $precio = $_GET['precio'];
           
                

                  
                               
        $sql = "insert into precios (idProducto,descripcion,cantidad,gramaje,tamanio,precio)
                values ($idProducto,'$descripcion','$cantidad','$gramaje','$tamanio','$precio')";
        
    echo $sql;
		
        $result = mysqli_query($conn,$sql);
     
        			
     
    break;
       
    case 'borrarPrecio':
 
            $id= $_GET['id'];
            
            $sql = "delete from precios 
                    where id=$id";
            $result = mysqli_query($conn,$sql);
           // echo $sql;
             
    break;
    
  
	
   case 'actualizarPrecio':	
	
                    $idPrecio = $_GET['idPrecio'];
                    $idProducto = $_GET['idProducto'];
                    $descripcion = urldecode($_GET['descripcion']);
                    $cantidad = urldecode($_GET['cantidad']);
                    $gramaje = urldecode($_GET['gramaje']);
                    $tamanio = urldecode($_GET['tamanio']);
                    $precio = urldecode($_GET['precio']);
                
					
					
                $sql = "update precios set
                            idProducto = $idProducto,
                            descripcion = '$descripcion',
                            cantidad = '$cantidad',
                            gramaje =  '$gramaje',
                            tamanio = '$tamanio',
                            precio = '$precio'
                        where id = $idPrecio";
            echo $sql;
            
        $result = mysqli_query($conn,$sql);
     break;
  
    };
?>
