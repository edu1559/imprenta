<?php
   // Conectar a la base de datos

        include_once ('../conexion.php');
        $conn = conectar();

    // Crear un array de la tabla menu con los campos padres: $padres[id]= nombre;

        $precios = array();
        $sql = "select id,idProducto,descripcion,cantidad,gramaje,tamanio,fecha,precio 
                from precios";
        
        $result = mysqli_query($conn,$sql);

        while($myrow = mysqli_fetch_row($result)){
            $precios[$myrow[0]] = array(
            "idProducto" => $myrow[1],
            "descripcion" => $myrow[2],
            "cantidad" => $myrow[3],
            "gramaje" => $myrow[4],
            "tamaño" => $myrow[5],
            "fecha" => $myrow[6],
            "precio" => $myrow[7]
        );
        };       

      
    //hago un array para los productos $productos[id]=nombre;
        
        $productos = array();
        $sql = "select id,nombre
                from productos";
        $result = mysqli_query($conn,$sql);
        
        while($myrow = mysqli_fetch_row($result)){
         $productos[$myrow[0]]=$myrow[1];
        };
        
    // si viene una categoriaProductos seleccionada
    
        if(isset($_GET['sproducto'])){
            $sproducto = $_GET['sproducto'];
        }else{
            $sproducto = 1;
        };
        
  
 ?> 
 
 <!--titulo principal-->
 
	<div class="container-fluid m-3 p-3  text-center justify-content">
		<h3> Administrar Página de Precios<span class="fa fa-tasks" style="color:gray; width:100px"></h3>
	</div><!-- fin container titulo-->
 
 
 <!-- botón agregar menu  #btnAgregarPrecios-->
 
	 <div class="container center">
		<button type="button" class="btn btn-danger" id="btnAgregarPrecio" style="align:center;width:150px">
			Agregar Precio
		</button>
	 </div>
 
 
 <!-- container con dos paneles izquierda seleccion y derecha edición -->
	<div class="row " id="formMuestraPrecios">
	
<!-- panelSeleccion -->
	
    <div class="col-sm-5 p-3 m-3 bg-light rounded border" id="panelSeleccion">
    
    <form>
      
            <select  id="filtroProductos" class="form-select text-black" style="width:300px">
                    <?php	
                            foreach ($productos as $clave => $valor){
                                    echo "<option value=\"".$clave. "\"";
                                            if($clave == $sproducto){
                                                    echo " selected ";
                                            };
                                    echo ">".$valor."</option>";
                            };
                    ?>
            </select>
		 
    
    </form>
    <table class="table table-striped table-hover mt-5 border rounded ">
    <thead>
		<tr class=" text-black table-danger">
			<th>id</th>
			<th>producto</th>
			<th>descripcion</th>
			<th>cantidad</th>
			<th>gramaje</th>  
			<th>tamanio</th>
			<th>precio</th>
			<th>borrar</th>
			<th>editar</th>
			
		</tr>
    </thead>
    
        <?php 
            
			
            $sql = "select 
                        pre.id,
                        pro.nombre,
                        pre.descripcion,
                        pre.cantidad,
                        pre.gramaje,
                        pre.tamanio,
                        pre.precio
                    from precios pre inner join productos pro
                       on pre.idProducto = pro.id
                    where idProducto=$sproducto";
                
            $result = mysqli_query($conn,$sql);
            
           
            
            while($myrow = mysqli_fetch_row($result)){
                echo "<tr>
                            <td>". $myrow[0]. "</td>
                            <td>". $myrow[1]. "</td>
                            <td>". $myrow[2]. "</td>
                            <td>". $myrow[3]. "</td>
                            <td>". $myrow[4]. "</td>
                            <td>". $myrow[5]. "</td>
                            <td>". $myrow[6]. "</td>
                    
                            <td><span class=\"fa fa-trash borrarPrecio\" style=\"cursor:pointer;font-size:25px\"></td>
                            <td><span class=\"fa fa-pencil editarPrecio\" style=\"cursor:pointer;font-size:25px\"></td>               
                    </tr>";
                };		
                            
?>
  </table>	
        <script>
                $('#FiltroProductos').change(function(){
                        v_scategoria = $('#filtroProdctos').val();
                        v_url = 'administracion/adminPrecios.php?scategoria='+ v_scategoria;
                        alert(v_url);
                        $('#contenido').load(v_url);
                });
        </script>
			
</div><!-- panelSeleccion -->
        
<!-- panelEdicion -->

	
	<div class="col-sm-5 bg-light p-3 m-3 rounded border" id="panelEdicion">
		<div class="container p-3  rounded border bg-danger" style="width:90%;"> 
		 
		<table>
			<tr>
				<td>
				<label for="Producto" class="form-label  text-white">Producto:</label>
				</td><td>
					<select id="selectIdProducto" name="Producto">
                                            <?php
                                                    foreach ($productos as $clave => $valor){
                                                        echo "<option value=\"".$clave. "\"";
                                                                if($clave == $sproducto){
                                                                        echo " selected ";
                                                                };
                                                        echo ">".$valor."</option>";
                                                    };
                                            ?>	
					</select>
				</td>
			</tr><tr>
                            <td>
				<label for="descripcion" class="form-label text-white" >Descripción:</label>
                                </td><td>
				<input type="text" class="form-input" id="inputDescripcion" placeholder="Descripción del  Precio" name="descripcion" maxlength="50" style="width:200px">
                            </td>     
			</tr><tr>
				<td>
				<label for="cantidad" class="form-label  text-white">Cantidad:</label>
				</td><td>   
				<input type="text" class="form-input" id="inputCantidad" placeholder="Ingrese la Cantidad" name="cantidad" maxlength="10" style="width:100px">
				</td>
			</tr><tr>
				<td>
					<label for="gramaje"  class="form-label text-white ">Gramaje:</label>
				</td><td>   
					<input type="text" class="form-input " id="inputGramaje" value="" />
			   </td><td>
			</tr><tr>
				<td>   
					<label for="tamanio"  class="form-label text-white ">Tamaño:</label>
				</td><td>   
					<input type="text" class="form-input " id="inputTamanio" rows="3" cols="50">
			   </td>
		       </tr><tr>
                            <td>   
					<label for="precio"  class="form-label text-white ">Precio:</label>
				</td><td>  
					<input type="text" class="form-input " id="inputPrecio" value="" style="width:10%:font-size:16pt">
			    </td><td><input type="hidden" id="inputIdPrecio" value="""></td> 
			</tr><tr>
			   <td></td>
			   <td>
					<button type="submit" id="btnGuardar" class="btn btn-warning">Guardar</button>
					<button type="submit" id="btnActualizar" class="btn btn-warning">Actualizar</button>
				</td>
                        </tr>
			
		</table>

		</div>
  </div>



<script>


     $('#panelEdicion').hide();
     $('#btnActualizar').hide();
    
     $('#btnAgregarPrecio').click(function(){
        $('#panelEdicion').toggle();
     });
	
	
	
	
   $('#filtroProductos').change(function(){
        v_sproducto = $(this).val();
        alert (v_sproducto);
        $('#contenido').load('administracion/adminPrecios.php?sproducto=' + v_sproducto);
   });
	
	
   $('#btnGuardar').click(function(){
     
      v_idProducto = $('#selectIdProducto').val();
      v_descripcion = $('#inputDescripcion').val();
      v_cantidad = $('#inputCantidad').val();
      v_gramaje = $('#inputGramaje').val();
      v_tamanio = $('#inputTamanio').val();
      v_precio = $('#inputPrecio').val();
    
      
      v_url = "imprenta/ajaxAdminPrecios.php?opcion=agregarPrecio"
                + "&idProducto=" + v_idProducto 
                + "&descripcion=" + encodeURI(v_descripcion) 
                + "&cantidad=" + encodeURI(v_cantidad )
                + "&gramaje=" + encodeURI(v_gramaje)
                + "&tamanio=" + encodeURI(v_tamanio)
                + "&precio=" + v_precio; 
	//alert(v_url);
    
   
  
  
	$('#formMuestraPrecios').load(v_url,function(){
            $('#contenido').load('administracion/adminPrecios.php');	  
        });
     });
  
    
    $('#btnActualizar').click(function(){
     
        
      v_idProducto = $('#selectIdProducto').val();
      v_descripcion = $('#inputDescripcion').val();
      v_cantidad = $('#inputCantidad').val();
      v_gramaje = $('#inputGramaje').val();
      v_tamanio = $('#inputTamanio').val();
      v_precio = $('#inputPrecio').val();
	  v_idPrecio = $('#inputIdPrecio').val();
	
	//alert( v_idProducto + "  " + v_descripcion + "  " + v_cantidad + " "+ v_gramaje + " " + v_tamanio + " " + v_precio);

	
   
     v_url = "administracion/ajaxAdminPrecios.php?opcion=actualizarPrecio" 
                + "&idProducto=" + v_idProducto 
                + "&descripcion=" + encodeURI(v_descripcion) 
                + "&cantidad=" + v_cantidad 
                + "&gramaje=" + v_gramaje 
                + "&tamanio=" + v_tamanio 
                + "&precio=" + v_precio
                + "&idPrecio=" + v_idPrecio;
     
	// alert(v_url);
 
	
	
  
  
      $('#formMuestraPrecios').load(v_url,function(){
            $('#contenido').load('administracion/adminPrecios.php');
      });
		  
   });
    
    
    
    $('.borrarPrecio').click(function(){
 
        // Iluminamos el renglón del registro seleccionado
        $(this).css('color', 'red');
 
        // Obtenemos el ID y el nombre del precio a eliminar
     
        var v_id = $(this).closest('tr').find('td:first').text();
        var v_nomProducto = $(this).closest('tr').find('td:eq(1)').text();
        var v_nomDescripcion = $(this).closest('tr').find('td:eq(2)').text();
        

        // Construimos la URL para la acción de borrado   
        var v_url = "administracion/ajaxAdminPrecios.php?opcion=borrarPrecio&id=" + v_id;
        //  alert (v_url);
  
     
        // Mostramos la ventana de confirmación con el nombre del menú
        var mensaje = "¿Estás seguro de que deseas eliminar el producto \"" + v_nomProducto + " " + v_nomDescripcion + "\"?";
            
        if (confirm(mensaje)) {
    
                // Si el usuario acepta, cargamos la URL para eliminar el archivo
            $('#formMuestraPrecios').load(v_url);
    
                // Y luego actualizamos el contenido con la lista de menús actualizada
            $('#contenido').load('adminPrecios.php');
                
            } else {
				
                // Si el usuario cancela, quitamos la clase de selección
                $(this).css('color', 'black');
            }
            
          $('#formMuestraPrecios').load(v_url,function(){
            $('#contenido').load('administracion/adminPrecios.php');
	  });

    });
    
 
   
    
    $('.editarPrecio').click(function(){
		
        $('#panelEdicion').show();
        var v_idPrecio = $(this).closest('tr').find('td:first').text();
        var v_idProducto = $('#filtroProductos').val();
        var v_descripcion = $(this).closest('tr').find('td:eq(2)').text();
        var v_cantidad = $(this).closest('tr').find('td:eq(3)').text();
        var v_gramaje = $(this).closest('tr').find('td:eq(4)').text();
        var v_tamanio = $(this).closest('tr').find('td:eq(5)').text();
        var v_precio = $(this).closest('tr').find('td:eq(6)').text();
      
	 
	// alert(v_idPrecio + "  "+ v_idProducto + "  " + v_descripcion + "  " + v_cantidad + " "+ v_gramaje + " " + v_tamanio + " " + v_precio);
   
      $(this).css('color', 'green');
   
     $('#selectIdProducto').val(v_idProducto);	
     $('#inputDescripcion').val(v_descripcion);
     $('#inputCantidad').val(v_cantidad);
     $('#inputGramaje').val(v_gramaje);
     $('#inputTamanio').val(v_tamanio);
     $('#inputPrecio').val(v_precio);
     $('#inputIdPrecio').val(v_idPrecio);
   
    
    
    $('#btnGuardar').hide();
    $('#btnActualizar').show();
	

    
    
    });
    
     

    
</script>
