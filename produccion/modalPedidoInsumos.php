<?php
include_once(__DIR__ . '/../sesion.php');
exigirPermisoPagina('produccion/pizarra.php');
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../insumos/comunInsumos.php');
$conn = conectar();

// Insumos que se gastaron en un pedido de venta (?idPedido=), con su costo del día en que se cargaron.
$idPedido = (int)($_GET['idPedido'] ?? 0);
$stmt = $conn->prepare("SELECT p.id, p.detalle, p.monto, TRIM(CONCAT(c.apellido, ' ', COALESCE(c.nombre, ''))) AS contacto
                        FROM pedidos p INNER JOIN contactos c ON c.id = p.idContacto
                        WHERE p.id = ? AND p.anulado = 0 AND p.idTipoPedido = 1");
$stmt->bind_param('i', $idPedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
if (!$pedido) {
    echo '<div class="modal-body"><div class="alert alert-warning mb-0">No hay un pedido de venta con ese número.</div></div>';
    exit;
}

$stmt = $conn->prepare("SELECT pi.*, i.nombre, i.unidadCompra, i.unidadesPorCompra, i.unidadUso, u.usuario
                        FROM pedidosInsumos pi
                        INNER JOIN insumos i ON i.id = pi.idInsumo
                        LEFT JOIN usuarios u ON u.id = pi.idUsuario
                        WHERE pi.idPedido = ?
                        ORDER BY pi.id");
$stmt->bind_param('i', $idPedido);
$stmt->execute();
$renglones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$costoTotal = array_sum(array_map(fn($r) => $r['cantidad'] * (float)$r['costoUnitario'], $renglones));
$sinCosto = count(array_filter($renglones, fn($r) => $r['costoUnitario'] === null));

// El toner no: se gasta por copias y sale del recuento.
$insumos = mysqli_fetch_all(mysqli_query($conn, "SELECT i.id, i.nombre, i.unidadCompra, i.unidadesPorCompra, i.unidadUso, i.controlaStock, c.nombre AS categoria
                                                 FROM insumos i INNER JOIN insumosCategorias c ON c.id = i.idCategoria
                                                 WHERE i.activo = 1 AND c.ficha <> 'toner'
                                                 ORDER BY c.orden, i.nombre"), MYSQLI_ASSOC);
?>

<div class="modal-header bg-dark text-white">
    <h5 class="modal-title"><i class="bi bi-box-seam"></i> Insumos del pedido #<?= $idPedido ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">
    <div class="p-3 bg-white rounded shadow-sm mb-3">
        <div class="fw-bold"><?= htmlspecialchars($pedido['contacto']) ?></div>
        <div class="small text-muted mb-2"><?= htmlspecialchars($pedido['detalle']) ?></div>

        <?php if ($renglones): ?>
        <table class="table table-sm align-middle mb-2" id="renglonesInsumos">
            <thead class="table-light"><tr><th>Insumo</th><th>Cantidad</th><th class="text-end">Costo</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($renglones as $r): ?>
                <tr data-id="<?= (int)$r['id'] ?>">
                    <td><?= htmlspecialchars($r['nombre']) ?><div class="small text-muted"><?= htmlspecialchars($r['usuario'] ?? '') ?> · <?= date('d/m H:i', strtotime($r['fecha'])) ?></div></td>
                    <td><?= cantidadEnUnidades($r, $r['cantidad']) ?></td>
                    <td class="text-end"><?= $r['costoUnitario'] !== null ? fmtPesos($r['cantidad'] * $r['costoUnitario']) : '<span class="small text-muted">sin precio</span>' ?></td>
                    <td class="text-end"><i class="bi bi-trash text-danger btnSacarInsumo" style="cursor: pointer" title="Sacar (si se cargó mal)"></i></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="d-flex justify-content-between small">
            <span>Insumos: <strong><?= fmtPesos($costoTotal) ?></strong><?= $sinCosto ? " <span class=\"text-muted\">($sinCosto sin precio cargado)</span>" : '' ?></span>
            <span>Monto del pedido: <strong><?= fmtPesos($pedido['monto']) ?></strong></span>
        </div>
        <?php else: ?>
        <div class="text-muted small">Todavía no se cargó ningún insumo.</div>
        <?php endif; ?>
    </div>

    <div class="p-3 bg-white rounded shadow-sm" id="formPedidoInsumo">
        <div class="small fw-bold mb-2">Agregar lo que se usó</div>
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label small mb-0">Insumo:</label>
                <select class="form-select" id="piInsumo" style="width: 100%">
                    <option value="">Elegir...</option>
                    <?php $categoria = null; foreach ($insumos as $i):
                        if ($i['categoria'] !== $categoria): if ($categoria !== null) echo '</optgroup>'; $categoria = $i['categoria']; ?>
                    <optgroup label="<?= htmlspecialchars($categoria) ?>">
                    <?php endif; ?>
                        <option value="<?= (int)$i['id'] ?>" data-por-compra="<?= max(1, (int)$i['unidadesPorCompra']) ?>"
                                data-compra="<?= htmlspecialchars(ucfirst(strtolower(plural($i['unidadCompra'])))) ?>"
                                data-uso="<?= htmlspecialchars(ucfirst(strtolower(plural($i['unidadUso'])))) ?>"><?= htmlspecialchars($i['nombre']) ?></option>
                    <?php endforeach; if ($categoria !== null) echo '</optgroup>'; ?>
                </select>
            </div>
            <div class="col-md-2 col-4">
                <label class="form-label small mb-0" id="piEnterasEtiqueta">Enteras:</label>
                <input type="number" class="form-control" id="piEnteras" min="0" step="1">
            </div>
            <div class="col-md-2 col-4" id="piSueltasCol">
                <label class="form-label small mb-0" id="piSueltasEtiqueta">Sueltas:</label>
                <input type="number" class="form-control" id="piSueltas" min="0" step="1">
            </div>
            <div class="col-md-2 col-4">
                <button type="button" class="btn btn-success w-100" id="btnAgregarInsumo"><i class="bi bi-plus-circle"></i> Agregar</button>
            </div>
        </div>
        <div class="form-text">Los papeles y demás insumos con stock se descuentan del stock. El toner no se carga acá: sale del recuento.</div>
    </div>
</div>

<script>
$(document).ready(function() {
    var idPedido = <?= $idPedido ?>;

    $('#piInsumo').select2({ dropdownParent: $('#modalUniversal'), placeholder: 'Elegir...' });

    // Las etiquetas de la cantidad siguen al insumo: "Resmas" y "Hojas sueltas", o solo "Rulos".
    function mostrarUnidades() {
        var $o = $('#piInsumo option:selected');
        var porCompra = parseInt($o.data('por-compra'), 10) || 1;
        $('#piEnterasEtiqueta').text(($o.data('compra') || 'Enteras') + ':');
        $('#piSueltasEtiqueta').text(($o.data('uso') || '') + ' sueltas:');
        $('#piSueltasCol').toggle(porCompra > 1);
        if (porCompra === 1) $('#piSueltas').val('');
    }
    $('#piInsumo').on('change', mostrarUnidades);
    mostrarUnidades();

    // Al cerrar, la pizarra se recarga solo si algo cambió (muestra el costo en la tarjeta).
    $('#modalUniversal').off('hidden.bs.modal.pedidoInsumos').one('hidden.bs.modal.pedidoInsumos', function() {
        if (window.pedidoInsumosCambiado) $('#contenido').load(window.urlListaActual || 'produccion/pizarra.php');
        window.pedidoInsumosCambiado = false;
    });

    function recargar() {
        window.pedidoInsumosCambiado = true;
        $('#modalUniversal .modal-content').load('produccion/modalPedidoInsumos.php?idPedido=' + idPedido);
    }

    $('#btnAgregarInsumo').on('click', function() {
        $.post('produccion/ajaxPedidoInsumos.php', {
            opcion: 'agregar',
            idPedido: idPedido,
            idInsumo: $('#piInsumo').val(),
            enteras: $('#piEnteras').val(),
            sueltas: $('#piSueltas').val()
        }, function(res) {
            if (res.trim().indexOf('✅') !== 0) { alert(res); return; }
            recargar();
        });
    });

    $('#renglonesInsumos').on('click', '.btnSacarInsumo', function() {
        if (!confirm('¿Sacar este insumo del pedido? Si descontó stock, se devuelve.')) return;
        $.post('produccion/ajaxPedidoInsumos.php', { opcion: 'sacar', id: $(this).closest('tr').data('id') }, function(res) {
            if (res.trim().indexOf('✅') !== 0) { alert(res); return; }
            recargar();
        });
    });
});
</script>
