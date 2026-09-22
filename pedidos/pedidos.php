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

<div class="container-fluid py-4 px-4">

    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
        <div>
            <h3 class="text-success mb-0 fw-bold">
                <i class="bi bi-clipboard2-fill me-2"></i> Pedidos
            </h3>
            <small class="text-muted">Gestión de pedidos, pagos y entregas</small>
        </div>
        <div class="d-flex gap-2">
            <button class='btn btn-success shadow-sm' id='btnPedidoNuevo' title='Nuevo Pedido'>
                <i class="bi bi-plus-circle me-1"></i> Nuevo Pedido
            </button>
            <button class='btn btn-outline-secondary' id='btnContactoNuevo' title='Nuevo Contacto'>
                <i class="bi bi-person-plus me-1"></i> Nuevo Contacto
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1">Buscar Cliente — para cobrar, entregar o ver su historial</label>
                    <select class="form-select" id="selBuscarClienteRapido" style="width:100%">
                        <option value="">Escriba apellido, nombre o teléfono...</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted mb-1">Filtrar esta tabla por pedido # o apellido</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-success"></i></span>
                        <input type="text" id="buscar" class="form-control border-start-0 ps-0" placeholder="Pedido # o Apellido...">
                        <button class="btn btn-outline-secondary" type="button" id="btnBuscarPedidos">Buscar</button>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mb-3">
                <button type="button" class="btn btn-sm btn-outline-success btnOpciones" data-valor="ultimos">Últimos</button>
                <button type="button" class="btn btn-sm btn-outline-primary btnOpciones" data-valor="sinTerminar">Sin Terminar</button>
                <button type="button" class="btn btn-sm btn-outline-danger btnOpciones" data-valor="sinPagar">Sin Pagar</button>
                <button type="button" class="btn btn-sm btn-outline-primary btnOpciones" data-valor="sinEntregar">Sin Entregar</button>
            </div>

            <div class="table-responsive">
            <table class="table table-hover align-middle" id="tblPedidos">
            <thead class="table-light">
                    <tr class="table-primary">
                            <th class="text-muted">ID</th>
                            <th colspan="2">Cliente</th>
                            <th colspan="2">Detalle</th>
                            <th title="entrada">Ent.</th>
                            <th title="Prometido">Prom.</th>
                            <th style="display:none">salida</th>
                            <th class="text-center" title='Entrega'>Entrega</th>
                            <th class="text-center" title='Producción'>Prod.</th>
                            <th class="text-center" title='Pago'>Pago</th>
                            <th class="text-end" title='Monto Pagado'>Pagado</th>
                            <th class="text-end" title='Monto Total'>Total</th>
                            <th class="text-end">Saldo</th>
                            <th>Medio Pago</th>
                            <th style="display:none">origen</th>
                            <th class="text-center" title='Editar Pedido'>Editar</th>
                            <th class="text-center" title='Orden'>Orden</th>
                            <th>Cargó</th>
                            <th class="text-center">Estado</th>
                    </tr>
            </thead>


            <?php

    // Etiquetas y colores de los 3 estados (mismo criterio visual para los tres,
    // en pastillas de color en vez de los íconos con borde que había antes).
    $estilosEstado = [1 => 'bg-danger', 2 => 'bg-warning text-dark', 3 => 'bg-success'];
    $etiquetasEntrega    = [1 => 'PENDIENTE',   2 => 'PARCIAL', 3 => 'ENTREGADO'];
    $etiquetasProduccion = [1 => 'S/COMENZAR',  2 => 'PARCIAL', 3 => 'TERMINADO'];
    $etiquetasPago       = [1 => 'DEBE',        2 => 'PARCIAL', 3 => 'PAGADO'];

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
                        echo " style= 'background-color:#eefbf1' >";
                        break;
                case 2:
                        echo " style= 'background-color:#fdecec' >";
                        break;
                case 3:
                        echo " style='background-color:#fff9e6' >";
                        break;
        };

        echo $myrow[0]. "</td> <!-- id -->
        <td style=\"display:none\">". $myrow[1]. "</td> <!-- idContacto -->
        <td style=\"display:none\">". $myrow[2]. "</td> <!-- idTipoPedido -->
        <td colspan=\"2\"><span class=\"fw-bold text-dark\">". $myrow[3]. "</span></td> <!--  apellido  y nombre-->
        <td  colspan=\"2\">". $myrow[4]. "</td> <!--  detalle -->
        <td class=\"text-muted small\">". $myrow[5]. "</td> <!--  entrada -->
        <td class=\"text-muted small\">". $myrow[6]. "</td><!--  prometido -->
        <td style=\"display:none\">". $myrow[7]. "</td><!--  salida -->
        <td class=\"text-center\" data-entrega=\"$myrow[8]\">"; /* entrega */
        $clase = $estilosEstado[$myrow[8]] ?? 'bg-secondary';
        $texto = $etiquetasEntrega[$myrow[8]] ?? '?';
        echo "<button class='btn btn-sm rounded-pill border-0 fw-bold text-white btnEstado $clase' data-tipo='entrega' data-id='$myrow[0]' data-actual='$myrow[8]'>$texto</button>";
            echo "</td>
                <td class=\"text-center\" data-produccion=\"$myrow[9]\">"; /* produccion */
        $clase = $estilosEstado[$myrow[9]] ?? 'bg-secondary';
        $texto = $etiquetasProduccion[$myrow[9]] ?? '?';
        echo "<button class='btn btn-sm rounded-pill border-0 fw-bold text-white btnEstado $clase' data-tipo='Produccion' data-id='$myrow[0]' data-actual='$myrow[9]'>$texto</button>";
                echo "</td>
                <td class=\"text-center\" data-pago=\"$myrow[10]\" >";  /* pago */
        $clase = $estilosEstado[$myrow[10]] ?? 'bg-secondary';
        $texto = $etiquetasPago[$myrow[10]] ?? '?';
        echo "<button class='btn btn-sm rounded-pill border-0 fw-bold text-white btnPagos $clase' data-tipo='Pago' data-id='$myrow[0]' data-actual='$myrow[10]'>$texto</button>";
                    echo "</td>
                    <td class=\"text-end\" data-montoPagadoOriginal =\"$myrow[11]\">". $myrow[11]. "</td><!-- Monto Pagado: sin number_format a propósito,
                         el JS le hace parseFloat() y el botón Cerrar lo manda tal cual al servidor -->
                    <td class=\"text-end\">". $myrow[12]. "</td><!-- Monto: mismo motivo que arriba -->
                    <td class=\"text-end fw-bold\" style=\"color: " . (($myrow[12] - $myrow[11]) > 0.01 ? '#dc3545' : '#198754') . ";\">". number_format($myrow[13], 2) . "</td><!-- Saldo: esta sí se puede formatear, nada la lee por JS -->
                    <td class=\"small text-muted\" data-medio=\"$myrow[14]\">";
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

                        echo "</td><td class=\"text-center\">";


                    // Detectamos si el pedido está 100% terminado myrow[8] = Entrega, myrow[9] = Produccion, myrow[10] = Pago
                        $estaTerminado = ($myrow[8] == 3 && $myrow[9] == 3 && $myrow[10] == 3);

                        if (!$estaTerminado) {
                            // Solo mostramos el botón si NO está terminado
                            echo "<button class='btn btn-sm btn-light border btnPedidoEditar' title='Editar'> <i class='bi bi-pencil-square text-success'></i></button>";
                        } else {
                            // Opcional: Mostrar un candado o dejar vacío
                            echo "<i class='bi bi-lock-fill text-muted' title='Pedido Cerrado'></i>";
                        }
                        echo "</td>";



                        echo "<td class=\"text-center\"><button  class='btn btn-sm btn-light border btnImprimirOrden' title='Imprimir Orden'> <i class='bi bi-printer text-primary'></i></button></td>
                       <td class=\"small text-muted\">$myrow[16]</td>";
                        // Columna Terminado (la que ya tenías con el botón 'Cerrar')

                        echo "<td class='terminado text-center'>";
                        if (isset($estaTerminado)) {
                            echo "<span class='badge bg-success rounded-pill px-3 py-2'>Terminado</span>";
                        } else {
                            echo "<button type='button' class='btn btn-success btn-sm rounded-pill btnCerrar'>Cerrar</button>";
                        }
                        echo "</td>";

                        echo "</tr>";
                    }
                    ?>



            </table>
            </div>
        </div>
    </div>

    <div class="rounded mt-3" id="panelMuestraPedido"></div>

</div>

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
                $(this).find('.terminado').html("<span class='badge bg-success rounded-pill px-3 py-2'>Terminado</span>");

            } else {
                $(this).find('.terminado').html('<button type="button" class="btn btn-success btn-sm rounded-pill btnCerrar">Cerrar</button>');
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

// Etiquetas y color de cada estado, para que el ciclo de clicks respete el
// mismo estilo de pastilla con el que se dibuja la tabla al cargarla.
const ETIQUETAS_ESTADO = {
    entrega:    {1: 'PENDIENTE',  2: 'PARCIAL', 3: 'ENTREGADO'},
    Produccion: {1: 'S/COMENZAR', 2: 'PARCIAL', 3: 'TERMINADO'}
};
const CLASES_ESTADO = {1: 'bg-danger', 2: 'bg-warning text-dark', 3: 'bg-success'};

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
        v_btn.data('actual', v_nuevo);
        v_btn.removeClass('bg-danger bg-warning bg-success text-dark');
        v_btn.addClass(CLASES_ESTADO[v_nuevo]);
        v_btn.text(ETIQUETAS_ESTADO[v_tipo][v_nuevo]);
    });

});
</script>
