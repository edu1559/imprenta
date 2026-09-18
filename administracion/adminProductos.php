  <?php
   // Conectar a la base de datos

        include_once ('../conexion.php');
        $conn = conectar();

    // Crear un array de la tabla menu con los campos padres: $padres[id]= nombre;

        $productos = array();
        $sql = "select id,nombre,foto,caracteristicas,color,enlace,orden
                from productos
                where idCategoria =1";
        $result = mysqli_query($conn,$sql);

        while($myrow = mysqli_fetch_row($result)){
            $productos[$myrow[0]] = array(
            "nombre" => $myrow[1],
            "foto" => $myrow[2],
            "caracteristicas" => $myrow[3],
            "color" => $myrow[4],
            "enlace" => $myrow[5],
            "orden" => $myrow[6]
        );
        };

    //hago un array para las categorias

        $categoriaProductos = array();
        $sql = "select id,nombre
                from categoriaProductos";
        $result = mysqli_query($conn,$sql);

        while($myrow = mysqli_fetch_row($result)){
         $categoriaProductos[$myrow[0]]=$myrow[1];
        };

    // si viene una categoriaProductos seleccionada

        if(isset($_GET['scategoriaProductos'])){
            $scategoriaProductos = $_GET['scategoriaProductos'];
        }else{
            $scategoriaProductos = 1;
        };


 ?>

 <!--titulo principal-->

	<div class="container-fluid m-3 p-3  text-center justify-content h3">
		 Administrar Página de Productos  <i class="bi bi-terminal color-secondary fs2 p-3"></i>
	</div><!-- fin container titulo-->


 <!-- botón agregar menu  #btnAgregarProductos-->

	 <div class="container center">
		<button type="button" class="btn btn-success" id="btnAgregarProducto" style="align:center;width:150px">
			Agregar Producto
		</button>
	 </div>


 <!-- container con dos paneles izquierda seleccion y derecha edición -->
	<div class="row " id="formMuestraProductos">

<!-- panelSeleccion -->

    <div class="col-sm-5 p-3 m-3 bg-light rounded border" id="panelSeleccion">

    <form>

            <select  id="filtroCategoriaProductos" class="form-select text-black" style="width:300px">
                    <?php
                            foreach ($categoriaProductos as $clave => $valor){
                                    echo "<option value=\"".$clave. "\"";
                                            if($clave == $scategoriaProductos){
                                                    echo " selected ";
                                            };
                                    echo ">".$valor."</option>";
                            };
                    ?>
            </select>


    </form>
    <table class="table table-striped table-light table-hover mt-5 border rounded ">
    <thead>
		<tr class=" text-black table-success">
			<th>id</th>
			<th>nombre</th>

			<th>foto</th>
			<th>color</th>
			<th>Cat.</th>
			<th>borrar</th>
			<th>editar</th>

		</tr>
    </thead>

        <?php


            $sql = "select id,
                        nombre,
                        foto,
                        color,
                        orden,
                        enlace,
                        caracteristicas
                    from productos
                    where idCategoria=$scategoriaProductos";

            $result = mysqli_query($conn,$sql);



            while($myrow = mysqli_fetch_row($result)){
                echo "<tr>
						<td>". $myrow[0]. "</td>
						<td>". $myrow[1]. "</td>
						<td>". $myrow[2]. "</td>
						<td>". $myrow[3]. "</td>
						<td>". $myrow[4]. "</td>
						<td style=\"display:none\">". $myrow[5]. "</td>
						<td style=\"display:none\">". $myrow[6]. "</td>

						<td><span class=\"fa fa-trash borrarProducto\" style=\"cursor:pointer;font-size:25px\"></td>
						<td><span class=\"fa fa-pencil editarProducto\" style=\"cursor:pointer;font-size:25px\"></td>
                    </tr>";
                };

?>
  </table>
        <script>
                $('#filtroCategoriaProductos').change(function(){
                        v_scategoria = $('#filtroCategoriaProductos').val();
                        v_url = 'administracion/adminProductos.php?scategoriaProductos='+ v_scategoria;
                        alert(v_url);
                        $('#contenido').load(v_url);
                });
        </script>

</div><!-- panelSeleccion -->

<!-- panelEdicion -->


	<div class="col-sm-5 bg-light p-3 m-3 rounded border" id="panelEdicion">
		<div class="container p-3  rounded border bg-success" style="width:90%;">

		<table>
			<tr><td>
				<label for="nombreProducto" class="form-label text-white" >Nombre:</label>
			</td><td>
				<input type="text" class="form-input" id="inputNombre" placeholder="Nombre del Producto" name="nombreProducto" maxlength="30" style="width:200px">
				</td>
			</tr>

            <tr>
              <td>
                  <label for="inputFoto" class="form-label text-white">Foto:</label>
                  </td>
              <td>
                  <input type="text" class="form-control" id="inputFoto" name="imagenProducto" accept="image/png, image/jpeg, image/gif">
                  </td>
          </tr>

			<tr>
				<td>
				<label for="categoriaProductos" class="form-label  text-white">Categoría del Producto :</label>
				</td><td>
					<select id="selectorCategoriaProductos" name="categoriaProductos">
						<?php
							foreach($categoriaProductos as $clave => $valor){
									echo "<option value=\"". $clave. "\">". $valor. "</option>";
							};
						?>
					</select>
				</td>

			</tr>
			<tr>
				<td>
					<label for="color"  class="form-label text-white ">Color:</label>
				</td><td>
					<input type="text" class="form-input " id="inputColor" value="">
			   </td><td>
			</tr>

			<tr>
				<td>
					<label for="caracteristicas"  class="form-label text-white ">Características:</label>
				</td><td>
					<textarea type="text" class="form-input " id="inputCaracteristicas" rows="3" cols="50"></textarea>
			   </td><td>
		    </tr>

			<tr>
				<td>
					<label for="enlace"  class="form-label text-white ">Enlace:</label>
				</td><td>
					<input type="text" class="form-input " id="inputEnlace" value="">
			   </td><td>
			</tr>

			<tr>
				<td>
					<label for="orden"  class="form-label text-white ">Orden:</label>
				</td><td>
					<input type="text" class="form-input " id="inputOrden" value="" style="width:10%">
			    </td><td><input type="hidden" id="inputIdProducto" value=""></td>
			</tr>

			<tr>
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

     $('#btnAgregarProducto').click(function(){
        $('#panelEdicion').toggle();
     });


   $('#btnGuardar').click(function(){

      v_nombre = $('#inputNombre').val();
      v_foto = $('#inputFoto').val();
      v_color = $('#inputColor').val();
      v_idCategoria = $('#selectorCategoriaProductos').val();
      v_orden = $('#inputOrden').val();
      v_caracteristicas = $('#inputCaracteristicas').val();
      v_enlace = $('#inputEnlace').val();

      v_url = "administracion/ajaxAdminProductos.php?opcion=agregarProducto"
				+ "&nombre=" + v_nombre
				+ "&foto=" + v_foto
				+ "&color=" + v_color
				+ "&idCategoria=" + v_idCategoria
				+ "&caracteristicas=" + encodeURI(v_caracteristicas)
				+ "&enlace=" + v_enlace
				+ "&orden=" + v_orden ;
	// alert(v_url);




	$('#formMuestraProductos').load(v_url,function(){
            $('#contenido').load('administracion/adminProductos.php');
     });

	 });


    $('#btnActualizar').click(function(){


      v_nombre = $('#inputNombre').val();
      v_foto = $('#inputFoto').val();
      v_color = $('#inputColor').val();
      v_orden = $('#inputOrden').val();
      v_enlace = $('#inputEnlace').val();
      v_caracteristicas = $('#inputCaracteristicas').val();
        v_idCategoria = $('#selectorCategoriaProductos').val();
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


    $('.borrarProducto').click(function(){

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




    $('.editarProducto').click(function(){
		$('#panelEdicion').show();
     var v_id = $(this).closest('tr').find('td:first').text();
     var v_nombre = $(this).closest('tr').find('td:eq(1)').text();
     var v_foto = $(this).closest('tr').find('td:eq(2)').text();
     var v_color = $(this).closest('tr').find('td:eq(3)').text();
	 var v_orden = $(this).closest('tr').find('td:eq(4)').text();
	 var v_enlace = $(this).closest('tr').find('td:eq(5)').text();
	 var v_caracteristicas = $(this).closest('tr').find('td:eq(6)').text();
	 var v_idCategoria = $(this).closest('tr').find('td:eq(3)').text();


	// alert(v_id + "  "+ v_nombre + "  " + v_foto + "  " + v_color + " "+ v_orden + " " + v_enlace + " " + v_caracteristicas);

      $(this).css('color', 'green');

     $('#inputNombre').val(v_nombre);
     $('#inputFoto').val(v_foto);
	 $('#inputColor').val(v_color);
	 $('#inputOrden').val(v_orden);
	 $('#inputEnlace').val(v_enlace);
	 $('#inputCaracteristicas').val(v_caracteristicas);
     $('#selectCategoriaProductos').val(v_idCategoria);
     $('#inputIdProducto').val(v_id);



    $('#btnGuardar').hide();
    $('#btnActualizar').show();



    });




</script>
