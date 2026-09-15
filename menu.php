
  <?php
//conectar a la base de datos                                          
		 include_once ('conexion.php');
		 $conn = conectar();
	   
// crear un array de la tabla menu con los campos padres: $padres[id]= nombre;
		 
		 $padres = array();
		 $sql = "select id,nombre
			     from menu 
			     where padre is null";
	     $result = mysqli_query($conn,$sql);
		 
		 while($myrow = mysqli_fetch_row($result)){	
			$padres[$myrow[0]] = $myrow[1];
		 };
	    
// print_r($padres);  
// Pongo un valor por defecto  llamo $smenu al menu seleccionado
		
		if(isset($_GET['smenu'])){
			$smenu = $_GET['smenu'];
		}else{
			$smenu = key($padres); //seleciona la clave del primer elemento (dónde esté el puntero interno)
		};
       echo $smenu;                
 ?> 
 
 <!--titulo principal-->
 
	<div class="container-fluid m-3 p-3  text-center justify-content">
		<h3> Creación del Menú<span class="fa fa-tasks" style="color:gray; width:100px"></h3>
	</div><!-- fin container titulo-->
 
 
 <!-- botón agregar menu  #btnAgregarMenu-->
 
	 <div class="container center">
		<button type="button" class="btn btn-primary" id="btnAgregarMenu" style="align:center;width:150px">
			Agregar Menú
		</button>
	 </div>
 
 
 <!-- container con dos paneles izquierda seleccion y derecha edición -->
	<div class="row " id="formMuestraMenu">
	
<!-- panelSeleccion -->
	
    <div class="col-sm-5 p-3 m-3 bg-light rounded border" id="panelSeleccion">
    
    <table class="table table-striped table-light table-hover mt-5 border rounded ">
    <thead>
		<tr class=" text-black table-primary">
			<th>id</th>
			<th>nombre</th>
			<th>pagina</th>
			<th>
			<select  id="filtroPadre" class="text-black">
				<?php	
					foreach ($padres as $clave => $valor){
						echo "<option value=\"".$clave. "\"";
							if($clave == $smenu){
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
            
			
            $sql = "select id,nombre,pagina, padre
                    from menu
                    where padre = $smenu";
            			 
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
				v_smenu = $('#filtroPadre').val();
				v_url = 'menu.php?smenu='+ v_smenu;
				$('#contenido').load(v_url);
			});
		</script>
			
</div><!-- panelSeleccion -->
        
<!-- panelEdicion -->

        
        <div class="col-sm-5 bg-light p-3 m-3 rounded border" id="panelEdicion">
             
			 <div class="container p-3  rounded border bg-primary" style="width:500px;"> 
			 
                   
                    <div class="mb-3 mt-3">
                        <label for="nombre" class="form-label text-white" >Nombre:</label>
                        <input type="text" class="form-control" id="inputNombre" placeholder="Nombre de la Opción" name="nombreOpcionMenu" maxlength="30" style="width:200px">
                    </div>
                    <div class="mb-3">
                        <label for="pagina" class="form-label  text-white">Página:</label>
                        <input type="text" class="form-control" id="inputPagina" placeholder="Ingrese el  archivo" name="pagina" maxlength="50" style="width:400px">
                    </div>
                    <div class="mb-3">
                        <label for="padre" class="form-label  text-white">padre:</label><br>
                                           
                        <select id="selectPadre" name="padre">
                        <?php
                            foreach($padres as $clave => $valor){
                            
                            echo "<option value=\"". $clave. "\">". $valor. "</option>";
                            
                            };
                        ?>
                        
                        </select>
                        
                    </div>
                    <div class="mb-3">
                        <label id="agregaId" class="form-label  ">Id:</label>
                        <input type="hidden" id="inputId" value="">
                    </div>
                   
                    <button type="submit" id="btnGuardar" class="btn btn-warning">Guardar</button>
                    <button type="submit" id="btnActualizar" class="btn btn-warning">Actualizar</button>
                    

            </div>
      </div>


</div><!-- container titulo-->
<script>


     $('#panelEdicion').hide();
     $('#btnActualizar').hide();
    
     $('#btnAgregarMenu').click(function(){
        $("#panelEdicion").toggle();
     });
	
	
   $('#btnGuardar').click(function(){
     
      v_nombre = $('#inputNombre').val();
      v_pagina = $('#inputPagina').val();
      v_spadre = $('#selectPadre').val();
      v_smenu = $('#filtroPadre').val();
      
      v_url = "ajaxMenu.php?opcion=agregarMenu&nombre=" + v_nombre +  "&pagina=" + v_pagina + "&padre=" + v_spadre;
     
		//alert(v_url);
        
      $('#formMuestraMenu').load(v_url);
      
      $('#contenido').load('menu.php?smenu='+ v_smenu);
   
    
    });
    
    
   
    
    $('#btnActualizar').click(function(){
     
        
      v_nombre = $('#inputNombre').val();
      v_pagina = $('#inputPagina').val();
      v_padre = $('#selectPadre').val();
      v_id = $('#inputId').val();
      v_smenu = $('#filtroPadre').val();
      
   
      
      
   //alert( v_nombre + '' + v_pagina + ' ' + v_padre + ' ' + v_id);
  
      
      
     v_url = "ajaxMenu.php?opcion=actualizarMenu&nombre=" + v_nombre +  "&pagina=" + v_pagina + "&padre=" + v_padre + "&id=" + v_id ;
     
  //alert(v_url);
     
      
      $('#formMuestraMenu').load(v_url,function(){
            $('#contenido').load('menu.php?smenu='+ v_smenu);
		  
      });
     
   
    });
   
    
    
    $('.borrarMenu').click(function(){
 
    // Obtenemos el ID y el nombre del menú a eliminar
        $(this).css('color', 'red');
        var v_id = $(this).closest('tr').find('td:first').text();
        var v_nombreMenu = $(this).closest('tr').find('td:eq(1)').text();
        
       v_smenu = $('#filtroPadre').val();
  
 
  
  // Construimos la URL para la acción de borrado
    
        var v_url = "ajaxMenu.php?opcion=borrarMenu&id=" + v_id;

  // Iluminamos el renglón del registro seleccionado
  
       $(this).closest('tr').addClass('filaSeleccionada');

  // Mostramos la ventana de confirmación con el nombre del menú
  
        var mensaje = "¿Estás seguro de que deseas eliminar el menú \"" + v_nombreMenu + "\"?";
            
            if (confirm(mensaje)) {
    
// Si el usuario acepta, cargamos la URL para eliminar el archivo
            $('#formMuestraMenu').load(v_url);
    
// Y luego actualizamos el contenido con la lista de menús actualizada
            $('#contenido').load('menu.php?smenu='+ v_smenu);
                
            } else {
            
// Si el usuario cancela, quitamos la clase de selección
                    $(this).closest('tr').removeClass('filaSeleccionada');
            }
            

});
    

    
    
    $('.editarMenu').click(function(){
		$('#panelEdicion').show();
     var v_id = $(this).closest('tr').find('td:first').text();
     var v_nombre = $(this).closest('tr').find('td:eq(1)').text();
     var v_form = $(this).closest('tr').find('td:eq(2)').text();
     var v_padre = $(this).closest('tr').find('td:eq(3)').text();
    
    // alert(v_id + "  "+ v_nombre + "  " + v_form + "  " + v_padre);
      $(this).css('color', 'green');
    
     $('#inputNombre').val(v_nombre);
     $('#inputPagina').val(v_form);
     $('#selectPadre').val(v_padre);
     $('#inputId').val(v_id);
 
     $('#agregaId').text('Id: ' + v_id);

    
    $('#btnGuardar').hide();
    $('#btnActualizar').show();
	
  

    
    });
    
</script>
