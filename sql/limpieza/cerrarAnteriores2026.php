<?php
// Cierra las ventas abiertas de antes de 2026 y anula los presupuestos y pedidos de
// tipo 4 (del sistema viejo) abiertos de antes de 2026.
//
//   php sql/limpieza/cerrarAnteriores2026.php             solo muestra lo que haría
//   php sql/limpieza/cerrarAnteriores2026.php --aplicar   modifica la base
//
// Regla (acordada el 8/10/2026): las ventas pasan a terminado, entregado y pagado;
// si falta plata se carga un pago "sinRegistro" con la fecha del pedido (Finanzas no
// lo suma, así que la contabilidad no cambia). El estado anterior queda en
// modificaciones (accion 'regularizar') y una nota corta en observaciones.
// Presupuestos y tipo 4 se anulan (accion 'anular'), no se borran.
// Las ventas de 2026 quedan para revisar en la imprenta.
require __DIR__ . '/comun.php';

const HASTA = '2026-01-01';
const USUARIO = 'edu';              // a nombre de quién quedan los pagos y el registro
const MEDIO = 'sinRegistro';

$abierto = "p.anulado = 0 AND NOT (p.estadoPago = 3 AND p.estadoEntrega = 3 AND p.estadoProduccion = 3)
            AND p.entrada < '" . HASTA . "'";
$saldo = "GREATEST(p.monto - COALESCE((SELECT SUM(g.monto) FROM pagos g WHERE g.idPedido = p.id), 0), 0)";
$conn->query("CREATE TEMPORARY TABLE tmpCerrar (PRIMARY KEY (id)) AS
    SELECT p.id, $saldo saldo,
        CONCAT('[Cierre " . date('m/Y') . " sin registro de: ', CONCAT_WS(', ',
            IF($saldo > 0.1, 'pago', NULL), IF(p.estadoEntrega <> 3, 'entrega', NULL),
            IF(p.estadoProduccion <> 3, 'terminación', NULL)), ']') nota
    FROM pedidos p WHERE $abierto AND p.idTipoPedido = 1");
$conn->query("CREATE TEMPORARY TABLE tmpAnular (PRIMARY KEY (id)) AS
    SELECT p.id FROM pedidos p WHERE $abierto AND p.idTipoPedido IN (3, 4)");

function tabla($conn, $titulo, $sql) {
    echo "\n$titulo\n";
    $r = $conn->query($sql);
    while ($f = $r->fetch_assoc()) echo '  ', implode(' | ', array_map(fn($k, $x) => "$k: $x", array_keys($f), $f)), "\n";
}
tabla($conn, "Ventas que se cierran", "SELECT p.id, DATE(p.entrada) entrada, p.monto, p.montoPagado, t.saldo pagoSinRegistro,
        LEFT(REPLACE(COALESCE(p.detalle, ''), '\n', ' '), 40) detalle
    FROM tmpCerrar t JOIN pedidos p ON p.id = t.id ORDER BY p.id");
tabla($conn, "Presupuestos y tipo 4 que se anulan", "SELECT p.id, p.idTipoPedido tipo, DATE(p.entrada) entrada, p.monto,
        LEFT(REPLACE(COALESCE(p.detalle, ''), '\n', ' '), 40) detalle
    FROM tmpAnular t JOIN pedidos p ON p.id = t.id ORDER BY p.id");
$n = array_merge($conn->query("SELECT COUNT(*), COALESCE(SUM(saldo), 0) FROM tmpCerrar")->fetch_row(),
                 $conn->query("SELECT COUNT(*) FROM tmpAnular")->fetch_row());
printf("\n%d ventas a cerrar (pagos sinRegistro por $ %s), %d pedidos a anular.\n", $n[0], number_format($n[1], 2, ',', '.'), $n[2]);

if (!$aplicar) {
    echo "No se modificó nada. Para aplicar, repetir el comando agregando --aplicar\n";
    exit;
}

$idMedio = $conn->query("SELECT id FROM mediosPago WHERE medio = '" . MEDIO . "'")->fetch_row()[0] ?? null;
if (!$idMedio) exit("\nFalta el medio de pago '" . MEDIO . "'.\n");
$idUsuario = $conn->query("SELECT id FROM usuarios WHERE usuario = '" . USUARIO . "'")->fetch_row()[0] ?? null;
if (!$idUsuario) exit("\nNo existe el usuario '" . USUARIO . "'.\n");

$conn->begin_transaction();
$conn->query("INSERT INTO pagos (fecha, monto, idPedido, idUsuario, idMedioPago)
    SELECT p.entrada, t.saldo, p.id, $idUsuario, $idMedio
    FROM tmpCerrar t JOIN pedidos p ON p.id = t.id WHERE t.saldo > 0.1");
$pagos = $conn->affected_rows;
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pedido', p.id, p.id, 'regularizar',
        JSON_OBJECT('estadoPago', p.estadoPago, 'estadoEntrega', p.estadoEntrega, 'estadoProduccion', p.estadoProduccion,
                    'montoPagado', p.montoPagado, 'observaciones', p.observaciones),
        JSON_OBJECT('pagoSinRegistro', t.saldo), 'Cierre de ventas anteriores a 2026 sin registro de pago, entrega o terminación'
    FROM tmpCerrar t JOIN pedidos p ON p.id = t.id");
$conn->query("UPDATE pedidos p JOIN tmpCerrar t ON t.id = p.id
    SET p.estadoPago = 3, p.estadoEntrega = 3, p.estadoProduccion = 3,
        p.montoPagado = GREATEST(p.montoPagado, p.monto),
        p.observaciones = IF(CHAR_LENGTH(CONCAT(COALESCE(p.observaciones, ''), ' ', t.nota)) <= 255,
                             TRIM(CONCAT(COALESCE(p.observaciones, ''), ' ', t.nota)), p.observaciones)");
$cerrados = $conn->affected_rows;
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pedido', p.id, p.id, 'anular', CONCAT('$', p.monto), NULL,
        IF(p.idTipoPedido = 3, 'Presupuesto vencido', 'Pedido de tipo viejo (sistema anterior)')
    FROM tmpAnular t JOIN pedidos p ON p.id = t.id");
$conn->query("UPDATE pedidos p JOIN tmpAnular t ON t.id = p.id SET p.anulado = 1");
$anulados = $conn->affected_rows;
$conn->commit();
echo "\n$cerrados ventas cerradas, $pagos pagos '" . MEDIO . "' cargados, $anulados pedidos anulados.\n";
