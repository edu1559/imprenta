<?php
      include_once ('../conexion.php');
      $conn = conectar();


if(isset($_GET['cadena'])){
    $cadena = $_GET['cadena'];
};
if(isset($_GET['ultimos'])){
    $ultimos = $_GET['ultimos'];
};


/*  vector papeles */

    $papeles = array();
    
    $sql = "select id,nombre,largo,ancho, gramaje,formato, hojasPaquete,precioPaquete,precioHoja,idProveedor
            from papeles ";
    if(isset($cadena)){
        $sql .= " where nombre like '%$cadena%' ";
    };
    if(isset($ultimos)){
        $sql .= " order by 1 desc ";
    };
    $sql .= " limit 15";
            
    $result = mysqli_query($conn,$sql);
    
    while ($myrow = mysqli_fetch_row($result)){
     $papeles[$myrow[0]]= array($myrow[1],$myrow[2],$myrow[3],$myrow[4],$myrow[5],$myrow[6],$myrow[7],$myrow[8]);
    };
     //print_r($papeles);
   
?> 
 

 
 
<!-- titulo--> 

<div class="container  mt-3 p-3 h3  text-center">
       Gestión de Papeles <i class="bi bi-stack"></i> 
</div>

<!-- contenedor principal-->

<div class="container-fluid p-5 m-2 border rounded bg-light">
 
<!-- barra de busqueda-->

<div class="input-group">
    <span class="input-group-text bg-success text-white"><i class="bi bi-search"></i></span>
    <input type="text" id="buscar" class="form-control" placeholder="Ingrese papel" aria-label="Username" aria-describedby="basic-addon1">
    <button type="button" class="btn btn-success" id="btnUltimos">Ultimos <span class="badge bg-dark">15</span></button>
</div>
   
    <table class="table table-striped table-hover rounded mt-5" id="tbPapels">
    <thead>
            <tr class="table-success">
                    <th>id</th>
                    <th>Nombre</th>
                    <th>largo</th>
                    <th>ancho</th>
                    <th>gramaje</th>
                    <th>formato</th>
                    <th>hojas paquete</th>
                    <th>precio paquete</th>
                    <th>precio hoja</th>
                    <th>Proveedor</th>
                     <th>editar</th>
                     <th>borrar</th>     
            </tr>
    </thead>
    
        <?php 
         
            foreach($papeles as $clave => $valor){
                        echo "<tr>
                                <td width='10' class='idPapel'>". $clave. "</td>
                                <td width='100' class='nombre'>".$valor[0]. "</td>
                                <td width='100' class='largo'>". $valor[1]. "</td>
                                <td width='100' class='ancho'>". $valor[2]. "</td>    
                                <td width='50' class='gramaje'>". $valor[3]. "</td>
                                <td width='100' class='formato'>". $valor[4]. "</td>
                                <td width='50' class='hojasPaquete'>". $valor[5]. "</td>
                                <td width='50' class='precioPaquete'>". $valor[6]. "</td>
                                <td width='50' class='precioHoja'>". $valor[7]. "</td>
                         
                                
                                <td><button class=\"btn btn-outline-primary\"
                                        data-bs-toggle=\"modal\"
                                        data-bs-target=\"#PapelModal\"
                                        data-id=". $clave. "><i class='bi bi-person'></i></button></td>       
                                <td width='5'><i class=\" bi bi-pencil fs-4 text-success btnEditarPapel \" style=\"cursor:pointer\"></i></td>
                                <td width='5'><i class=\" bi bi-trash fs-4 text-danger btnBorrarPapel \" style=\"cursor:pointer\"></i></td>
                            </tr>";
            };	
                   	
                            
?>
    <tr class="nuevoRenglon"><td></td>
        <td><input type="text" id="nuevoNombre" style="display:none;width:150pt"></td>
        <td><input type="text" id="nuevoLargo" style="display:none;width:50pt"></td>
        <td><input type="text" id="nuevoAncho" style="display:none;width:50pt"></td>
        <td><input type="text" id="nuevoGramaje" style="display:none;width:50pt"></td>
        <td><input type="text" id="nuevoFormato" style="display:none;width:50pt"></td>
        <td><input type="text" id="nuevoHojasPaquete" style="display:none;width:50pt"></td>
        <td><input type="text" id="nuevoPreciosPaquete" style="display:none;width:50pt"></td>
        <td><input type="text" id="nuevoPrecioHoja" style="display:none;width:50pt"></td>   
        <td><input type="text" id="nuevoidProveedor" style="display:none;width:20pt"></td>
        <td><i class="bi bi-plus-square text-danger btnAgregarPapel  fs-4" style="cursor:pointer"></i></td>
    </tr>
  </table>	
	<!-- ficha del Papel -->
    <div class="modal fade" id="PapelModal" tabindex="-1" aria-labelledby="PapelModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="PapelModalLabel">Datos del Papel</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="modal-content-placeholder">
          </div>
      </div>
    </div>
  </div>
</div>
    <!-- fin ficha del Papel -->
        
</div>
<script>
/* buscar */	

$('#buscar').keyup(function() {
    
    var v_cadena = $(this).val();

    if (v_cadena.length >= 5) { // Puedes ajustar la longitud mínima
        $('#tbPapeles').html('<div class="text-center">Buscando...</div>'); // Indicador de carga

        v_url = 'insumos/`papeles.php?cadena=' + v_cadena;
     // alert (v_url)
        $('#contenido').load(v_url);
    };    
});

/* ultimos 15 */	
$('#btnUltimos').click(function() {
   v_ultimos = 'ultimos';
        v_url = 'insumos/papeles.php?ultimos=' + v_ultimos;
     // alert(v_url);
        $('#contenido').load(v_url);
       
});
    
 /* editar */   
$('table').on('click', '.btnEditarPapel', function() {
        // Obtenemos la fila actual
        var $row = $(this).closest('tr');
        

        // Obtenemos los valores de las celdas
        var v_id = $row.find('td.idPapel').text();
        var v_nombre = $row.find('td.nombre').text();
        var v_largo = $row.find('td.largo').text();
        var v_ancho = $row.find('td.ancho').text();
        var v_gramaje = $row.find('td.gramaje').text();
        var v_formato = $row.find('td.formato').text();
        var v_hojasPaquete = $row.find('td.hojasPaquete').text();
        var v_precioPaquete = $row.find('td.precioPaquete').text();
        var v_precioHoja = $row.find('td.precioHoja').text();
             
        
        // Convertimos las celdas en inputs 
      
        $row.find('td.nombre').html('<input type="text" class="form-control nombre" value="' + v_nombre + '">');
        $row.find('td.largo').html('<input type="text" class="form-control largo" value="' + v_largo + '">');
        $row.find('td.ancho').html('<input type="text" class="form-control ancho" value="' + v_ancho + '">');
        $row.find('td.gramaje').html('<input type="text" class="form-control gramaje" value="' + v_gramaje + '">');
        $row.find('td.formato').html('<input type="text" class="form-control formato" value="' + v_formato + '">');
        $row.find('td.hojasPaquete').html('<input type="text" class="form-control hojasPaquete" value="' + v_hojasPaquete + '">');
        $row.find('td.precioPaquete').html('<input type="text" class="form-control precioPaquete" value="' + v_precioPaquete + '">');

     
         // Cambiamos el botón de editar por el de actualizar
    
        $(this)
            .removeClass('btnEditarPapel bi-pencil')
            .addClass('btnActualizarPapel bi-save');
});	



$('table').on('click', '.btnActualizarPapel', function() {
    
        // Obtenemos los nuevos valores de los inputs
            var $row = $(this).closest('tr');+$row
            var v_id = $row.find('td.idPapel').text();
            var v_nombre =$row.find('input.nombre').val();
            var v_largo = $row.find('input.largo').val();
        
          
            var v_ancho = $row.find('input.ancho').val();
            var v_formato = $row.find('input.formato').val();
            var v_hojasPaquete = $row.find('input.hojasPaquete').val();
            var v_precioPaquete = $row.find('input.precioPaquete').val();

  
            // los preparamos para enviarlos por Get 


            var v_nombre = encodeURI(v_nombre);
       
          


        
        // Enviamos los datos al servidor (ejemplo con AJAX)
            v_url = 'insumos/ajaxPapeles.php?opcion=actualizarPapel&id=' + v_id + '&nombre=' + v_nombre + '&largo=' + v_largo
                    + '&ancho=' + v_ancho + '&formato=' + v_formato + '&hojasPaquete=' + v_hojasPaquete + '&precioPaquete=' + v_precioPaquete;
            alert(v_url);
         //   $('#mensaje').load(v_url);
            $('#contenido').load('insumos/papeles.php');
        

});

/* agregar */


$('table').on('click', '.btnAgregarPapel', function() {
    
            var $row = $(this).closest('tr');

    // Cambiar el icono y mostrar/ocultar inputs
            $(this).toggleClass('bi-plus-square bi-save-fill');
            $row.find('input').toggle();
});



$('table').on('click', '.bi-save-fill', function() {
    // Obtener los valores de los inputs
    var nuevoNombre = encodeURI($('#nuevoNombre').val());
    var nuevoLargo = encodeURI($('#nuevoLargo').val());
    var nuevoAncho = encodeURI($('#nuevoAncho').val());
    var nuevoFormato = encodeURI($('#nuevoFormato').val());
    var nuevoHojasPaquete = encodeURI($('#nuevoHojasPaquete').val());
    var nuevoPrecioPaquete = encodeURI($('#nuevoPrecioPaquete').val());
 
    var nuevoProveedor = encodeURI($('#nuevoProveedor').val());


  

   // cargar el registro, mostrar el mensaje y hacerlo desaparecer pasado un tiempo
    v_url = 'insumos/ajaxPapeles.php?opcion=agregarPapel&nombre=' + nuevoNombre + '&largo=' + nuevoLargo 
             + '&ancho=' + nuevoAncho + '&formato=' + nuevoFormato + '&hojasPaquete=' + nuevoHojasPaquete + '&precioPaquete=' + nuevoPrecioPaquete 
           + '&idProveedor=' + nuevoProveedor ;
   alert(v_url);
    
    $('#mensaje').load(v_url);
/*
    $('#contenido').laad('insumos/papeles.php?cadena=' + nuevoApellido);
    alert(v_url);
    // Ejemplo de uso:
   
    $.get(v_url, function(data) {
        cargarMensaje(data); // Asumimos que la respuesta de la petición es el mensaje
    });
*/	

});
    
/* borrar */

$('table').on('click', '.btnBorrarPapel', function() {
     
    var $row = $(this).closest('tr');
    var v_id = $row.find('td:first').text();
   
     var v_nombre = $row.find('input[type="text"]:first').val();

     v_url = "Papels/ajaxPapels.php?opcion=borrarPapel&id=" + v_id;
    // alert(v_url)		
     var respuesta = confirm("¿Estás seguro de que quieres borrar el registro? - " + v_nombre );
 
     if (respuesta) {
         // Eliminar el registro
            
             
         $(this).load(v_url);
         $('#contenido').load('Papels/Papels.php');
     
     };
 
 });
 // Escuchamos el evento `show.bs.modal`, que se activa justo antes de que el modal se abra.
  $('#PapelModal').on('show.bs.modal', function (event) {
  
  var button = $(event.relatedTarget);
  var idPapel = button.data('id');
  
  if (idPapel) {
    var url = 'Papels/verPapel.php?idPapel=' + idPapel;
    
      $('#modal-content-placeholder').load(url, function(response, status, xhr) {
      if (status == "error") {
        // En caso de que la carga falle, puedes mostrar un mensaje de error.
        var msg = "Lo siento, hubo un error al cargar el contenido: " + xhr.status + " " + xhr.statusText;
        $('#modal-content-placeholder').html(msg);
      }
    });
    
  } else {
    $('#modal-content-placeholder').html('<p>No se pudo encontrar el ID del Papel.</p>');
  }
  
});
</script>