<?php
// Historial de un pedido: cuándo se cargó, sus pagos y todo lo que quedó en
// 'modificaciones' (cambios de monto, pagos borrados o movidos, anulación,
// reapertura...), en orden. Se carga dentro del modal de Editar pedido.
session_start();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
exigirTrabajadorPagina();
$conn = conectar();

$idPedido = (int)($_GET['idPedido'] ?? 0);

$stmt = $conn->prepare("SELECT p.id, p.entrada, p.monto, p.fechaTerminado, u.usuario
                        FROM pedidos p LEFT JOIN usuarios u ON u.id = p.idUsuario
                        WHERE p.id = ?");
$stmt->bind_param('i', $idPedido);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
if (!$pedido) die('<div class="text-muted small p-2">No se encontró el pedido.</div>');

$stmt = $conn->prepare("SELECT m.*, u.usuario FROM modificaciones m
                        LEFT JOIN usuarios u ON u.id = m.idUsuario
                        WHERE m.idPedido = ? ORDER BY m.fecha, m.id");
$stmt->bind_param('i', $idPedido);
$stmt->execute();
$mods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("SELECT pg.id, pg.fecha, pg.monto, mp.medio, u.usuario FROM pagos pg
                        LEFT JOIN mediosPago mp ON mp.id = pg.idMedioPago
                        LEFT JOIN usuarios u ON u.id = pg.idUsuario
                        WHERE pg.idPedido = ? ORDER BY pg.fecha, pg.id");
$stmt->bind_param('i', $idPedido);
$stmt->execute();
$pagos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

function pesosH($n) {
    return ((float)$n < 0 ? '-$' : '$') . number_format(abs((float)$n), 0, ',', '.');
}
function nombreMedioH($medio) {
    return $medio === 'sinRegistro' ? 'sin registro (no entró a ninguna caja)' : $medio;
}

// Los pagos de ajuste se muestran una sola vez, en la fila del pago, con su motivo.
$ajustes = [];
foreach ($mods as $m) {
    if ($m['entidad'] === 'pago' && $m['accion'] === 'ajuste') $ajustes[$m['idEntidad']] = $m;
}

// Monto con que se cargó: el anterior al primer cambio de monto, si lo hubo.
$montoInicial = $pedido['monto'];
foreach ($mods as $m) {
    if ($m['entidad'] === 'pedido' && $m['accion'] === 'modificarMonto') { $montoInicial = $m['valorAnterior']; break; }
}

// Cada evento: fecha, usuario, icono, texto, motivo, importante (resaltado) y limpieza (gris).
$eventos = [];
$eventos[] = ['fecha' => $pedido['entrada'], 'usuario' => $pedido['usuario'], 'icono' => 'bi-plus-circle text-primary',
              'texto' => 'Pedido cargado por ' . pesosH($montoInicial)];

foreach ($pagos as $p) {
    $aj = $ajustes[$p['id']] ?? null;
    $eventos[] = ['fecha' => $p['fecha'], 'usuario' => $aj['usuario'] ?? $p['usuario'],
                  'icono' => $aj ? 'bi-wrench text-warning' : 'bi-cash-coin text-success',
                  'texto' => ($aj ? 'Pago de ajuste ' : 'Pago ') . pesosH($p['monto']) . ' en ' . nombreMedioH($p['medio']),
                  'motivo' => $aj['motivo'] ?? null, 'importante' => (bool)$aj];
}

if ($pedido['fechaTerminado']) {
    $eventos[] = ['fecha' => $pedido['fechaTerminado'], 'icono' => 'bi-check2-circle text-success', 'texto' => 'Marcado como terminado'];
}

foreach ($mods as $m) {
    $ev = ['fecha' => $m['fecha'], 'usuario' => $m['usuario'], 'motivo' => $m['motivo'], 'importante' => true];
    $antes = $m['valorAnterior'];
    $despues = $m['valorNuevo'];
    switch ($m['entidad'] . '/' . $m['accion']) {
        case 'pago/ajuste':
            continue 2; // ya está en la fila del pago
        case 'pedido/modificarMonto':
            $ev += ['icono' => 'bi-pencil-square text-danger', 'texto' => 'Monto del pedido: ' . pesosH($antes) . ' → ' . pesosH($despues)];
            if ($m['motivo'] === 'Editar pedido') $ev['motivo'] = null; // cambios anteriores al motivo obligatorio
            break;
        case 'pedido/anular':
            $ev += ['icono' => 'bi-x-circle text-danger', 'texto' => 'Pedido anulado (monto ' . pesosH(ltrim((string)$antes, '$')) . ')'];
            break;
        case 'pedido/reabrir':
            $ev += ['icono' => 'bi-unlock text-warning', 'texto' => 'Pedido reabierto'];
            break;
        case 'pedido/aprobarWeb':
            $ev += ['icono' => 'bi-globe text-success', 'texto' => 'Pedido web aprobado por ' . pesosH(ltrim((string)$despues, '$')), 'importante' => false];
            break;
        case 'pedido/rechazarWeb':
            $ev += ['icono' => 'bi-globe text-danger', 'texto' => 'Pedido web rechazado'];
            break;
        case 'pago/borrar':
            $ev += ['icono' => 'bi-trash text-danger', 'texto' => 'Pago borrado: ' . $antes];
            break;
        case 'pago/cambiarMedio':
            $ev += ['icono' => 'bi-arrow-left-right text-warning', 'texto' => "Pago #{$m['idEntidad']} movido de $antes a $despues"];
            break;
        case 'pago/modificarMonto':
            $ev += ['icono' => 'bi-pencil-square text-danger', 'texto' => "Pago #{$m['idEntidad']}: monto " . pesosH($antes) . ' → ' . pesosH($despues)];
            break;
        case 'pedido/regularizar':
            // Botón "ya estaba cobrado" de Pedidos: lo hace un empleado y cierra el pedido sin que entre plata
            if ($m['motivo'] === 'Cerrado desde Pedidos: ya estaba cobrado') {
                $saldo = json_decode((string)$despues, true)['pagoSinRegistro'] ?? 0;
                $ev['motivo'] = null;
                $ev += ['icono' => 'bi-lock text-danger',
                        'texto' => 'Cerrado como «ya estaba cobrado»' . ($saldo > 0 ? ': el saldo de ' . pesosH($saldo) . ' quedó como pago sin registro' : '')];
                break;
            }
            // si no, es de los scripts de limpieza: sigue abajo
        case 'pago/regularizar':
            $ev += ['icono' => 'bi-gear text-secondary', 'texto' => 'Regularizado en la limpieza de datos', 'importante' => false, 'limpieza' => true];
            break;
        default:
            $ev += ['icono' => 'bi-dot', 'texto' => $m['entidad'] . ' ' . $m['accion'] . ($antes !== null ? ": $antes" : '') . ($despues !== null ? " → $despues" : '')];
    }
    $eventos[] = $ev;
}

usort($eventos, fn($a, $b) => strcmp((string)$a['fecha'], (string)$b['fecha']));
?>

<table class="table table-sm align-middle mb-0 small">
    <tbody>
    <?php foreach ($eventos as $e): ?>
        <tr class="<?php echo !empty($e['limpieza']) ? 'text-muted' : (!empty($e['importante']) ? 'table-warning' : ''); ?>">
            <td class="text-nowrap text-muted" style="width: 1%;"><?php echo $e['fecha'] ? date('d/m/y H:i', strtotime($e['fecha'])) : ''; ?></td>
            <td class="text-nowrap" style="width: 1%;"><?php echo htmlspecialchars($e['usuario'] ?? ''); ?></td>
            <td>
                <i class="bi <?php echo $e['icono']; ?> me-1"></i><?php echo htmlspecialchars($e['texto']); ?>
                <?php if (!empty($e['motivo'])): ?>
                    <div class="fst-italic text-muted">Motivo: <?php echo htmlspecialchars($e['motivo']); ?></div>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<div class="text-muted p-2" style="font-size: .75rem;">
    En amarillo, los cambios que alguien hizo a mano. Los cambios de estado (producción, entrega) no se registran.
</div>
