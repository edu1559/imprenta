<?php
include_once ('../conexion.php');
$conn = conectar();   


function obtenerDiccionario($conn, $tabla, $columna) {
    $arr = [];
    $res = mysqli_query($conn, "SELECT id, $columna FROM $tabla");
    while($row = mysqli_fetch_row($res)) $arr[$row[0]] = $row[1];
    return $arr;
}

$origen = obtenerDiccionario($conn, 'origen', 'origen');
$tipoPedido = obtenerDiccionario($conn, 'tipoPedido', 'tipo');
$medioPago = obtenerDiccionario($conn, 'mediosPago', 'medio');
?>


<!-- titulo-->
<div class="container  mt-2 p-3  text-center h3">
    Pedidos <i class="bi bi-clipboard2 fs-1 text-success"></i>
</div>


 <!-- panelGeneral -->

<div class="container-fluid border bg-light ms-5 me-5">

<div class="row mb-3 mt-3">
    <div class="col-md-6">
        <label class="form-label fw-bold small text-muted mb-1">Buscar Cliente — para cobrar, entregar o ver su historial</label>
        <select class="form-select" id="selBuscarClienteRapido" style="width:100%">
            <option value="">Escriba apellido, nombre o teléfono...</option>
        </select>
    </div>
</div>

<div class="row align-items-center mb-5 me-5" style="width:90">
   

        <div class="col-md-1 d-flex justify-content-end">
                    <button class='btn btn-success'
                            id='btnPedidoNuevo'
                            title='Nuevo Pedido'
                            data-bs-toggle='modal'
                            data-bs-target='#modalUniversal'>
                        <i class="bi bi-clipboard2"></i> Nuevo Pedido
                    </button>
        </div>

         <div class="col-md-1 d-flex justify-content-end">
                    <button class='btn btn-danger'
                            id='btnContactoNuevo'
                            title='Nuevo Contacto'
                            data-bs-toggle='modal'
                            data-bs-target='#modalUniversal'>
                        <i class="bi bi-person"></i> Nuevo Contacto
                    </button>
        </div>


 <!-- botones de búsqueda  -->
  
        <div class="col-md-5 container-fluid mb-3"> 
            <div class="input-group form-inline d-flex">  
                <button type="button" class="btn btn-outline-success btnOpciones" data-valor="ultimos">Últimos</button>
                <button type="button" class="btn btn-outline-primary btnOpciones"  data-valor="sinTerminar">Sin Terminar</button>
                <button type="button" class="btn btn-outline-danger btnOpciones"  data-valor="sinPagar">Sin Pagar</button>
                <button type="button" class="btn btn-outline-primary btnOpciones"  data-valor="sinEntregar">Sin Entregar</button>
            </div>
        </div>



 <!-- panelbusca -->

      

        <div class="col-md-3" id="panelesPedidos">
            <div class="rounded" id="panelListaPedidos">
                <div class="input-group mb-3">
                    <span class="input-group-text bg-success text-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="buscar" class="form-control" placeholder="Pedido # o Apellido..." aria-label="Buscar">
                    <button class="btn btn-success" type="button" id="btnBuscarPedidos">Buscar</button>
                </div>
            </div>
        </div>


<!-- tblPedidos-->  

    <div id="tblContenedor" class="container text-left" >
 
    <table class="table table-striped table-hover rounded me-5 mt-3" id="tblPedidos">
    <thead>
            <tr class="table text-bg-color table-dark">
                    <th >id</th>
                    <th colspan="2">apellido nombre</th>
                    <th colspan="2">detalle</th>
                    <th title="entrada">Ent</th>
                    <th title="Prom">Prom.</th>
                    <th style="display:none">salida</th>
                    <th title='Entrega'>Ent.</th>
                    <th title='Produccion'>Prod.</th>
                    <th title='Pago'>Pago</th>
                    <th title='Monto Pagado'>Pagado</th>
                    <th title='Monto Total'>Total</th>
                    <th>Saldo</th>
                    <th>MedioPago</th>
                    <th style="display:none">origen</th>
                    <th style="display:none">Cargó</th>
                    <th style="display:none">cuit</th>
                    <th style="display:none">idContacto</th>
                    <th title='Editar Pedido'>editar</th>
                    <th title='Orden'>Orden</th>
                    <th title='Cargo la orden'>Cargo</th>
                    <th>Terminado</th>         
            </tr>
    </thead>
	
    
    <?php 

    
           //    include_once ('../conexion.php');
 

    $sql = "select p.id,
                p.idContacto,
                p.idTipoPedido,
                concat(c.apellido,' ',c.nombre) as contacto,
                left (p.detalle,50),
                date_format(p.entrada,'%d/%m/%y') as entrada,
                date_format(p.prometido,'%d/%m/%y') as prometido,
                p.salida,
                p.estadoEntrega,
                p.estadoProduccion,
                p.estadoPago,
                p.montoPagado,
                p.monto,
                (p.monto - p.montoPagado) as saldo,
                p.idMedioPago,
                p.idOrigen,
                u.usuario 
        from pedidos p 
                inner join contactos c 
                    on p.idContacto = c.id 
                inner join usuarios u 
                    on u.id = p.idUsuario";
    
                
// FILTRO DE BÚSQUEDA (Cadena o ID)
if(isset($_GET['cadena']) && !empty($_GET['cadena'])){
    $cadena = mysqli_real_escape_string($conn, $_GET['cadena']);
    
    // Si la cadena es un número, buscamos por ID exacto, sino por Apellido
    if(is_numeric($cadena)){
        $sql .= " WHERE p.id = $cadena ";
    } else {
        $sql .= " WHERE c.apellido LIKE '%$cadena%' ";
    }
}

// BOTONES DE OPCIONES (Se agregan con AND si ya hay un WHERE, o con WHERE si no lo hay)
if(isset($_GET['opcion'])){
    $opcion = $_GET['opcion'];
    // Determinamos si ya pusimos el WHERE arriba
    $prefijo = (strpos($sql, 'WHERE') !== false) ? " AND " : " WHERE ";

    switch ($opcion){
        case 'sinPagar':
            $sql .= $prefijo . " p.estadoPago in (1,2) ";
        break;
        case 'sinEntregar':
            $sql .= $prefijo . " p.estadoEntrega in (1,2) ";
        break;
        case 'sinTerminar':
            $sql .= $prefijo . " p.estadoProduccion in (1,2) ";
        break;
        // 'ultimos' no necesita WHERE extra, el LIMIT al final se encarga
    }
}
                        
    $sql .= " order by id desc limit 30 ";
    //echo $sql;   
    
    $result = mysqli_query($conn,$sql);
    
           
            
    while($myrow = mysqli_fetch_row($result)){
            echo "<tr><td ";  //tipo de pedido
            switch ($myrow[2]) {
                case 1:
                        echo " style= 'background-color:#dfd' >";
                        break;
                case 2:
                        echo " style= 'background-color:#fdd' >";
                        break;
                case 3:
                        echo " style='background-color:#ffd' >";
                        break;
        };

        echo $myrow[0]. "</td> <!-- id -->
        <td style=\"display:none\">". $myrow[1]. "</td> <!-- idContacto -->
        <td style=\"display:none\">". $myrow[2]. "</td> <!-- idTipoPedido -->
        <td colspan=\"2\">". $myrow[3]. "</td> <!--  apellido  y nombre--> 
        <td  colspan=\"2\">". $myrow[4]. "</td> <!--  detalle -->
        <td >". $myrow[5]. "</td> <!--  entrada -->
        <td>". $myrow[6]. "</td><!--  prometido -->
        <td style=\"display:none\">". $myrow[7]. "</td><!--  salida -->
        <td  data-entrega=\"$myrow[8]\">"; /* entrega */
        switch ($myrow[8]) {
                case 1:
                        echo "<button class='btn btn-outline-danger btnEstado' data-tipo='entrega' data-id='$myrow[0]' data-actual='$myrow[8]'> <i class=\"bi bi-hand-index \"></i></button>";
                        break;
                case 2:
                        echo "<button class='btn btn-outline-warning  btnEstado' data-tipo='entrega' data-id='$myrow[0]' data-actual='$myrow[8]'> <i class=\"bi bi-hand-index \"></i></button>";
                        break;
                case 3:
                        echo "<button class='btn btn-outline-success  btnEstado' data-tipo='entrega' data-id='$myrow[0]' data-actual='$myrow[8]'> <i class=\"bi bi-hand-index  \"></i></button>";
                        break;
        };
            echo "</td>
                <td data-produccion=\"$myrow[9]\">"; /* produccion */
        switch ($myrow[9]) {
                case 1:
                        echo "<button class='btn btn-outline-danger btnEstado' data-tipo='Produccion'  data-id='$myrow[0]' data-actual='$myrow[9]'> <i class=\"bi bi-gear-fill  \"></i></button>";
                        break;
                case 2:
                        echo "<button class='btn btn-outline-warning  btnEstado' data-tipo='Produccion'   data-id='$myrow[0]' data-actual='$myrow[9]'> <i class=\"bi bi-gear-fill  \"></i></button>";
                        break;
                case 3:
                        echo "<button class='btn btn-outline-success  btnEstado' data-tipo='Produccion'   data-id='$myrow[0]' data-actual='$myrow[9]'> <i class=\"bi bi-gear-fill  \"></i></button>";
                        break;
        };
                echo "</td>
                <td data-pago=\"$myrow[10]\" >";  /* pago */
        switch ($myrow[10]) {
                case 1:
                        echo "<button class='btn btn-outline-danger  btnPagos' data-tipo='Pago'  data-id='$myrow[0]' data-actual='$myrow[10]'><i class=\"bi bi-cash \"></i></button>";
                        break;
                case 2:
                        echo "<button class='btn btn-outline-warning btnPagos' data-tipo='Pago' data-id='$myrow[0]' data-actual='$myrow[10]'><i class=\"bi bi-cash \"></i></button>";
                        break;
                case 3:
                        echo "<button class='btn btn-outline-success btnPagos' data-tipo='Pago' data-id='$myrow[0]' data-actual='$myrow[10]'><i class=\"bi bi-cash \"></i></button>";
                        break;
            };
                    echo "</td>
                    <td data-montoPagadoOriginal =\"$myrow[11]\">". $myrow[11]. "</td><!-- Monto Pagado-->
                    <td>". $myrow[12]. "</td><!-- Monto-->
                    <td>". $myrow[13]. "</td><!-- Saldo-->
                    <td data-medio=\"$myrow[14]\">";
                    switch ($myrow[14]) {
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
                    switch ($myrow[15]) {
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

                        echo "</td><td>";

                  
                    // Detectamos si el pedido está 100% terminado myrow[8] = Entrega, myrow[9] = Produccion, myrow[10] = Pago
                        $estaTerminado = ($myrow[8] == 3 && $myrow[9] == 3 && $myrow[10] == 3);
                   
                        if (!$estaTerminado) {
                            // Solo mostramos el botón si NO está terminado
                            echo "<button class='btn btn-outline-dark btnPedidoEditar'> <i class='bi bi-pencil'> </i></button>";
                        } else {
                            // Opcional: Mostrar un candado o dejar vacío
                            echo "<i class='bi bi-lock-fill text-muted' title='Pedido Cerrado'></i>";
                        }
                        echo "</td>";

                                            
                        
                        echo "<td><button  class='btn btn-outline-dark btnImprimirOrden'> <i class='bi bi-printer'> </i></button></td>
                       <td>$myrow[16]</td>";
                        // Columna Terminado (la que ya tenías con el botón 'Cerrar')
                       
                        echo "<td class='terminado'>";
                        if (isset($estaTerminado)) {
                            echo "<span class='badge bg-success'>Terminado</span>";
                        } else {
                            echo "<button type='button' class='btn btn-success btnCerrar btn-sm'>Cerrar</button>";
                        }
                        echo "</td>";
                        
                        echo "</tr>";
                    }
                    ?>
                                    	
                                                    

    </table>	
</div>
</div> 
		 
		 <div class="col-5  rounded" id="panelMuestraPedido" >
		 </div>
	
	</div>
		 <!--
       <div class="modal fade" id="modalUniversal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
            </div>     
        </div>
       </div>
   
        -->
       
<script>

    // Buscador rápido de cliente: escribís, elegís y va directo a su historial
    // (el caso más frecuente: "el cliente llama para pagar o retirar").
    $('#selBuscarClienteRapido').select2({
        placeholder: 'Escriba apellido, nombre o teléfono...',
        minimumInputLength: 3,
        ajax: {
            url: 'contactos/buscarContactos.php',
            dataType: 'json',
            delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data }; },
            cache: true
        },
        templateResult: function(c) {
            if (!c.id) { return c.text; }
            if (c.id === 'NEW') {
                return '<div class="d-flex align-items-center gap-2 text-warning-emphasis fw-bold px-1 py-1"><i class="bi bi-plus-circle-fill"></i> ' + $('<div>').text(c.text).html() + '</div>';
            }
            var saldo = parseFloat(c.saldo) || 0;
            var saldoTxt = saldo > 0 ? ('Debe $' + saldo.toLocaleString('es-AR')) : 'Al día';
            var saldoClase = saldo > 0 ? 'bg-danger' : 'bg-success';
            return '<div class="d-flex justify-content-between align-items-center px-1 py-1">' +
                     '<div><div class="fw-bold">' + $('<div>').text(c.text).html() + '</div>' +
                     '<div class="small text-muted">' + $('<div>').text(c.telefono || '').html() + ' · ' + (c.pedidos || 0) + ' pedidos</div></div>' +
                     '<span class="badge ' + saldoClase + '">' + saldoTxt + '</span>' +
                   '</div>';
        },
        escapeMarkup: function(m) { return m; }
    });

    $('#selBuscarClienteRapido').on('select2:select', function(e) {
        var data = e.params.data;
        $('#selBuscarClienteRapido').val(null).trigger('change');
        if (data.id === 'NEW') {
            // No tiene sentido dar de alta un cliente sin cargarle un pedido:
            // lo mandamos directo al modal de Nuevo Pedido con el alta inline.
            $('#modalUniversal .modal-content').load('pedidos/modalPedidoNuevo.php', function(){
                $('#modalUniversal').modal('show');
            });
            return;
        }
        abrirModalHistorial(data.id);
    });

 //  Agregar pedido nuevo
    $('#btnPedidoNuevo').on('click', function(){
        v_url = 'pedidos/modalPedidoNuevo.php';
       // alert(v_url);
        $('#modalUniversal .modal-content').load(v_url, function(){
            $('#modalUniversal').modal('show');
        });
    });

    $('#btnContactoNuevo').on('click', function(){
        v_url = 'contactos/modalContactoNuevo.php?id=null';
        // alert(v_url);
        $('#modalUniversal .modal-content').load(v_url, function(){
            $('#modalUniversal').modal('show');
        });
    });

      $('.btnPedidoEditar').on('click', function(){
        var v_idPedido = $(this).closest('tr').find('td:first').text();
        v_url = 'pedidos/modalEditarPedido.php?idPedido=' + v_idPedido ;
       //alert(v_url);
        $('#modalUniversal .modal-content').load(v_url, function(){
            $('#modalUniversal').modal('show');
        });
    });

     $('.btnPagos').on('click', function(){
        var v_idPedido = $(this).closest('tr').find('td:first').text();
        v_url = 'pedidos/modalPedidoPagos.php?idPedido=' + v_idPedido ;
      // alert(v_url);
        $('#modalUniversal .modal-content').load(v_url, function(){
            $('#modalUniversal').modal('show');
        });
    });

    $('.btnImprimirOrden').click(function() {
        var v_idPedido = $(this).closest('tr').find('td:first').text();
        // Abrimos una ventana nueva con el archivo de la orden
        var url = 'pedidos/modalOrden.php?idPedido=' + v_idPedido + '&imprimir=true';
        window.open(url, '_blank', 'width=900,height=800');
    });
    
    
                     
    $('.imprimirPedidoPdf').click(function() {
        var v_idPedido = $(this).closest('tr').find('td:first').text();
        var v_url = 'pedidos/pdfOrden.php?idPedido=' + v_idPedido;

        // Abrir una nueva ventana con las dimensiones especificadas

        window.open(v_url, 'ventana', 'width=480,height=620,top=30,left=299,scrollbars=yes');
    });
    
    $('#btnBuscarPedidos').click(function() {
        
            var v_cadena = $('#buscar').val();
            v_cadena = encodeURI(v_cadena);
      
            v_url = 'pedidos/pedidos.php?cadena=' + v_cadena;
         //   alert(v_url);
                $('#contenido').load(v_url);
              
    });

    $('.btnOpciones').click(function() {
            v_opcion = $(this).data('valor')
            v_url = 'pedidos/pedidos.php?opcion=' + v_opcion;
            $('#contenido').load(v_url); 

    });
   
    $('#tblPedidos tbody tr').each(function() {
            const estadoEntrega = $(this).find('td[data-entrega]').data('entrega');
            const estadoProduccion = $(this).find('td[data-produccion]').data('produccion');
            const estadoPago = $(this).find('td[data-pago]').data('pago');
            const montoPagado = parseFloat($(this).find('td[data-montoPagadoOriginal]').text().trim());
            const montoTotal = parseFloat($(this).find('td').eq(12).text().trim());

            const diferenciaMonto = Math.abs(montoTotal - montoPagado);
            
          //  alert(estadoEntrega + '  ' + estadoProduccion + '  ' + estadoPago + '  ' +  montoPagado + ' ' + montoTotal  );
            
            if (diferenciaMonto <= 0.1 && estadoPago == 3 && estadoEntrega == 3 && estadoProduccion == 3) {
                $(this).find('.terminado').text('Terminado');
               
            } else {
                $(this).find('.terminado').html('<button type="button" class="btn btn-success btnCerrar">Cerrar</button>');
            }
        });

/* Botón Cerrar */

$('table').on('click', '.btnCerrar', function() {
    var $row = $(this).closest('tr');
    
    // Recolección de variables
    var datos = {
        opcion: 'cerrarPedido',
        idPedido: $row.find('td:first').text(),
        idContacto: $row.find('td:eq(1)').text(),
        montoPagado: $row.find('td[data-montoPagadoOriginal]').text(),
        monto: $row.find('td:eq(12)').text(),
        idMedio: $row.find('td[data-medio]').attr('data-medio')
    };

    // Confirmación opcional para evitar cierres accidentales
    if(confirm("¿Estás seguro de que deseas cerrar este pedido?")) {
        $.ajax({
            url: 'pedidos/ajaxPedidos.php',
            type: 'POST',
            data: datos,
            success: function(response) {
                // 1. Mostramos el mensaje del servidor
                $('#mensajes').html(response);

                // 2. Refrescamos el listado solo después de confirmar el éxito
                $('#contenido').load('pedidos/pedidos.php', function() {
                    // Opcional: Alguna animación o aviso de que se actualizó
                    console.log("Tabla de pedidos actualizada.");
                });
            },
            error: function() {
                alert("Error crítico al intentar cerrar el pedido.");
            }
        });
    }
});

$('.btnEstado').click(function() {
    let v_btn = $(this);
    let v_idPedido = v_btn.data('id');
    let v_tipo = v_btn.data('tipo'); 
    let v_actual = parseInt(v_btn.data('actual'));
    let v_nuevo = (v_actual % 3) + 1; // Ciclo 1 -> 2 -> 3 -> 1

    // Mostrar un spinner o feedback visual de "procesando"
    v_btn.html('<span class="spinner-border spinner-border-sm"></span>');

    $.post('pedidos/ajaxPedidos.php', {
        opcion: 'modificarEstado',
        idPedido: v_idPedido,
        tipo: v_tipo,
        nuevoEstado: v_nuevo
    }, function(respuesta) {
        // Restaurar icono y actualizar datos
        v_btn.data('actual', v_nuevo);
        v_btn.removeClass('btn-outline-danger btn-outline-warning btn-outline-success');
        
        // Definir iconos según el tipo para no perderlos
        let icono = (v_tipo === 'entrega') ? 'bi-hand-index' : 'bi-gear-fill';
        v_btn.html('<i class="bi ' + icono + '"></i>');

        if (v_nuevo == 1) v_btn.addClass('btn-outline-danger');
        else if (v_nuevo == 2) v_btn.addClass('btn-outline-warning');
        else if (v_nuevo == 3) v_btn.addClass('btn-outline-success');
    });

});
</script>


