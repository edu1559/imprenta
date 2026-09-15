<?php
      include_once ('../conexion.php');
      $conn = conectar();

 ?>
 
 
<!-- titulo--> 

 <div class="container text-center display-6 m-3">
    Editor <i class="bi bi-file-earmark-text h1 text-success ml-4" ></i>
</div>


<div class="container m-5 p-3">
	 
<?php
 
 /* array clases */
  $clases = array();

    $sql = "select id,nombre 
            from clases";
    $result = mysqli_query($conn,$sql);
    while($myrow = mysqli_fetch_row($result)){
        $clases[$myrow[0]] = $myrow[1];
    };
   

  /* array editor */  
  $editor = array();

      $sql = "select e.id,e.idClase,e.texto,c.nombre,c.clase 
              from editor e inner join clases c 
                  on e.idClase = c.id";
      $result = mysqli_query($conn,$sql);
      
      while($myrow = mysqli_fetch_row($result)){
        $editor[$myrow[0]] = array($myrow[1],$myrow[2],$myrow[3],$myrow[4]);
      };


   /* escribe en la página todo */

      foreach ($editor as $clave => $valor){
          echo "<div id='$clave' data-idClase='$valor[0]' data-nombreClase='$valor[2]' class='texto $valor[3]'> $valor[1] </div>";
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
    
            v_url = 'sistema/ajaxEditor.php?opcion=guardar&texto=' + v_texto + '&idClase=' + v_idClase;

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

// llevar al editor

$('.texto').dblclick(function() {
        // Obtener el texto y la clase del elemento seleccionado
        var texto = $(this).text();
        var clase = $(this).attr('class');
        var id = $(this).attr('id');
        var idClase = $(this).attr('data-idClase');
        
  //  alert(texto + ' ' + clase + ' '+  id + ' ' + idClase);

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
    
            v_url = 'sistema/ajaxEditor.php?opcion=modificar&texto=' + v_texto + '&idClase=' + v_idClase + '&id=' + v_idParrafo;
    

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
    
            
            v_url = 'sistema/ajaxEditor.php?opcion=borrar&id=' + v_idParrafo;

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

</script>