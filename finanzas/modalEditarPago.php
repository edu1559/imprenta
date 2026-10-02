<?php
include_once('../conexion.php');
$conn = conectar();
$idPago = (int)$_GET['idPago'];
// Pantalla a recargar después de guardar: Cierre (por defecto) o Pedidos.
$volver = (($_GET['volver'] ?? '') === 'pedidos') ? 'pedidos/pedidos.php' : 'finanzas/cierre.php';

$sql = "SELECT p.*, concat(c.apellido, ' ', c.nombre) as contacto 
        FROM pagos p 
        INNER JOIN pedidos pe ON p.idPedido = pe.id 
        INNER JOIN contactos c ON pe.idContacto = c.id 
        WHERE p.id = $idPago";
$res = mysqli_query($conn, $sql);
$reg = mysqli_fetch_assoc($res);

$medios = mysqli_query($conn, "SELECT * FROM mediosPago WHERE visible = 1");
?>

<div class="modal-header bg-success text-white">
    <h5 class="modal-title">Modificar Pago #<?php echo $idPago; ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="alert alert-secondary">
        <strong>Cliente:</strong> <?php echo $reg['contacto']; ?><br>
        <strong>Referencia Pedido:</strong> #<?php echo $reg['idPedido']; ?>
    </div>
    <form id="formModificarPago">
        <input type="hidden" id="editIdPago" value="<?php echo $idPago; ?>">
        <div class="mb-3">
            <label class="form-label fw-bold">Monto del Pago:</label>
            <input type="number" step="0.01" class="form-control form-control-lg text-success fw-bold" id="editMontoPago" value="<?php echo $reg['monto']; ?>">
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Medio de Pago:</label>
            <select class="form-select" id="editMedioPago">
                <?php while($m = mysqli_fetch_assoc($medios)): 
                    $s = ($m['id'] == $reg['idMedioPago']) ? "selected" : ""; ?>
                    <option value="<?php echo $m['id']; ?>" <?php echo $s; ?>><?php echo $m['medio']; ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label fw-bold">Motivo del cambio:</label>
            <textarea class="form-control" id="editMotivoPago" rows="2" maxlength="255" placeholder="Obligatorio: queda registrado"></textarea>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="button" id="btnGuardarCambioPago" class="btn btn-success w-100">
        <i class="bi bi-save"></i> ACTUALIZAR PAGO
    </button>
</div>

<script>
$('#btnGuardarCambioPago').on('click', function() {
    const datos = {
        opcion: 'actualizarPago',
        id: $('#editIdPago').val(),
        monto: $('#editMontoPago').val(),
        idMedioPago: $('#editMedioPago').val(),
        motivo: $('#editMotivoPago').val().trim()
    };
    if (datos.motivo === '') {
        alert('Tenés que indicar el motivo.');
        return;
    }
    
    $.post('finanzas/ajaxCierre.php', datos, function(r) {
        alert(r);
        // Si no se guardó (sin permiso, caja cerrada, etc.) dejamos el modal abierto.
        if (r.trim().indexOf('✅') !== 0) return;
        $('#modalUniversal').modal('hide');
        $('#contenido').load('<?php echo $volver; ?>');
    });
});
</script>
