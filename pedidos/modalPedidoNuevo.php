<?php
include_once('../conexion.php');
$conn = conectar();

// Cargamos diccionarios pequeños (esto no pesa)
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

<div class="modal-header bg-primary text-white">
    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nuevo Pedido</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">
    <form id="formNuevoPedido">
        <div class="container-fluid p-3 bg-success rounded shadow-sm">
            
            <div class="row mb-3">
                <div class="col-12">
                    <label class="form-label text-white fw-bold">Buscar Cliente (Apellido o Nombre):</label>
                    <select class="form-select" id="selContacto" name="idContacto" style="width: 100%">
                        <option value="">Escriba para buscar...</option>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label text-white">Origen:</label>
                    <select class="form-select" id="selIdOrigen" name="idOrigen">
                        <?php foreach ($origen as $id => $val) echo "<option value='$id'>$val</option>"; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-white">Tipo de Trabajo:</label>
                    <select class="form-select" id="selIdTipo" name="idTipoPedido">
                        <?php foreach ($tipoPedido as $id => $val) echo "<option value='$id'>$val</option>"; ?>
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-12">
                    <label class="form-label text-white">Detalle:</label>
                    <textarea class="form-control" id="txtDetalle" name="detalle" rows="2"></textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label text-white">Observaciones Internas:</label>
                    <textarea class="form-control" id="txtObservaciones" name="observaciones" rows="2"></textarea>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-3">
                    <label class="form-label text-white">Fecha Entrega:</label>
                    <input type="date" class="form-control" id="inpPrometido" name="prometido" value="<?php echo date("Y-m-d");?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white">Monto Total $:</label>
                    <input type="number" class="form-control fw-bold" id="inpMonto" name="monto" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white">Seña / Pago Parcial $:</label>
                    <input type="number" class="form-control" id="inpMontoPagado" name="montoPagado" value="0">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-white">Medio de Pago:</label>
                    <select class="form-select" id="selMedioPago" name="idMedioPago">
                        <?php foreach ($medioPago as $id => $val) echo "<option value='$id'>$val</option>"; ?>
                    </select>
                </div>
            </div>

            <div class="row align-items-end">
                <div class="col-md-6">
                   
                </div>
                <div class="col-md-6 text-end">
                    <button type="button" id="btnGuardarPedido" class="btn btn-warning btn-lg w-100">
                        <i class="bi bi-save"></i> GUARDAR PEDIDO
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {
    
    // 1. SELECT2 CON BUSQUEDA REMOTA (Para manejar 17k contactos sin morir)
    $('#selContacto').select2({
        dropdownParent: $('#modalUniversal'),
        ajax: {
            url: 'contactos/buscarContactos.php', // Necesitaremos este archivito simple
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term }; // Envia lo que escribes como 'q'
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        },
        minimumInputLength: 3 // Empieza a buscar despues de 3 letras
    });

    // 2. LOGICA DEL BOTON GUARDAR
    $('#btnGuardarPedido').on('click', function() {
        // Armamos el objeto manualmente para tener control total
        var datos = {
            opcion: 'agregarPedido',
            idContacto: $('#selContacto').val(),
            idTipoPedido: $('#selIdTipo').val(),
            idOrigen: $('#selIdOrigen').val(),
            detalle: $('#txtDetalle').val(),
            observaciones: $('#txtObservaciones').val(),
            idEstadoEntrega: 1, // Nuevo siempre es 1
            idEstadoProduccion: 1, // Nuevo siempre es 1
            idEstadoPago: ($('#inpMontoPagado').val() > 0) ? 2 : 1, // Si hay seña es Parcial
            prometido: $('#inpPrometido').val(),
            montoPagado: $('#inpMontoPagado').val(),
            monto: $('#inpMonto').val(),
            idMedioPago: $('#selMedioPago').val()
        };

        // DEBUG: Mira la consola de Firefox (F12) para ver esto
        console.log("DATOS A ENVIAR:", datos);

        if(!datos.idContacto){
            alert("Por favor, seleccione un cliente.");
            return;
        }

        $.post('pedidos/ajaxPedidos.php', datos, function(res) {
            console.log("RESPUESTA SERVIDOR:", res);
            $('#mensajes').html(res);
            $('#modalUniversal').modal('hide');
           $('#contenido').load('pedidos/pedidos.php');
        });
    });
});
</script>