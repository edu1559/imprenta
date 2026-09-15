  <?php
   // Conectar a la base de datos

        include_once ('../conexion.php');
        $conn = conectar();

     //hago un array para las categorias
        
        $categorias = array();
        $sql = "select id,nombre
                from categoriaProductos";
        $result = mysqli_query($conn,$sql);
        
        while($myrow = mysqli_fetch_assoc($result)){
         $categoria[$myrow['id']]=$myrow['nombre'];
        };
        if(isset($_GET['idCategoria'])){
            $idCategoria=$_GET['idCategoria'];
        }else{
            $idCategoria = 1;
        };


    // Crear un array $productos[nombre,foto,caracteristicas, color,enlace,orden]

        $productos = array();
        $sql = "select id,idCategoria,nombre,foto,caracteristicas,color,enlace,orden
                from productos 
                where idCategoria = $idCategoria";
        $result = mysqli_query($conn,$sql);

        while($myrow = mysqli_fetch_assoc($result)){
            $productos[$myrow['id']] = array(
            "idCategoria" => $myrow['idCategoria'],
            "nombre" => $myrow['nombre'],
            "foto" => $myrow['foto'],
            "caracteristicas" => $myrow['caracterisiticas'],
            "color" => $myrow['color'],
            "enlace" => $myrow['enlace'],
            "orden" => $myrow['orden']);
        };       
        
   //print_r($productos);

   

 ?> 
 
 <!--titulo principal-->
 
	<div class="container-fluid m-3 p-3  text-center justify-content h3">
		 Administrar Productos  <i class="bi bi-binoculars text-success fs2 p-3"></i>
	</div><!-- fin container titulo-->
 
 
 <!-- botón agregar menu  #btnAgregarProductos y selector de Categoria-->
 
	<div class="d-flex align-items-center gap-3">
        <button type="button" class="btn btn-outline-success ms-3" id="btnAgregarProducto">Agregar</button>
        <select class="form-select w-auto outline-success text-success" id="selCategoria">
            <?php 
            foreach ($categoria as $clave => $valor) {
                echo "<option value='$clave'"; 
                     if ($clave == $idCategoria){
                        echo " selected " ;
                     };        
                echo ">$valor</option>";
            }
            ?>
        </select>
    </div>
 
 <!-- container con dos paneles izquierda seleccion y derecha edición -->
	<div class="row border m-2 p-2" id="formMuestraProductos">
	
<!-- panelSeleccion -->
	
    <div class="col-sm-5 p-3 m-3 bg-light rounded border" id="panelSeleccion">
    
  
    <table class="table table-striped table-light table-hover mt-5 border rounded ">
    <thead>
		<tr class=" text-black table-success">
			<th>id</th>
            <th>Cat</th>
			<th>nombre</th>
			<th>foto</th>
			<th>color</th>  
			<th>Orden</th>
			<th>borrar</th>
			<th>editar</th>
			
		</tr>
    </thead>
    
        <?php 
            
			
            $sql = "select id,
                        idCategoria,
                        nombre,
                        foto,
                        color,
                        orden,
                        enlace
                    from productos 
                    where idCategoria = $idCategoria";
                
            $result = mysqli_query($conn,$sql);
            
           
            
            while($myrow = mysqli_fetch_assoc($result)){
                echo "<tr>
						<td class=\"id\">". $myrow['id']. "</td>
                        <td class=\"idCategoria\">". $myrow['idCategoria']. "</td>
						<td class=\"nombre\">". $myrow['nombre']. "</td>
						<td class=\"foto\">". $myrow['foto']. "</td>
						<td class=\"color\">". $myrow['color']. "</td>
						<td class=\"orden\">". $myrow['orden']. "</td>
						<td style=\"display:none\">". $myrow['enlace']. "</td>
						<td style=\"display:none\">". $myrow['caracteristicas']. "</td>
					
                        <td ><i class=\" bi bi-trash fs-4 text-danger btnBorrarProducto \" style=\"cursor:pointer\"></i></td>
                        <td ><i class=\" bi bi-pencil fs-4 text-success btnEditarProducto \" style=\"cursor:pointer\"></i></td>      
                
                        </tr>";
                };		
                            
?>
  </table>	
 
     	
</div><!-- fin panelSeleccion -->

 
<!-- panelEdicion -->
<div class="col-sm-5 bg-light p-3 m-3 rounded border" id="panelEdicion">
    <form class="row g-3">
        <div class="h3 text-success text-center " id="nombreProducto"></div>
        <div class="col-md-6">
            <label for="inputNombre" class="form-label">Nombre:</label>
            <input type="text" class="form-control" id="inputNombre" placeholder="Nombre del Producto" name="nombreProducto" maxlength="30">
        </div>

        <div class="col-md-6">
            <label for="inputFoto" class="form-label">Foto (URL):</label>
            <input type="text" class="form-control" id="inputFoto" name="imagenProducto">
        </div>

        <div class="col-6">
            <label for="fotoProducto" class="form-label">foto:</label>
            <img src="" class="img-fluid rounded" id="fotoProducto" alt="Previsualización de la foto del producto" style="width: 150px; height: 150px;">
        </div>

        <div class="col-6">
            <label for="archivo" class="form-label">Nueva Imagen:</label>
            <form id="formSubirArchivo" enctype="multipart/form-data" method="POST" action="administracion/ajaxSubirArchivo.php">
                <div class="input-group">
                    <input type="file" name="archivo" id="archivo" class="form-control">
                    <button type="submit" class="btn btn-primary" id="btnSubir">Subir</button>
                </div>
            </form>
        </div>
         <div class="col-12">
            <label for="inputCaracteristicas" class="form-label">Características:</label>
            <textarea class="form-control" id="inputCaracteristicas" rows="3"></textarea>
        </div>
     
        <div class="row">
        <div class="col-md-3">
            <label for="inputColor" class="form-label">Color:</label>
            <input type="text" class="form-control" id="inputColor" value="">
        </div>
      

        <div class="col-md-3">
            <label for="inputEnlace" class="form-label">Enlace:</label>
            <input type="text" class="form-control" id="inputEnlace" value="">
        </div>

        <div class="col-md-3">
            <label for="inputOrden" class="form-label">Orden:</label>
            <input type="number" class="form-control" id="inputOrden" value="" style="width: 100px;">
        </div>
</div>
        <div class="col-12 text-end">
            <input type="hidden" id="inputIdProducto" value="">
            <input type="hidden" id="inputIdCategoria" value="">
            <button type="button" id="btnGuardar" class="btn btn-warning me-2">Guardar</button>
            <button type="button" id="btnActualizar" class="btn btn-warning">Actualizar</button>
        </div>
    </form>
</div>

<script>
 
    $('#selCategoria').change(function(){
       v_Categoria = $(this).val();
       v_url = 'administracion/adminProductos.php?idCategoria=' + v_Categoria;
       $('#contenido').load(v_url);
    });


     $('#panelEdicion').hide();
     $('#btnActualizar').hide();
    
     $('#btnAgregarProducto').click(function(){
        $('#panelEdicion').toggle();
     });
	
	
   $('#btnGuardar').click(function(){
     
     
      v_nombre = $('#inputNombre').val();
      v_nombre = encodeURI(v_nombre);
      v_foto = $('#inputFoto').val();
      v_color = $('#inputColor').val();
      v_idCategoria = $('#selectorCategoriaProductos').val();
      v_orden = $('#inputOrden').val();
      v_caracteristicas = $('#inputCaracteristicas').val();
      v_enlace = $('#inputEnlace').val();
       v_idCategoria = $('#selCategoria').val();
      
      v_url = "administracion/ajaxAdminProductos.php?opcion=agregarProducto"
				+ "&nombre=" + v_nombre 
				+ "&foto=" + v_foto 
				+ "&color=" + v_color 
				+ "&idCategoria=" + v_idCategoria 
				+ "&caracteristicas=" + encodeURI(v_caracteristicas) 
				+ "&enlace=" + v_enlace 
				+ "&orden=" + v_orden 
                + "&idCategoria" + v_idCategoria; 
	alert(v_url);
    
   
  
  
	$('#formMuestraProductos').load(v_url,function(){
            $('#contenido').load('administracion/adminProductos.php');	  
     });

	 });
  
    
    $('#btnActualizar').click(function(){
     
      v_idCategoria =$('#idCategoria').val();  
      v_nombre = $('#inputNombre').val();
      v_foto = $('#inputFoto').val();
      v_color = $('#inputColor').val();
      v_orden = $('#inputOrden').val();
      v_enlace = $('#inputEnlace').val();
      v_caracteristicas = $('#inputCaracteristicas').val();
        v_idCategoria = $('#inputIdCategoria').val();	
        v_idProducto = $('#inputIdProducto').val();
	
//	 alert( v_nombre + "  " + v_foto + "  " + v_color + " "+ v_orden + " " + v_enlace + " " + v_caracteristicas +  " " + v_idCategoria +  " " + v_idProducto);

	
   
     v_url = "administracion/ajaxAdminProductos.php?opcion=actualizarProducto" 
				+ "&nombre=" + encodeURI(v_nombre)	 
				+ "&foto=" + v_foto 
				+ "&color=" + v_color 
				+ "&orden=" + v_orden 
				+ "&enlace=" + v_enlace 
				+ "&caracteristicas=" + encodeURI(v_caracteristicas)
				+ "&idCategoria=" + v_idCategoria 
				+ "&idProducto=" + v_idProducto;
     
	alert(v_url);
 
	
	
  
  
      $('#formMuestraProductos').load(v_url,function(){
            $('#contenido').load('administracion/adminProductos.php');
	  });
		  
  });
    
    
    $('.btnBorrarProducto').click(function(){
 
    // Iluminamos el renglón del registro seleccionado
  
     $(this).css('color', 'red');
 
 
 
    // Obtenemos el ID y el nombre del menú a eliminar
     
        var v_id = $(this).closest('tr').find('td:first').text();
        var v_nombreProducto = $(this).closest('tr').find('td:eq(1)').text();
        
       v_smenu = $('#filtroPadre').val();
  
 
  
  // Construimos la URL para la acción de borrado
    
        var v_url = "administracion/ajaxAdminProductos.php?opcion=borrarProducto&id=" + v_id;

     //   alert (v_url);
  
     

  // Mostramos la ventana de confirmación con el nombre del menú
  
        var mensaje = "¿Estás seguro de que deseas eliminar el producto \"" + v_nombreProducto + "\"?";
            
            if (confirm(mensaje)) {
    
// Si el usuario acepta, cargamos la URL para eliminar el archivo
            $('#formMuestraProductos').load(v_url);
    
// Y luego actualizamos el contenido con la lista de menús actualizada
            $('#contenido').load('adminProductos.php');
                
            } else {
				
				
            
// Si el usuario cancela, quitamos la clase de selección
                    $(this).css('color', 'black');
            }
             $('#formMuestraProductos').load(v_url,function(){
            $('#contenido').load('administracion/adminProductos.php');
	  });

});
    
 
   
    
    $('.btnEditarProducto').click(function(){
		$('#panelEdicion').show();
     var v_id = $(this).closest('tr').find('.id').text();
     var v_nombre = $(this).closest('tr').find('.nombre').text();
     var v_foto = $(this).closest('tr').find('.foto').text();
     var v_color = $(this).closest('tr').find('.color').text();
	 var v_orden = $(this).closest('tr').find('.orden').text();
	 var v_enlace = $(this).closest('tr').find('.enlace').text();
	 var v_caracteristicas = $(this).closest('tr').find('.caracteristicas').text();
	 var v_idCategoria = $(this).closest('tr').find('.idCategoria').text();
     
	 
	//alert(v_id + "  "+ v_nombre + "  " + v_foto + "  " + v_color + " "+ v_orden + " " + v_enlace + " " + v_caracteristicas);
   
      $(this).css('color', 'green');
    $('#nombreProducto').html(v_nombre);
    
     $('#inputNombre').val(v_nombre);	
     $('#inputFoto').val(v_foto);
     $('#fotoProducto').attr('src',v_foto);
	 $('#inputColor').val(v_color);
	 $('#inputOrden').val(v_orden);
	 $('#inputEnlace').val(v_enlace);
	 $('#inputCaracteristicas').val(v_caracteristicas);
     $('#selectCategoriaProductos').val(v_idCategoria);
     $('#inputIdProducto').val(v_id);
     $('#inputIdCategoria').val(v_idCategoria);
    
    
    $('#btnGuardar').hide();
    $('#btnActualizar').show();
	

    
    });
    
     

    
</script>