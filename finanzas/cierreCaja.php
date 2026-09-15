<?php
      include_once ('../conexion.php');
      $conn = conectar();


 /* array mediosPago */

$medioPago = array();
$sql = "select id,medio 
        from mediosPago";
$result = mysqli_query($conn,$sql);
   
while($myrow = mysqli_fetch_row($result)){
        $medioPago[$myrow[0]] = $myrow[1];
};
 
// print_r($medioPago);

/* array cierre */
/* Busco los últimos cierres de cada uno de los medios de pago 
   array cierre  $cierre[idMedio]= (ultimaFecha,saldo)  */

$cierre= array();
$sql = "select c.idMedio, c.fecha, c.monto
        from cierre c 
        where c.fecha in 
            (select max(c1.fecha) from cierre c1 where c1.fecha = c.fecha)";
$result = mysqli_query($conn,$sql);
  
//echo $sql;
   while($myrow = mysqli_fetch_row($result)){
        $cierre[$myrow[0]] = array($myrow[1], $myrow[2]);
    };
     
 // print_r($cierre);



 $resumen = array();

foreach ($cierre as $idMedio => $datosCierre) {
    
    $ultimaFecha = $datosCierre[0];
    $ultimoMonto = $datosCierre[1];
   
    // Obtenemos los movimientos del medio de pago actual
    
    $sql = "select sum(monto),count(*) 
            from pagos 
            where idMedioPago = $idMedio and fecha >=". "'$ultimaFecha'";
//    echo $sql. '<hr>';
    $result = mysqli_query($conn,$sql);
    $myrow = mysqli_fetch_row($result);
    $sumaUltimosMovimientos = $myrow[0];
    $cantMovimientos = $myrow[1];

    // Agregamos los datos al array resumen
    $resumen[$idMedio] = [
        'ultimaFecha' => $ultimaFecha,
        'ultimoMonto' => $ultimoMonto,
        'sumaUltimosMovimientos' => $sumaUltimosMovimientos,
        'cantMovimientos' => $cantMovimientos,
        'medioPago' => $medioPago[$idMedio]
    ];
}

//print_r($resumen);

?>
<div class="container  mt-5 p-3  text-center">
    <h3> Cierre de Caja <i class="bi bi-cash fs-1 text-danger" ></i> </h3>
</div>
<!-- cuadro resumen de cierre -->
<div class="container bg-light">
    
    <div class="table form-control m-3 p-3">
        <table class="table table-bordered">
            <thead>
                <tr class="table-danger">
                    <th style="display:none"></th>
                    <th>Medio de Pago</th>
                    <th>Última Fecha</th>
                    <th class="text-right">Monto</th>
                    <th class="text-right">Monto Nuevo</th>
                    <th class="text-right">Cant.Pagos</th>
                    <th class="text-right">Actual Calculado</th>
                    <th class="text-right">Saldo Real</th>
                    <th class="text-right">ver</th>
                    <th class="text-right">Cerrar</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($resumen as $clave => $valor) {
                    $fechaFormateada = date('d-m-y', strtotime($valor['ultimaFecha']));
                    $montoFormateado = '$ ' . number_format($valor['ultimoMonto'], 2);
                    $montoNuevoFormateado = '$ ' . number_format($valor['sumaUltimosMovimientos'], 2);
                    $cantMovimientos = $valor['cantMovimientos'];
                    $total = '$ ' . number_format($valor['sumaUltimosMovimientos'] + $valor['ultimoMonto'],2);

                    echo "<tr>
                        <td style='display:none'>" . $clave . "</td>
                        <td>" . $valor['medioPago'] . "</td>
                        <td>" . $fechaFormateada . "</td>
                        <td class='text-right'>" . $montoFormateado . "</td>
                        <td class='text-right'>" . $montoNuevoFormateado . "</td>
                        <td class='text-right'>"  . $cantMovimientos."</td>
                        <td class='text-right'>" . $total . "</td>
                        <td class='form-control'><input type='text' class='inpMedio'></td>
                        <td class='button-control'><button class='btnVerMovimientos'>ver Movimientos</button></td> 
                        <td class='button-control'><button class='btnCerrar'>Cerrar</button></td> 
                    </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>


<?php

        if(isset ($_GET['idMedio'])) {  
            $idMedio = $_GET['idMedio'];
        } else{
            ?> 
        <script>
                $('#muestraMedio').hide(); 
        </script> 
         
            <?php
        };
?>
        <div id="muestraMedio" class="card  bg-warning m-5" style="width:400px;float:left">
            <div class="card-header bg-light text-center h3"><?php echo $medioPago[$idMedio]; ?></div>
            <div class="card-body bg-white">
            <table class="table table-striped table-hover rounded me-3 p-3">
                    <thead>
                            <tr>
                                    <th style="display:none">id</th>
                                    <th style="display:none">idPedido</th>
                                    <th>fecha</th>
                                    <th>contacto</th>
                                    <th>monto</th>
               
                            </tr>
                    </thead>
                    
                        <?php 
                            

                            $sql = "select 
                                        pa.id,
                                        pa.idPedido, 
                                        date_format(pa.fecha,'%d-%m') as fecha,
                                        c.apellido,
                                        pa.monto,
                                        pe.idTipoPedido
                                    from pagos pa inner join pedidos pe 
                                            on pe.id = pa.idPedido 
                                        inner join contactos c 
                                            on c.id = pe.idContacto
                                        inner join mediosPago m 
                                            on pe.idMedioPago = m.id 
                                        inner join usuarios u 
                                            on u.id = pa.idUsuario
                                    where pa.idMedioPago =". $idMedio. 
                                    " order by pa.id desc";
                                
                            
                            $result = mysqli_query($conn,$sql);
                            
                        //echo $sql;
                            
                            while($myrow = mysqli_fetch_row($result)){
                                        echo "<tr";
                                            switch ($myrow[5]){
                                                case  2:
                                                    echo " class=\"table-danger\" ";
                                                    break;
                                                case  1:
                                                    echo " class=\"table-success\" ";
                                                    break;
                                                };
                                                echo ">
                                                <td  style=\"display:none\">". $myrow[0]. "</td>
                                                <td  style=\"display:none\">". $myrow[1]. "</td>
                                                <td>". $myrow[2]. "</td>
                                                <td>". $myrow[3]. "</td>
                                                <td>". $myrow[4]. "</td>
                                               
                                            
                                            </tr>";
                                };		
                                            
                ?>
                </table>

            </div>
            <div class="card-footer bg-light">Footer</div>
        </div>


</div>
</div>
<script>
    
	$('.btnVerMovimientos').click(function(){
        var $row = $(this).closest('tr');
        v_idMedio = $row.find('td:first').text();
      
        $('#muestraMedio').show();
        v_url= 'finanzas/cierreCaja.php?idMedio=' + v_idMedio;
        $('#contenido').load(v_url);
        
    
    });
    $('.btnCerrar').click(function(){
            alert('holaCerrar')
        /*
        var $row = $(this).closest('tr');
        v_idMedio = $row.find('td:first').text();
      
        $('#muestraMedio').show();
        v_url= 'finanzas/cierreCaja.php?idMedio=' + v_idMedio;
        $('#contenido').load(v_url);
        */
    
    });

</script>

