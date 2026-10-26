<?php
include_once(__DIR__ . '/../sesion.php');
exigirPermisoPagina('insumos/procesos.php');
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/comunProcesos.php');
$conn = conectar();

// Alta o edición de un proceso (?id=) con sus tramos de precio y sus insumos. Abajo, una prueba
// de precio y costo para algunas cantidades, que se recalcula mientras se escribe.
$id = (int)($_GET['id'] ?? 0);
$proceso = ['nombre' => '', 'tipo' => '', 'idMaquina' => 0, 'toner' => 'no', 'unidad' => '', 'valorHora' => '', 'idComplejidad' => 0,
            'tiempoPreparacion' => '', 'tiempoPorUnidad' => '', 'ventaSuelta' => 1, 'observaciones' => '', 'activo' => 1];
$tramos = [['desde' => 1, 'arranque' => '', 'porUnidad' => '']];
$renglones = [];
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM procesos WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $proceso = $stmt->get_result()->fetch_assoc();
    if (!$proceso) {
        echo '<div class="modal-body"><div class="alert alert-warning mb-0">No se encontró el proceso.</div></div>';
        exit;
    }
    $stmt = $conn->prepare("SELECT desde, arranque, porUnidad FROM procesosPrecios WHERE idProceso = ? ORDER BY desde");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $tramos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: $tramos;
    $stmt = $conn->prepare("SELECT idInsumo, cantidad FROM procesosInsumos WHERE idProceso = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $renglones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$complejidades = mysqli_fetch_all(mysqli_query($conn, "SELECT id, nombre, factor FROM complejidades ORDER BY orden, nombre"), MYSQLI_ASSOC);
$stmt = $conn->prepare("SELECT id, tipo, nombre, modelo, costoPorUso FROM maquinas WHERE activo = 1 OR id = ? ORDER BY tipo, nombre");
$stmt->bind_param('i', $proceso['idMaquina']);
$stmt->execute();
$maquinas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Insumos para elegir: todos menos el toner (sale de la máquina). Los que ya usa, aunque estén inactivos.
$usados = implode(',', array_map('intval', array_column($renglones, 'idInsumo'))) ?: '0';
$insumos = mysqli_fetch_all(mysqli_query($conn, "SELECT i.id, i.nombre, i.unidadUso, c.nombre AS categoria
                                                 FROM insumos i INNER JOIN insumosCategorias c ON c.id = i.idCategoria
                                                 WHERE c.ficha <> 'toner' AND (i.activo = 1 OR i.id IN ($usados))
                                                 ORDER BY c.orden, i.nombre"), MYSQLI_ASSOC);
$costos = datosDeCosto($conn);

$tipos = ['impresión', 'corte', 'terminación', 'diseño'];
$res = mysqli_query($conn, "SELECT DISTINCT tipo FROM procesos WHERE tipo IS NOT NULL ORDER BY tipo");
while ($fila = mysqli_fetch_row($res)) $tipos[] = $fila[0];
$tipos = array_unique($tipos);

// Lo que necesita el cálculo en el navegador.
$datosJs = [
    'factores' => array_column($complejidades, 'factor', 'id'),
    'maquinas' => array_map(fn($m) => ['costoPorUso' => (float)$m['costoPorUso'], 'toner' => $costos['toner'][$m['id']] ?? []],
                            array_column($maquinas, null, 'id')),
    'insumos'  => array_map(fn($i) => $costos['insumos'][$i['id']] ?? null, array_column($insumos, null, 'id')),
];

function valor($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES); }
// Decimales guardados sin ceros de más: 0.0500 → 0.05, 40.00 → 40.
function numeroCampo($v) { return str_contains((string)$v, '.') ? rtrim(rtrim((string)$v, '0'), '.') : (string)$v; }
?>

<div class="modal-header bg-dark text-white">
    <h5 class="modal-title"><i class="bi bi-gear-wide-connected"></i> <?= $id ? valor($proceso['nombre']) : 'Nuevo proceso' ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light" id="formProceso">
    <div class="container-fluid p-3 bg-white rounded shadow-sm mb-3">
        <div class="row g-2 mb-2">
            <div class="col-md-6">
                <label class="form-label fw-bold">Nombre:</label>
                <input type="text" class="form-control" id="proNombre" maxlength="80" value="<?= valor($proceso['nombre']) ?>" placeholder="Ej: Impresión color A4, Plastificado A3">
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label">Tipo:</label>
                <input type="text" class="form-control" id="proTipo" maxlength="30" list="proTipos" value="<?= valor($proceso['tipo']) ?>">
                <datalist id="proTipos"><?php foreach ($tipos as $t): ?><option value="<?= valor($t) ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label fw-bold">Se cobra por:</label>
                <input type="text" class="form-control" id="proUnidad" maxlength="20" list="proUnidades" value="<?= valor($proceso['unidad']) ?>" placeholder="cara, hoja, m²...">
                <datalist id="proUnidades"><?php foreach (UNIDADES_PROCESO as $u): ?><option value="<?= valor($u) ?>"><?php endforeach; ?></datalist>
            </div>
            <div class="col-md-6">
                <label class="form-label">Máquina:</label>
                <select class="form-select" id="proMaquina">
                    <option value="">Sin máquina</option>
                    <?php foreach ($maquinas as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= (int)$m['id'] === (int)$proceso['idMaquina'] ? 'selected' : '' ?>>
                        <?= valor($m['nombre'] . ($m['modelo'] ? " ({$m['modelo']})" : '') . " · {$m['tipo']}") ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$maquinas): ?><div class="form-text">Las máquinas se cargan en Insumos &gt; Máquinas.</div><?php endif; ?>
            </div>
            <div class="col-md-6 conMaquina">
                <label class="form-label">Toner que gasta:</label>
                <select class="form-select" id="proToner">
                    <?php foreach (TONER_PROCESO as $clave => $texto): ?>
                    <option value="<?= $clave ?>" <?= $clave === $proceso['toner'] ? 'selected' : '' ?>><?= $texto ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <input type="text" class="form-control form-control-sm" id="proObservaciones" maxlength="255" value="<?= valor($proceso['observaciones']) ?>" placeholder="Observaciones (opcional)">
            </div>
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="proVentaSuelta" <?= $proceso['ventaSuelta'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="proVentaSuelta">Se vende suelto (si no, solo dentro de productos)</label>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="proActivo" <?= $proceso['activo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="proActivo">Activo (se sigue haciendo)</label>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid p-3 bg-white rounded shadow-sm mb-3">
        <h6 class="fw-bold"><i class="bi bi-person-gear"></i> Mano de obra</h6>
        <div class="row g-2">
            <div class="col-md-3 col-6">
                <label class="form-label small mb-0">Valor hora $:</label>
                <input type="number" class="form-control calc" id="proValorHora" min="0" step="0.01" value="<?= valor(numeroCampo($proceso['valorHora'])) ?>">
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small mb-0">Complejidad:</label>
                <select class="form-select calc" id="proComplejidad">
                    <?php foreach ($complejidades as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$proceso['idComplejidad'] ? 'selected' : '' ?>>
                        <?= valor($c['nombre']) ?> (× <?= number_format($c['factor'], 2, ',', '') ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small mb-0">Preparación (minutos):</label>
                <input type="number" class="form-control calc" id="proTiempoPreparacion" min="0" step="0.5" value="<?= valor(numeroCampo($proceso['tiempoPreparacion'])) ?>" placeholder="una vez por trabajo">
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small mb-0">Minutos por <span class="nombreUnidad">unidad</span>:</label>
                <input type="number" class="form-control calc" id="proTiempoPorUnidad" min="0" step="0.01" value="<?= valor(numeroCampo($proceso['tiempoPorUnidad'])) ?>">
                <div class="form-text" id="proPorHora"></div>
            </div>
        </div>
    </div>

    <div class="container-fluid p-3 bg-white rounded shadow-sm mb-3">
        <h6 class="fw-bold"><i class="bi bi-box-seam"></i> Insumos que gasta <span class="fw-normal text-muted small">por cada <span class="nombreUnidad">unidad</span> (ni papel ni toner)</span></h6>
        <table class="table table-sm align-middle mb-2" id="tablaInsumos">
            <tbody>
                <?php foreach ($renglones ?: [['idInsumo' => 0, 'cantidad' => '']] as $r): ?>
                <tr>
                    <td>
                        <select class="form-select form-select-sm calc insInsumo">
                            <option value="">Elegir insumo...</option>
                            <?php foreach ($insumos as $i): ?>
                            <option value="<?= (int)$i['id'] ?>" data-unidad="<?= valor($i['unidadUso']) ?>" <?= (int)$i['id'] === (int)$r['idInsumo'] ? 'selected' : '' ?>>
                                <?= valor("{$i['categoria']} · {$i['nombre']}") ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td style="width: 150px">
                        <div class="input-group input-group-sm">
                            <input type="number" class="form-control calc insCantidad" min="0" step="0.01" value="<?= valor(numeroCampo($r['cantidad'])) ?>">
                            <span class="input-group-text insUnidad"></span>
                        </div>
                    </td>
                    <td class="text-end small insCosto" style="width: 110px"></td>
                    <td class="text-end" style="width: 30px"><i class="bi bi-x-lg text-danger btnQuitar" style="cursor: pointer" title="Sacar"></i></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="btn btn-outline-success btn-sm" id="btnAgregarInsumo"><i class="bi bi-plus"></i> Otro insumo</button>
    </div>

    <div class="container-fluid p-3 bg-white rounded shadow-sm mb-3">
        <h6 class="fw-bold"><i class="bi bi-tags"></i> Precio de venta</h6>
        <div class="small text-muted mb-2">
            Total = arranque + precio por <span class="nombreUnidad">unidad</span> × cantidad. Con un solo tramo alcanza;
            agregá otro si a partir de cierta cantidad cambia el precio.
        </div>
        <table class="table table-sm align-middle mb-2" id="tablaTramos">
            <thead class="table-light"><tr><th>Desde</th><th>Arranque $</th><th>Por <span class="nombreUnidad">unidad</span> $</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($tramos as $n => $t): ?>
                <tr>
                    <td><input type="number" class="form-control form-control-sm calc traDesde" min="1" step="1" value="<?= valor($t['desde']) ?>" <?= $n === 0 ? 'readonly' : '' ?>></td>
                    <td><input type="number" class="form-control form-control-sm calc traArranque" min="0" step="0.01" value="<?= valor(numeroCampo($t['arranque'])) ?>"></td>
                    <td><input type="number" class="form-control form-control-sm calc traPorUnidad" min="0" step="0.01" value="<?= valor(numeroCampo($t['porUnidad'])) ?>"></td>
                    <td class="text-end" style="width: 30px"><?php if ($n > 0): ?><i class="bi bi-x-lg text-danger btnQuitar" style="cursor: pointer" title="Sacar"></i><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="button" class="btn btn-outline-success btn-sm" id="btnAgregarTramo"><i class="bi bi-plus"></i> Otro tramo</button>
    </div>

    <div class="container-fluid p-3 bg-white rounded shadow-sm mb-3">
        <h6 class="fw-bold"><i class="bi bi-calculator"></i> Prueba
            <span class="fw-normal small">con <input type="number" class="form-control form-control-sm d-inline-block calc" style="width: 90px" id="proPrueba" min="1" step="1" placeholder="otra"> <span class="nombreUnidad">unidad</span>(s)</span>
        </h6>
        <div class="table-responsive">
            <table class="table table-sm small text-end mb-1" id="tablaPrueba">
                <thead class="table-light"><tr><th class="text-start">Cantidad</th><th>Precio total</th><th>Precio c/u</th><th>Costo total</th><th>Margen</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="small text-muted" id="proCostoDetalle"></div>
        <div class="small text-danger" id="proAvisos"></div>
    </div>

    <div class="d-flex gap-2">
        <button type="button" id="btnGuardarProceso" class="btn btn-warning fw-bold flex-grow-1"><i class="bi bi-save"></i> GUARDAR</button>
        <?php if ($id): ?>
        <button type="button" id="btnBorrarProceso" class="btn btn-outline-danger" title="Borrar"><i class="bi bi-trash"></i></button>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    var idProceso = <?= $id ?>;
    var DATOS = <?= json_encode($datosJs) ?>;
    var CANTIDADES = [1, 10, 100, 1000];

    function num(v) { var n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? 0 : n; }
    function pesos(n) {
        var decimales = Math.abs(n) < 10 && Math.round(n * 100) / 100 !== n ? 4 : 2;
        return '$' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: decimales });
    }

    function tramos() {
        var lista = [];
        $('#tablaTramos tbody tr').each(function() {
            var desde = parseInt($(this).find('.traDesde').val(), 10);
            if (desde >= 1) lista.push({ desde: desde, arranque: num($(this).find('.traArranque').val()), porUnidad: num($(this).find('.traPorUnidad').val()) });
        });
        return lista.sort(function(a, b) { return a.desde - b.desde; });
    }

    // Igual que precioProceso() en comunProcesos.php.
    function precio(lista, cantidad) {
        var tramo = null;
        lista.forEach(function(t) { if (t.desde <= cantidad) tramo = t; });
        return tramo ? tramo.arranque + tramo.porUnidad * cantidad : null;
    }

    // Igual que costoProceso() en comunProcesos.php.
    function costo() {
        var faltan = [], partes = [];
        var valorHora = num($('#proValorHora').val()) * num(DATOS.factores[$('#proComplejidad').val()]);
        var prep = num($('#proTiempoPreparacion').val()), porUnidad = num($('#proTiempoPorUnidad').val());
        if (!num($('#proValorHora').val()) && (prep || porUnidad)) faltan.push('el valor hora');
        var c = { fijo: prep / 60 * valorHora, porUnidad: porUnidad / 60 * valorHora };
        if (c.porUnidad) partes.push('mano de obra ' + pesos(c.porUnidad));

        var insumos = 0;
        $('#tablaInsumos tbody tr').each(function() {
            var idInsumo = $(this).find('.insInsumo').val();
            var $opcion = $(this).find('.insInsumo option:selected');
            $(this).find('.insUnidad').text($opcion.data('unidad') || '');
            var cantidad = num($(this).find('.insCantidad').val());
            var unitario = idInsumo ? DATOS.insumos[idInsumo] : undefined;
            if (idInsumo && (unitario === null || unitario === undefined)) {
                faltan.push('el precio de ' + $opcion.text().trim());
                $(this).find('.insCosto').text('sin precio');
            } else {
                $(this).find('.insCosto').text(idInsumo && cantidad ? pesos(cantidad * unitario) : '');
                if (idInsumo) insumos += cantidad * unitario;
            }
        });
        if (insumos) partes.push('insumos ' + pesos(insumos));
        c.porUnidad += insumos;

        var maquina = DATOS.maquinas[$('#proMaquina').val()];
        if (maquina) {
            if (maquina.costoPorUso) partes.push('máquina ' + pesos(maquina.costoPorUso));
            c.porUnidad += maquina.costoPorUso;
            var toner = $('#proToner').val();
            if (toner !== 'no') {
                if (maquina.toner[toner] === undefined) faltan.push('el toner de la máquina (precio y rendimiento)');
                else { c.porUnidad += maquina.toner[toner]; partes.push('toner ' + pesos(maquina.toner[toner])); }
            }
        }
        c.faltan = faltan;
        c.partes = partes;
        return c;
    }

    function recalcular() {
        var unidad = $('#proUnidad').val().trim() || 'unidad';
        $('.nombreUnidad').text(unidad);
        $('.conMaquina').toggle(!!$('#proMaquina').val());

        var minutos = num($('#proTiempoPorUnidad').val());
        $('#proPorHora').text(minutos ? '≈ ' + Math.round(60 / minutos).toLocaleString('es-AR') + ' por hora' : '');

        var lista = tramos(), c = costo();
        var cantidades = CANTIDADES.slice();
        var prueba = parseInt($('#proPrueba').val(), 10);
        if (prueba > 0 && cantidades.indexOf(prueba) === -1) cantidades.push(prueba);
        cantidades.sort(function(a, b) { return a - b; });

        var filas = cantidades.map(function(q) {
            var p = precio(lista, q), k = c.fijo + c.porUnidad * q;
            var margen = p ? Math.round((p - k) / p * 100) : null;
            return '<tr' + (q === prueba ? ' class="table-warning"' : '') + '>'
                 + '<td class="text-start">' + q.toLocaleString('es-AR') + '</td>'
                 + '<td class="fw-bold">' + (p === null ? '-' : pesos(p)) + '</td>'
                 + '<td>' + (p === null ? '-' : pesos(p / q)) + '</td>'
                 + '<td>' + pesos(k) + '</td>'
                 + '<td class="' + (margen !== null && margen < 0 ? 'text-danger fw-bold' : '') + '">' + (margen === null ? '-' : margen + ' %') + '</td></tr>';
        });
        $('#tablaPrueba tbody').html(filas.join(''));

        $('#proCostoDetalle').text('Costo: ' + (c.fijo ? pesos(c.fijo) + ' de preparación + ' : '') + pesos(c.porUnidad) + ' por ' + unidad
                                   + (c.partes.length > 1 ? ' (' + c.partes.join(' + ') + ')' : ''));

        // Un tramo nuevo no debería hacer que salga más barato pedir más (500 copias más baratas que 499).
        var avisos = [];
        for (var n = 1; n < lista.length; n++) {
            var antes = precio(lista, lista[n].desde - 1), despues = precio(lista, lista[n].desde);
            if (despues < antes) {
                avisos.push('Ojo: ' + lista[n].desde + ' salen ' + pesos(despues) + ', más barato que ' + (lista[n].desde - 1) + ' (' + pesos(antes) + ').');
            }
        }
        if (c.faltan.length) avisos.push('El costo está incompleto: falta ' + c.faltan.join(', ') + '.');
        $('#proAvisos').html(avisos.map(function(a) { return $('<div>').text(a).html(); }).join('<br>'));
    }

    $('#formProceso').on('input change', '.calc, #proUnidad, #proMaquina, #proToner', recalcular);

    $('#btnAgregarInsumo').on('click', function() {
        var $fila = $('#tablaInsumos tbody tr:first').clone();
        $fila.find('select').val('');
        $fila.find('input').val('');
        $('#tablaInsumos tbody').append($fila);
        recalcular();
    });

    $('#btnAgregarTramo').on('click', function() {
        var ultimo = tramos().pop();
        var $fila = $('#tablaTramos tbody tr:first').clone();
        $fila.find('input').val('').prop('readonly', false);
        $fila.find('.traDesde').val(ultimo ? ultimo.desde * 10 : '');
        $fila.find('td:last').html('<i class="bi bi-x-lg text-danger btnQuitar" style="cursor: pointer" title="Sacar"></i>');
        $('#tablaTramos tbody').append($fila);
        recalcular();
    });

    // Del último renglón de insumos se vacía en vez de sacarlo, para que quede dónde elegir.
    $('#formProceso').on('click', '.btnQuitar', function() {
        var $tr = $(this).closest('tr');
        if ($tr.siblings().length) $tr.remove();
        else { $tr.find('select, input').val(''); }
        recalcular();
    });

    recalcular();

    $('#btnGuardarProceso').on('click', function() {
        var datos = {
            opcion: 'guardarProceso',
            id: idProceso,
            nombre: $('#proNombre').val(),
            tipo: $('#proTipo').val(),
            unidad: $('#proUnidad').val(),
            idMaquina: $('#proMaquina').val(),
            toner: $('#proToner').val(),
            valorHora: $('#proValorHora').val(),
            idComplejidad: $('#proComplejidad').val(),
            tiempoPreparacion: $('#proTiempoPreparacion').val(),
            tiempoPorUnidad: $('#proTiempoPorUnidad').val(),
            ventaSuelta: $('#proVentaSuelta').is(':checked') ? 1 : '',
            activo: $('#proActivo').is(':checked') ? 1 : '',
            observaciones: $('#proObservaciones').val(),
            tramos: [],
            insumos: []
        };
        $('#tablaTramos tbody tr').each(function() {
            datos.tramos.push({ desde: $(this).find('.traDesde').val(), arranque: $(this).find('.traArranque').val(), porUnidad: $(this).find('.traPorUnidad').val() });
        });
        $('#tablaInsumos tbody tr').each(function() {
            datos.insumos.push({ idInsumo: $(this).find('.insInsumo').val(), cantidad: $(this).find('.insCantidad').val() });
        });
        $.post('insumos/ajaxProcesos.php', datos, function(res) {
            // Con un error, el formulario queda como está para no perder lo que se escribió.
            if (res.trim().indexOf('✅') !== 0) { alert(res); return; }
            $('#modalUniversal').modal('hide');
            $('#contenido').load(window.urlProcesos || 'insumos/procesos.php');
        });
    });

    $('#btnBorrarProceso').on('click', function() {
        if (!confirm('¿Borrar este proceso?')) return;
        $.post('insumos/ajaxProcesos.php', { opcion: 'borrarProceso', id: idProceso }, function(res) {
            if (res.trim().indexOf('✅') !== 0) { alert(res); return; }
            $('#modalUniversal').modal('hide');
            $('#contenido').load(window.urlProcesos || 'insumos/procesos.php');
        });
    });
});
</script>
