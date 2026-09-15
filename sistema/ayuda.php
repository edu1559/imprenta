<?php
      include_once ('../conexion.php');
      $conn = conectar();

 ?>
  
<!-- titulo--> 

 <div class="container text-center display-6">
    Ayudas y Glosario  <i class="bi bi-file-earmark-text h3 text-success" ></i>
</div>


<div class="container m-5 p-3">
	 
<?php
 
 /* array clases */

    $clases = array();
    $sql = "select id,nombre 
            from clases";
    $result = mysqli_query($conn,$sql);
    
    if($result){
            while($myrow = mysqli_fetch_assoc($result)){
                $clases[$myrow['id']] = $myrow['nombre'];
            };
    } else{
          echo "Error en la consulta: " . mysqli_error($conn);
    };
 



 
  /* array ayuda */ 
  $ayudas = array();

  // La consulta SQL está bien
  $sql = "select a.id, a.idClase, a.texto, c.nombre, c.clase 
          from ayuda a inner join clases c 
            on a.idClase = c.id";
  //echo $sql;
  $result = mysqli_query($conn, $sql);
  
  // Siempre es buena idea verificar si la consulta falló
  if (!$result) {
      // En un entorno real, manejarías el error de forma más robusta
      die("Error en la consulta SQL: " . mysqli_error($conn)); 
  };
  
  // Usar mysqli_fetch_assoc() para obtener claves asociativas
  while ($myrow = mysqli_fetch_assoc($result)) {
      // Usar los nombres de columna correctos como claves
      // La clave del array $ayudas será el 'id' de la tabla 'ayuda'
      $ayudas[$myrow['id']] = array( 
          "idClase" => $myrow['idClase'],
          "texto"   => $myrow['texto'], 
          "nombre"  => $myrow['nombre'], 
          "clase"   => $myrow['clase']
      );
  };
   //  print_r($ayudas);  
     
// Iterar y generar el HTML

foreach ($ayudas as $clave => $valor) {
    // Escapar los datos antes de insertarlos en HTML usando htmlspecialchars()
    $escaped_clave = htmlspecialchars($clave, ENT_QUOTES, 'UTF-8');
    $escaped_idClase = htmlspecialchars($valor['idClase'], ENT_QUOTES, 'UTF-8');
    $escaped_nombre = htmlspecialchars($valor['nombre'], ENT_QUOTES, 'UTF-8');
    $escaped_texto = htmlspecialchars($valor['texto'], ENT_QUOTES, 'UTF-8');
    $escaped_clase = htmlspecialchars($valor['clase'], ENT_QUOTES, 'UTF-8');
    
    // Usar la sintaxis con llaves {} para insertar variables de array en strings con comillas dobles
    // O usar concatenación (mostrado abajo como alternativa en comentarios)
    echo "<div id=\"{$escaped_clave}\" data-idClase=\"{$escaped_idClase}\" data-nombreClase=\"{$escaped_nombre}\" class=\"{$escaped_clase} texto\">{$escaped_texto}</div>\n";     
};  
?> 
</div>

<!-- Editor de textos -->
<button type="button" class="btn btn-primary ms-5" id="btnEditar">Editar</button>

<div class="container  bg-light m-5" id="editor" style="display:none">
    <div class="row">
        <div class="col-9">
            <textarea id="editorTexto" class="form-control" cols="150" rows="5"></textarea>
        </div>
    </div>
    <div class="row">
        <div class="col-3">
            <select id="selectClase" class="form-control">
            <?php 
                    foreach ($clases as $clave => $valor){
                    echo "<option value ='$clave'>$valor</option>";
                    };
            ?>
            </select>
        </div>
        <div class="col-1">
                 <input type="text" id="idParrafo" class="form-control" size="2" value="" disabled>
        </div>
        <div class="col-8">
            <div class="form-inline">
                
                <button class="btn btn-outline-danger" id="btnBorrar" >Borrar Párrafo</button>
                <button class="btn btn-outline-warning" id="btnModificar">Modificar Párrafo</button>
                <button class="btn btn-outline-primary" id="btnAgregar">Agregar</button>
            </div>
        </div>
    </div>
</div>
   

<script>
    
// Mostrar y ocultar el cuadro de edicion

        $('#btnEditar').click (function() {
            $('#editor').toggle();
        });

// Agregar 

$('#btnAgregar').click (function() {
            v_texto = $('#editorTexto').val();
            v_texto = encodeURI(v_texto);
            v_idClase = $('#selectClase').val();
    
            v_url = 'sistema/ajaxAyuda.php?opcion=guardar&texto=' + v_texto + '&idClase=' + v_idClase;
            alert(v_url);
            $.ajax({
            url: v_url,
            success: function(response) {
                // La base de datos ha sido actualizada, ahora podemos refrescar la pantalla
                v_url = 'sistema/ayuda.php';
                $('#contenido').load(v_url);
            },
            error: function(error) {
                // Manejo de errores si la operación de guardado falla
                $('#mensaje').text("Error al guardar datos:", error);
            }
            });
            
        });

// llevar al editor

$('.texto').dblclick(function() {
        // Obtener el texto y la clase del elemento seleccionado
        var texto = $(this).text();
        var clase = $(this).attr('class');
        var id = $(this).attr('id');
        var idClase = $(this).attr('data-idClase');
        
   // alert(texto + ' ' + clase + ' '+  id + ' ' + idClase);

        // Mostrar el editor y llenar los campos
    
        $('#editor').show();
        $('#editorTexto').val(texto);
        $('#selectClase').val(idClase);
        $('#idParrafo').val(id);
        
    
       // Ocultar el botón Agregar
       $('#btnAgregar').hide();
         
    });

        
    $('#btnModificar').click (function() {
            v_texto = $('#editorTexto').val();
            v_texto = encodeURI(v_texto);
            v_idClase = $('#selectClase').val();
            v_idParrafo= $('#idParrafo').val();
    
            v_url = 'sistema/ajaxAyuda.php?opcion=modificar&texto=' + v_texto + '&idClase=' + v_idClase + '&id=' + v_idParrafo;
            

            $.ajax({
            url: v_url,
            success: function(response) {
                // La base de datos ha sido actualizada, ahora podemos refrescar la pantalla
                v_url = 'sistema/editor.php';
                $('#contenido').load(v_url);
            },
            error: function(error) {
                // Manejo de errores si la operación de guardado falla
                $('#mensaje').text("Error al guardar datos:", error);
            }
            });

        });

        $('#btnBorrar').click(function() {
            v_texto = $('#editorTexto').val();
            v_texto = encodeURI(v_texto);
            v_idClase = $('#editorClase').val();
            v_idParrafo= $('#idParrafo').val();
    
            
            v_url = 'sistema/ajaxAyuda.php?opcion=borrar&id=' + v_idParrafo;

            $.ajax({
            url: v_url,
            success: function(response) {
                // La base de datos ha sido actualizada, ahora podemos refrescar la pantalla
                v_url = 'sistema/ayuda.php';
                $('#contenido').load(v_url);
            },
            error: function(error) {
                // Manejo de errores si la operación de guardado falla
                $('#mensaje').text("Error al guardar datos:", error);
            }
            });

            
        });

</script>