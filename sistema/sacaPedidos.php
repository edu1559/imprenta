<?php
      include_once ("../conexionImprenta.php");
      $connImp = conectar();
    
      if(isset ($connImp)){
        echo " <div class=\"alert alert-success\"> la conexion fue exitosa</div>";
      }else{
        echo " <div class=\"alert alert-success\">no se pudo conectar</div>";
      };
      
 ?>
 
 
<!-- titulo--> 

 <div class="container text-center display-6">
    Saca Pedidos<i class="bi bi-file-earmark-text h1 text-success" ></i>
</div>

<div class="container form-gorup bg-light rounder p-3 m-3">
      <label for="inpInicio" class="form-label m-3"> Fecha Inicio </label>
      <input type="date" id="inpInicio" class="form-group">

      <label for="inpFin" class="form-label"> Fecha Fin </label>
      <input type="date" id="inpFin" class="form-group">

      <button class="button-group from-group  bg-warning" id="btnBuscar">Buscar</button>

</div>

<?php 


    if(isset($_GET['inicio']) && isset($_GET['fin'])){
      $inicio = $_GET['inicio'];
      $fin = $_GET['fin'];


$inicio = $_GET['inicio'] ?? null;
$fin    = $_GET['fin'] ?? null;

if (!$inicio || !$fin) {
    die("<div class='alert alert-warning'>Por favor, seleccione un rango de fechas.</div>");
}
      echo "El inicio es ". $inicio. " y el fin".  $fin;

  

?>



<div class="container m-5 p-3">
	 
<div class="container bg-light">
<h3 class="text-center h4">Pedidos</h3>
<?php
 echo "<div class='alert alert-info small'>Migrando período: <b>$inicio</b> al <b>$fin</b></div>";

if ($inicio || $fin) {
?>
<div class="card mb-3">
    <div class="card-header bg-primary text-white">1. SQL de Contactos Nuevos</div>
    <div class="card-body bg-light font-monospace" style="font-size: 0.8rem;">
    <?php
    // Buscamos contactos cuya fecha de carga esté en el rango
    $sqlC = "SELECT idContacto, apellido, nombre, correo, telefono1, fechaCarga,cuit
             FROM contactos 
             WHERE fechacarga BETWEEN '$inicio' AND '$fin'";
    $resC = mysqli_query($connImp, $sqlC);

    while($c = mysqli_fetch_assoc($resC)) {
        echo "INSERT INTO contactos (id, apellido, nombre, correo, telefono, fechaCarga, cuit) VALUES ("
             . $c['idContacto'] . ", \"" 
             . $c['apellido'] . "\", \"" 
             . $c['nombre'] . "\", \"" 
             . $c['correo'] . "\", \"" 
             . $c['telefono1'] . "\", \"" 
             . $c['fechaCarga'] . "\", \"" 
             . $c['cuit'] . "\");<br>";
    }
    ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-success text-white">2. SQL de Pedidos</div>
    <div class="card-body bg-light font-monospace" style="font-size: 0.8rem;">
    <?php
    $sqlP = "SELECT idpedido, entrada, tipo, idcontacto, detalle, prometido, 
                    estadoproceso, estadoentrega, estadopago, monto, montopagado 
             FROM pedidos 
             WHERE entrada BETWEEN '$inicio' AND '$fin'
             ORDER BY idpedido ASC";
    $resP = mysqli_query($connImp, $sqlP);

    while($p = mysqli_fetch_assoc($resP)) {
        echo "INSERT INTO pedidos (id, entrada, idTipoPedido, idContacto, detalle, prometido, estadoProduccion, estadoEntrega, estadoPago, monto, montoPagado) VALUES ("
             . $p['idpedido'] . ", \"" 
             . $p['entrada'] . "\", " 
             . $p['tipo'] . ", " 
             . $p['idcontacto'] . ", \"" 
             . mysqli_real_escape_string($connImp, $p['detalle']) . "\", \"" 
             . $p['prometido'] . "\", " 
             . $p['estadoproceso'] . ", " 
             . $p['estadoentrega'] . ", " 
             . $p['estadopago'] . ", " 
             . $p['monto'] . ", " 
             . $p['montopagado'] . ");<br>";
    }
    ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-warning text-dark">3. SQL de Pagos</div>
    <div class="card-body bg-light font-monospace" style="font-size: 0.8rem;">
    <?php
    // Buscamos pagos realizados en ese período
    // Hacemos el JOIN con usuarios de la base vieja para obtener el ID de la base nueva
    $sqlPg = "SELECT p.id, p.fecha, p.idPedido, p.monto, u.idUsuario 
              FROM pagos p 
              INNER JOIN usuarios u ON p.usuario = u.usuario
              WHERE p.fecha BETWEEN '$inicio' AND '$fin'
              ORDER BY p.id ASC";
    $resPg = mysqli_query($connImp, $sqlPg);

    while($pg = mysqli_fetch_assoc($resPg)) {
        // Importante: Asignamos idMedioPago = 1 (Efectivo) por defecto para que aparezcan en Finanzas
        echo "INSERT INTO pagos (id, fecha, idPedido, monto, idUsuario, idMedioPago) VALUES ("
             . $pg['id'] . ", \"" 
             . $pg['fecha'] . "\", " 
             . $pg['idPedido'] . ", " 
             . $pg['monto'] . ", " 
             . $pg['idUsuario'] . ", 1);<br>";
    };
};
    };
?>
   

<script>
    
  $('#btnBuscar').click(function(){
    v_fechaInicio = $('#inpInicio').val();
    v_fechaFin = $('#inpFin').val();
   // alert (v_fechaInicio + ' '+ v_fechaFin); 

   v_url = 'sistema/sacaPedidos.php?inicio=' + v_fechaInicio + '&fin=' + v_fechaFin;
   $('#contenido').load(v_url);
  alert(v_url);


  });


</script>

