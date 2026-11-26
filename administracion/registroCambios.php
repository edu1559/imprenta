<?php
// Registro de cambios: todo lo que quedó en 'modificaciones' (cambios de monto, pagos
// borrados o movidos, anulaciones, cierres...), con filtros. Solo administradores.
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
$conn = conectar();

if (!esAdministrador($conn)) {
    die('<div class="container p-4"><div class="alert alert-warning">Solo un administrador puede ver el registro de cambios.</div></div>');
}

// Grupos de cambios para el filtro. "Todos" deja afuera los cierres de caja de rutina y la limpieza.
$anularLimpieza = "'" . implode("', '", MOTIVOS_ANULAR_LIMPIEZA) . "'";
$esLimpieza = "((m.accion = 'regularizar' AND m.motivo <> '" . MOTIVO_YA_COBRADO . "')
               OR (m.accion = 'anular' AND m.motivo IN ($anularLimpieza)))";
$grupos = [
    'todos'       => ['Todos', null],
    'montos'      => ['Cambios de monto', "m.accion = 'modificarMonto'"],
    'pagos'       => ['Pagos borrados, movidos o ajustados', "m.entidad = 'pago' AND m.accion IN ('borrar', 'cambiarMedio', 'modificarMonto', 'ajuste')"],
    'cobrado'     => ['Cerrados como «ya estaba cobrado»', "m.entidad = 'pedido' AND m.accion = 'regularizar' AND m.motivo = '" . MOTIVO_YA_COBRADO . "'"],
    'anulaciones' => ['Anulados y reabiertos', "m.entidad = 'pedido' AND m.accion IN ('anular', 'reabrir') AND NOT $esLimpieza"],
    'web'         => ['Pedidos web', "m.accion IN ('aprobarWeb', 'rechazarWeb')"],
    'caja'        => ['Cierres de caja, movimientos y cotización', "m.entidad IN ('cierre', 'movimiento', 'dolar')"],
    'cheques'     => ['Cheques', "m.entidad = 'cheque' OR m.accion = 'rechazoCheque'"],
    'limpieza'    => ['Limpieza de datos', $esLimpieza],
];
$condTodos = "NOT (m.entidad = 'cierre' AND m.accion IN ('cierreParcial', 'cierreTotal')) AND NOT $esLimpieza";

$grupo = $_GET['grupo'] ?? 'todos';
if (!isset($grupos[$grupo])) $grupo = 'todos';
$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = date('Y-m-d');
$idUsuarioFiltro = (int)($_GET['idUsuario'] ?? 0);
$idPedidoFiltro = (int)($_GET['idPedido'] ?? 0);

// Con un número de pedido se ve todo lo de ese pedido, sin importar las fechas.
$donde = $idPedidoFiltro ? ["m.idPedido = $idPedidoFiltro"] : ["m.fecha >= '$desde'", "m.fecha < '$hasta' + INTERVAL 1 DAY"];
if ($idUsuarioFiltro) $donde[] = "m.idUsuario = $idUsuarioFiltro";
$dondeBase = implode(' AND ', $donde);

// Cantidad por grupo (para los botones), con las fechas y el usuario elegidos
$cantidades = [];
foreach ($grupos as $clave => [$nombre, $cond]) {
    $c = $cond ?? $condTodos;
    $cantidades[$clave] = (int)mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM modificaciones m WHERE $dondeBase AND ($c)"))[0];
}

$LIMITE = 500;
$condGrupo = $grupos[$grupo][1] ?? $condTodos;
$sql = "SELECT m.*, u.usuario, CONCAT_WS(' ', c.apellido, c.nombre) AS contacto
        FROM modificaciones m
        LEFT JOIN usuarios u ON u.id = m.idUsuario
        LEFT JOIN pedidos p ON p.id = m.idPedido
        LEFT JOIN contactos c ON c.id = p.idContacto
        WHERE $dondeBase AND ($condGrupo)
        ORDER BY m.fecha DESC, m.id DESC
        LIMIT $LIMITE";
$filas = mysqli_fetch_all(mysqli_query($conn, $sql), MYSQLI_ASSOC);

$medios = [];
$res = mysqli_query($conn, "SELECT idMedio, medio FROM cierreMedios");
while ($f = mysqli_fetch_row($res)) $medios[$f[0]] = $f[1];

// Usuarios que alguna vez hicieron cambios, para el filtro
$usuarios = mysqli_fetch_all(mysqli_query($conn, "SELECT DISTINCT u.id, u.usuario FROM usuarios u
                                                  INNER JOIN modificaciones m ON m.idUsuario = u.id ORDER BY u.usuario"), MYSQLI_ASSOC);
?>

<style>
    #tablaRegistro thead th { background: #e7f1ff; white-space: nowrap; }
    #tablaRegistro tr.limpieza td { color: #6c757d; }
    .btnGrupo .badge { font-weight: 500; }
</style>

<div class="container-fluid p-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h2 class="fw-bold mb-0"><i class="bi bi-journal-text text-primary me-2"></i>Registro de cambios</h2>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <input type="date" id="regDesde" class="form-control form-control-sm" style="width: 150px;" value="<?php echo $desde; ?>" <?php echo $idPedidoFiltro ? 'disabled' : ''; ?>>
            <span class="small text-muted">a</span>
            <input type="date" id="regHasta" class="form-control form-control-sm" style="width: 150px;" value="<?php echo $hasta; ?>" <?php echo $idPedidoFiltro ? 'disabled' : ''; ?>>
            <select id="regUsuario" class="form-select form-select-sm" style="width: 150px;">
                <option value="0">Todos los usuarios</option>
                <?php foreach ($usuarios as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $idUsuarioFiltro ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['usuario']); ?></option>
                <?php endforeach; ?>
            </select>
            <div class="input-group input-group-sm" style="width: 160px;">
                <span class="input-group-text">Pedido #</span>
                <input type="text" inputmode="numeric" id="regPedido" class="form-control" value="<?php echo $idPedidoFiltro ?: ''; ?>">
            </div>
            <button class="btn btn-sm btn-primary" id="btnRegFiltrar"><i class="bi bi-search"></i></button>
            <?php if ($idPedidoFiltro || $idUsuarioFiltro): ?>
                <button class="btn btn-sm btn-outline-secondary" id="btnRegLimpiar" title="Sacar filtros de pedido y usuario"><i class="bi bi-x-lg"></i></button>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-1 mb-3">
        <?php foreach ($grupos as $clave => [$nombre, $cond]): ?>
            <button class="btn btn-sm btnGrupo <?php echo $clave === $grupo ? 'btn-primary' : 'btn-outline-primary'; ?>" data-grupo="<?php echo $clave; ?>">
                <?php echo $nombre; ?> <span class="badge <?php echo $clave === $grupo ? 'bg-light text-primary' : 'bg-primary'; ?>"><?php echo $cantidades[$clave]; ?></span>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="small text-muted mb-2">
        <?php if ($idPedidoFiltro): ?>
            Todo lo del pedido #<?php echo $idPedidoFiltro; ?>.
        <?php else: ?>
            Del <?php echo date('d/m/Y', strtotime($desde)); ?> al <?php echo date('d/m/Y', strtotime($hasta)); ?>.
        <?php endif; ?>
        "Todos" no incluye los cierres de caja de cada día ni la limpieza de datos.
        <?php if (count($filas) === $LIMITE): ?>
            <span class="text-danger">Se muestran los <?php echo $LIMITE; ?> más recientes: achicá las fechas para ver el resto.</span>
        <?php endif; ?>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="tablaRegistro">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th class="text-center">Pedido</th>
                            <th>Cambio</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filas as $m):
                        $d = describirModificacion($m, $medios);
                    ?>
                        <tr class="<?php echo $d['limpieza'] ? 'limpieza' : ''; ?>">
                            <td class="small text-nowrap"><?php echo date('d/m/y H:i', strtotime($m['fecha'])); ?></td>
                            <td class="small"><?php echo htmlspecialchars($m['usuario'] ?? ''); ?></td>
                            <td class="text-center text-nowrap">
                                <?php if ($m['idPedido']): ?>
                                    <button class="btn btn-outline-secondary btn-sm btnRegPedido" data-id="<?php echo $m['idPedido']; ?>" title="Abrir el pedido">#<?php echo $m['idPedido']; ?></button>
                                    <div class="small text-muted text-truncate" style="max-width: 160px;"><?php echo htmlspecialchars($m['contacto'] ?? ''); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><i class="bi <?php echo $d['icono']; ?> me-1"></i><?php echo htmlspecialchars($d['texto']); ?></td>
                            <td class="small fst-italic"><?php echo htmlspecialchars($d['motivo'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$filas): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No hay cambios para mostrar.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function cargarRegistro(cambios) {
    let p = $.extend({
        grupo: '<?php echo $grupo; ?>',
        desde: $('#regDesde').val(),
        hasta: $('#regHasta').val(),
        idUsuario: $('#regUsuario').val(),
        idPedido: $('#regPedido').val().replace(/\D/g, '')
    }, cambios || {});
    $('#contenido').load('administracion/registroCambios.php?' + $.param(p));
}
$('.btnGrupo').click(function() { cargarRegistro({ grupo: $(this).data('grupo') }); });
$('#btnRegFiltrar').click(function() { cargarRegistro(); });
$('#regDesde, #regHasta, #regUsuario').change(function() { cargarRegistro(); });
$('#regPedido').keydown(function(e) { if (e.key === 'Enter') cargarRegistro(); });
$('#btnRegLimpiar').click(function() { cargarRegistro({ idPedido: '', idUsuario: 0 }); });

// Abre el pedido en Editar pedido (ahí está su Historial)
$('.btnRegPedido').click(function() {
    $('#modalUniversal .modal-content').load('pedidos/modalEditarPedido.php?idPedido=' + $(this).data('id'), function(response, status) {
        if (status !== 'error') $('#modalUniversal').modal('show');
    });
});
</script>
