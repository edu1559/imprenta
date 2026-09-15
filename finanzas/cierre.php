<?php
      include_once ('../conexion.php');
      $conn = conectar();



 /* array $medioPago */

$medioPago = array();
$sql = "select id,medio 
        from mediosPago
        where visible=1";
$result = mysqli_query($conn,$sql);
   
while($myrow = mysqli_fetch_row($result)){
        $medioPago[$myrow[0]] = $myrow[1];
};
 


/*- matriz $ultimoCierre[idMedio]= array (fecha, monto,diferencia); */

$ultimoCierre = array();


  
    $sql = "select cm.idMedio,cm.medio,date(cm.fechaUC),cm.montoUC,cm.diferenciaUC
            from cierreMedios cm inner join mediosPago mp on cm.idMedio= mp.id where mp.visible=1"; 
  
    $result = mysqli_query($conn,$sql);   

     while ($myrow= mysqli_fetch_row($result)){
       $ultimoCierre[$myrow[0]]=array($myrow[1],$myrow[2],$myrow[3],$myrow[4]); 
     };

  // print_r($ultimoCierre);

/*- matriz $ultimosPagos[idMedio]= array (cantidad, monto); */

$cierre = array();

foreach($ultimoCierre as $clave => $valor){

   // $fecha = $ultimoCierre[$clave][1];
  
    $sql = "select count(*),
                sum(monto)
          from pagos p
            inner join cierreMedios cm
                on p.idMedioPago = cm.idMedio
          where idMedioPago=$clave  
                and fecha >= cm.fechaUC
          group by idMedioPago";
  //  echo $sql . "<hr>";


 $result = mysqli_query($conn,$sql);
        $myrow = mysqli_fetch_row($result);

        $cierre[$clave] = array($valor[0],$valor[1],$valor[2],$valor[3],$myrow[0],$myrow[1]);
        // $cierre[idMedio] = medio, fechaUC, montoUC, difUC, cantOp, montoCalculado,

};
//print_r($cierre);
?>


    <div class="container  mt-3 p-3  text-center h3">
        Cierre Finanzas <i class="bi bi-bank fs-1 text-success"></i>
    </div>




<!-- Armo la tabla -->


<!-- <div id="marco" class="container-fluid d-flex flex-column" style="min-height: 80vh;">-->

<div class="row flex-grow-1">
<div class="container col-md-6 text-center text-success form-control"><h6>Estado Actual por Medios de Pago</h6></div>
<div  class="col-md-6 p-5">
        <table class=" table table-striped table-bordered form-control " id="tablaCierre" >
            <tr class="table-success">
                <th style="display:none">id</th>
                <th title="Medio de Pago">Medio</th>
                <th title="Fecha Ultimo Cierre">fechaUC</th>
                <th style="display:none">fechaComp</th>
                <th title="Monto del último cierre">MontoUC</th>
                <th title="Diferencia en el último cierre">difUC</th>
                <th title="Cantidad de Operaciones Nuevas">CanOp.</th>
                <th title="Monto Calculado">montoCal</th>
                <th title="Monto Real">Saldo Real</th>
                <th style ="display:none" title="Monto que Deberia haber sin formato"></th>
                <th>Ver</th>
                <th>Cerrar</th>
            </tr>

                <?php
                foreach ($cierre as $clave =>$valor){
                    echo "<tr>
                        <td class=\"idMedio\" style=\"display:none\">". $clave. "</td>
                        <td class=\"medio\" >". $valor[0]. "</td>
                        <td class=\"fechaUC\">". $valor[1]. "</td>
                        <td class=\"montoUC\" >".number_format( $valor[2],'0','',''). "$</td>
                        <td class=\"dif\">". number_format($valor[3],'0','','')." $</td>
                        <td class=\"cantOp\">". $valor[4]."</td>
                        <td  class=\"montoCal\" style=\"color:red;text-align:right\">". number_format($valor[5],'0','','')." $</td>
                        <td class=\"montoReal\"><input type=\"text\" class=\"inpMontoReal $valor[0]\" size=\"7\" value=\"". ($valor[2] + $valor[5]) ."\">
                        <td style=\"display:none\" class=\"calculado\">".  ($valor[2] + $valor[5]) ."</td>
                        <td><button class=\"button btnVer\">ver</button></td>
                        <td><button class=\"button btnCerrar\">Cerrar</button></td>
                    </tr>";
                };
                ?>
        </table>
</div>


<?php
if(isset($_GET['idMedio'])){
    $idMedio = $_GET['idMedio'];
    $fechaUC = $_GET['fechaUC'];
    $mPagoNombre = URLdecode($_GET['nombre']);

?>
<div id="verPagos" class="col-md-4 bg-light card bg-warning">
       
    <div class="card-header bg-success text-center h4 text-white">
            <?php echo $mPagoNombre; ?>
            <div id="montoCalculado" ></div>
    </div>

    <div class="card-body bg-white">   
        <?php 
            

$sql = "select  
            p.id,
            p.idPedido,
            date(p.fecha),
            c.apellido,
            p.monto,
            pe.idTipoPedido,
            u.usuario 
        from pagos p inner join pedidos pe 
                on p.idPedido = pe.id 
            inner join contactos c 
                on p.idUsuario = c.id 
            inner join usuarios u 
                on p.idUsuario = u.id 
            inner join cierreMedios cm
                on p.idMedioPago = cm.idMedio
        where p.fecha > cm.fechaUC 
            and p.idMedioPago=$idMedio";
           // echo $sql;

    $result = mysqli_query($conn,$sql);

  


    echo "<table class=\"table pagos\">";
    echo "<tr>
            <th>Id</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Monto</th>
            <th>Cambiar</th>
            <th>Cargó</th>
          </tr>";
        $sumaTotal = 0;
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
                   
                    <td>". $myrow[0]. "</td>
                    <td  style=\"display:none\">". $myrow[1]. "</td>
                    <td>". $myrow[2]. "</td>
                    <td>". $myrow[3]. "</td>
                    <td>". $myrow[4]. "</td>";
                    
                     echo "<td><select class=\"selMedio\">";
                        foreach ($medioPago as $clave =>$valor){
                            
                            echo "<option value=\"$clave\"";
                                 if($clave == $idMedio){
                                     echo " selected ";
                                 };
                            echo ">$valor</option>";
                        };
                echo "</select></td>                   
                     <td>". $myrow[6]. "</td>             
                    </tr>";
                $sumaTotal += $myrow[4];
          };		
                
    ?>
            </table>
        <div class="form-group in-line">  
            <label for="#miSuma" class="form-group in line">Total: </label>
            <input name="miSuma" type="text" size="7" class="form-group in-line text-danger miSuma " value="<?php echo $sumaTotal. " $ "?>">
            <button type="button" data-medio="<?php echo $mPagoNombre;?>" class="btn btn-outline-primary form-group button-group btnCopiarMonto" >CopiarMonto</button>
         </div> 
    </div>

</div>
</div>

card
<?php 
 };
?>
<div class="container-fluid text-center">
        <button id="btnCerrarTodos" class="form-group bg-success mb-5 text-white" >Cerrar Todos</button> 
</div>


<?php

/* preparar la consulta de cierre  */
           
        $medios = ""; 
        $valores = "";
        $suma = 0;
        $diferencia = 0;
        
        
    foreach ($ultimoCierre as $clave => $valor){
    
       $medios .= $valor[0]. ",";
       $valores .= $valor[2]. ",";
       $suma = $suma + $valor[2];
       $diferencia = $diferencia + $valor[3];
       
    };
    /*
      echo "medios= ". $medios . "<hr>";
      echo "valores= ". $valores . "<hr>";
      echo "suma= ". $suma . "<hr>";
      echo "diferencia= ". $diferencia . "<hr>";
    */
    
?>

    <input type="hidden" id="datos" data-nombres="<?php echo $medios; ?>" 
                                    data-valores="<?php echo $valores; ?>" 
                                    data-suma="<?php echo $suma; ?>"
                                    data-diferencia="<?php echo $diferencia; ?>">  
<?php 
$sql = "select 
            id,
            date(fecha),
            efectivo, 
            transferencia, 
            mercadoPago, 
            cheques,    
            credito,    
            dolares,   
            brubank, 
            naranjaX, 
            suma,    
            diferencia,
            idUsuarioCierre 
        from cierre";
         $result = mysqli_query($conn,$sql);   

     $cierreFinal = array();

     while ($myrow= mysqli_fetch_row($result)){
       $cierreFinal[$myrow[0]]=array($myrow[1],$myrow[2],$myrow[3],$myrow[4],$myrow[5],$myrow[6],$myrow[7],$myrow[8],$myrow[9],$myrow[10]); 
     };
   // print_r($cierreFinal);
?> 

<div class="container col-md-6 text-center text-warning form-control"><h6>Historial de Cierres</h6></div>

<div  class="col-md-8 p-5">
        <table class=" table table-striped table-bordered " id="tablaCierre" >
            <tr class="table-warning">
                <th style="display:none">id</th>
                <th>Fecha</th>
                <th title="Efectivo">Efectivo</th>
                <th title="Transferencias">Transf.</th>
                <th>MercadoPago</th>
                <th>Cheques</th>
                <th>Credito</th>
                <th>Dolares</th>
                <th>Brubank</th>
                <th>NaranjaX</th>
                <th>Suma</th>
                <th>Diferencia</th>
                <th>Borrar</th>
              
            </tr>

                <?php
                foreach ($cierreFinal as $clave =>$valor){
                    echo "<tr>
                        <th style=\"display:none\">". $clave. "</th>
                        <td>". $valor[0]."</td>
                        <td>". number_format($valor[1],'0','','')." $</td>
                        <td>". number_format($valor[2],'0','','')." $</td>
                        <td>". number_format($valor[3],'0','','')." $</td>
                        <td>". number_format($valor[4],'0','','')." $</td>
                        <td>". number_format($valor[5],'0','','')." $</td>
                        <td>". number_format($valor[6],'0','','')." $</td>
                        <td>". number_format($valor[7],'0','','')." $</td>
                        <td>". number_format($valor[8],'0','','')." $</td>
                        <td>". number_format($valor[9],'0','','')." $</td>
                        <td>". number_format($valor[10],'0','','')." $</td>
                       
                        <td><button  class=\"button btnBorrar\"><i class=\"bi bi-trash\"></i></button></td>
                    </tr>";
                };
                ?>
        </table>
        <!-- Button to Open the Modal -->
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#myModal">
             Efectivo
        </button>

        <!-- The Modal -->
            <div class="modal" id="myModal">
            <div class="modal-dialog">
                <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                    <h4 class="modal-title">Recuento de Billetes</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <!-- Modal body -->
                <div class="modal-body">
                   <?php include('efectivo.php'); ?>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
                </div>

                </div>
            </div>
            </div>
            </div>



<script>    

   $('.btnCopiarMonto').click(function(){
         var v_monto = $('.miSuma').val();
         var v_medio = $(this).attr('data-medio');
          $('#tablaCierre .inpMontoReal' + '.' + v_medio).val(v_monto);
    alert(v_monto + ' ' + v_medio);
   });

    $('#tablaCierre').on('click', '.btnVer', function() {
      //      alert('hola');
      
      $('#verPagos').toggle(); // Muestra/oculta el div
        var $row = $(this).closest('tr');
        var v_id = $row.find('td.idMedio').text(); // Obtener el ID (primer <td>
        var v_nombre = $row.find('td.medio').text();
        v_nombre = encodeURI(v_nombre);
        var v_fechaUC = $row.find('td.fechaUC').text();
       
        v_url= 'finanzas/cierre.php?idMedio=' + v_id + '&fechaUC=' + v_fechaUC + '&nombre=' + v_nombre;
         alert(v_url);  

       $('#contenido').load(v_url);
    
    
    });
    
    $('.selMedio').change(function(){
            v_idMedio = $(this).val();
            var $row = $(this).closest('tr');
            v_idPago = $row.find('td:first').text();
            v_url = 'finanzas/ajaxCierre.php?opcion=cambiaMedio' +
			        '&idPago=' + v_idPago +
			        '&idMedio=' + v_idMedio;
     $('#mensaje').load(v_url);
       // alert(v_url);
    });

    $('.btnCerrar').click(function(){
        var $row = $(this).closest('tr');
        v_idMedio = $row.find('td:first').text();
        v_montoCalculado = $row.find('.calculado').text();
        v_montoReal = $row.find('.inpMontoReal').val();
        v_fechaUC = $row.find('.fechaUC').text();
      
     //   alert(v_idMedio + ' ' + v_montoCalculado + ' ' + v_montoReal + ' ' + v_fechaUC);


        v_url = "finanzas/ajaxCierre.php?opcion=cierre" +
                '&idMedio=' + v_idMedio +
                '&montoCalculado=' + v_montoCalculado +
                '&montoReal=' + v_montoReal;
      //  alert (v_url);
      $('#mensaje').load(v_url);
      $('#contenido').load('finanzas/cierre.php');

    });


    $('#btnCerrarTodos').click(function(){
        var v_nombres = $('#datos').data('nombres');
        var v_valores = $('#datos').data('valores');
        var v_suma = $('#datos').data('suma');
        var v_diferencia = $('#datos').data('diferencia');
    
     v_url = 'finanzas/ajaxCierre.php?opcion=cerrarTodos' + 
                    '&nombres=' + v_nombres +
                    '&valores=' + v_valores +
                    '&suma=' + v_suma +
                    '&diferencia=' + v_diferencia;
        alert(v_url);
      $('#mensaje').load(v_url);
      $('#contenido').load('finanzas/cierre.php');
  });





</script>





