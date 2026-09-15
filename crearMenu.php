
  <?php
        //conectar a la base de datos                                          
		 include_once ('conexion.php');
		 $conn = conectar();
	   
		// crear un array de la tabla menu con los campos padres: $padres[id]= nombre;
		 
		 $padres = array();
		 $sql = "select id,nombre
			   from menu 
			   where padre=0";
	     $result = mysqli_query($conn,$sql);
		 
		 while($myrow = mysqli_fetch_row($result)){	
			$padres[$myrow[0]] = $myrow[1];
		 };
	    
		// print_r($padres);  
	    // Pongo un valor por defecto
		
		if(isset($_GET['menuSeleccionado'])){
		$menuSeleccionado = $_GET['menuSeleccionado'];
		}else{
		$menuSeleccionado = key($padres); //seleciona la clave del primer elemento (dónde esté el puntero interno)
		};
                       
 ?> 
 
 <!--titulo principal-->
<div class="container-fluid m-3 p-3  text-center justify-content">
    <h3> Creación del Menú<span class="fa fa-tasks" style="color:gray; width:100px"></h3>
	
</div><!-- fin container titulo-->
 
 
 <!-- container con dos paneles izquierda seleccion y derecha edición -->
 
 
 <div class="row m-2 p-5" id="formMuestraMenu">
	
	<!-- panelSeleccion -->
	
    <div class="col-sm-6 bg-secondary p-3  rounded panelSeleccion">
    
    <table class="table table-striped table-light table-hover mt-5 ">
    <thead>
		<tr class="table-dark text-white">
			<th>id</th>
			<th>nombre</th>
			<th>form</th>
			<th>
			<select  id="filtroPadre" class="bg-dark text-white">
				<?php	
					foreach ($padres as $clave => $valor){
						echo "<option value=\"".$clave. "\"";
							if($clave == $menuSeleccionado){
								echo " selected ";
							};
						echo ">".$valor."</option>";
					};
				?>
			</select>
			</th>
			<th>borrar</th>
			<th>editar</th>
			
		</tr>
    </thead>
    
        <?php 
            
			
			if(isset($_GET['spadre'])){
				$spadre = $_GET['spadre'];
			}else{
				$spadre = 3;
			};

            $sql = "select id,nombre,form, padre
                    from menu
					where padre = $spadre";
                
            $result = mysqli_query($conn,$sql);
            
           
            
            while($myrow = mysqli_fetch_row($result)){
                echo "<tr>
						<td>". $myrow[0]. "</td>
						<td>". $myrow[1]. "</td>
						<td>". $myrow[2]. "</td>
						<td>". $myrow[3]. "</td>
						<td><span class=\"fa fa-trash borrarMenu\" style=\"cursor:pointer;font-size:25px\"></td>
						<td><span class=\"fa fa-pencil editarMenu\" style=\"cursor:pointer;font-size:25px\"></td>               
                    </tr>";
                };		
                            
?>
  </table>	
		<script>
			$('#filtroPadre').change(function(){
				v_spadre = $('#filtroPadre').val();
				v_url = 'crearMenu.php?spadre='+ v_spadre;
				$('#contenido').load(v_url);
			});
		</script>
			
        </div>
        
<!-- panelEdicion -->
        
        <div class="col-sm-6 panelEdicion">
             <div class="container p-3 bg-secondary rounded"> 
                   
                    <div class="mb-3 mt-3">
                        <label for="nombre" class="form-label text-white">Nombre:</label>
                        <input type="text" class="form-control" id="inputNombre" placeholder="Nombre de la Opción" name="nombreOpcionMenu" maxlength="30">
                    </div>
                    <div class="mb-3">
                        <label for="pagina" class="form-label  text-white">Página:</label>
                        <input type="text" class="form-control" id="inputPagina" placeholder="Ingrese el  archivo" name="pagina" maxlength="30">
                    </div>
                    <div class="mb-3">
                        <label for="padre" class="form-label  text-white">padre:</label>
                                           
                        <select id="selectPadre" name="padre">
                        <?php
                            foreach($padres as $clave => $valor){
                            
                            echo "<option value=\"". $clave. "\">". $valor. "</option>";
                            
                            };
                        ?>
                        
                        </select>
                        
                    </div>
                    <div class="mb-3">
                        <label id="agregaId" class="form-label  text-white">Id:</label>
                        <input type="hidden" id="inputId" value="">
                    </div>
                   
                    <button type="submit" id="btnGuardar" class="btn btn-outline-light">Guardar</button>
                    <button type="submit" id="btnActualizar" class="btn btn-outline-light">Actualizar</button>
                    

            </div>
      </div>


</div><!-- container titulo-->
<script>
    
     $('#btnActualizar').hide();
    
    $('#btnGuardar').click(function(){
     
      v_nombre = $('#inputNombre').val();
      v_form = $('#inputPagina').val();
      v_spadre = $('#selectPadre').val();
      
      v_url = "ajaxCrearMenu.php?opcion=agregarMenu&nombre=" + v_nombre +  "&form=" + v_form + "&padre=" + v_spadre;
     
   alert(v_url);
        
      $('#formMuestraMenu').load(v_url);
      
      $('#contenido').load('crearMenu.php');
    
    });
    
    
   
    
    $('#btnActualizar').click(function(){
     
        
      v_nombre = $('#inputNombre').val();
      v_form = $('#inputPagina').val();
      v_padre = $('#selectPadre').val();
      v_id = $('#inputId').val();
      
      
  //alert( v_nombre + '' + v_form + ' ' + v_padre + ' ' + v_id);
  
      
      
     v_url = "ajaxCrearMenu.php?opcion=actualizarMenu&nombre=" + v_nombre +  "&form=" + v_form + "&padre=" + v_padre + "&id=" + v_id ;
     
  //alert(v_url);
     
      
      $('#formMuestraMenu').load(v_url,function(){
		   $('#contenido').load('crearMenu.php');
		  
	  });
     
   
    });
   
    
    $('.borrarMenu').click(function(){
    
     var v_id = $(this).closest('tr').find('td:first').text()
    
     v_url = "ajaxCrearMenu.php?opcion=borrarMenu&id=" + v_id;
     //alert(v_url);
   
     
     $('#formMuestraMenu').load(v_url);
     $('#contenido').load('crearMenu.php');
    
    });
    
    
    $('.editarMenu').click(function(){
     var v_id = $(this).closest('tr').find('td:first').text();
     var v_nombre = $(this).closest('tr').find('td:eq(1)').text();
     var v_form = $(this).closest('tr').find('td:eq(2)').text();
     var v_padre = $(this).closest('tr').find('td:eq(3)').text();
    
    // alert(v_id + "  "+ v_nombre + "  " + v_form + "  " + v_padre);
     
    
     $('#inputNombre').val(v_nombre);
     $('#inputPagina').val(v_form);
     $('#selectPadre').val(v_padre);
     $('#inputId').val(v_id);
 
     $('#agregaId').text('Id: ' + v_id);

    
    $('#btnGuardar').hide();
    $('#btnActualizar').show();
	
  

    
    });
    
</script>
