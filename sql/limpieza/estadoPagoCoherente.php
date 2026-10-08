<?php
// Pone el estado de pago de acuerdo con lo pagado.
//
//   php sql/limpieza/estadoPagoCoherente.php             solo muestra lo que haría
//   php sql/limpieza/estadoPagoCoherente.php --aplicar   modifica la base
//
// Reglas (acordadas el 8/10/2026), pedidos no anulados con monto mayor que 0:
// 1. Sin saldo pero no marcados como pagados (al cargarlos, el estado salía del
//    formulario): pasan a pagado.
// 2. Marcados "sin pagar" con algún pago: pasan a pago parcial.
// 3. Cerrados (terminado, entregado y pagado) de antes del sistema nuevo pero con
//    saldo: el saldo se completa con un pago "sinRegistro" con la fecha del pedido,
//    como en los otros cierres (Finanzas no lo suma).
// Los pedidos con monto 0 no se tocan. Todo queda en modificaciones ('regularizar').
require __DIR__ . '/comun.php';

const DESDE_SISTEMA_NUEVO = '2026-10-05';
const USUARIO = 'edu';              // a nombre de quién quedan los pagos y el registro

$base = "p.anulado = 0 AND p.monto > 0";
$conn->query("CREATE TEMPORARY TABLE tmpEstado (PRIMARY KEY (id)) AS
    SELECT p.id, IF(p.monto - p.montoPagado <= 0.1, 3, 2) nuevo, 'sin saldo, no marcado pagado' motivo
    FROM pedidos p WHERE $base AND p.estadoPago <> 3 AND p.monto - p.montoPagado <= 0.1
    UNION ALL
    SELECT p.id, 2, 'sin pagar con pagos'
    FROM pedidos p WHERE $base AND p.estadoPago = 1 AND p.montoPagado > 0.1 AND p.monto - p.montoPagado > 0.1");
$conn->query("CREATE TEMPORARY TABLE tmpSaldo (PRIMARY KEY (id)) AS
    SELECT p.id, ROUND(p.monto - p.montoPagado, 2) saldo FROM pedidos p
    WHERE $base AND p.estadoPago = 3 AND p.estadoEntrega = 3 AND p.estadoProduccion = 3
      AND p.monto - p.montoPagado > 0.1 AND p.entrada < '" . DESDE_SISTEMA_NUEVO . "'");

function tabla($conn, $titulo, $sql) {
    echo "\n$titulo\n";
    $r = $conn->query($sql);
    while ($f = $r->fetch_assoc()) echo '  ', implode(' | ', array_map(fn($k, $x) => "$k: $x", array_keys($f), $f)), "\n";
}
tabla($conn, "Estado de pago a corregir", "SELECT t.motivo, COUNT(*) pedidos, MIN(DATE(p.entrada)) desde, MAX(DATE(p.entrada)) hasta
    FROM tmpEstado t JOIN pedidos p ON p.id = t.id GROUP BY t.motivo");
tabla($conn, "Cerrados con saldo (pago sinRegistro)", "SELECT p.id, DATE(p.entrada) entrada, p.monto, p.montoPagado, t.saldo,
        LEFT(REPLACE(COALESCE(p.detalle, ''), '\n', ' '), 40) detalle
    FROM tmpSaldo t JOIN pedidos p ON p.id = t.id ORDER BY p.id");

if (!$aplicar) {
    echo "\nNo se modificó nada. Para aplicar, repetir el comando agregando --aplicar\n";
    exit;
}

$idSR = $conn->query("SELECT id FROM mediosPago WHERE medio = 'sinRegistro'")->fetch_row()[0] ?? null;
if (!$idSR) exit("\nNo existe el medio de pago 'sinRegistro'.\n");
$idUsuario = $conn->query("SELECT id FROM usuarios WHERE usuario = '" . USUARIO . "'")->fetch_row()[0] ?? null;
if (!$idUsuario) exit("\nNo existe el usuario '" . USUARIO . "'.\n");

$conn->begin_transaction();
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pedido', p.id, p.id, 'regularizar', JSON_OBJECT('estadoPago', p.estadoPago),
        JSON_OBJECT('estadoPago', t.nuevo), CONCAT('Estado de pago según lo pagado: ', t.motivo)
    FROM tmpEstado t JOIN pedidos p ON p.id = t.id");
$conn->query("UPDATE pedidos p JOIN tmpEstado t ON t.id = p.id SET p.estadoPago = t.nuevo");
$estados = $conn->affected_rows;
$conn->query("INSERT INTO pagos (fecha, monto, idPedido, idUsuario, idMedioPago)
    SELECT p.entrada, t.saldo, p.id, $idUsuario, $idSR FROM tmpSaldo t JOIN pedidos p ON p.id = t.id");
$pagos = $conn->affected_rows;
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pedido', p.id, p.id, 'regularizar', JSON_OBJECT('montoPagado', p.montoPagado),
        JSON_OBJECT('pagoSinRegistro', t.saldo), 'Cerrado con saldo: se completa con pago sinRegistro'
    FROM tmpSaldo t JOIN pedidos p ON p.id = t.id");
$conn->query("UPDATE pedidos p JOIN tmpSaldo t ON t.id = p.id SET p.montoPagado = p.monto");
$conn->commit();
echo "\nAplicado: $estados estados de pago corregidos, $pagos pagos sinRegistro cargados.\n";
