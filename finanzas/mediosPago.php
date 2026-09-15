<?php
      include_once ('../conexion.php');
      $conn = conectar();

/*  vector medios */

    $medios = array();
    
    $sql = "select id,medio,logo,visible
            from mediosPago";
            
    $result = mysqli_query($conn,$sql);
    
    while ($myrow = mysqli_fetch_row($result)){
     $medios[$myrow[0]]= array($myrow[1],$myrow[2],$myrow[3]);
    };
       
  /*
       if(isset($_GET['sMedio'])){
                $sMedio = $_GET['sMedio'];
        }else{
                $sMedio = 1;
        };
       
   */
	  
   // print_r($medios);
?> 
 

 
 
<!-- titulo--> 

 <div class="container  mt-5 p-3  text-center h3">
    Gestión de Medios de Pago <i class="bi bi-credit-card fs-1 text-success" ></i>
</div>

<!-- container principal-->
<div class="container-fluid p-5 border bg-light">
 
 <div class="row" id="panelMedios">
 	 
    <div class="col-6  rounded " id="panelMuestraMedios">
    
    <table class="table table-striped table-hover rounded mt-5">
    <thead>
            <tr>
                    <th>id</th>
                    <th>Medio de pago</th>
                    <th>logo</th>
                    <th>Visible</th>
                   
                     <th>editar</th>
                    
            </tr>
    </thead>
    
        <?php 
         
            foreach($medios as $clave => $valor){
                        echo "<tr>
                                <td class=\"clave\">". $clave. "</td>
                                <td class=\"medio\">".  $valor[0]. "</td>
                                <td class=\"logo\">". $valor[1]. "</td>
                                <td class=\"visible\">". $valor[2]. "</td>
                                <td><i class=\" bi bi-pencil fs-4 text-danger btnEditarMedio \" style=\"cursor:pointer\"></i></td>
                            </tr>";
            };	
                   	
                            
?>
    <tr class="nuevoRenglon"><td></td>
        <td><input type="text" id="nuevoMedio" style="display:none"></td>
        <td><input type="text" id="nuevoLogo" style="display:none"></td>
        <td><input type="text" id="nuevoVisible" style="display:none"></td>
        <td><i class="bi bi-plus-square text-danger btnAgregarMedio  fs-4" style="cursor:pointer"></i></td>
    </tr>
  </table>	
		
        
</div>
<script>
	
        $('table').on('click', '.btnEditarMedio', function() {
        // Obtenemos la fila actual
        var $row = $(this).closest('tr');

        // Obtenemos los valores de las celdas
        var v_id = $row.find('td.clave').text();  
        var v_medio = $row.find('td.medio').text();
        var v_logo = $row.find('td.logo').text();
        var v_visible = $row.find('td.visible').text();
           //      alert(v_id + ' ' + v_medio +' '+ v_logo + ' '+ v_visible );
        
        // Convertimos las celdas en inputs 
       
        $row.find('td.logo').html('<input type="text" class="form-control nuevoLogo" value="' + v_logo + '">');
        $row.find('td.visible').html('<input type="text" class="form-control nuevoVisible" value="' + v_visible + '">');
        
         // Cambiamos el botón de editar por el de actualizar

        $(this)
            .removeClass('btnEditarMedio bi-pencil')
            .addClass('btnActualizarMedio bi-save');
      


        });	


        $('table').on('click', '.btnActualizarMedio', function() {
    
        // Obtenemos los nuevos valores de los inputs
            var $row = $(this).closest('tr'); //+$row
            var v_id = $row.find('td:first').text();
            /*
            var nuevoLogo = $row.find('input[type="text"]:first').val();
            var nuevoVisible = $row.find('input[type="text"]:eq(1)').val();
            */
            var nuevoLogo = $row.find('input[type="text"].nuevoLogo').val();
            var nuevoVisible = $row.find('input[type="text"].nuevoVisible').val();

           // alert( v_id + ' ' + nuevoLogo + ' '+ nuevoVisible);
        
        // Enviamos los datos al servidor (ejemplo con AJAX)
            v_url = 'finanzas/ajaxMediosPago.php?opcion=actualizarMedio&id=' + v_id + '&logo=' + nuevoLogo + '&visible=' + nuevoVisible;
            $('#mensaje').load(v_url);
            $('#contenido').load('finanzas/mediosPago.php');
    });

    $('table').on('click', '.btnAgregarMedio', function() {
    
            var $row = $(this).closest('tr');

    // Cambiar el icono y mostrar/ocultar inputs
            $(this).toggleClass('bi-plus-square bi-save');
            $row.find('input').toggle();
    });

    $('table').on('click', '.bi-save', function() {
    // Obtener los valores de los inputsº
    var nuevoMedio = $('#nuevoMedio').val();
    var nuevoLogo = $('#nuevoLogo').val();
    var nuevoVisible = $('#nuevoVisible').val();

   // alert(nuevoMedio + ' ' + nuevoLogo + ' ' + nuevoVisible);

   // cargar el registro, mostrar el mensaje y hacerlo desaparecer pasado un tiempo
    v_url = 'finanzas/ajaxMediosPago.php?opcion=agregarMedio&medio=' + nuevoMedio + '&logo=' + nuevoLogo + '&visible=' + nuevoVisible;
    alert(v_url);
    $('#mensaje').load(v_url);
    
    function cargarMensaje(mensaje) {
        $('#mensaje').text(mensaje);
        $('#mensaje').fadeIn(); // Mostrar el mensaje con una animación suave
        setTimeout(function() {
            $('#mensaje').fadeOut(); // Ocultar el mensaje después de 10 segundos
        }, 10000);
    }

    // Ejemplo de uso:
   
    $.get(v_url, function(data) {
        cargarMensaje(data); // Asumimos que la respuesta de la petición es el mensaje
    });
	$('#contenido').load('finanzas/mediosPago.php');

	 });
    


</script>