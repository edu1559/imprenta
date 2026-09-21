<?php
      include_once ('../conexion.php');
      $conn = conectarPDO();

 
      if(isset($_GET['opcion'])){
          $fechaDesde = $_GET['fechaDesde'];
          $fechaHasta = $_GET['fechaHasta'];

      } else{
        $fechaDesde = '2024-01-10';
        $fechaHasta = '2025-03-21';

      };

?>

<div class="container mt-5 p-3 text-center h3">
    Indicadores <i class="bi bi-bar-chart-fill fs-1 text-danger"></i>
</div>

<!-- Selector de fechas -->

<div class="container form-gorup bg-light rounder p-3 m-3 text-center m-5">
      <label for="inpInicio" class="form-label m-3"> Fecha Inicio </label>
      <input type="date" id="inpInicio" class="form-group" value="<?php echo $fechaDesde;?>">

      <label for="inpFin" class="form-label"> Fecha Fin </label>
      <input type="date" id="inpFin" class="form-group"  value="<?php echo $fechaHasta;?>">

      <button class="button-group from-group  bg-warning" id="btnGraficar">Graficar</button>

</div>
<!-- Aca comienzo a buscar los valores del gráfico --> 

            <?php 
           
            $sql = "select idTipoPedido,
                            count(*),
                            sum(montoPagado),
                            sum(monto-montoPagado)
                    from pedidos
                    where entrada >= :fechaDesde and
                        entrada <= :fechaHasta and
                    idTipoPedido in (1,2)
                    group by idTipoPedido";
           // echo $sql;

            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':fechaDesde', $fechaDesde, PDO::PARAM_STR);
            $stmt->bindParam(':fechaHasta', $fechaHasta, PDO::PARAM_STR);
            $stmt->execute();

        //    $resumen = array();

            while ($myrow = $stmt->fetch(PDO::FETCH_NUM)){
            
                $resumen[$myrow[0]] = array ($myrow[1],$myrow[2],$myrow[3]);
            };
            // print_r($resumen);


            $entrada = intval($entrada);
            $salida = intval($salida);
            $nosDeben = intval($nosDeben);
            $debemos = intval($debemos);

            $entrada = number_format($resumen[1][1],0,'','');
            $salida = number_format($resumen[2][1],0,'','');
            $nosDeben = number_format($resumen[1][2],0,'','');
            $debemos = number_format($resumen[1][2],0,'','');
  
            $entrada = intval($entrada);
            $salida = intval($salida);
            $nosDeben = intval($nosDeben);
            $debemos = intval($debemos);
            //
            $valores = array($entrada,$salida,$nosDeben,$debemos);
           // print_r($valores);
            $valoresJson = json_encode($valores);
          //  print_r($valoresJson);
            ?>  

<!-- Container con la tabla y el grafico -->

<div class="container m-5 bg-light">
    
    <div class="row">
        <div class="fs-3 text-center text-secondary  p-5">Entadas, salidas, deudores y acreedores <br>Fecha: entre  <?php echo  " ". $fechaDesde. " y el ". $fechaHasta; ?> </div>
        <div class="col-5">
                <table class="table table-striped" style="width:60%">
                    <tr>
                            <th>Nombre</th>
                            <th style="text-align:right">Valores</th>
                    </tr>
                    <tr>
                            <td>Entrda</td>
                            <td style="text-align:right"><?php echo $entrada;?>$</td>
                    </tr>
                    <tr>
                            <td>Salida</td>
                            <td style="text-align:right"><?php echo $salida;?>$</td>
                    </tr>
                    <tr>
                            <td>Nos Deben</td>
                            <td style="text-align:right"><?php echo $nosDeben;?>$</td>
                    </tr>
                    <tr>
                            <td>Debemos</td>
                            <td style="text-align:right"><?php echo $debemos;?>$</td>
                    </tr>
                    


                </table>

        </div>

    
        <div class="col-5">
            
            <canvas id="myChart"></canvas>

        </div>
    </div>

</div>


<script>
 
    $('#btnGraficar').click(function(){
        var_fechaDesde = $('#inpInicio').val();
        var_fechaHasta = $('#inpFin').val();
        var_fechaDesde= encodeURI(var_fechaDesde);
        var_fechaHasta= encodeURI(var_fechaHasta);

        
        v_url = 'finanzas/indicadores.php?opcion=graficar' +
                '&fechaDesde=' + var_fechaDesde +
                '&fechaHasta=' + var_fechaHasta;
        alert(v_url);
       $('#contenido').load(v_url);
    });

    var yValues = <?php echo $valoresJson; ?>
   // alert(yValues);
    var xValues = ["Entradas", "Salidas", "Deudores", "Acreedores"];
  //  var yValues = [55, 49, 44, 24];
    var barColors = ["green", "red","blue","orange"];

    new Chart("myChart", {
    type: "polarArea",
     data: {
        labels: xValues,
        datasets: [{
        backgroundColor: barColors,
        data: yValues
    }]
    },
    options: {
            legend: {
                display: false // Esto oculta la leyenda
            },
            plugins: {
                legend: {
                    display: false // En versiones más recientes de Chart.js se usa esto
                }
            }
        }
    });

</script>