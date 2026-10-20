<?php
include_once(__DIR__ . '/../sesion.php');
exigirPermisoPagina('insumos/stock.php');
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/comunInsumos.php');
$conn = conectar();

// Movimientos de stock de un insumo: carga de compra, salida o recuento (?tipo=), e historial.
$idInsumo = (int)($_GET['idInsumo'] ?? 0);
$tipo = in_array($_GET['tipo'] ?? '', ['compra', 'salida', 'inventario'], true) ? $_GET['tipo'] : 'compra';

$stmt = $conn->prepare("SELECT * FROM insumos WHERE id = ? AND controlaStock = 1");
$stmt->bind_param('i', $idInsumo);
$stmt->execute();
$insumo = $stmt->get_result()->fetch_assoc();
if (!$insumo) {
    echo '<div class="modal-body"><div class="alert alert-warning mb-0">No se encontró el insumo, o no tiene el control de stock activado.</div></div>';
    exit;
}

const MOVIMIENTOS_A_MOSTRAR = 30;
$stmt = $conn->prepare("SELECT m.*, TRIM(CONCAT(c.apellido, ' ', COALESCE(c.nombre, ''))) AS proveedor, u.usuario
                        FROM insumosMovimientos m
                        LEFT JOIN contactos c ON c.id = m.idProveedor
                        LEFT JOIN usuarios u ON u.id = m.idUsuario
                        WHERE m.idInsumo = ?
                        ORDER BY m.id DESC LIMIT " . MOVIMIENTOS_A_MOSTRAR);
$stmt->bind_param('i', $idInsumo);
$stmt->execute();
$movimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Atajos para elegir el proveedor: los que tienen precio cargado para este insumo.
$habituales = array_map(fn($p) => ['id' => $p['idProveedor'], 'proveedor' => $p['proveedor']], preciosVigentes($conn, $idInsumo)[$idInsumo] ?? []);

$porCompra = max(1, (int)$insumo['unidadesPorCompra']);
$unidadCompra = strtolower(plural($insumo['unidadCompra']));
$unidadUso = strtolower(plural($insumo['unidadUso']));
const ETIQUETAS_MOVIMIENTO = ['compra' => ['Compra', 'bg-success'], 'salida' => ['Salida', 'bg-secondary'], 'inventario' => ['Recuento', 'bg-primary']];
?>

<div class="modal-header bg-dark text-white">
    <h5 class="modal-title"><i class="bi bi-boxes"></i> <?= htmlspecialchars($insumo['nombre']) ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">
    <div class="p-3 bg-white rounded shadow-sm mb-3" id="formStock">
        <div class="mb-3">
            Hay <strong><?= cantidadEnUnidades($insumo, $insumo['stockActual']) ?></strong>
            <?php if ($insumo['stockMinimo'] !== null): ?><span class="text-muted small">(mínimo <?= cantidadEnUnidades($insumo, $insumo['stockMinimo']) ?>)</span><?php endif; ?>
        </div>

        <div class="btn-group w-100 mb-3" role="group">
            <?php foreach (['compra' => 'Compra (entra)', 'salida' => 'Salida suelta', 'inventario' => 'Recuento (lo que hay)'] as $clave => $texto): ?>
            <input type="radio" class="btn-check" name="stkTipo" id="stkTipo_<?= $clave ?>" value="<?= $clave ?>" <?= $clave === $tipo ? 'checked' : '' ?>>
            <label class="btn btn-outline-dark" for="stkTipo_<?= $clave ?>"><?= $texto ?></label>
            <?php endforeach; ?>
        </div>

        <div class="row g-2 mb-3 align-items-end">
            <div class="col-12 small text-muted" id="stkAyuda"></div>
            <div class="col-md-4 col-6">
                <label class="form-label mb-0"><?= ucfirst($unidadCompra) ?>:</label>
                <input type="number" class="form-control" id="stkEnteras" min="0" step="1">
            </div>
            <?php if ($porCompra > 1): ?>
            <div class="col-md-4 col-6">
                <label class="form-label mb-0"><?= ucfirst($unidadUso) ?> sueltas:</label>
                <input type="number" class="form-control" id="stkSueltas" min="0" step="1">
            </div>
            <?php endif; ?>
        </div>

        <div class="row g-2 mb-3 soloCompra">
            <div class="col-md-4">
                <label class="form-label mb-0">Pedido de compra # <span class="text-muted">(opcional)</span>:</label>
                <input type="number" class="form-control" id="stkPedido">
            </div>
            <div class="col-md-8">
                <label class="form-label mb-0">Proveedor <span class="text-muted">(si no, el del pedido)</span>:</label>
                <select class="form-select" id="stkProveedor" style="width: 100%"></select>
            </div>
            <div class="col-12 small" id="stkPedidoInfo"></div>
            <?php if ($habituales): ?>
            <div class="col-12 small">
                Con precio cargado:
                <?php foreach ($habituales as $h): ?>
                <a href="#" class="badge bg-secondary text-decoration-none provHabitual" data-id="<?= (int)$h['id'] ?>"><?= htmlspecialchars($h['proveedor']) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <input type="text" class="form-control mb-3" id="stkObservaciones" maxlength="255" placeholder="Observaciones (opcional)">

        <button type="button" id="btnGuardarStock" class="btn btn-warning fw-bold w-100"><i class="bi bi-save"></i> GUARDAR</button>
    </div>

    <div class="p-3 bg-white rounded shadow-sm" id="movimientosStock">
        <h6 class="fw-bold">Últimos movimientos</h6>
        <?php if (!$movimientos): ?>
        <div class="text-muted small">Todavía no tiene movimientos.</div>
        <?php else: ?>
        <table class="table table-sm small mb-0 align-middle">
            <tbody>
                <?php foreach ($movimientos as $n => $m): [$etiqueta, $clase] = ETIQUETAS_MOVIMIENTO[$m['tipo']]; ?>
                <tr data-id="<?= (int)$m['id'] ?>">
                    <td class="text-nowrap"><?= date('d/m/y H:i', strtotime($m['fecha'])) ?></td>
                    <td><span class="badge <?= $clase ?>"><?= $etiqueta ?></span></td>
                    <td class="text-nowrap <?= $m['cantidad'] < 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $m['cantidad'] > 0 ? '+' : '' ?><?= cantidadEnUnidades($insumo, $m['cantidad']) ?>
                    </td>
                    <td class="text-muted">
                        <?= $m['tipo'] === 'inventario' ? 'se contaron ' . cantidadEnUnidades($insumo, $m['contado']) . ' · ' : '' ?>
                        <?= $m['idPedido'] ? 'pedido #' . (int)$m['idPedido'] . ' · ' : '' ?>
                        <?= $m['proveedor'] ? htmlspecialchars($m['proveedor']) . ' · ' : '' ?>
                        <?= $m['observaciones'] ? htmlspecialchars($m['observaciones']) . ' · ' : '' ?>
                        <?= htmlspecialchars($m['usuario'] ?? '') ?>
                    </td>
                    <td class="text-end">
                        <?php if ($n === 0): ?>
                        <i class="bi bi-arrow-counterclockwise text-danger btnDeshacerMovimiento" style="cursor: pointer" title="Deshacer (si se cargó mal)"></i>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    var idInsumo = <?= $idInsumo ?>;
    var AYUDA = {
        compra: 'Lo que entró. Se suma a lo que hay.',
        salida: 'Lo que se sacó por algo puntual (no hace falta para el uso diario: eso sale del recuento).',
        inventario: 'Lo que hay ahora, contado. El sistema registra la diferencia con lo que tenía.'
    };

    function mostrarTipo() {
        var tipo = $('input[name=stkTipo]:checked').val();
        $('#formStock .soloCompra').toggle(tipo === 'compra');
        $('#stkAyuda').text(AYUDA[tipo]);
    }
    $('input[name=stkTipo]').on('change', mostrarTipo);
    mostrarTipo();
    $('#stkEnteras').trigger('focus');

    // Al cerrar, la página de stock se recarga solo si algo cambió.
    $('#modalUniversal').off('hidden.bs.modal.stock').one('hidden.bs.modal.stock', function() {
        if (window.stockCambiado) $('#contenido').load('insumos/stock.php');
        window.stockCambiado = false;
    });

    function recargar() {
        window.stockCambiado = true;
        $('#modalUniversal .modal-content').load('insumos/modalStock.php?idInsumo=' + idInsumo + '&tipo=' + $('input[name=stkTipo]:checked').val());
    }

    $('#stkProveedor').select2({
        dropdownParent: $('#modalUniversal'),
        placeholder: 'Buscar proveedor...',
        allowClear: true,
        minimumInputLength: 3,
        ajax: {
            url: 'contactos/buscarContactos.php',
            dataType: 'json',
            delay: 250,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) {
                return { results: data.filter(function(c) { return c.id !== 'NEW'; }) };
            }
        }
    });

    $('.provHabitual').on('click', function(e) {
        e.preventDefault();
        $('#stkProveedor').empty().append(new Option($(this).text(), $(this).data('id'), true, true)).trigger('change');
    });

    // Muestra de quién es el pedido, para no enganchar la compra a otro por un número mal tipeado.
    $('#stkPedido').on('change', function() {
        var idPedido = $(this).val();
        if (!idPedido) { $('#stkPedidoInfo').text(''); return; }
        $.post('insumos/ajaxStock.php', { opcion: 'verPedido', idPedido: idPedido }, function(res) {
            var ok = res.trim().indexOf('✅') === 0;
            $('#stkPedidoInfo').text(res.trim().substring(2)).toggleClass('text-danger', !ok).toggleClass('text-success', ok);
        });
    });

    $('#btnGuardarStock').on('click', function() {
        var tipo = $('input[name=stkTipo]:checked').val();
        $.post('insumos/ajaxStock.php', {
            opcion: 'movimiento',
            idInsumo: idInsumo,
            tipo: tipo,
            enteras: $('#stkEnteras').val(),
            sueltas: $('#stkSueltas').val() || '',
            idPedido: tipo === 'compra' ? $('#stkPedido').val() : '',
            idProveedor: tipo === 'compra' ? ($('#stkProveedor').val() || '') : '',
            observaciones: $('#stkObservaciones').val()
        }, function(res) {
            if (res.trim().indexOf('✅') !== 0) { alert(res); return; }
            recargar();
        });
    });

    $('#movimientosStock').on('click', '.btnDeshacerMovimiento', function() {
        if (!confirm('¿Deshacer este movimiento? El stock vuelve a como estaba antes.')) return;
        $.post('insumos/ajaxStock.php', { opcion: 'deshacer', id: $(this).closest('tr').data('id') }, function(res) {
            if (res.trim().indexOf('✅') !== 0) { alert(res); return; }
            recargar();
        });
    });
});
</script>
