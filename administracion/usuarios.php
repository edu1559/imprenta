<style>
.mi-fila-resaltada {
  font-weight: bold;
  border:solid 2px red;
}
</style>
<?php
      include_once ('../conexion.php');
      $conn = conectar();

/* creo un vector perfiles */

    $perfiles = array();
    
    $sql = "select id,perfil
            from perfiles";
            
    $result = mysqli_query($conn,$sql);
    
    while ($myrow = mysqli_fetch_row($result)){
     $perfiles[$myrow[0]]= $myrow[1];
    };
    
 
 /* valor por defecto de perfil */
 
 
 // $sperfil = key($perfiles); //seleciona la clave del primer elemento (dónde esté el puntero interno)
        
       
       if(isset($_GET['sperfil'])){
                $idPerfil = $_GET['sperfil'];
        }else{
                $idPerfil = 1;
        };
            
  // Creo un array para los contactos que no tienen creado un usuario
    
	$contactos = array();
    
    $sql = "select c.id,c.apellido,c.nombre
            from contactos c
			where c.id not in (select u.id from usuarios u)";
            
    $result = mysqli_query($conn,$sql);
    
    while ($myrow = mysqli_fetch_row($result)){
     $contactos[$myrow[0]]= $myrow[1]." ".$myrow[2];
    };
  
    
?> 
 

 
 
<!-- titulo--> 

 <div class="container  mt-5 p-3  text-center">
    <h3> Gestión de Usuarios <span class="fa fa-user" style="color:green;width:100px"></h3>
</div>
<!-- container titulo-->


<div class="container-fluid  p-5 border bg-light">
 
 <!-- barra de busqueda-->

<div class="container col-md-8 bg-light rounded border m-3 p-3" id="contBuscarUsuarios" style="width:45%;height:20%;float:left;">
            
            <div class="input-group mb-3">
                <span class="input-group-text bg-success text-white"><i class="bi bi-search"></i></span>
                <input type="text" id="buscar" class="form-control " placeholder="Ingrese apellido Usuario" aria-label="Username" aria-describedby="basic-addon1">
             
                    
                    <select class="select-form rounded bg-success text-white" id="selPerfil">
                            <?php 
                                foreach ($perfiles as $clave => $valor){
                                    echo "<option value='$clave' ";
                                       if(isset($sperfil) && $clave == $sperfil){
                                         echo " selected ";
                                       };
                                    echo ">$valor</option>";
                                };
                            ?>
                    </select>    
            </div>
 </div>

 


   
   
<!-- panelMuestraUsuarios -->
	 
    <div class="col  rounded " id="panelUsuarios">
    
    <table class="table table-striped table-hover rounded mt-5" id="tblUsuarios">
    <thead>
            <tr>
                    <th>id</th>
                    <th>contacto</th>
                    <th>usuario</th>
                    <th>clave</th>
                    <th>perfil</th>
                    <th>borrar</th>
                    <th>editar</th>
                    
            </tr>
    </thead>
    
        <?php 
            //include_once ('conexion.php');
 

            $sql = "select u.id,concat(c.apellido,' ',c.nombre), u.usuario, u.clave, p.perfil,c.id
                    from contactos c inner join usuarios u 
                        on u.id = c.id
                    inner join perfiles p 
                        on p.id = u.idPerfil 
                     where u.idPerfil = $idPerfil";
            if (isset($_GET['cadena'])){
                $cadena = $_GET['cadena'];
                $sql .= " and c.apellido like  '%$cadena%' ";
            };
               // echo $sql;    
                
            $result = mysqli_query($conn,$sql);
            
           
            
            while($myrow = mysqli_fetch_row($result)){
                        echo "<tr>
                                <td>". $myrow[0]. "</td>
                                <td>". $myrow[1]. "</td>
                                <td>". $myrow[2]. "</td>
                                <td>". $myrow[3]. "</td>
                                <td>". $myrow[4]. "</td>
                                <td style=\"display:none\">". $myrow[5]. "</td>
                                <td><span class=\"fa fa-trash btnBorrarUsuario\" style=\"cursor:pointer;font-size:25px\"></td>
                                <td><span class=\"fa fa-pencil btnEditarUsuario\" style=\"cursor:pointer;font-size:25px\"></td>
                            </tr>";
                };
                ?>	
                <!-- Agregar registros-->	
                <tr id="nuevoRenglon"><td></td>
                <td><select id="nuevoIdContacto" class="select-form rounded" style="display:none">
                       <?php 
                      
                            foreach ($contactos as $clave => $valor){
                                echo "<option value='$clave'>$valor</option>";
                            };
                       ?>
                    </select>
                </td>
                <td><input type="text" id="nuevoUsuario" style="display:none;width:15em"></td>
                <td><input type="text" id="nuevaClave" style="display:none;width:5em"></td>
                <td><select id="nuevoIdPerfil" class="select-form rounded" style="display:none">
                       <?php 
                      
                            foreach ($perfiles as $clave => $valor){
                                echo "<option value='$clave'>$valor</option>";
                            };
                       ?>
                    </select>
                </td>
                
                <td><i class="bi bi-plus-square btnAgregarUsuario  fs-4" style="cursor:pointer"></i></td>
            </tr>           

  </table>	
		
			
        </div>
        
     



<script>

 /* buscar */       
   $('#buscar').keyup(function() {
    var v_cadena = $(this).val();

    if (v_cadena.length >= 4) { // Puedes ajustar la longitud mínima
        $('#tblUsuarios').html('<div class="text-center">Buscando...</div>'); // Indicador de carga

        v_url = 'administracion/usuarios.php?cadena=' + v_cadena;
     //alert (v_url)
        $('#contenido').load(v_url);
    };    
    });
     
  /* por prefil */   
  $('#selPerfil').change(function(){
            v_sperfil= $(this).val();
            v_url = 'administracion/usuarios.php?sperfil='+ v_sperfil;
         //   alert(v_url);
            $('#contenido').load(v_url);
            
    });

   /* Actualizar Usuario */

    $('table').on('click', '.btnEditarUsuario', function() {
        // Obtenemos la fila actual
        var $row = $(this).closest('tr');
        // Agregamos la clase mi-fila-resaltada
        $row.addClass('mi-fila-resaltada bg-black');
        // Obtenemos los valores de las celdas
        var v_id = $row.find('td:first').text();
        var v_contacto = $row.find('td:eq(1)').text();
        var v_usuario = $row.find('td:eq(2)').text();
        var v_clave = $row.find('td:eq(3)').text();
        var v_perfil = $row.find('td:eq(4)').text();
       
      //  alert( v_id + ' ' + v_contacto + ' '+ v_usuario + ' ' + v_clave + ' ' + v_perfil);
        
        // Convertimos las celdas en inputs 
       
        $row.find('td:eq(1)').html('<input type="text" class="form-control" value="' + v_contacto + '" disabled>');
        $row.find('td:eq(2)').html('<input type="text" class="form-control" value="' + v_usuario + '">');
        $row.find('td:eq(3)').html('<input type="text" class="form-control" value="' + v_clave + '">');
        $row.find('td:eq(4)').html('<select  class="form-control"><option value="1">Administrador</option><option value="2">Visitante</option></select>');
    
        
         // Cambiamos el botón de editar por el de actualizar

        $(this)
            .removeClass('btnEditarUsuario bi-pencil')
            .addClass('btnActualizarUsuario bi-save');
});	

    $('table').on('click', '.btnActualizarUsuario', function() {
    
    // Obtenemos los nuevos valores de los inputs
        var $row = $(this).closest('tr');+$row
        var v_id = $row.find('td:first').text();
        var v_contacto = $row.find('input[type="text"]:first').val();
        var v_usuario = $row.find('input[type="text"]:eq(1)').val();
        var v_clave = $row.find('input[type="text"]:eq(2)').val();
        var v_perfil = $row.find('input[type="text"]:eq(3)').val();
     
   alert( v_id + ' ' + v_contacto + ' '+ v_usuario + ' ' + v_clave + ' ' + v_perfil);

        // los preparamos para enviarlos por Get 


        var v_usuario = encodeURI(v_usuario);
        var v_clave = encodeURI(v_clave);
        
   
      


    
    // Enviamos los datos al servidor (ejemplo con AJAX)
 
        v_url = 'administracion/ajaxUsuarios.php?opcion=actualizarUsuario&id=' + v_id + '&usuario=' + v_usuario + '&clave=' + v_clave;
        alert(v_url);
        $('#mensaje').load(v_url);
        $('#contenido').load('administracion/usuarios.php');
      
});

// Agregar Usuario
$('table').on('click', '.btnAgregarUsuario', function() {
  var $row = $(this).closest('tr');
  var $icon = $(this);

  // Cambiar el icono y mostrar/ocultar inputs
  $icon.toggleClass('bi-plus-square bi-save-fill');
  $row.find('input, select').toggle();

    });
$('table').on('click', '.bi-save-fill', function() {
// Obtener los valores de los inputs

var nuevoIdContacto= $('#nuevoIdContacto').val();
var nuevoUsuario = $('#nuevoUsuario').val();
var nuevaClave = $('#nuevaClave').val();
var nuevoIdPerfil = $('#nuevoIdPerfil').val();


alert(nuevoIdContacto + ' ' + nuevoUsuario + ' ' + nuevaClave + ' ' + nuevoIdPerfil);

// cargar el registro, mostrar el mensaje y hacerlo desaparecer pasado un tiempo

v_url = 'administracion/ajaxUsuarios.php?opcion=agregarUsuario&id=' + nuevoIdContacto + '&usuario=' + nuevoUsuario 
     + '&clave=' + nuevaClave + '&idPerfil=' + nuevoIdPerfil;
alert(v_url);
$('#mensaje').load(v_url);
$('#contenido').load('administracion/usuarios.php');

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

</script>