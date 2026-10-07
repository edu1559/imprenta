<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../celular.php');
$conn = conectar();

// Trabajos mandados a otros proveedores, agrupados por proveedor. Por defecto se ven
// los pendientes (falta recibirlos o pagarlos); con ?ver=todos, también los que se
// terminaron de recibir en los últimos 90 días.
$verTodos = ($_GET['ver'] ?? '') === 'todos';
$pendiente = "NOT (t.fechaRecibido IS NOT NULL AND t.pagado = 1)";
$filtro = $verTodos ? "($pendiente OR t.fechaRecibido >= NOW() - INTERVAL 90 DAY)" : $pendiente;

$res = mysqli_query($conn, "SELECT t.*, pr.apellido AS provApellido, pr.nombre AS provNombre, pr.celular,
                                   TRIM(CONCAT(c.apellido, ' ', COALESCE(c.nombre, ''))) AS cliente, u.usuario AS cargo
                            FROM tercerizados t
                            INNER JOIN contactos pr ON pr.id = t.idProveedor
                            LEFT JOIN pedidos p ON p.id = t.idPedido
                            LEFT JOIN contactos c ON c.id = p.idContacto
                            LEFT JOIN usuarios u ON u.id = t.idUsuario
                            WHERE $filtro
                            ORDER BY t.id");

// Estados en el orden en que conviene verlos: primero lo que hay que ir a buscar.
// clave => [etiqueta, clase del badge, botón para pasar al paso siguiente]
const ESTADOS_TERCERIZADO = [
    'listo'       => ['Listo para retirar', 'bg-success',           'Se retiró'],
    'enProveedor' => ['En el proveedor',    'bg-warning text-dark', 'Está listo'],
    'paraLlevar'  => ['Para llevar',        'bg-secondary',         'Se llevó'],
    'recibido'    => ['Recibido',           'bg-light text-dark border', null],
];

function estadoTercerizado($t) {
    if ($t['fechaRecibido']) return 'recibido';
    if ($t['fechaListo'])    return 'listo';
    if ($t['fechaEnviado'])  return 'enProveedor';
    return 'paraLlevar';
}

$proveedores = [];
$cantidades = array_fill_keys(array_keys(ESTADOS_TERCERIZADO), 0);
$sinPagar = ['cant' => 0, 'monto' => 0.0, 'sinPrecio' => 0];
while ($t = mysqli_fetch_assoc($res)) {
    $t['estado'] = estadoTercerizado($t);
    $idProveedor = (int)$t['idProveedor'];
    $proveedores[$idProveedor] ??= ['apellido' => $t['provApellido'], 'nombre' => $t['provNombre'], 'celular' => $t['celular'],
                                    'trabajos' => [], 'listos' => 0, 'debeCant' => 0, 'debeMonto' => 0.0];
    $proveedores[$idProveedor]['trabajos'][] = $t;
    if ($t['estado'] !== 'recibido' || !$t['pagado']) $cantidades[$t['estado']]++;
    if ($t['estado'] === 'listo') $proveedores[$idProveedor]['listos']++;
    if ($t['estado'] === 'recibido' && !$t['pagado']) {
        $proveedores[$idProveedor]['debeCant']++;
        $proveedores[$idProveedor]['debeMonto'] += (float)$t['precio'];
        $sinPagar['cant']++;
        $sinPagar['monto'] += (float)$t['precio'];
        if ($t['precio'] === null) $sinPagar['sinPrecio']++;
    }
}

// Primero los proveedores que tienen algo listo para retirar.
uasort($proveedores, fn($a, $b) => [$b['listos'] > 0, $a['apellido']] <=> [$a['listos'] > 0, $b['apellido']]);
$ordenEstados = array_flip(array_keys(ESTADOS_TERCERIZADO));

function fmtPrecio($n) { return $n === null ? '-' : '$' . number_format((float)$n, 0, ',', '.'); }
function fechaCorta($f) { return date('d/m', strtotime($f)); }

// Mensaje para preguntarle al proveedor por lo que tiene en su taller.
function enlaceConsultaProveedor($proveedor) {
    $enCurso = array_filter($proveedor['trabajos'], fn($t) => $t['estado'] === 'enProveedor');
    $mensaje = 'Hola ' . nombreSaludo($proveedor['apellido'], $proveedor['nombre']) . ', ';
    if ($enCurso) {
        $mensaje .= "te consulto cómo vienen estos trabajos de Imprenta Corintios 13:\n"
                  . implode("\n", array_map(fn($t) => '- ' . $t['descripcion'], $enCurso));
    }
    return enlaceWhatsApp($proveedor['celular'], $mensaje);
}
?>

<div class="container-fluid py-3 px-4" id="tercerizados">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 bg-white p-3 shadow-sm rounded">
        <div>
            <h3 class="text-success mb-0 fw-bold"><i class="bi bi-truck me-2"></i> Tercerizados</h3>
            <small class="text-muted">Trabajos que se mandan a otros proveedores</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnVerTercerizados" data-ver="<?= $verTodos ? '' : 'todos' ?>">
                <?= $verTodos ? 'Ver solo los pendientes' : 'Ver también los terminados' ?>
            </button>
            <button type="button" class="btn btn-success" id="btnNuevoTercerizado"><i class="bi bi-plus-circle"></i> Nuevo trabajo</button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach (['paraLlevar' => 'bi-box-seam', 'enProveedor' => 'bi-hourglass-split', 'listo' => 'bi-bag-check'] as $clave => $icono): ?>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="small text-muted fw-bold text-uppercase"><i class="bi <?= $icono ?>"></i> <?= ESTADOS_TERCERIZADO[$clave][0] ?></div>
                    <div class="h3 mb-0"><?= $cantidades[$clave] ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="small text-muted fw-bold"><i class="bi bi-cash"></i> RECIBIDOS SIN PAGAR</div>
                    <div class="h3 mb-0 text-danger"><?= fmtPrecio($sinPagar['monto']) ?></div>
                    <div class="small text-muted">
                        <?= $sinPagar['cant'] ?> trabajos<?= $sinPagar['sinPrecio'] ? ", {$sinPagar['sinPrecio']} sin precio cargado" : '' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$proveedores): ?>
    <div class="alert alert-light border text-center text-muted">No hay trabajos tercerizados pendientes.</div>
    <?php endif; ?>

    <?php foreach ($proveedores as $idProveedor => $proveedor):
        usort($proveedor['trabajos'], fn($a, $b) => [$ordenEstados[$a['estado']], $a['id']] <=> [$ordenEstados[$b['estado']], $b['id']]);
    ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-dark text-white py-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <span class="fw-bold"><?= htmlspecialchars(trim($proveedor['apellido'] . ' ' . $proveedor['nombre'])) ?></span>
                <?php if ($proveedor['celular']): ?>
                <a class="btn btn-success btn-sm ms-2" target="_blank" rel="noopener" href="<?= htmlspecialchars(enlaceConsultaProveedor($proveedor), ENT_QUOTES) ?>"
                   title="WhatsApp: <?= mostrarCelular($proveedor['celular']) ?>"><i class="bi bi-whatsapp"></i></a>
                <?php endif; ?>
                <?php if ($proveedor['listos']): ?>
                <span class="badge bg-success ms-2"><?= $proveedor['listos'] ?> para retirar</span>
                <?php endif; ?>
            </div>
            <?php if ($proveedor['debeCant']): ?>
            <div>
                <span class="small me-2">Recibido sin pagar: <strong><?= fmtPrecio($proveedor['debeMonto']) ?></strong> (<?= $proveedor['debeCant'] ?>)</span>
                <button type="button" class="btn btn-outline-light btn-sm btnPagarProveedor" data-proveedor="<?= $idProveedor ?>" data-cant="<?= $proveedor['debeCant'] ?>">Marcar pagados</button>
            </div>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Qué se mandó</th>
                            <th>Pedido</th>
                            <th>Estado</th>
                            <th>Prometido</th>
                            <th class="text-end">Precio</th>
                            <th class="text-center">Pagado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proveedor['trabajos'] as $t):
                            [$etiqueta, $claseBadge, $siguiente] = ESTADOS_TERCERIZADO[$t['estado']];
                            $fechas = array_filter([
                                $t['fechaEnviado']  ? 'llevado '  . fechaCorta($t['fechaEnviado'])  : null,
                                $t['fechaListo']    ? 'listo '    . fechaCorta($t['fechaListo'])    : null,
                                $t['fechaRecibido'] ? 'retirado ' . fechaCorta($t['fechaRecibido']) : null,
                            ]);
                            $atrasado = $t['fechaPrometida'] && $t['estado'] === 'enProveedor' && $t['fechaPrometida'] < date('Y-m-d');
                        ?>
                        <tr data-id="<?= (int)$t['id'] ?>">
                            <td>
                                <?= htmlspecialchars($t['descripcion']) ?>
                                <div class="small text-muted">cargó <?= htmlspecialchars($t['cargo'] ?? '-') ?> el <?= fechaCorta($t['fechaCarga']) ?></div>
                            </td>
                            <td class="small">
                                <?php if ($t['idPedido']): ?>
                                <span class="fw-bold">#<?= (int)$t['idPedido'] ?></span> <?= htmlspecialchars($t['cliente'] ?? '') ?>
                                <?php else: ?>
                                <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $claseBadge ?>"><?= $etiqueta ?></span>
                                <div class="small text-muted"><?= implode(' · ', $fechas) ?></div>
                            </td>
                            <td class="small <?= $atrasado ? 'text-danger fw-bold' : '' ?>">
                                <?= $t['fechaPrometida'] ? fechaCorta($t['fechaPrometida']) . ($atrasado ? ' · atrasado' : '') : '-' ?>
                            </td>
                            <td class="text-end"><?= fmtPrecio($t['precio']) ?></td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input chkPagado" <?= $t['pagado'] ? 'checked' : '' ?>
                                       title="<?= $t['pagado'] ? 'Pagado el ' . fechaCorta($t['fechaPagado']) : 'Marcar como pagado' ?>">
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if ($siguiente): ?>
                                <button type="button" class="btn btn-success btn-sm btnAvanzar"><?= $siguiente ?></button>
                                <?php endif; ?>
                                <?php if ($t['estado'] !== 'paraLlevar'): ?>
                                <button type="button" class="btn btn-outline-secondary btn-sm btnDeshacer" title="Volver al paso anterior"><i class="bi bi-arrow-counterclockwise"></i></button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-dark btn-sm btnEditarTercerizado" title="Editar"><i class="bi bi-pencil"></i></button>
                                <button type="button" class="btn btn-outline-danger btn-sm btnBorrarTercerizado" title="Borrar"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
    // El formulario recarga "la lista actual" al guardar: que vuelva a esta página.
    var urlListaActual = 'produccion/tercerizados.php<?= $verTodos ? '?ver=todos' : '' ?>';

    function accionTercerizado(datos) {
        $.post('produccion/ajaxTercerizados.php', datos, function(res) {
            if (res.trim().indexOf('✅') !== 0) alert(res);
            $('#contenido').load(urlListaActual);
        });
    }

    function abrirTercerizado(parametros) {
        $('#modalUniversal .modal-content').load('produccion/modalTercerizado.php?' + parametros, function() {
            $('#modalUniversal').modal('show');
        });
    }

    $('#btnNuevoTercerizado').on('click', function() { abrirTercerizado(''); });

    $('#btnVerTercerizados').on('click', function() {
        var ver = $(this).data('ver');
        $('#contenido').load('produccion/tercerizados.php' + (ver ? '?ver=' + ver : ''));
    });

    $('#tercerizados').on('click', '.btnEditarTercerizado', function() {
        abrirTercerizado('id=' + $(this).closest('tr').data('id'));
    });

    $('#tercerizados').on('click', '.btnAvanzar', function() {
        accionTercerizado({ opcion: 'avanzar', id: $(this).closest('tr').data('id') });
    });

    $('#tercerizados').on('click', '.btnDeshacer', function() {
        accionTercerizado({ opcion: 'deshacer', id: $(this).closest('tr').data('id') });
    });

    $('#tercerizados').on('change', '.chkPagado', function() {
        accionTercerizado({ opcion: 'pagado', id: $(this).closest('tr').data('id'), valor: this.checked ? 1 : '' });
    });

    $('#tercerizados').on('click', '.btnPagarProveedor', function() {
        if (confirm('¿Marcar como pagados los ' + $(this).data('cant') + ' trabajos recibidos de este proveedor?')) {
            accionTercerizado({ opcion: 'pagarProveedor', idProveedor: $(this).data('proveedor') });
        }
    });

    $('#tercerizados').on('click', '.btnBorrarTercerizado', function() {
        if (confirm('¿Borrar este trabajo tercerizado?')) {
            accionTercerizado({ opcion: 'borrar', id: $(this).closest('tr').data('id') });
        }
    });
</script>
