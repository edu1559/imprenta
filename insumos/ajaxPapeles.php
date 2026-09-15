<?php

    include_once('../conexion.php');
	$conn = conectar();
    
    if(isset($_GET['opcion'])){
        $opcion = $_GET['opcion'];
    };
   echo $opcion;
  
    switch ($opcion){

	case 'agregarPapel':	
		  	
                    $nombre = urldecode($_GET['nombre']);
                    $largo = urldecode($_GET['largo']);
                    $ancho = $_GET['ancho'];
                    $gramaje = $_GET['gramaje'];
                    $formato = $_GET['formato'];
                    $hojasPaquete = $_GET['hojasPaquete'];
                    $precioPaquete = $_GET['precioPaquete'];
                 
                $sql = "insert into papeles (nombre,largo,ancho,gramaje,formato,hojasPaquete,precioPaquete)
                        values ('$nombre',$largo,$ancho,$gramaje,'$formato',$hojasPaquete,'$precioPaquete')";
				echo $sql; 

                $result = mysqli_query($conn,$sql);
		
                if($result){
                   echo 'Se cargó con éxito';    
                }else{
                    echo 'No pudo cargarse';
                }; 
			
    
       break;
       
       case 'borrarPapel':
        
            
            $id= $_GET['id'];
            
            $sql = "delete from papeles 
                    where id=$id";
                    
     
            $result = mysqli_query($conn,$sql);
                if($result){
                    echo 'Se borró con éxito';    
                }else{
                     echo 'No pudo borrarse';
                }; 
        break;
    
     case 'actualizarPapel':	
		  	
             
                   
                    $id = $_GET['id'];
                    $nombre = urldecode($_GET['nombre']);
                    $largo = $_GET['largo'];
                    $ancho = $_GET['ancho'];
                    $gramaje = $_GET['gramaje'];
                    $formato = $_GET['formato'];
                    $hojasPaquete = $_GET['hojasPaquete'];
                    $precioPaquete = $_GET['precioPaquete'];
                   
                
                    
                $sql = "update papeles set 
                            nombre = '$nombre',
                            largo = $largo,
                            ancho = $ancho,
                            gramaje = $gramaje, 
                            formato = '$formato',
                            hojasPaquete = $hojasPaquete,
                            precioPaquete = '$precioPaquete'  
                        where id = $id";
	    echo $sql;        
                $result = mysqli_query($conn,$sql);
                if ($result) {
                    echo "Se actualizo correctamene"; // Este mensaje será enviado al JavaScript
                } else {
                    echo "Error al actualizar";
                };
               
    
       break;
    
    
    
    };