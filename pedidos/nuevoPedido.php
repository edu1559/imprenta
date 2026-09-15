
<?php
		  include_once ('../conexion.php');
		  $conn = conectar();

/* array contactos */

		$contactos = array();
		$sql = "select id,apellido,nombre 
				from contactos order by apellido";
		$result = mysqli_query($conn,$sql);
		   
		   while($myrow = mysqli_fetch_row($result)){
				$contactos[$myrow[0]] = $myrow[1]. ' '. $myrow[2];
		   };
/* array origen */

		$sql = "select id,origen 
				from origen";

		$result = mysqli_query($conn,$sql);
		   
			while($myrow = mysqli_fetch_row($result)){
				$origen[$myrow[0]] = $myrow[1];
			};


/* array tipoPedido */

		$tipoPedido = array();
		$sql = "select id,tipo 
				from tipoPedido";

		$result = mysqli_query($conn,$sql);

			while($myrow = mysqli_fetch_row($result)){
				$tipoPedido[$myrow[0]] = $myrow[1];
			};  

	
/* array medioPago */

		$medioPago = array();
		$sql = "select id,medio 
				from mediosPago";

		$result = mysqli_query($conn,$sql);

			while($myrow = mysqli_fetch_row($result)){
				$medioPago[$myrow[0]] = $myrow[1];
			};  
 
?> 

 
 
<!-- titulo--> 

<div class="container  mt-5 p-3 display-6 text-center">
    Nuevo Pedido <i class="bi bi-clipboard2 fs-1 text-success"></i>
</div>


<!-- container titulo-->

 <div class="container-fluid  p-5 border bg-light">
              
 <div class="container p-3 bg-success rounded" id="formPedidos"> 
    <!-- titulo -->	 
    <div class="container-fluid h3 text-white text-center mb-4">Agregar Pedido</div>	
    
    <!-- Contacto, Origen y Tipo -->
    <div class="row mb-3">
        <div class="col-md-6 col-12 mb-3 mb-md-0">
            <label for="selContacto" class="form-label text-white">Contacto:</label>
            <select class="form-select form-control" id="selContacto" style="width:100%">
                <?php 
                    foreach ($contactos as $clave => $valor){
                        echo "<option value='$clave'>$valor</option>";
                    };
                ?>    
            </select>
        </div>
        
        <div class="col-md-3 col-6 mb-3 mb-md-0">
            <label for="selIdOrigen" class="form-label text-white">Origen:</label>
            <select class="form-control form-select" id="selIdOrigen">
                <?php foreach ($origen as $clave => $valor) {
                    echo "<option value=\"".$clave. "\">".$valor."</option>";
                }; ?>	
            </select>
        </div>
        
        <div class="col-md-3 col-6">
            <label for="selIdTipo" class="form-label text-white">Tipo de Pedido:</label>
            <select class="form-control form-select" id="selIdTipo">
                <?php foreach ($tipoPedido as $clave => $valor) {
                    echo "<option value=\"".$clave. "\">".$valor."</option>";
                }; ?>	
            </select>
        </div>
    </div>
    
    <!-- detalle -->
    <div class="row mb-3">
        <div class="col-12">
            <label for="detalle" class="form-label text-white">Detalle:</label>
            <textarea class="form-control" id="txtDetalle" placeholder="Ingrese Detalle" name="detalle" rows="3"></textarea>
        </div>
    </div>
    
    <!-- observaciones -->
    <div class="row mb-3">
        <div class="col-12">
            <label for="observaciones" class="form-label text-white">Observaciones:</label>
            <textarea class="form-control" id="txtObservaciones" placeholder="Ingrese alguna observacion" name="observaciones" rows="2"></textarea>
        </div>
    </div>
    
    <!-- Estados -->
    <div class="row mb-3">
        <div class="col-md-4 col-12 mb-3">
            <div class="card bg-transparent border-light">
                <div class="card-body">
                    <label class="form-label text-white text-center h5 d-block">Entrega:</label>
                    <div class="form-check">
                        <input type="radio" id="sinEntregar" name="estadoEntrega" value="1" class="form-check-input" checked>
                        <label for="sinEntregar" class="form-check-label text-white">Sin Entregar</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" id="parcialmenteEntregado" name="estadoEntrega" value="2" class="form-check-input">
                        <label for="parcialmenteEntregado" class="form-check-label text-white">Parcialmente</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" id="entregado" name="estadoEntrega" value="3" class="form-check-input">
                        <label for="entregado" class="form-check-label text-white">Entregado</label>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-12 mb-3">
            <div class="card bg-transparent border-light">
                <div class="card-body">
                    <label class="form-label text-white text-center h5 d-block">Producción:</label>
                    <div class="form-check">
                        <input type="radio" id="sinComenzar" name="estadoProduccion" value="1" class="form-check-input" checked>
                        <label for="sinComenzar" class="form-check-label text-white">Sin Comenzar</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" id="parcialmente" name="estadoProduccion" value="2" class="form-check-input">
                        <label for="parcialmente" class="form-check-label text-white">Parcialmente</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" id="terminado" name="estadoProduccion" value="3" class="form-check-input">
                        <label for="terminado" class="form-check-label text-white">Terminado</label>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 col-12">
            <div class="card bg-transparent border-light">
                <div class="card-body">
                    <label class="form-label text-white text-center h5 d-block">Pago:</label>
                    <div class="form-check">
                        <input type="radio" id="sinPagar" name="estadoPago" value="1" class="form-check-input" checked>
                        <label for="sinPagar" class="form-check-label text-white">Sin Pagar</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" id="parcialmentePagado" name="estadoPago" value="2" class="form-check-input">
                        <label for="parcialmentePagado" class="form-check-label text-white">Parcialmente</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" id="pagado" name="estadoPago" value="3" class="form-check-input">
                        <label for="pagado" class="form-check-label text-white">Pagado</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Fecha, pagos y otros campos -->
    <div class="row mb-3">
        <div class="col-md-3 col-12 mb-3">
            <label for="inpPrometido" class="form-label text-white">Prometido:</label>
            <input type="date" class="form-control" id="inpPrometido" value="<?php echo date("Y-m-d");?>">
        </div>
        
        <div class="col-md-3 col-12 mb-3">
            <label for="inpMonto" class="form-label text-white">Monto en $:</label>
            <input type="text" class="form-control text-danger" id="inpMonto" placeholder="$" name="impMonto">
        </div>
        
        <div class="col-md-3 col-12 mb-3">
            <label for="impMontoPagado" class="form-label text-white">Pago Parcial:</label>
            <input type="text" class="form-control" id="inpMontoPagado" placeholder="Ingrese Seña" name="impSenia" style="color:red">
        </div>
        
        <div class="col-md-3 col-12 mb-3">
            <label for="selMedioPago" class="form-label text-white">Medio Pago:</label>
            <select class="form-control form-select" id="selMedioPago">
                <?php foreach ($medioPago as $clave => $valor) {
                    echo "<option value=\"".$clave. "\">".$valor."</option>";
                }; ?>	
            </select>
        </div>
    </div>
    
    <div class="row mb-3 align-items-center">
        <div class="col-md-6 col-12 mb-3 mb-md-0">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="checkPagadoEntregado">
                <label class="form-check-label text-white" for="checkPagadoEntregado">Entregado / Terminado / Pagado</label>
            </div>
        </div>
        
        <div class="col-md-6 col-12 text-md-end text-center">
            <button type="button" id="btnGuardarPedido" class="btn btn-warning btn-lg w-100 w-md-auto">Guardar</button>
        </div>
    </div>
</div>	
      </div>
                   
</div>


<script>
/*
    $('#selCliente').select2({
        minimumInputLength: 3, // Inicia la búsqueda después de 3 caracteres
        ajax: {
            url: 'pedidos/ajaxNuevoPedido.php?opcion=buscaContacto', // Tu script PHP que buscará en la DB
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term // El texto que el usuario escribió
                };
            },
            processResults: function (data) {
                return {
                    results: data.results
                };
            }
        }
    });     
*/
	$('#selIdTipo').change(function() {
		var selectedValue = $(this).val();
		var form = $('#formPedidos');

		// limpio clases anteriores
		form.removeClass('bg-success bg-danger bg-secondary');

		// Aplicar clase de color según el valor seleccionado
		switch (selectedValue) {
		  case '1':
			form.addClass('bg-success');
			break;
		  case '2':
			form.addClass('bg-danger');
			break;
		  case '3':
			form.addClass('bg-secondary');
			break;
		  // Agrega más casos según los valores posibles y los colores deseados
		  default:
			// Si no hay coincidencia, puedes aplicar un color por defecto o ninguna clase
			form.addClass('bg-light');
		}
	 });
     
 
    $('#btnGuardarPedido').click(function(){
     
   	  v_idContacto = $('#selContacto').val();
     
      	
      v_idTipo = $('#selIdTipo').val();
	  v_idOrigen = $('#selIdOrigen').val();

        v_detalle = encodeURI($('#txtDetalle').val().trim());
        v_observaciones = encodeURI($('#txtObservaciones').val().trim());

	  
      
      //alert(v_idContacto + ' ' + v_idTipo + ' '+ v_idOrigen + ' ' + v_detalle + ' ' + v_observaciones);
           
            

	  v_estadoEntrega = $('input[name="estadoEntrega"]:checked').val();
	  v_estadoProduccion = $('input[name="estadoProduccion"]:checked').val();
      v_estadoPago = $('input[name="estadoPago"]:checked').val();
		// alert(v_estadoEntrega + ' ' + v_estadoProduccion + ' '+ v_estadoPago);
	
	  v_prometido = $('#inpPrometido').val();
      v_montoPagado = $('#inpMontoPagado').val();
	  v_monto = $('#inpMonto').val();
	  v_idMedioPago = $('#selMedioPago').val();
	    // alert(v_fechaPrometida + ' ' + v_montoPagado + ' '+ v_monto + ' '+ v_idMedioPago);
      
      v_url = "pedidos/ajaxNuevoPedido.php?opcion=agregarPedido&idContacto=" + v_idContacto + "&idTipoPedido=" + v_idTipo +  "&idOrigen=" + v_idOrigen       
                +  "&detalle=" + v_detalle  +  "&observaciones=" + v_observaciones 
				+  "&idEstadoEntrega=" + v_estadoEntrega  +  "&idEstadoProduccion=" + v_estadoProduccion +  "&idEstadoPago=" + v_estadoPago
				+  "&prometido=" + v_prometido +  "&montoPagado=" + v_montoPagado +  "&monto=" + v_monto + "&idMedioPago=" + v_idMedioPago;	
     alert(v_url);
      
 
    $('#mensaje').load(v_url);
    v_url = 'pedidos/pedidos.php';
    $('#contenido').load(v_url);
        
   
    });




</script>

