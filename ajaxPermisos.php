<?php
  
  include_once('conexion.php');
  $conn = conectar();

  if(isset($_GET['opcion'])) {
    $opcion = $_GET['opcion'];
  } else {
    $opcion = 'ninguna';
  };

 // echo $opcion;
  
  
  switch($opcion) {

    case 'buscar':

      if(isset($_GET['cadena'])) {
        $cadena = $_GET['cadena'];
      } else {
        $cadena = '';
      }

      $sql = "select id, apellido, nombre
              from usuarios
              where apellido like '%$cadena%'";
              
  // echo $sql;
      
              
      $result = mysqli_query($conn, $sql);

    
      echo "<table class=\"table table-hover\" style=\"width:200px\">";
      
      while($mifila = mysqli_fetch_row($result)) {
       echo "<tr>
                <td>".$mifila[0]. "</td>
                <td>".$mifila[1]. "</td>
                <td>".$mifila[2]. "</td>
                <td><button class=\"btnUsuario bg-warning\">Elegir</button><tr>
            </tr>";
      };
      
      echo "</table>";
      ?>
      
      <script>
      
      $('.btnUsuario').click(function(){
      
        var v_id = $(this).closest('tr').find('td:first').text();
        v_url = 'ajaxPermisos.php?opcion=mostrarPermisos&idUsuario=' + v_id
        
        $('#muestraUsuarios').hide();
        
        $('#divMuestraPermisos').load(v_url);
      });
      
      
      </script>
      
      <?php
      
      

      break;
      
      case 'mostrarPermisos':
      
       $idUsuario = $_GET['idUsuario'];
       
       $sql = " select nombre,apellido, idPerfil
                from usuarios
                where id = $idUsuario";
       
       $result = mysqli_query($conn,$sql);
       
       while ($mifila = mysqli_fetch_row($result)){
          $apellido = $mifila[0];
          $nombre = $mifila[1];
          $idPerfil = $mifila[2];
        };
        
        echo "<div class=\"container bg-light p-3\"><h4>Permisos de ". $apellido . " ". $nombre. "</h4></div>";
        
        
     
            $sql = "select id,nombre 
                    from menu
                    where padre = 0";
            $result = mysqli_query($conn,$sql);
            
            $padreCero = array();
            
            
            
            while($myrow = mysqli_fetch_row($result)){
                $padreCero[$myrow[0]] = $myrow[1];
            };
            
            // muestro los permisos de cada menu 
            
                            
            foreach ($padreCero as $clave => $valor){
            
            echo "<div class=\"card m-3\" style=\"width:250px;display:flex;float:left\">";
            
            echo "<table class=\"table table-striped;display-inline;flex-direction: row;justify-content:flex-start\" >
                
                <tr><th class=\"table-warning\" colspan=\"2\" style=\"text-align:center\">". $valor. "</th></tr>";
            /*
                $sql = "select m.id,m.nombre,p.permiso 
                        from menu m left join permisos p
                        on m.id = p.idMenu 
                        where padre = $clave and p.idUsuario = $idUsuario";
            
            */
			
				$sql = "select m.nombre, if(p.idMenu is null, '',p.idMenu) as idMenu,m.id 
						from menu m left join permisos p 
							on m.id = p.idMenu 
							and p.idUsuario=$idUsuario
						where m.padre = $clave";
                 //  echo $sql . "<br>"; 
				 
              $result = mysqli_query($conn,$sql);
                         
              while ($mifila = mysqli_fetch_row($result)){
                                
                echo "<tr class=\"table-active\"><td>" . $mifila[0]. "</td><td>";
                                                        
					if( $mifila[1] <> ''){
						echo "<button type=\"button\" class=\"btn btn-success desactivar\" name=\"". $mifila[2]. "\">Desactivar</button>";
					}else{
						echo "<button type=\"button\" class=\"btn btn-danger activar\"  name=\"". $mifila[2]. "\">Activar</button>";	
					};
					
					echo "</td></tr>";
                };
                                                                                                        
                            
                            echo "</table></div>";
                };
                  
                  
               echo "</div>";
               echo "</div>";
			   ?>
               <script>
			
					$('.desactivar').click(function(){
						var v_idUsuario = <?php echo $idUsuario; ?>;
						var v_idMenu = $(this).attr('name');
						v_url = "ajaxPermisos.php?opcion=desactivar&idUsuario=" + v_idUsuario + "&idMenu=" + v_idMenu;
						alert(v_url);
						$('divMuestraPermisos').load(v_url);
					});
				
					
						
		
			   
			   </script>
			   
			  
			   <?php
               break;

			   case 'desactivar':
			  
			   $idUsuario = $_GET['idUsuario'];
			   $idMenu = $_GET['idMenu'];
			   
			   $sql = "delete from permisos 
			           where idUsuario = $idUsuario 
					   and idMenu = $idMenu";
			  
			   $result = mysqli_query($conn,$sql);
			   break;
			   
			   case 'activar':
			   
			   $idUsuario = $_GET['idUsuario'];
			   $idMenu = $_GET['idMenu'];
			   
			   $sql = "insert into permisos (idUsuario,idMenu) 
						values($idUsuario,$idMenu)";
			   echo $sql;         
			   $result = mysqli_query($conn,$sql);
			   break;
			   
			   
               };
			   
			
			
			
			
                   
            ?>
                    
               
       
      
   
 
