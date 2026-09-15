<style>
.mi-fila-resaltada {
  font-weight: bold;
  border:solid 2px red;
}
</style>
<?php
      include_once ('../conexion.php');
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

if(isset($_GET['spadre'])){
  $spadre = $_GET['spadre'];
}else{
  $spadre = key($padres); //seleciona la clave del primer elemento (dónde esté el puntero interno)
};
 // echo $spadre;                

if (isset($_GET['cadena'])){
  $cadena = $_GET['cadena'];
};

?> 
    
	

 
 
<!-- titulo--> 

 <div class="container  mt-5 p-3  text-center">
    <h3> Gestión de Munu <i class="bi bi-menu-app " style="color:green;width:100px"></i></h3>
</div>




 
 <!-- barra de busqueda-->
 <div class="container-fluid  p-5 border bg-light">

<div class="container col-md-8 bg-light rounded border m-3 p-3" id="contBuscarMenu" style="width:45%;height:20%;float:left;">
            
            <div class="input-group mb-3">
                <span class="input-group-text bg-success text-white"><i class="bi bi-search"></i></span>
                <input type="text" id="buscar" class="form-control " placeholder="Ingrese nombre menu" aria-label="Username" aria-describedby="basic-addon1">
             
                    
                    <select class="select-form rounded bg-success text-white" id="selPadre">
                            <?php 
                                foreach ($padres as $clave => $valor){
                                    echo "<option value='$clave' ";
                                       if(isset($spadre) && $clave == $spadre){
                                         echo " selected ";
                                       };
                                    echo ">$valor</option>";
                                };
                            ?>
                    </select>    
            </div>
 </div>

 


   
   
<!-- panelMuestraUsuarios -->
	 
    <div class="col  rounded " id="panelMenu">
    
    <table class="table table-striped col-6 table-light table-hover mt-5 border rounded " id="tblMenu">
    <thead>
		<tr class=" text-black table-primary">
			<th>id</th>
			<th>nombre</th>
			<th>pagina</th>
			<th>padre</th>
			<th>borrar</th>
			<th>editar</th>
			
		</tr>
    </thead>
    
        <?php 
            
			
            $sql = "select m.id,m.nombre,m.pagina, p.nombre,m.padre
                    from menu m left join menu p 
                    on m.padre = p.id
                    where ";
                    if(isset($cadena)){
                      $sql .= " m.nombre like '%$cadena%'";
                    } else {
                      $sql .= " m.padre = $spadre";
                    };
                    
                   
            			 
            $result = mysqli_query($conn,$sql);
            
          // echo $sql;
            
            while($myrow = mysqli_fetch_row($result)){
                echo "<tr>
						<td>". $myrow[0]. "</td>
						<td>". $myrow[1]. "</td>
						<td>". $myrow[2]. "</td>
						<td>". $myrow[3]. "</td>
            <td style=\"display:none\">". $myrow[4]. "</td>
						<td><span class=\"fa fa-trash btnBorrarMenu\" style=\"cursor:pointer;font-size:25px\"></td>
						<td><span class=\"fa fa-pencil btnEditarMenu\" style=\"cursor:pointer;font-size:25px\"></td>               
                    </tr>";
                };	
            ?>
                <tr class="nuevoRenglon"><td></td>
                <td><input type="text" id="nuevoNombre" style="display:none"></td>
                <td><input type="text" id="nuevaPagina" style="display:none"></td>
                <td><select id="nuevoPadre" style="display:none">
                    <?php 
                          foreach ($padres as $clave => $valor){
                            echo "<option value ='$clave'>$valor</option>";
                          };
                    ?>
                  </select>
                </td>
                <td></td>
                <td><i class="bi bi-plus-square text-danger btnAgregarMenu  fs-4" style="cursor:pointer"></i></td>
            </tr>	
                            

  </table>
		
			
        </div>
        
     



<script>

 /* buscar */       
   $('#buscar').keyup(function() {
    var v_cadena = $(this).val();

    if (v_cadena.length >= 3) { // Puedes ajustar la longitud mínima
        $('#tblMenu').html('<div class="text-center">Buscando...</div>'); // Indicador de carga

        v_url = 'sistema/menu.php?cadena=' + v_cadena;
     alert (v_url)
        $('#contenido').load(v_url);
    };    
    });
     
  /* sel */   
  $('#selPadre').change(function(){
            v_spadre= $(this).val();
            v_url = 'sistema/menu.php?spadre='+ v_spadre;
         //   alert(v_url);
            $('#contenido').load(v_url);
            
    });

   /* Actualizar Menu */

    $('table').on('click', '.btnEditarMenu', function() {
        // Obtenemos la fila actual
        var $row = $(this).closest('tr');
        // Agregamos la clase mi-fila-resaltada
        $row.addClass('mi-fila-resaltada bg-black');
        // Obtenemos los valores de las celdas
        var v_id = $row.find('td:first').text();
        var v_nombre = $row.find('td:eq(1)').text();
        var v_pagina = $row.find('td:eq(2)').text();
        var v_idPadre = $row.find('td:eq(4)').text();
      //  var v_idPadre = $row.find('td:eq(5)').text();
    
        
       
    //    alert( v_id + ' ' + v_nombre + ' '+ v_pagina + ' ' + v_idPadre );
        
        // Convertimos las celdas en inputs 
      
        $row.find('td:eq(1)').html('<input type="text" class="form-control" value="' + v_nombre + '">');
        $row.find('td:eq(2)').html('<input type="text" class="form-control" value="' + v_pagina + '">');
        $row.find('td:eq(3)').html('<input type="text" class="form-control" value="' + v_idPadre + '">');
        
        
         // Cambiamos el botón de editar por el de actualizar

        $(this)
            .removeClass('btnEditarMenu bi-pencil')
            .addClass('btnActualizarMenu bi-save');
   
});	

    $('table').on('click', '.btnActualizarMenu', function() {
    
    // Obtenemos los nuevos valores de los inputs
        var $row = $(this).closest('tr');+$row
        var v_id = $row.find('td:first').text();
        var v_nombre = $row.find('input[type="text"]:first').val();
        var v_pagina = $row.find('input[type="text"]:eq(1)').val();
        var v_idPadre = $row.find('input[type="text"]:eq(2)').val();
        
     
   // alert( v_id + ' ' + v_nombre + ' '+ v_pagina + ' ' + v_idPadre );

        
      


    
    // Enviamos los datos al servidor (ejemplo con AJAX)
 
        v_url = 'sistema/ajaxMenu.php?opcion=actualizarMenu&id=' + v_id + '&nombre=' + v_nombre + '&pagina=' + v_pagina + '&idPadre=' + v_idPadre ;
      //  alert(v_url);
        $('#mensaje').load(v_url);
        $('#contenido').load('sistema/menu.php?spadre=' + v_idPadre);
      
});

// Agregar Menu

$('table').on('click', '.btnAgregarMenu', function() {
  var $row = $(this).closest('tr');
  var $icon = $(this);

  // Cambiar el icono y mostrar/ocultar inputs
  $icon.toggleClass('bi-plus-square bi-save-fill');
  $row.find('input, select').toggle();

    });
$('table').on('click', '.bi-save-fill', function() {
// Obtener los valores de los inputs

var nuevoNombre= $('#nuevoNombre').val();
var nuevaPagina = $('#nuevaPagina').val();
var nuevoPadre = $('#nuevoPadre').val();



// alert(nuevoNombre + ' ' + nuevaPagina + ' ' + nuevoPadre);

// cargar el registro, mostrar el mensaje y hacerlo desaparecer pasado un tiempo

v_url = 'sistema/ajaxMenu.php?opcion=agregarMenu&nombre=' + nuevoNombre + '&pagina=' + nuevaPagina 
     + '&padre=' + nuevoPadre;
alert(v_url);
$('#mensaje').load(v_url);
$('#contenido').load('sistema/menu.php?spadre=' + nuevoPadre);

function cargarMensaje(mensaje) {
$('#mensaje').text(mensaje);
$('#mensaje').fadeIn(); // Mostrar el mensaje con una animación suave
setTimeout(function() {
    $('#mensaje').fadeOut(); // Ocultar el mensaje después de 10 segundos
}, 10000);
};

// Ejemplo de uso:

$.get(v_url, function(data) {
cargarMensaje(data); // Asumimos que la respuesta de la petición es el mensaje
});

});

  /* Borrar Menu */

 
$('table').on('click', '.btnBorrarMenu', function() {
     
     var $row = $(this).closest('tr');
     var v_id = $row.find('td:first').text();
    
      var v_nombre = $row.find('input[type="text"]:first').val();
 
      v_url = "sistema/ajaxMenu.php?opcion=borrarMenu&id=" + v_id;
     // alert(v_url)		
      var respuesta = confirm("¿Estás seguro de que quieres borrar el menu ? - " + v_nombre );
  
      if (respuesta) {
          // Eliminar el registro
             
              
          $(this).load(v_url);
          $('#contenido').load('sistema/menu.php');
      
      };
  
  });
 


</script>