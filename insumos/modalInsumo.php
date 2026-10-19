<?php
include_once(__DIR__ . '/../sesion.php');
exigirPermisoPagina('insumos/insumos.php');
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/comunInsumos.php');
$conn = conectar();

// Alta o edición de un insumo (?id=). Los precios se cargan una vez guardado el insumo.
$id = (int)($_GET['id'] ?? 0);
$insumo = ['idCategoria' => 0, 'nombre' => '', 'tipo' => '', 'largo' => '', 'ancho' => '', 'gramaje' => '', 'color' => '',
           'idMaquina' => 0, 'rendimiento' => '', 'medida' => '', 'unidadCompra' => '', 'unidadesPorCompra' => '',
           'unidadUso' => '', 'observaciones' => '', 'activo' => 1, 'maquina' => null];
if ($id > 0) {
    $stmt = $conn->prepare("SELECT i.*, m.nombre AS maquina FROM insumos i LEFT JOIN maquinas m ON m.id = i.idMaquina WHERE i.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $insumo = $stmt->get_result()->fetch_assoc();
    if (!$insumo) {
        echo '<div class="modal-body"><div class="alert alert-warning mb-0">No se encontró el insumo.</div></div>';
        exit;
    }
}

$categorias = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nombre, ficha FROM insumosCategorias ORDER BY orden, nombre"), MYSQLI_ASSOC);
$stmt = $conn->prepare("SELECT id, tipo, nombre, modelo FROM maquinas WHERE activo = 1 OR id = ? ORDER BY tipo, nombre");
$stmt->bind_param('i', $insumo['idMaquina']);
$stmt->execute();
$maquinas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if ($id > 0) {
    $vigentes = preciosVigentes($conn, $id)[$id] ?? [];
    $stmt = $conn->prepare("SELECT ip.*, TRIM(CONCAT(c.apellido, ' ', COALESCE(c.nombre, ''))) AS proveedor, u.usuario AS cargo
                            FROM insumosPrecios ip
                            INNER JOIN contactos c ON c.id = ip.idProveedor
                            LEFT JOIN usuarios u ON u.id = ip.idUsuario
                            WHERE ip.idInsumo = ?
                            ORDER BY ip.fecha DESC, ip.id DESC");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    // Atajos para elegir el proveedor: los que más precios tienen cargados en cualquier insumo.
    $habituales = mysqli_fetch_all(mysqli_query($conn, "SELECT c.id, TRIM(CONCAT(c.apellido, ' ', COALESCE(c.nombre, ''))) AS proveedor
                                                        FROM insumosPrecios ip INNER JOIN contactos c ON c.id = ip.idProveedor
                                                        GROUP BY c.id, c.apellido, c.nombre
                                                        ORDER BY COUNT(*) DESC LIMIT 8"), MYSQLI_ASSOC);
}

function valor($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }
?>

<div class="modal-header bg-dark text-white">
    <h5 class="modal-title"><i class="bi bi-box-seam"></i> <?= $id ? valor($insumo['nombre']) : 'Nuevo insumo' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">
    <div class="container-fluid p-3 bg-white rounded shadow-sm mb-3" id="formInsumo">
        <div class="row mb-3">
            <div class="col-md-4">
                <label class="form-label fw-bold">Categoría:</label>
                <select class="form-select" id="insCategoria">
                    <option value="">Elegir...</option>
                    <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" data-ficha="<?= $c['ficha'] ?>" <?= (int)$c['id'] === (int)$insumo['idCategoria'] ? 'selected' : '' ?>><?= valor($c['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label fw-bold">Nombre:</label>
                <input type="text" class="form-control" id="insNombre" maxlength="80" value="<?= valor($insumo['nombre']) ?>" placeholder="Ej: A4 75 g obra, Toner negro Ricoh 2501, Rulo plástico 12 mm">
            </div>
        </div>

        <div class="row mb-3 g-2">
            <div class="col-md-4 ficha papel encuadernado general">
                <label class="form-label">Tipo:</label>
                <input type="text" class="form-control" id="insTipo" maxlength="40" value="<?= valor($insumo['tipo']) ?>">
            </div>
            <div class="col-md-2 col-4 ficha papel">
                <label class="form-label">Largo (mm):</label>
                <input type="number" class="form-control" id="insLargo" min="1" value="<?= valor($insumo['largo']) ?>">
            </div>
            <div class="col-md-2 col-4 ficha papel">
                <label class="form-label">Ancho (mm):</label>
                <input type="number" class="form-control" id="insAncho" min="1" value="<?= valor($insumo['ancho']) ?>">
            </div>
            <div class="col-md-2 col-4 ficha papel">
                <label class="form-label">Gramaje (g):</label>
                <input type="number" class="form-control" id="insGramaje" min="1" value="<?= valor($insumo['gramaje']) ?>">
            </div>
            <div class="col-md-5 ficha toner">
                <label class="form-label">Máquina:</label>
                <select class="form-select" id="insMaquina">
                    <option value="">Elegir...</option>
                    <?php foreach ($maquinas as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= (int)$m['id'] === (int)$insumo['idMaquina'] ? 'selected' : '' ?>>
                        <?= valor($m['nombre'] . ($m['modelo'] ? " ({$m['modelo']})" : '') . " · {$m['tipo']}") ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$maquinas): ?><div class="form-text">Primero cargá las máquinas en Insumos &gt; Máquinas.</div><?php endif; ?>
            </div>
            <div class="col-md-3 ficha toner">
                <label class="form-label">Rendimiento (copias):</label>
                <input type="number" class="form-control" id="insRendimiento" min="1" value="<?= valor($insumo['rendimiento']) ?>">
            </div>
            <div class="col-md-3 ficha encuadernado general">
                <label class="form-label">Medida:</label>
                <input type="text" class="form-control" id="insMedida" maxlength="30" value="<?= valor($insumo['medida']) ?>" placeholder="Ej: 12 mm">
            </div>
            <div class="col-md-2 ficha papel toner encuadernado general">
                <label class="form-label">Color:</label>
                <input type="text" class="form-control" id="insColor" maxlength="30" value="<?= valor($insumo['color']) ?>">
            </div>
        </div>

        <div class="row mb-3 g-2 align-items-end">
            <div class="col-md-3 col-4">
                <label class="form-label">Se compra por:</label>
                <input type="text" class="form-control" id="insUnidadCompra" maxlength="20" value="<?= valor($insumo['unidadCompra']) ?>" placeholder="Resma">
            </div>
            <div class="col-md-2 col-4">
                <label class="form-label">que trae:</label>
                <input type="number" class="form-control" id="insUnidadesPorCompra" min="1" value="<?= valor($insumo['unidadesPorCompra']) ?>" placeholder="500">
            </div>
            <div class="col-md-3 col-4">
                <label class="form-label">unidad de uso:</label>
                <input type="text" class="form-control" id="insUnidadUso" maxlength="20" value="<?= valor($insumo['unidadUso']) ?>" placeholder="hoja">
            </div>
            <div class="col-md-4">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="insActivo" <?= $insumo['activo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="insActivo">Activo (se sigue usando)</label>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Observaciones:</label>
            <input type="text" class="form-control" id="insObservaciones" maxlength="255" value="<?= valor($insumo['observaciones']) ?>">
        </div>

        <div class="d-flex gap-2">
            <button type="button" id="btnGuardarInsumo" class="btn btn-warning fw-bold flex-grow-1"><i class="bi bi-save"></i> GUARDAR</button>
            <?php if ($id): ?>
            <button type="button" id="btnBorrarInsumo" class="btn btn-outline-danger" title="Borrar"><i class="bi bi-trash"></i></button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($id): ?>
    <div class="container-fluid p-3 bg-white rounded shadow-sm" id="preciosInsumo">
        <h6 class="fw-bold"><i class="bi bi-tags"></i> Precios por proveedor <span class="fw-normal text-muted small">(por <?= valor(strtolower($insumo['unidadCompra'])) ?>)</span></h6>

        <?php if ($vigentes): ?>
        <table class="table table-sm align-middle mb-3">
            <thead class="table-light"><tr><th>Proveedor</th><th class="text-end">Precio vigente</th><th class="text-end">Costo por unidad</th><th>Desde</th></tr></thead>
            <tbody>
                <?php foreach ($vigentes as $n => $p):
                    $viejo = $p['fecha'] < date('Y-m-d', strtotime('-' . DIAS_PRECIO_VIEJO . ' days'));
                ?>
                <tr class="<?= $n === 0 ? 'table-success' : '' ?>">
                    <td><?= valor($p['proveedor']) ?><?= $n === 0 && count($vigentes) > 1 ? ' <span class="badge bg-success">más barato</span>' : '' ?></td>
                    <td class="text-end fw-bold"><?= fmtPesos($p['precio']) ?></td>
                    <td class="text-end small"><?= valor(costoPorUnidad($insumo, $p['precio'])) ?></td>
                    <td class="small <?= $viejo ? 'text-danger' : '' ?>"><?= date('d/m/y', strtotime($p['fecha'])) ?><?= $viejo ? ' <i class="bi bi-exclamation-triangle" title="Conviene actualizarlo"></i>' : '' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="text-muted small mb-3">Todavía no tiene precios cargados.</div>
        <?php endif; ?>

        <div class="border rounded p-2 bg-light mb-2">
            <div class="small fw-bold mb-2">Cargar precio nuevo (no borra el anterior: queda en el historial)</div>
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small mb-0">Proveedor:</label>
                    <select class="form-select" id="preProveedor" style="width: 100%"></select>
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small mb-0">Precio $:</label>
                    <input type="number" class="form-control" id="prePrecio" min="0" step="0.01">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-0">Fecha:</label>
                    <input type="date" class="form-control" id="preFecha" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-success w-100" id="btnGuardarPrecio"><i class="bi bi-plus-circle"></i> Agregar</button>
                </div>
                <?php if ($habituales): ?>
                <div class="col-12 small">
                    Habituales:
                    <?php foreach ($habituales as $h): ?>
                    <a href="#" class="badge bg-secondary text-decoration-none provHabitual" data-id="<?= (int)$h['id'] ?>"><?= valor($h['proveedor']) ?></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="col-12">
                    <input type="text" class="form-control form-control-sm" id="preObservaciones" maxlength="255" placeholder="Observaciones (opcional): con IVA, pago contado...">
                </div>
            </div>
        </div>

        <?php if ($historial): ?>
        <a class="small" data-bs-toggle="collapse" href="#historialPrecios">Ver historial de precios (<?= count($historial) ?>)</a>
        <div class="collapse" id="historialPrecios">
            <table class="table table-sm small mt-2 mb-0">
                <tbody>
                    <?php foreach ($historial as $h): ?>
                    <tr data-id="<?= (int)$h['id'] ?>">
                        <td><?= date('d/m/y', strtotime($h['fecha'])) ?></td>
                        <td><?= valor($h['proveedor']) ?></td>
                        <td class="text-end"><?= fmtPesos($h['precio']) ?></td>
                        <td class="text-muted"><?= valor($h['observaciones']) ?><?= $h['cargo'] ? ' · cargó ' . valor($h['cargo']) : '' ?></td>
                        <td class="text-end"><i class="bi bi-trash text-danger btnBorrarPrecio" style="cursor: pointer" title="Borrar este precio (si se cargó mal)"></i></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    var idInsumo = <?= $id ?>;

    // Lo que trae por defecto cada ficha al elegir la categoría de un insumo nuevo.
    var PRESENTACION = {
        papel:        ['Resma', 500, 'hoja'],
        toner:        ['Cartucho', 1, 'cartucho'],
        encuadernado: ['Caja', 100, 'unidad'],
        general:      ['Unidad', 1, 'unidad']
    };
    var TIPO = { papel: 'Obra, ilustración, autoadhesivo...', encuadernado: 'Rulo plástico, rulo metálico...', general: '' };

    function mostrarFicha(cambioCategoria) {
        var ficha = $('#insCategoria option:selected').data('ficha');
        $('#formInsumo .ficha').hide();
        if (!ficha) return;
        $('#formInsumo .ficha.' + ficha).show();
        $('#insTipo').attr('placeholder', TIPO[ficha] || '');
        if (cambioCategoria && !idInsumo) {
            $('#insUnidadCompra').val(PRESENTACION[ficha][0]);
            $('#insUnidadesPorCompra').val(PRESENTACION[ficha][1]);
            $('#insUnidadUso').val(PRESENTACION[ficha][2]);
        }
    }
    $('#insCategoria').on('change', function() { mostrarFicha(true); });
    mostrarFicha(false);

    // Al cerrar, la lista se recarga solo si algo cambió.
    $('#modalUniversal').off('hidden.bs.modal.insumos').one('hidden.bs.modal.insumos', function() {
        if (window.insumosCambiados) $('#contenido').load(window.urlListaActual || 'insumos/insumos.php');
        window.insumosCambiados = false;
    });

    function recargarModal(id) {
        window.insumosCambiados = true;
        $('#modalUniversal .modal-content').load('insumos/modalInsumo.php?id=' + id);
    }

    $('#btnGuardarInsumo').on('click', function() {
        $.post('insumos/ajaxInsumos.php', {
            opcion: 'guardarInsumo',
            id: idInsumo,
            idCategoria: $('#insCategoria').val(),
            nombre: $('#insNombre').val(),
            tipo: $('#insTipo').val(),
            largo: $('#insLargo').val(),
            ancho: $('#insAncho').val(),
            gramaje: $('#insGramaje').val(),
            color: $('#insColor').val(),
            idMaquina: $('#insMaquina').val(),
            rendimiento: $('#insRendimiento').val(),
            medida: $('#insMedida').val(),
            unidadCompra: $('#insUnidadCompra').val(),
            unidadesPorCompra: $('#insUnidadesPorCompra').val(),
            unidadUso: $('#insUnidadUso').val(),
            observaciones: $('#insObservaciones').val(),
            activo: $('#insActivo').is(':checked') ? 1 : ''
        }, function(res) {
            res = res.trim();
            // Con un error, el formulario queda como está para no perder lo que se escribió.
            if (res.indexOf('✅') !== 0) { alert(res); return; }
            // Queda abierto, ya guardado, para cargarle los precios.
            recargarModal(parseInt(res.substring(1).trim(), 10));
        });
    });

    $('#btnBorrarInsumo').on('click', function() {
        if (!confirm('¿Borrar este insumo?')) return;
        $.post('insumos/ajaxInsumos.php', { opcion: 'borrarInsumo', id: idInsumo }, function(res) {
            if (res.trim().indexOf('✅') !== 0) { alert(res); return; }
            window.insumosCambiados = true;
            $('#modalUniversal').modal('hide');
        });
    });

    if (!idInsumo) return;

    // Proveedor: cualquier contacto, buscado como en Nuevo pedido (son muchos para un combo fijo).
    $('#preProveedor').select2({
        dropdownParent: $('#modalUniversal'),
        placeholder: 'Buscar proveedor...',
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
        var opcion = new Option($(this).text(), $(this).data('id'), true, true);
        $('#preProveedor').empty().append(opcion).trigger('change');
    });

    $('#btnGuardarPrecio').on('click', function() {
        $.post('insumos/ajaxInsumos.php', {
            opcion: 'guardarPrecio',
            idInsumo: idInsumo,
            idProveedor: $('#preProveedor').val(),
            precio: $('#prePrecio').val(),
            fecha: $('#preFecha').val(),
            observaciones: $('#preObservaciones').val()
        }, function(res) {
            if (res.trim().indexOf('✅') !== 0) { alert(res); return; }
            recargarModal(idInsumo);
        });
    });

    $('#preciosInsumo').on('click', '.btnBorrarPrecio', function() {
        if (!confirm('¿Borrar este precio? Usalo solo si se cargó mal; para un precio nuevo, agregalo arriba.')) return;
        $.post('insumos/ajaxInsumos.php', { opcion: 'borrarPrecio', id: $(this).closest('tr').data('id') }, function(res) {
            if (res.trim().indexOf('✅') !== 0) { alert(res); return; }
            recargarModal(idInsumo);
        });
    });
});
</script>
