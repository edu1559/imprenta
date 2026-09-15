<?php
      include_once ('../conexion.php');
      $conn = conectar();

?>
 
<!-- titulo--> 

 <div class="container  mt-2 p-3  text-center">
    <h3> Armar el filtro <i class="bi bi-funnel fs-1 text-success" ></i> </h3>
</div>

<!-- container titulo-->


    <div class="container-fluid  p-5 border bg-light">
 
 

	 
    <div class="col-8 bg-secondary p-3 m-3 rounded form-inline"  id="panelPagos">

    <!-- titulo -->
    <div class="col-12 container rounded h3 p-3  p-2 text-white text-center border">Contactos</div>


     <!-- filtros -->
      <div class="form-inline">
                <label for="busqueda"
                <input type="text" id="busqueda" class="form-control col-3" width="30" placeholder="ingrese pedido a buscar">
        </div>
      </div>

    <!-- encabezado -->
    <table class="table table-striped table-hover rounded mt-5">
    <thead>
            <tr class="table">
                    <th style="display:none">id</th>
					<!-- tipo pedido-->
                    <th colspan="2">apellido nombre</th>
                    <th colspan="2">detalle</th>
                    <th>entrada</th>
                    <th style="display:none">prom.</th>
                    <th style="display:none">salida</th>
                    <th>Ent.</th>
                    <th>prod.</th>
                    <th>Pago</th>
                    <th>Monto Pagado</th>
                    <th>Monto</th>
                    <th>Saldo</th>
                    <th>MedioPago</th>
                    <th style="display:none">origen</th>
                    <th style="display:none">Cargó</th>
                    <th style="display:none">cuit</th>
                    <th>editar</th>
                    
            </tr>
    </thead>
	
	
    
        <?php 
            include_once ('../conexion.php');
 

            $sql = "select p.id,concat(c.apellido, ' ',c.nombre) as contacto,p.idTipoPedido,p.detalle,date_format(p.entrada,'%d-%m') as entrada,
						  date_format(p.prometido,'%d-%m') as prometido,p.salida,p.estadoEntrega,p.estadoProduccion,p.estadoPago,p.montoPagado,p.monto,
						  (p.monto - p.montoPagado) as saldo,p.idMedioPago,p.idOrigen
					from pedidos p inner join contactos c 
						  on p.idContacto = c.id 
				    order by id desc limit 20 ";
                
            $result = mysqli_query($conn,$sql);
            
           
            
            while($myrow = mysqli_fetch_row($result)){
                        echo "<tr ";
                                switch ($myrow[2]) {
                                                case 1:
                                                        echo "class=\"table-success\"";
                                                        break;
                                                case 2:
                                                        echo "class=\"table-danger\"";
                                                        break;
                                                case 3:
                                                        echo "class=\"table-secondary\"";
                                                        break;
                                        };
						
								echo ">
                                <td style=\"display:none\">". $myrow[0]. "</td> <!-- id -->
                                <td colspan=\"2\">". $myrow[1]. "</td> <!--  apellido -->
                                <td style=\"display:none\">". $myrow[2]. "</td> <!--  tipo -->
                                <td  colspan=\"2\">". $myrow[3]. "</td></td> <!--  detalle -->
                                <td>". $myrow[4]. "</td> <!--  entrada -->
                                <td style=\"display:none\">". $myrow[5]. "</td><!--  prometido -->
                                <td style=\"display:none\">". $myrow[6]. "</td><!--  salida -->
                                <td "; /* entrega */
                                        switch ($myrow[7]) {
                                                case 1:
                                                        echo "<i class=\"bi bi-hand-index fs-2 text-danger\"></i";
                                                        break;
                                                case 2:
                                                        echo "<i class=\"bi bi-hand-index fs-2 text-warning\"></i";
                                                        break;
                                                case 3:
                                                        echo "<i class=\"bi bi-hand-index fs-2 text-success\"></i";
                                                        break;
                                        };
                                            echo "></td>
                                                <td "; /* produccion */
                                                        switch ($myrow[8]) {
                                                                case 1:
                                                                        echo "<i class=\"bi bi-gear-fill fs-2 text-danger\"></i";
                                                                        break;
                                                                case 2:
                                                                        echo "<i class=\"bi bi-gear-fill fs-2 text-warning\"></i";
                                                                        break;
                                                                case 3:
                                                                        echo "<i class=\"bi bi-gear-fill fs-2 text-success\"></i";
                                                                        break;
                                                        };
                                                echo "></td>
                                                <td "; /* pago */
                                                        switch ($myrow[9]) {
                                                                case 1:
                                                                        echo "<i class=\"bi bi-cash fs-2 text-danger\"></i";
                                                                        break;
                                                                case 2:
                                                                        echo "<i class=\"bi bi-cash fs-2 text-warning\"></i";
                                                                        break;
                                                                case 3:
                                                                        echo "<i class=\"bi bi-cash fs-2 text-success\"></i";
                                                                        break;
                                                            };
                                                    echo "></td>
                                                    <td>". $myrow[10]. "</td><!-- Monto Pagado-->
                                                    <td>". $myrow[11]. "</td><!-- Monto-->
                                                    <td>". $myrow[12]. "</td><!-- Saldo-->
                                                    <td>";
                                                    switch ($myrow[13]) {
                                                        case 1:
                                                            echo $medioPago[1];
                                                            break;
                                                        case 2:
                                                            echo $medioPago[2];
                                                            break;
                                                        case 3:
                                                            echo $medioPago[3];
                                                            break;
                                                        case 4:
                                                            echo $medioPago[4];
                                                            break;;
                                                        // ... otros casos ...
                                                        default:
                                                                
                                                            echo "Medio de pago no encontrado";
                                                        };
                                                        echo "</td>
                                                    <td  style=\"display:none\">"; /*--MedioPago--*/
                                                    switch ($myrow[14]) {
                                                        case 1:
                                                            echo $origen[1];
                                                            break;
                                                        case 2:
                                                            echo $origen[2];
                                                            break;
                                                        case 3:
                                                            echo $origen[3];
                                                            break;
                                                        case 4:
                                                            echo $origen[4];
                                                            break;
                                                        // ... otros casos ...
                                                        default:
                                                            echo "origen no encontrado";
                                                        };
                                                        echo "</td>
                                                        <td><i class=\" bi bi-pencil fs-2 text-danger editarPedido \" style=\"cursor:pointer\"></i>
                                                    </td>
                                                </tr>";
                        };		
                                                    
?>
  </table>	
		</div>
         
		 
		 <div class="col-5  rounded" id="panelMuestraPedido" >
		 </div>
	
	</div>
		 
      
   
        
       
<script>

	$('.busqueda').click(function(){
		v_cadena = $(this).val();
		
		v_url = 'pedidos/ajaxVerPedidos.php?opcion=buscar=' + v_cadena;
		//alert(v_url);
		$('#panelMuestraPedido').load(v_url);
		
		
	});
</script>
	