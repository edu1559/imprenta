
<?php

/* puede traer un idPedido o nada (registro nuevo)*/
		include_once ('../conexion.php');
		$conn = conectar();

/* array contactos */

		$contactos = array();
		$sql = "select id,apellido,nombre 
				from contactos 
                order by apellido";
		$result = mysqli_query($conn,$sql);
		   
        while($myrow = mysqli_fetch_assoc($result)){
            $contactos[$myrow['id']] = $myrow['apellido']. ' '. $myrow['nombre'];
        };


/* array origen */

        $origen = array();
		$sql = "select id,origen 
				from origen";

		$result = mysqli_query($conn,$sql);
		   
        while($myrow = mysqli_fetch_assoc($result)){
            $origen[$myrow['id']] = $myrow['origen'];
        };


/* array tipoPedido */

		$tipoPedido = array();
		$sql = "select id,tipo 
				from tipoPedido";

		$result = mysqli_query($conn,$sql);

        while($myrow = mysqli_fetch_assoc($result)){
            $tipoPedido[$myrow['id']] = $myrow['tipo'];
        };  

	
/* array medioPago */

		$medioPago = array();
		$sql = "select id,medio 
				from mediosPago";

		$result = mysqli_query($conn,$sql);

        while($myrow = mysqli_fetch_assoc($result)){
            $medioPago[$myrow['id']] = $myrow['medio'];
        };  

/* Recupero valores del pedido si viene un idPedido*/

   if(isset($_GET['idPedido'])){
            $idPedido = $_GET['idPedido'];
            


    $sql = "select  idContacto,
                    idTipoPedido,
                    detalle,
                    observaciones, 
                    monto, 
                    montoPagado,
                    entrada, 
                    salida,
                    estadoPago,
                    estadoEntrega,
                    estadoProduccion, 
                    idTipoPedido,
                    idOrigen,
                    idMedioPago,
                    prometido,
                    idUsuario
            from pedidos 
            where id=" . $idPedido;
  //   echo $sql;

    $result = mysqli_query($conn,$sql);
   
    // Usamos el while para recorrer el resultado

        while($myrow = mysqli_fetch_assoc($result)){
            // Accedemos a los datos usando el nombre de la columna como clave del array
            $idContacto      = $myrow['idContacto'];
            $idTipoPedido    = $myrow['idTipoPedido'];
            $detalle         = $myrow['detalle'];
            $observaciones   = $myrow['observaciones'];
            $monto           = $myrow['monto'];
            $montoPagado     = $myrow['montoPagado'];
            $estadoPago      = $myrow['estadoPago'];
            $estadoEntrega   = $myrow['estadoEntrega'];
            $estadoProduccion = $myrow['estadoProduccion'];
            $idOrigen        = $myrow['idOrigen'];
/*
            echo "<hr> 
                   idOrigen: ". $idOrigen.
                 " idTipoPedido: ". $idTipoPedido. 
                 " monto: ". $monto. 
                 " montoPagado: " . $montoPagado. 
                 " estadoPago: ". $estadoPago. 
                 " estadoEntrega: ". $estadoEntrega. 
                 " estadoProduccion: ". $estadoProduccion;
  */         
            
        };
 };
    ?> 

 
 
<!-- encabezado-->

<div class="modal-header bg-success text-white"> 
    <h5 class="modal-title ">
        <?php
            if(isset($idPedido)){
                echo "Editar Pedido Nº ". $idPedido;
                echo "<input type=\"hidden\" id=\"nroPedido\" value=\"". $idPedido. "\">";
            }else{
                echo "Nuevo Pedido";
            };
        ?>

    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
</div>


<!-- body-->

<div class="modal-body"> 
    <div class="container-fluid  p-5 border bg-light">
        
 <div class="container p-3 bg-success rounded" id="formPedidos"> 
	
    
    <!-- Contacto, Origen y Tipo -->
    <div class="row mb-3">
        <div class="col-md-6 col-12 mb-3 mb-md-0">
            <label for="selContacto" class="form-label text-white">Contacto:</label>
            <?php 
                if(isset($idPedido)){
                    echo "<select class='form-select form-control' id='selContacto' style='width:100%'>
                            <option value='$idContacto'>$contactos[$idContacto]</option>";                   
                }else{
               //   echo "<input type='text' id='selContacto' class='form-control form-input'>";
               
                echo "<select class='form-select form-control' id='selContacto' style='width:100%'>";
                     foreach ($contactos as $clave => $valor){
                        echo "<option value=$clave>$valor</option>";
                      };
               
                };
                echo "</select>";       
            ?>    
        </div>
        
        <div class="col-md-3 col-6 mb-3 mb-md-0">
            <label for="selIdOrigen" class="form-label text-white">Origen:</label>
            <select class="form-control form-select" id="selIdOrigen">
                <?php 
                    foreach ($origen as $clave => $valor) {
                    echo "<option value='$clave'"; 
                        if(isset($idOrigen) && ($idOrigen == $clave)){
                                echo " selected ";
                            };
                    echo ">$valor</option>";
                    }; 
                ?>	
            </select>
        </div>
        
        <div class="col-md-3 col-6">
            <label for="selIdTipo" class="form-label text-white">Tipo de Pedido:</label>
            <select class="form-control form-select" id="selIdTipo">
                
                <?php foreach ($tipoPedido as $clave => $valor) {
                    echo "<option value=$clave ";
                    if(isset($idPedido) && ($idTipoPedido == $clave)){
                        echo ' selected ';
                    };
                    echo  ">". $valor. "</option>";
                }; ?>	
            </select>
        </div>
    </div>
    
    <!-- detalle -->
    <div class="row mb-3">
        <div class="col-12">
            <label for="detalle" class="form-label text-white">Detalle:</label>
            <textarea class="form-control" id="txtDetalle" placeholder="Ingrese Detalle" 
            name="detalle" rows="3"><?php 
                if(isset($detalle)){ echo $detalle;}?></textarea>
        </div>
    </div>
    
    <!-- observaciones -->
    <div class="row mb-3">
        <div class="col-12">
            <label for="observaciones" class="form-label text-white">Observaciones:</label>
            <textarea class="form-control" id="txtObservaciones" placeholder="Ingrese alguna observacion" 
                name="observaciones" rows="2"><?php if(isset($observaciones)){ echo $observaciones;}?></textarea>
        </div>
    </div>
    
    <!-- Estados -->
    <div class="row mb-3">
        <div class="col-md-4 col-12 mb-3">
            <div class="card bg-transparent border-light">
                <div class="card-body">
                    <label class="form-label text-white text-center h5 d-block">Entrega:</label>
                    <div class="form-check">
                        <input type="radio" id="sinEntregar" name="estadoEntrega" value="1" class="form-check-input" checked
                        <?php if(isset($estadoEntrega) && ($estadoEntrega == 1)){ echo " checked=\"checked\"";}; ?>>
                        <label for="sinEntregar" class="form-check-label text-white">Sin Entregar</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" id="parcialmenteEntregado" name="estadoEntrega" value="2" class="form-check-input"
                        <?php if(isset($estadoEntrega) && ($estadoEntrega == 2)){ echo " checked=\"checked\"";}; ?>>
                        <label for="parcialmenteEntregado" class="form-check-label text-white">Parcialmente</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" id="entregado" name="estadoEntrega" value="3" class="form-check-input"
                        <?php if(isset($estadoEntrega) && ($estadoEntrega == 3)){ echo " checked=\"checked\"";}; ?>>
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
                        <input type="radio" id="sinComenzar" name="estadoProduccion" value="1" class="form-check-input" checked
                        <?php if(isset($estadoProduccion) && ($estadoProduccion == 1)){ echo " checked=\"checked\"";}; ?>>
                        <label for="sinComenzar" class="form-check-label text-white">Sin Comenzar</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" id="parcialmente" name="estadoProduccion" value="2" class="form-check-input" 
                        <?php if(isset($estadoProduccion) && ($estadoProduccion == 2)){ echo " checked=\"checked\"";}; ?>>
                        <label for="parcialmente" class="form-check-label text-white">Parcialmente</label>
                    </div>
                    <div class="form-check">
                        <input type="radio" id="terminado" name="estadoProduccion" value="3" class="form-check-input" 
                        <?php if(isset($estadoProduccion) && ($estadoProduccion == 3)){ echo " checked=\"checked\"";}; ?>>
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
            <input type="text" class="form-control text-danger" id="inpMonto" placeholder="$" name="impMonto"
            <?php if(isset($monto)){ echo  " value= $monto ";} ?> >
        </div>
        
        <div class="col-md-3 col-12 mb-3">
            <label for="impMontoPagado" class="form-label text-white">Pago Parcial:</label>
            <input type="hidden" id="inpMontoPagadoOriginal" value="<?php echo $montoPagado; ?>">
            <input type="text" class="form-control" id="inpMontoPagado" placeholder="Ingrese Seña" name="impSenia" style="color:red"
            <?php if(isset($montoPagado)){ echo  " value= $montoPagado ";} ?> >
        </div>
        
        <div class="col-md-3 col-12 mb-3">
            <label for="selMedioPago" class="form-label text-white">Medio Pago:</label>
            <select class="form-control form-select" id="selMedioPago">
                <?php foreach ($medioPago as $clave => $valor) {
                    echo "<option value=\"".$clave. "\">".$valor."</option>";
                }; 
                ?>	
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
   <?php if(isset($idPedido)){ ?>

      
        <div class="col-md-6 col-12 text-md-end text-center">
            <button type="button" id="btnActualizarPedido" class="btn btn-warning btn-lg w-100 w-md-auto">Actualizar</button>
        </div>
    <?php }else{ ?>


        <div class="col-md-6 col-12 text-md-end text-center">
            <button type="button" id="btnGuardarPedido" class="btn btn-warning btn-lg w-100 w-md-auto">Guardar</button>
        </div>
    <?php }; ?>
    </div>
</div>	
      </div>
                   
</div>
</div>

<script>

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
     
$(document).off('click', '#btnActualizarPedido').on('click', '#btnActualizarPedido', function(e) {
    e.preventDefault(); // Evita cualquier acción por defecto

    // Recolección de variables (Asegúrate que los IDs existan)
    var datos = {
        opcion: 'actualizarPedido',
        idPedido: $('#nroPedido').val(),
        idContacto: $('#selContacto').val(), // O selContacto, verifica tu HTML
        idTipoPedido: $('#selIdTipo').val(),
        idOrigen: $('#selIdOrigen').val(),
        detalle: $('#txtDetalle').val(),
        observaciones: $('#txtObservaciones').val(),
        idEstadoEntrega: $('input[name="estadoEntrega"]:checked').val(),
        idEstadoProduccion: $('input[name="estadoProduccion"]:checked').val(),
        idEstadoPago: $('input[name="estadoPago"]:checked').val(),
        prometido: $('#inpPrometido').val(),
        montoPagado: $('#inpMontoPagado').val(),
        montoPagadoOriginal: $('#inpMontoPagadoOriginal').val(),
        monto: $('#inpMonto').val(),
        idMedioPago: $('#selMedioPago').val()
    };

    $.ajax({
        url: 'pedidos/ajaxPedidos.php',
        type: 'POST', // Cambia a POST si prefieres más seguridad
        data: datos,
        success: function(response) {
            // 1. Inyectamos la respuesta en mensajes
            $('#mensajes').html(response);

            // 2. Cerramos el modal de forma segura
            $('#modalUniversal').modal('hide');
            
            // 3. Limpieza manual por si Bootstrap falla (muy común)
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();

            // 4. Refrescamos el listado
            $('#contenido').load('pedidos/pedidos.php');
        },
        error: function() {
            alert("Error al procesar la actualización");
        }
    });
});

$(document).off('click', '#btnGuardarPedido').on('click', '#btnGuardarPedido', function(e) {
    e.preventDefault(); // Evita cualquier acción por defecto

    // Recolección de variables (Asegúrate que los IDs existan)
    var datos = {
        opcion: 'agregarPedido',
      //  idPedido: $('#nroPedido').val(),
        idContacto: $('#selContacto').val(), // O selContacto, verifica tu HTML
        idTipoPedido: $('#selIdTipo').val(),
        idOrigen: $('#selIdOrigen').val(),
        detalle: $('#txtDetalle').val(),
        observaciones: $('#txtObservaciones').val(),
        idEstadoEntrega: $('input[name="estadoEntrega"]:checked').val(),
        idEstadoProduccion: $('input[name="estadoProduccion"]:checked').val(),
        idEstadoPago: $('input[name="estadoPago"]:checked').val(),
        prometido: $('#inpPrometido').val(),
        montoPagado: $('#inpMontoPagado').val(),
        monto: $('#inpMonto').val(),
        idMedioPago: $('#selMedioPago').val()
    };

    $.ajax({
        url: 'pedidos/ajaxPedidos.php',
        type: 'POST', // Cambia a POST si prefieres más seguridad
        data: datos,
        success: function(response) {
            // 1. Inyectamos la respuesta en mensajes
            $('#mensajes').html(response);

            // 2. Cerramos el modal de forma segura
            $('#modalUniversal').modal('hide');
            
            // 3. Limpieza manual por si Bootstrap falla (muy común)
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();

            // 4. Refrescamos el listado
            $('#contenido').load('pedidos/pedidos.php');
        },
        error: function() {
            alert("Error al procesar la actualización");
        }
    });
});



    $('#checkPagadoEntregado').change(function() {
    // Verificamos si el checkbox está marcado
    if ($(this).is(':checked')) {
        // Selecciona los radios con valor 3 y los marca como seleccionados
        $('input[name="estadoEntrega"][value="3"]').prop('checked', true);
        $('input[name="estadoProduccion"][value="3"]').prop('checked', true);
        $('input[name="estadoPago"][value="3"]').prop('checked', true);
    } else {
        // Si el checkbox es desmarcado, puedes realizar alguna otra acción aquí (opcional)
        // Por ejemplo, desmarcar los radios
        $('input[name="estadoEntrega"], input[name="estadoProduccion"], input[name="estadoPago"]').prop('checked', false);
		$('input[name="estadoEntrega"][value="1"]').prop('checked', true);
        $('input[name="estadoProduccion"][value="1"]').prop('checked', true);
        $('input[name="estadoPago"][value="1"]').prop('checked', true);
    }
});

$('#selContacto').select2({
        dropdownParent: $('#modalUniversal'), 
        placeholder: "Seleccione un cliente",
        width: '100%', // Para que ocupe todo el ancho del div
        allowClear: true
    });
	

</script>

