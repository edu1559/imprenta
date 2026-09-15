<?php
include_once('../conexion.php');
$conn = conectar();

$idPedido = $_GET['idPedido']; 

$sql = "SELECT p.*, CONCAT(c.apellido, ' ', c.nombre) as nombreContacto
        ,u.usuario as cargo
        ,date(p.prometido) as prometidoDia 
        FROM pedidos p 
            LEFT JOIN contactos c 
                ON p.idContacto = c.id
            LEFT JOIN usuarios u 
                ON p.idUsuario = u.id 
        
        WHERE p.id = $idPedido";
$res = mysqli_query($conn, $sql);
$reg = mysqli_fetch_assoc($res);

$medioPago = [];
$resM = mysqli_query($conn, "SELECT id, medio FROM mediosPago");
while($row = mysqli_fetch_row($resM)) $medioPago[$row[0]] = $row[1];
?>

<div class="modal-header">
    <h5 class="modal-title center">Editar Pedido Nº <?php echo $idPedido; ?></h5>
    <button type="button" class="btn-close btn-close-black" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light"> <form id="formEditarPedido">
        <input type="hidden" id="editIdPedido" value="<?php echo $idPedido; ?>">
        <input type="hidden" id="editIdContacto" value="<?php echo $reg['idContacto']; ?>">
        <input type="hidden" id="montoPagadoOriginal" value="<?php echo $reg['montoPagado']; ?>">

        <div class="alert alert-info py-2 mb-3">
            <h6 class="mb-0"><strong>Cliente:</strong> <?php echo $reg['nombreContacto']; ?></h6>
        </div>

        

        <div class="row mb-3">
            <div class="col-12 mb-3">
                <label class="form-label  fw-bold">Detalle del Pedido:</label>
                <textarea class="form-control" id="editDetalle" rows="2"><?php echo $reg['detalle']; ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label  fw-bold">Observaciones Internas:</label>
                <textarea class="form-control" id="editObservaciones" rows="2"><?php echo $reg['observaciones']; ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label  fw-bold">Cargó:  </label>
                <label class="form-input" id="usuarioCarga"><?php echo $reg['cargo']; ?></label>
            </div>
        </div>

        
        <div class="row g-2 mb-3 bg-secondary">
            <div class="col-md-4 col-12">
                <div class="card bg-transparent border-light h-100">
                    <div class="card-body py-2">
                        <label class="form-label text-white text-center h6 d-block border-bottom pb-1">Producción:</label>
                        <?php 
                        $estadosP = [1 => 'Sin Comenzar', 2 => 'Parcialmente', 3 => 'Terminado'];
                        foreach($estadosP as $v => $l): 
                            $chk = ($reg['estadoProduccion'] == $v) ? 'checked' : '';
                        ?>
                        <div class="form-check">
                            <input type="radio" id="prod_<?php echo $v; ?>" name="estadoProduccion" value="<?php echo $v; ?>" class="form-check-input" <?php echo $chk; ?>>
                            <label for="prod_<?php echo $v; ?>" class="form-check-label text-white small"><?php echo $l; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-12">
                <div class="card bg-transparent border-light h-100">
                    <div class="card-body py-2">
                        <label class="form-label text-white text-center h6 d-block border-bottom pb-1">Entrega:</label>
                        <?php 
                        $estadosE = [1 => 'Pendiente', 2 => 'Enviado', 3 => 'Entregado'];
                        foreach($estadosE as $v => $l): 
                            $chk = ($reg['estadoEntrega'] == $v) ? 'checked' : '';
                        ?>
                        <div class="form-check">
                            <input type="radio" id="ent_<?php echo $v; ?>" name="estadoEntrega" value="<?php echo $v; ?>" class="form-check-input" <?php echo $chk; ?>>
                            <label for="ent_<?php echo $v; ?>" class="form-check-label text-white small"><?php echo $l; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4 col-12">
                <div class="card bg-transparent border-light h-100">
                    <div class="card-body py-2">
                        <label class="form-label text-white text-center h6 d-block border-bottom pb-1">Pago:</label>
                        <?php 
                        $estadosPa = [1 => 'Sin Pagar', 2 => 'Parcialmente', 3 => 'Pagado'];
                        foreach($estadosPa as $v => $l): 
                            $chk = ($reg['estadoPago'] == $v) ? 'checked' : '';
                        ?>
                        <div class="form-check">
                            <input type="radio" id="pago_<?php echo $v; ?>" name="estadoPago" value="<?php echo $v; ?>" class="form-check-input" <?php echo $chk; ?>>
                            <label for="pago_<?php echo $v; ?>" class="form-check-label text-white small"><?php echo $l; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-2 mb-4 p-2 rounded bg-secondary">
            <div class="col-md-3">
                <label class="small text-white">Total $:</label>
                <input type="number" class="form-control form-control-sm fw-bold" id="editMonto" value="<?php echo $reg['monto']; ?>">
            </div>
            <div class="col-md-3">
                <label class="small text-white">Pagado $:</label>
                <input type="number" class="form-control form-control-sm fw-bold text-success" id="editMontoPagado" value="<?php echo $reg['montoPagado']; ?>">
            </div>
            <div class="col-md-3">
                <label class="small text-white">Prometido:</label>
                <input type="date" class="form-control form-control-sm" id="editPrometido" value="<?php echo $reg['prometidoDia']; ?>">
            </div>
            <div class="col-md-3">
                <label class="small text-white">Medio Pago:</label>
                <select class="form-select form-select-sm" id="editMedioPago">
                    <?php foreach ($medioPago as $id => $val): 
                        $s = ($id == $reg['idMedioPago']) ? "selected" : "";
                    ?>
                        <option value="<?php echo $id; ?>" <?php echo $s; ?>><?php echo $val; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="button" id="btnActualizarPedido" class="btn btn-warning btn-lg w-100 fw-bold">
            <i class="bi bi-save"></i> ACTUALIZAR PEDIDO
        </button>
    </form>
</div>

<script>
$('#btnActualizarPedido').on('click', function() {
    var datos = {
        opcion: 'actualizarPedido',
        idPedido: $('#editIdPedido').val(),
        idContacto: $('#editIdContacto').val(),
        detalle: $('#editDetalle').val(),
        observaciones: $('#editObservaciones').val(),
        prometido: $('#editPrometido').val(),
        monto: $('#editMonto').val(),
        montoPagado: $('#editMontoPagado').val(),
        montoPagadoOriginal: $('#montoPagadoOriginal').val(),
        idEstadoEntrega: $('input[name="estadoEntrega"]:checked').val(),
        idEstadoProduccion: $('input[name="estadoProduccion"]:checked').val(),
        idEstadoPago: $('input[name="estadoPago"]:checked').val(),
        idMedioPago: $('#editMedioPago').val(),
        // Agregamos estos para que el UPDATE de ajaxPedidos no falle por falta de datos
        idTipoPedido: <?php echo $reg['idTipoPedido']; ?>,
        idOrigen: <?php echo $reg['idOrigen']; ?>
    };

    $.post('pedidos/ajaxPedidos.php', datos, function(res) {
        alert(res);
        $('#modalUniversal').modal('hide');
        $('#contenido').load('pedidos/pedidos.php');
    });
});
</script>