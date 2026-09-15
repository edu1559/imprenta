<?php
      include_once ('../conexion.php');
      $conn = conectar();


if(isset($_GET['cadena'])){
    $cadena = $_GET['cadena'];
};
if(isset($_GET['ultimos'])){
    $ultimos = $_GET['ultimos'];
};
/*  vector padres */

    $padres = array();
        $sql = "select id,nombre 
            from menu 
            where padre is null";
              
    $result = mysqli_query($conn,$sql);
    
    while ($myrow = mysqli_fetch_assoc($result)){
     $padres[$myrow['id']]= $myrow['nombre'];
    };     
    if(isset($_GET['idPadre'])){
        $padre = $_GET['idPadre'];
    }else{
        $idPadre = array_key_first($padres);
    };
 
    
 //  print_r($padres);

/*  vector paginas */

    $paginas = array();
        $sql = "select id,padre,nombre 
            from menu";
              
    $result = mysqli_query($conn,$sql);
    
    while ($myrow = mysqli_fetch_assoc($result)){
     $paginas[$myrow['id']]= array($myrow['padre'],$myrow['nombre']);
    };     
     
   // print_r($paginas);

/*  vector pruebas */

    $pruebas = array();
    
    $sql = "select p.id,
                   date(p.fecha),
                   p.idPadre,
                   m1.nombre,
                   p.idPagina,
                   m2.nombre,
                   p.funcionalidad,
                   p.prueba,
                   p.resultado,
                   p.mejora
            from pruebas p inner join menu m1 
                            on p.idPadre = m1.id 
                        inner join menu m2 
                            on p.idPagina = m2.id ";
    if(isset($cadena)){
        $sql .= " where m2.nombre like '%$cadena%' ";
    };
    if(isset($ultimos)){
        $sql .= " order by 1 desc ";
    };
    $sql .= " limit 15";
           
    
 //  echo $sql;
    $result = mysqli_query($conn,$sql);
    
    while ($myrow = mysqli_fetch_row($result)){
     $pruebas[$myrow[0]]= array($myrow[1],$myrow[2],$myrow[3],$myrow[4],$myrow[5],$myrow[6],$myrow[7],$myrow[8],$myrow[9]);
    };

       
     
   //print_r($pruebas);
?> 
 

 
<!-- titulo--> 

<div class="container  mt-5 p-3 display-6 text-center">
    Pruebas <i class="bi bi-binoculars fs-1 text-danger"></i>
</div>
<!-- contenedor principal-->

<div class="container-fluid p-5 m-2 border rounded bg-light">
 
<!-- barra de busqueda-->

<div class="input-group">
    <span class="input-group-text bg-danger text-white"><i class="bi bi-search"></i></span>
    <input type="text" id="buscar" class="form-control" placeholder="Ingrese nombre menu" aria-label="Username" aria-describedby="basic-addon1">
    <button type="button" class="btn btn-danger" id="btnUltimos">Ultimos <span class="badge bg-dark">15</span></button>
</div>
   
    <table class="table table-striped table-hover rounded mt-5" id="tbPruebas">
    <thead>
            <tr class="table-danger">
                    <th style="display:none">id</th>
                    <th>Fecha</th>
                    <th style="display:none">idPadre</th>
                    <th>Padre</th>
                    <th style="display:none">idPagina</th>
                    <th>pagina</th>
                    <th>funcionalidad</th>
                    <th>prueba</th>
                    <th>Resultado</th>
                    <th>Mejora</th>
                     <th>editar</th>
                     <th>borrar</th>     
            </tr>
    </thead>
    
        <?php 
         
            foreach($pruebas as $clave => $valor){
                        echo "<tr>
                                <td style='display:none'  class='idPrueba'>". $clave. "</td>
                                <td width='80' class='fecha'>". $valor[0]. "</td>
                                <td style='display:none' class='idPadre'>". $valor[1]. "</td>
                                <td width='80' class='padre'>". $valor[2]. "</td>
                                <td  style='display:none'  class='idPagina'>". $valor[3]. "</td>
                                <td width='80'  class='pagina'>". $valor[4]. "</td>
                                <td width='200' class='funcionalidad'>". $valor[5]. "</td>
                                <td width='200' class='prueba'>". $valor[6]. "</td> 
                                <td width='200' class='resultado'>". $valor[7]. "</td> 
                                <td width='200'class='mejora'>". $valor[8]. "</td> 
                                <td width='5'><i class=\" bi bi-pencil fs-4 text-danger btnEditarPrueba \" style=\"cursor:pointer\"></i></td>
                                <td width='5'><i class=\" bi bi-trash fs-4 text-danger btnBorrarPrueba \" style=\"cursor:pointer\"></i></td>
                            </tr>";
            };	
                   	
                            
?>
    <tr id="nuevoRenglon"><td></td>
         <td>
            <select class="selPadre" style="display:none">
                <?php
                    foreach ($padres as $id => $nombre){
                        echo "<option value='". $id. "'>". $nombre. "</option>";
                    };
                ?>
            </select>
        </td>
        <td>
            <select class="selPagina" style="display:none">
                <?php
                    foreach ($paginas as $id => $pagina){
                        echo "<option value='". $id. "' data-padre='". $pagina['padre'] ."'>". $pagina[1]. "</option>";
                    };
                ?>
            </select>
        </td>
        <td><input type="text" id="inpFuncionalidad" style="display:none"></td>
        <td><input type="text" id="inpPrueba" style="display:none"></td>
        <td><input type="text" id="inpResultado" style="display:none"></td>
        <td><input type="text" id="inpMejora" style="display:none"></td>
       
        <td><i class="bi bi-plus-square text-danger btnAgregarPrueba  fs-4" style="cursor:pointer"></i></td>
    </tr>
  </table>	
		
        
</div>

<script>
/* buscar */	
/*
$('.selPadre').change(function(){
       v_idPadre = $(this).val();
       v_url = 'sistema/pruebas.php&idPadre=' + v_idPadre;
       alert(v_url);
 //   $('#contenido').load(v_url);
});
*/
 $('.selPadre').change(function() {
        var v_idPadre = $(this).val();
       // var selPagina = $(this).closest('tr').find('.selPaginas');
   //     selPaginas.empty(); // Limpiar las opciones anteriores
/*
        //Agregar una opción por defecto
        selPaginas.append($('<option>', {
            value: '',
            text: 'Seleccione una página'
        }));

        $('.selPaginas > option').each(function() {
            if ($(this).data('padre') == v_idPadre) {
                selPaginas.append($(this).clone());
            }
        });
        */
    });
$('#buscar').keyup(function() {
        var v_cadena = $(this).val();
    if (v_cadena.length >= 3) { // Puedes ajustar la longitud mínima
        $('#tbPruebas').html('<div class="text-center">Buscando...</div>'); // Indicador de carga
        v_url = 'sistema/pruebas.php?cadena=' + v_cadena;
     // alert (v_url)
        $('#contenido').load(v_url);
    };    
});

/* ultimos 15 */	
$('#btnUltimos').click(function() {
   v_ultimos = 'ultimos';
        v_url = 'sistema/pruebas.php?ultimos=' + v_ultimos;
     // alert(v_url);
        $('#contenido').load(v_url);
       
});
    
 /* editar */   
$('table').on('click', '.btnEditarPrueba', function() {
        // Obtenemos la fila actual
        var $row = $(this).closest('tr');

        // Obtenemos los valores de las celdas
        var v_idPrueba = $row.find('td.idPrueba').text();
        var v_fecha = $row.find('td.fecha').text();
        var v_idPadre = $row.find('td.idPadre').text();
        var v_padre = $row.find('td.padre').text();
        var v_idPagina = $row.find('td.idPagina').text();
        var v_pagina = $row.find('td.pagina').text();
        var v_funcionalidad = $row.find('td.funcionalidad').text();
        var v_prueba = $row.find('td.prueba').text();
        var v_resultado = $row.find('td.resultado').text();
        var v_mejora = $row.find('td.mejora').text();
               // alert(v_idPrueba + ' ' + v_fecha +' '+ v_idPadre + ' '+ v_padre );
        
        // Convertimos las celdas en inputs 
       
      //  $row.find('td.padre').html('<input type="text" class="form-control padre" value="' + v_padre + '">');
        $row.find('td.padre').html(v_padre);
        $row.find('td.pagina').html(v_pagina);
        $row.find('td.funcionalidad').html('<input type="text" class="form-control funcionalidad" value="' + v_funcionalidad + '">');
        $row.find('td.prueba').html('<input type="text" class="form-control prueba" value="' + v_prueba + '">');
        $row.find('td.resultado').html('<input type="text" class="form-control resultado" value="' + v_resultado + '">');
        $row.find('td.mejora').html('<input type="text" class="form-control mejora" value="' + v_mejora + '">');
        
         // Cambiamos el botón de editar por el de actualizar

        $(this)
            .removeClass('btnEditarPrueba bi-pencil')
            .addClass('btnActualizarPrueba bi-save');
});	



$('table').on('click', '.btnActualizarPrueba', function() {
 
   
        // Obtenemos los nuevos valores de los inputs
            var $row = $(this).closest('tr');+$row
            var v_id = $row.find('td.idPrueba').text();
            var v_idPadre = $row.find('td.idPadre').text();
            var v_padre = $row.find('input[type="text"].padre').val();
            var v_pagina = $row.find('input[type="text"].pagina').val();
            var v_idPagina = $row.find('td.idPagina').text();
        
            var v_funcionalidad = $row.find('input[type="text"].funcionalidad').val();
            var v_prueba = $row.find('input[type="text"].prueba').val();
            var v_resultado = $row.find('input[type="text"].resultado').val();
            var v_mejora = $row.find('input[type="text"].mejora').val();
      
            // los preparamos para enviarlos por Get 

            var v_funcionalidad = encodeURI(v_funcionalidad);
            var v_prueba = encodeURI(v_prueba);
            var v_resultado = encodeURI(v_resultado);
            var v_mejora = encodeURI(v_mejora);
          
            //alert( v_prueba + ' ' + v_resultado + ' '+ v_mejora);

        
            v_url = 'sistema/ajaxPruebas.php?opcion=actualizarPrueba&id=' + v_id + '&idPadre=' + v_idPadre + '&idPagina=' + v_idPagina
                    + '&funcionalidad=' + v_funcionalidad + '&prueba=' + v_prueba + '&resultado=' + v_resultado + '&mejora=' + v_mejora;
          //  alert(v_url);
 
        $('#mensaje').load(v_url);
        $('#contenido').load('sistema/pruebas.php');

});

/* agregar */


$('table').on('click', '.btnAgregarPrueba', function() {
    
            var $row = $(this).closest('tr');

    // Cambiar el icono y mostrar/ocultar inputs
            $(this).toggleClass('bi-plus-square bi-save-fill');
            $row.find('input').toggle();
            $row.find('select').toggle();
});



$('table').on('click', '.bi-save-fill', function() {
    // Obtener los valores de los inputs
    var nuevoIdPadre = $('.selPadre').val();
    var nuevoIdPagina = $('.selPagina').val();
    var nuevaFuncionalidad = $('#inpFuncionalidad').val();
    var nuevaPrueba = $('#inpPrueba').val();
    var nuevoResultado = $('#inpResultado').val();
    var nuevaMejora = $('#inpMejora').val();

            // los preparamos para enviarlos por Get 
      

            var nuevaFuncionalidad = encodeURI(nuevaFuncionalidad);
            var nuevaPrueba = encodeURI(nuevaPrueba);
            var nuevoResultado = encodeURI(nuevoResultado);
            var nuevaMejora = encodeURI(nuevaMejora);

    

   // cargar el registro, mostrar el mensaje y hacerlo desaparecer pasado un tiempo
    v_url = 'sistema/ajaxPruebas.php?opcion=agregarPrueba&idPadre=' + nuevoIdPadre + '&idPagina=' + nuevoIdPagina 
             + '&funcionalidad=' + nuevaFuncionalidad + '&prueba=' + nuevaPrueba + '&resultado=' + nuevoResultado + '&mejora=' + nuevaMejora;
   //alert(v_url);
    
  $('#mensaje').load(v_url);
    $('#contenido').load('fianzas/pruebas.php');
/*
    function cargarMensaje(mensaje) {
        $('#mensaje').text(mensaje);
        $('#mensaje').fadeIn(); // Mostrar el mensaje con una animación suave
        setTimeout(function() {
            $('#mensaje').fadeOut(); // Ocultar el mensaje después de 10 segundos
        }, 10000);
    };

 $('#contenido').laad('contactos/contactos.php?cadena=' + nuevoApellido);
    alert(v_url);
    // Ejemplo de uso:
   
    $.get(v_url, function(data) {
        cargarMensaje(data); // Asumimos que la respuesta de la petición es el mensaje
    });
	
*/
});
    
/* borrar */

$('table').on('click', '.btnBorrarPrueba', function() {
     
    var $row = $(this).closest('tr');
    var v_id = $row.find('td:first').text();
   
     var v_nombre = $row.find('input[type="text"]:first').val();

     v_url = "sistema/ajaxPruebas.php?opcion=borrarPrueba&id=" + v_id;
     alert(v_url)		
     var respuesta = confirm("¿Estás seguro de que quieres borrar el registro? - " + v_nombre );
 
     if (respuesta) {
         // Eliminar el registro
            
             
         $(this).load(v_url);
         $('#contenido').load('sistema/pruebas.php');
     
     };
 
 });

</script>
