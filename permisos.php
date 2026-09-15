
<?php
      include_once ('conexion.php');
      $conn = conectar();
?> 



<div class="container  m-5 p-3  text-center">
    <h3> Gestión de Permisos <span class="fa fas fa-sitemap" style="color:gray;width:100px"></h3>
</div>
 
<div class="container">
  <p>  
  <input type="text" id="buscaUsuario" placeholder="Ingrese apellido del usuario" width="300px" style="padding:5px;margin:3px;">
  <span id="btnBuscaUsuario" class="fa fa-search" style="scale:2;color:gray;cursor:pointer;margin-left:10px">
  </p>  
  </div>

<div class="container" id="muestraUsuarios">

</div>
<div class="container" id="divMuestraPermisos">

</div>

<script>
$('#buscaUsuario').keypress(function(){

    v_cadena = $('#buscaUsuario').val();
    v_url = 'ajaxPermisos.php?opcion=buscar&cadena=' + v_cadena;
  
    $('#muestraUsuarios').load(v_url);
 });
 
 

</script>

