<?php
include_once('../conexion.php');
$conn = conectarPDO();

// Cargamos diccionarios pequeños (esto no pesa)
function obtenerDiccionario($conn, $tabla, $columna) {
    $arr = [];
    $res = $conn->query("SELECT id, $columna FROM $tabla");
    while($row = $res->fetch(PDO::FETCH_NUM)) $arr[$row[0]] = $row[1];
    return $arr;
}

$origen = obtenerDiccionario($conn, 'origen', 'origen');
$tipoPedido = obtenerDiccionario($conn, 'tipoPedido', 'tipo');
$medioPago = obtenerDiccionario($conn, 'mediosPago', 'medio');

// Si venimos de "Crear Nuevo Pedido" desde el historial de un cliente,
// llega el cliente ya elegido y arrancamos directo en el formulario.
$idContactoPre = isset($_GET['idContacto']) ? intval($_GET['idContacto']) : 0;
$nombreContactoPre = isset($_GET['nombreContacto']) ? $_GET['nombreContacto'] : '';
?>

<div class="modal-header bg-primary text-white">
    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Nuevo Pedido</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">
    <form id="formNuevoPedido">
        <div class="container-fluid p-3 bg-success rounded shadow-sm">

            <input type="hidden" id="hidIdContacto" value="<?php echo $idContactoPre ?: ''; ?>">

            <div class="row mb-3">
                <div class="col-12">
                    <label class="form-label text-white fw-bold">Cliente:</label>

                    <!-- Estado "cliente elegido": chip con nombre + ver historial.
                         Ojo: el layout va por estilo inline, no por .d-flex — esa clase
                         de Bootstrap usa !important y pisaba el display:none al ocultarlo. -->
                    <div id="clienteChip" class="bg-white rounded p-2 px-3" style="justify-content:space-between; align-items:center; display: <?php echo $idContactoPre ? 'flex' : 'none'; ?>;">
                        <div class="fw-bold text-success">
                            <i class="bi bi-check-circle-fill"></i>
                            <span id="clienteChipTexto"><?php echo htmlspecialchars($nombreContactoPre); ?></span>
                        </div>
                        <div class="d-flex gap-3 align-items-center">
                            <a href="#" id="lnkVerHistorial" class="small fw-bold text-decoration-none">Ver historial</a>
                            <a href="#" id="lnkCambiarCliente" class="small text-muted text-decoration-none">Cambiar</a>
                        </div>
                    </div>

                    <!-- Estado "buscando cliente": combo con Select2 -->
                    <div id="clienteBuscador" style="<?php echo $idContactoPre ? 'display:none;' : ''; ?>">
                        <select class="form-select" id="selContacto" style="width: 100%">
                            <option value="">Escriba para buscar...</option>
                        </select>
                    </div>

                    <!-- Alta rápida inline: aparece si el cliente buscado no existe -->
                    <div id="panelNuevoCliente" class="border border-warning rounded p-3 mt-1 bg-white" style="display:none;">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-person-plus-fill text-warning"></i>
                            <strong class="small text-warning-emphasis">Cliente nuevo — se crea sin salir de este formulario</strong>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="small fw-bold">Apellido / Empresa:</label>
                                <input type="text" class="form-control form-control-sm" id="ncApellido">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Nombre:</label>
                                <input type="text" class="form-control form-control-sm" id="ncNombre">
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="small fw-bold">Teléfono / WhatsApp:</label>
                                <input type="text" class="form-control form-control-sm" id="ncTelefono">
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold">Correo (opcional):</label>
                                <input type="email" class="form-control form-control-sm" id="ncCorreo">
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-success btn-sm flex-grow-1" id="btnGuardarClienteInline">
                                <i class="bi bi-save"></i> Guardar cliente y continuar
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCancelarClienteInline">Cancelar</button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="restoFormulario">
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
                    <div class="col-md-6"></div>
                    <div class="col-md-6 text-end">
                        <button type="button" id="btnGuardarPedido" class="btn btn-warning btn-lg w-100">
                            <i class="bi bi-save"></i> GUARDAR PEDIDO
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
$(document).ready(function() {

    // SELECT2 CON BUSQUEDA REMOTA (Para manejar 17k contactos sin morir)
    $('#selContacto').select2({
        dropdownParent: $('#modalUniversal'),
        ajax: {
            url: 'contactos/buscarContactos.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term };
            },
            processResults: function (data) {
                return { results: data };
            },
            cache: true
        },
        minimumInputLength: 3,
        templateResult: formatearResultadoCliente,
        escapeMarkup: function(m) { return m; }
    });

    function formatearResultadoCliente(c) {
        if (!c.id) { return c.text; } // "Buscando..." / placeholder de Select2
        if (c.id === 'NEW') {
            return '<div class="d-flex align-items-center gap-2 text-warning-emphasis fw-bold px-1 py-1">' +
                   '<i class="bi bi-plus-circle-fill"></i> ' + escapeHtml(c.text) + '</div>';
        }
        var saldo = parseFloat(c.saldo) || 0;
        var saldoTxt = saldo > 0 ? ('Debe $' + saldo.toLocaleString('es-AR')) : 'Al día';
        var saldoClase = saldo > 0 ? 'bg-danger' : 'bg-success';
        return '<div class="d-flex justify-content-between align-items-center px-1 py-1">' +
                 '<div><div class="fw-bold">' + escapeHtml(c.text) + '</div>' +
                 '<div class="small text-muted">' + escapeHtml(c.telefono || '') + ' · ' + (c.pedidos || 0) + ' pedidos</div></div>' +
                 '<span class="badge ' + saldoClase + '">' + saldoTxt + '</span>' +
               '</div>';
    }

    function escapeHtml(s) {
        return $('<div>').text(s || '').html();
    }

    // Selección en el combo: si es "NEW" abrimos el alta inline, si no, mostramos el chip
    $('#selContacto').on('select2:select', function(e) {
        var data = e.params.data;
        if (data.id === 'NEW') {
            $('#ncApellido').val(data.nombreNuevo || '');
            $('#ncNombre, #ncTelefono, #ncCorreo').val('');
            $('#clienteBuscador').hide();
            $('#panelNuevoCliente').show();
            $('#restoFormulario').css('opacity', '.35').find('input, select, textarea, button').prop('disabled', true);
            $('#ncApellido').focus();
        } else {
            elegirCliente(data.id, data.text);
        }
    });

    function elegirCliente(idContacto, texto) {
        $('#hidIdContacto').val(idContacto);
        $('#clienteChipTexto').text(texto);
        $('#clienteBuscador').hide();
        $('#panelNuevoCliente').hide();
        $('#clienteChip').css('display', 'flex'); // no usar .show(): sin la clase d-flex, el default de jQuery para un <div> es 'block'
        $('#restoFormulario').css('opacity', '1').find('input, select, textarea, button').prop('disabled', false);
    }

    $('#lnkCambiarCliente').on('click', function(e) {
        e.preventDefault();
        $('#hidIdContacto').val('');
        $('#clienteChip').hide();
        $('#selContacto').val(null).trigger('change');
        $('#clienteBuscador').show();
    });

    $('#lnkVerHistorial').on('click', function(e) {
        e.preventDefault();
        var idC = $('#hidIdContacto').val();
        if (idC && typeof abrirModalHistorial === 'function') {
            abrirModalHistorial(idC);
        }
    });

    $('#btnCancelarClienteInline').on('click', function() {
        $('#panelNuevoCliente').hide();
        $('#clienteBuscador').show();
        $('#restoFormulario').css('opacity', '1').find('input, select, textarea, button').prop('disabled', false);
    });

    $('#btnGuardarClienteInline').on('click', function() {
        var v_apellido = $('#ncApellido').val().trim();
        if (!v_apellido) {
            alert('El apellido o empresa es obligatorio.');
            $('#ncApellido').focus();
            return;
        }

        var boton = $(this);
        boton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Guardando...');

        $.get('contactos/ajaxContactos.php', {
            opcion: 'agregarContactoInline',
            apellido: encodeURIComponent(v_apellido),
            nombre: encodeURIComponent($('#ncNombre').val().trim()),
            telefono: $('#ncTelefono').val().trim(),
            correo: $('#ncCorreo').val().trim()
        }, function(resp) {
            boton.prop('disabled', false).html('<i class="bi bi-save"></i> Guardar cliente y continuar');
            if (resp && resp.ok) {
                // Lo agregamos como opción del combo para que quede consistente si vuelve a buscarlo
                var opt = new Option(resp.text, resp.id, true, true);
                $('#selContacto').append(opt).trigger('change');
                elegirCliente(resp.id, resp.text);
            } else {
                alert('No se pudo crear el cliente: ' + (resp && resp.error ? resp.error : 'error desconocido'));
            }
        }, 'json');
    });

    // GUARDAR PEDIDO
    $('#btnGuardarPedido').on('click', function() {
        var datos = {
            opcion: 'agregarPedido',
            idContacto: $('#hidIdContacto').val(),
            idTipoPedido: $('#selIdTipo').val(),
            idOrigen: $('#selIdOrigen').val(),
            detalle: $('#txtDetalle').val(),
            observaciones: $('#txtObservaciones').val(),
            idEstadoEntrega: 1,
            idEstadoProduccion: 1,
            idEstadoPago: ($('#inpMontoPagado').val() > 0) ? 2 : 1,
            prometido: $('#inpPrometido').val(),
            montoPagado: $('#inpMontoPagado').val(),
            monto: $('#inpMonto').val(),
            idMedioPago: $('#selMedioPago').val()
        };

        if(!datos.idContacto){
            alert("Por favor, seleccione un cliente.");
            return;
        }

        $.post('pedidos/ajaxPedidos.php', datos, function(res) {
            $('#mensajes').html(res);
            $('#modalUniversal').modal('hide');
            $('#contenido').load('pedidos/pedidos.php');
        });
    });
});
</script>
