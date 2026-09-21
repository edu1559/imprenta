<?php
      include_once ('../conexion.php');
      $conn = conectarPDO();


if(isset($_GET['cadena'])){
    $cadena = $_GET['cadena'];
};
if(isset($_GET['ultimos'])){
    $ultimos = $_GET['ultimos'];
};


/*  vector contactos */

    $contactos = array();
    
    $sql = "select id,apellido,nombre,telefono, correo,cuit, tipoFactura,notas
            from contactos ";
    if(isset($cadena)){
        $sql .= " where apellido like :cadena ";
    };
    if(isset($ultimos)){
        $sql .= " order by 1 desc ";
    };
    $sql .= " limit 15";

    $stmt = $conn->prepare($sql);
    if(isset($cadena)){
        $busqueda = "%$cadena%";
        $stmt->bindParam(':cadena', $busqueda, PDO::PARAM_STR);
    };
    $stmt->execute();

    while ($myrow = $stmt->fetch(PDO::FETCH_NUM)){
     $contactos[$myrow[0]]= array($myrow[1],$myrow[2],$myrow[3],$myrow[4],$myrow[5],$myrow[6],$myrow[7]);
    };
       
       if(isset($_GET['sContacto'])){
                $sContacto = $_GET['sContacto'];
        }else{
                $sContacto = 1;
        };
       
     
   // print_r($contactos);
?> 
 

 
 
<!-- titulo--> 

<div class="container  mt-3 p-3  text-center">
       <h3> Gestión de Contactos <i class="bi bi-person fs-1 text-success" ></i> </h3>
</div>




<!-- contenedor principal-->

<div class="container-fluid p-5 m-2 border rounded bg-light">
 
<!-- barra de busqueda-->

<div class="input-group">
    <span class="input-group-text bg-success text-white"><i class="bi bi-search"></i></span>
    <input type="text" id="buscar" class="form-control" placeholder="Ingrese apellido a buscar" aria-label="Username" aria-describedby="basic-addon1">
    <button type="button" class="btn btn-success" id="btnUltimos">Ultimos <span class="badge bg-dark">15</span></button>
</div>
   
    <table class="table table-striped table-hover rounded mt-5" id="tbContactos">
    <thead>
            <tr class="table-success">
                    <th>id</th>
                    <th>Apellido</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Correo</th>
                    <th>Cuit</th>
                    <th>Tipo Factura</th>
                    <th>Notas</th>
                     <th>editar</th>
                     <th>borrar</th>     
            </tr>
    </thead>
    
        <?php 
         
            foreach($contactos as $clave => $valor){
                        echo "<tr>
                                <td width='10'>". $clave. "</td>
                                <td width='130'>". $valor[0]. "</td>
                                <td width='130'>". $valor[1]. "</td>
                                <td width='130'>". $valor[2]. "</td>
                                <td width='150'>". $valor[3]. "</td>
                                <td width='150'>". $valor[4]. "</td>
                                <td width='15'>". $valor[5]. "</td>
                                <td >". $valor[6]. "</td> 
                                <td width='5'><i class=\" bi bi-pencil fs-4 text-success btnEditarContacto \" style=\"cursor:pointer\"></i></td>
                                <td width='5'><i class=\" bi bi-trash fs-4 text-danger btnBorrarContacto \" style=\"cursor:pointer\"></i></td>
                            </tr>";
            };	
                   	
                            
?>
    <tr class="nuevoRenglon"><td></td>
        <td><input type="text" id="nuevoApellido" style="display:none"></td>
        <td><input type="text" id="nuevoNombre" style="display:none"></td>
        <td><input type="text" id="nuevoTelefono" style="display:none"></td>
        <td><input type="text" id="nuevoCorreo" style="display:none"></td>
        <td><input type="text" id="nuevoCuit" style="display:none"></td>
        <td><input type="text" id="nuevoTipoFactura" style="display:none;width:20pt"></td>
        <td><input type="text" id="nuevoNotas" style="display:none;width:250pt"></td>
        <td><i class="bi bi-plus-square text-danger btnAgregarContacto  fs-4" style="cursor:pointer"></i></td>
    </tr>
  </table>	
		
        
</div>
<script>
/* buscar */	

$('#buscar').keyup(function() {
    
    var v_cadena = $(this).val();

    if (v_cadena.length >= 3) { // Puedes ajustar la longitud mínima
        $('#tbContactos').html('<div class="text-center">Buscando...</div>'); // Indicador de carga

        v_url = 'administracion/contactos.php?cadena=' + v_cadena;
     // alert (v_url)
        $('#contenido').load(v_url);
    };    
});

/* ultimos 15 */	
$('#btnUltimos').click(function() {
   v_ultimos = 'ultimos';
        v_url = 'administracion/contactos.php?ultimos=' + v_ultimos;
     // alert(v_url);
        $('#contenido').load(v_url);
       
});
    
 /* editar */   
$('table').on('click', '.btnEditarContacto', function() {
        // Obtenemos la fila actual
        var $row = $(this).closest('tr');

        // Obtenemos los valores de las celdas
        var v_id = $row.find('td:first').text();
        var v_apellido = $row.find('td:eq(1)').text();
        var v_nombre = $row.find('td:eq(2)').text();
        var v_telefono = $row.find('td:eq(3)').text();
        var v_correo = $row.find('td:eq(4)').text();
        var v_cuit = $row.find('td:eq(5)').text();
        var v_tipoFactura = $row.find('td:eq(6)').text();
        var v_notas = $row.find('td:eq(7)').text();
              //  alert(v_id + ' ' + v_apellido +' '+ v_nombre + ' '+ v_telefono );
        
        // Convertimos las celdas en inputs 
       
        $row.find('td:eq(2)').html('<input type="text" class="form-control" value="' + v_nombre + '">');
        $row.find('td:eq(3)').html('<input type="text" class="form-control" value="' + v_telefono + '">');
        $row.find('td:eq(4)').html('<input type="text" class="form-control" value="' + v_correo + '">');
        $row.find('td:eq(5)').html('<input type="text" class="form-control" value="' + v_cuit + '">');
        $row.find('td:eq(6)').html('<input type="text" class="form-control" value="' + v_tipoFactura + '">');
        $row.find('td:eq(7)').html('<input type="text" class="form-control" value="' + v_notas + '">');
        
         // Cambiamos el botón de editar por el de actualizar

        $(this)
            .removeClass('btnEditarContacto bi-pencil')
            .addClass('btnActualizarContacto bi-save');
});	



$('table').on('click', '.btnActualizarContacto', function() {
    
        // Obtenemos los nuevos valores de los inputs
            var $row = $(this).closest('tr');+$row
            var v_id = $row.find('td:first').text();
            var v_nombre = $row.find('input[type="text"]:first').val();
            var v_telefono = $row.find('input[type="text"]:eq(1)').val();
            var v_correo = $row.find('input[type="text"]:eq(2)').val();
            var v_cuit = $row.find('input[type="text"]:eq(3)').val();
            var v_tipoFactura = $row.find('input[type="text"]:eq(4)').val();
            var v_notas = $row.find('input[type="text"]:eq(5)').val();
         //  alert( v_id + ' ' + apellido + ' '+ nombre);

            // los preparamos para enviarlos por Get 


            var v_nombre = encodeURI(v_nombre);
            var v_telefono = encodeURI(v_telefono);
            var v_correo = encodeURI(v_correo);
            var v_notas = encodeURI(v_notas);
          


        
        // Enviamos los datos al servidor (ejemplo con AJAX)
            v_url = 'administracion/ajaxContactos.php?opcion=actualizarContacto&id=' + v_id + '&nombre=' + v_nombre + '&telefono=' + v_telefono
                    + '&correo=' + v_correo + '&cuit=' + v_cuit + '&tipoFactura=' + v_tipoFactura + '&notas=' + v_notas;
            //alert(v_url);
            $('#mensaje').load(v_url);
            $('#contenido').load('administracion/contactos.php');

});

/* agregar */


$('table').on('click', '.btnAgregarContacto', function() {
    
            var $row = $(this).closest('tr');

    // Cambiar el icono y mostrar/ocultar inputs
            $(this).toggleClass('bi-plus-square bi-save-fill');
            $row.find('input').toggle();
});



$('table').on('click', '.bi-save-fill', function() {
    // Obtener los valores de los inputs
    var nuevoApellido = encodeURI($('#nuevoApellido').val());
    var nuevoNombre = encodeURI($('#nuevoNombre').val());
    var nuevoTelefono = encodeURI($('#nuevoTelefono').val());
    var nuevoCorreo = encodeURI($('#nuevoCorreo').val());
    var nuevoCuit = encodeURI($('#nuevoCuit').val());
    var nuevoTipoFactura = encodeURI($('#nuevoTipoFactura').val());
    var nuevoNotas = encodeURI($('#nuevoNotas').val());


  

   // cargar el registro, mostrar el mensaje y hacerlo desaparecer pasado un tiempo
    v_url = 'administracion/ajaxContactos1.php?opcion=agregarContacto&apellido=' + nuevoApellido + '&nombre=' + nuevoNombre 
             + '&telefono=' + nuevoTelefono + '&correo=' + nuevoCorreo + '&cuit=' + nuevoCuit + '&tipoFactura=' + nuevoTipoFactura 
             + '&notas=' + nuevoNotas;
  // alert(v_url);
    
    $('#mensaje').load(v_url);
    
/*
    function cargarMensaje(mensaje) {
        $('#mensaje').text(mensaje);
        $('#mensaje').fadeIn(); // Mostrar el mensaje con una animación suave
        setTimeout(function() {
            $('#mensaje').fadeOut(); // Ocultar el mensaje después de 10 segundos
        }, 10000);
    };
*/
    $('#contenido').laad('administracion/contactos.php?cadena=' + nuevoApellido);
    alert(v_url);
    // Ejemplo de uso:
   
    $.get(v_url, function(data) {
        cargarMensaje(data); // Asumimos que la respuesta de la petición es el mensaje
    });
	

});
    
/* borrar */

$('table').on('click', '.btnBorrarContacto', function() {
     
    var $row = $(this).closest('tr');
    var v_id = $row.find('td:first').text();
   
     var v_nombre = $row.find('input[type="text"]:first').val();

     v_url = "administracion/ajaxContactos.php?opcion=borrarContacto&id=" + v_id;
    // alert(v_url)		
     var respuesta = confirm("¿Estás seguro de que quieres borrar el registro? - " + v_nombre );
 
     if (respuesta) {
         // Eliminar el registro
            
             
         $(this).load(v_url);
         $('#contenido').load('administracion/contactos.php');
     
     };
 
 });

</script>