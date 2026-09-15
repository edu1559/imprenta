<?php
      include_once ('../conexion.php');
      $conn = conectar();

?>
 
<!-- titulo--> 

 <div class="container  mt-2 p-3  text-center">
    <h3> Filtrar Pedidos <i class="bi bi-search fs-1 text-success" ></i> </h3>
</div>

<!-- container titulo-->


    <div class="container-fluid  p-5 border bg-light">
 
 

	 
    <div class="col-8  p-3 m-3 rounded form-inline"  id="panelPagos">

   

     <!-- filtro de búsqueda -->
     <div class="form-inline d-flex align-items-center">
      <div class="input-group">  
       <button type="button" class="btn btn-outline-success">Últimos</button>
        <button type="button" class="btn btn-outline-primary">Sin Terminar</button>
        <button type="button" class="btn btn-outline-danger">Sin Pagar</button>
        <button type="button" class="btn btn-outline-primary">Sin Entregar</button>
        <button type="button" class="btn btn-outline-danger">Sin Pagar</button>
        <button type="button" class="btn btn-outline-danger">Pendientes</button>
     </div>
        <div class="input-group">
            <span class="input-group-text">
                <i class="bi bi-search fs-3 text-success"></i>
            </span>
            <input type="text" id="busqueda" class="form-control" placeholder="ingrese pedido a buscar">
        </div>
    </div>


    <table class="table table-striped table-hover rounded" id="tbPedidos">
        

     
        <?php 
         if(isset($_GET['cadena'])){
            $cadena = $_GET['cadena'];

        $sql = "select c.apellido, p.entrada, p.detalle, p.monto ,p.montoPagado 
                from pedidos p inner join contactos c on c.id = p.idContacto 
                where apellido like '%ote%'";
        $result = mysqli_query($conn,$sql);
           while ($myrow = mysqli_fetch_row($result)){
            echo "<tr><td>". $myrow[0]. "</td>
                        <td>". $myrow[1]. "</td>
                        <td>". $myrow[2]. "</td>
                        <td>". $myrow[3]. "</td>
                  </tr>";
           }
         };
         ?>
        </table>
 </div>


    
        
       
<script>
  
    $('#busqueda').keyup(function() {
        var v_cadena = $(this).val();
        if (v_cadena.length > 2) { // Validación mínima de 2 caracteres
            $('#contenido').load('pedidos/buscarPedidos.php?cadena=' + v_cadena);
        } else {
            $('#tbPedidos').html('<p>Ingrese al menos 2 caracteres.</p>');
        }
    });

</script>
	