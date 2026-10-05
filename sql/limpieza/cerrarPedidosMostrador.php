<?php
// Cierra las ventas de Mostrador que quedaron abiertas: se hacen y se cobran en el
// momento, así que si no figuran terminadas, entregadas o pagadas es que no se registró.
//
//   php sql/limpieza/cerrarPedidosMostrador.php             solo muestra lo que haría
//   php sql/limpieza/cerrarPedidosMostrador.php --aplicar   modifica la base
//
// Regla (acordada el 5/10/2026): ventas del contacto genérico Mostrador pasan a
// terminado, entregado y pagado. Quedan afuera los encargos (el detalle dice
// "encargo": se dejan con seña y se retiran después) y los presupuestos.
// Si falta plata se carga un pago "sinRegistro" con la fecha del pedido, como en
// cerrarPedidosViejos.php. El estado anterior queda en modificaciones (accion
// 'regularizar') y una nota corta en observaciones.
require __DIR__ . '/comun.php';

const MOSTRADOR = 908;
const USUARIO = 'edu';              // a nombre de quién quedan los pagos y el registro
const MEDIO = 'sinRegistro';

$saldo = "GREATEST(p.monto - p.montoPagado, 0)";
$conn->query("CREATE TEMPORARY TABLE tmpMostrador (PRIMARY KEY (id)) AS
    SELECT p.id, $saldo saldo,
        CONCAT('[Cierre " . date('m/Y') . " Mostrador sin registro de: ', CONCAT_WS(', ',
            IF(p.estadoPago <> 3 OR $saldo > 0.1, 'pago', NULL), IF(p.estadoEntrega <> 3, 'entrega', NULL),
            IF(p.estadoProduccion <> 3, 'terminación', NULL)), ']') nota
    FROM pedidos p
    WHERE p.anulado = 0 AND p.idContacto = " . MOSTRADOR . " AND p.idTipoPedido = 1
      AND COALESCE(p.detalle, '') NOT LIKE '%encargo%'
      AND (NOT (p.estadoPago = 3 AND p.estadoEntrega = 3 AND p.estadoProduccion = 3) OR $saldo > 0.1)");

function tabla($conn, $titulo, $sql) {
    echo "\n$titulo\n";
    $r = $conn->query($sql);
    while ($f = $r->fetch_assoc()) echo '  ', implode(' | ', array_map(fn($k, $x) => "$k: $x", array_keys($f), $f)), "\n";
}
$cantidades = "SELECT SUM(NOT (estadoProduccion = 3 AND estadoEntrega = 3 AND estadoPago = 3)) abiertos,
        SUM(estadoProduccion IN (1,2)) sinTerminar, SUM(estadoPago IN (1,2)) sinPagar, SUM(estadoEntrega IN (1,2)) sinEntregar
    FROM pedidos WHERE anulado = 0";

tabla($conn, "Ventas de Mostrador que se cierran", "SELECT p.id, DATE(p.entrada) entrada, p.monto, p.montoPagado,
        t.saldo pagoSinRegistro, LEFT(COALESCE(p.detalle, ''), 45) detalle
    FROM tmpMostrador t JOIN pedidos p ON p.id = t.id ORDER BY p.id DESC");
tabla($conn, "Botones de Pedidos antes", $cantidades);

if (!$aplicar) {
    echo "\nNo se modificó nada. Para aplicar, repetir el comando agregando --aplicar\n";
    exit;
}

// --- aplicar
$idMedio = $conn->query("SELECT id FROM mediosPago WHERE medio = '" . MEDIO . "'")->fetch_row()[0] ?? null;
if (!$idMedio) exit("\nFalta el medio de pago '" . MEDIO . "': aplicar primero sql/2026-10-02_medioPago_sinRegistro.sql\n");
$idUsuario = $conn->query("SELECT id FROM usuarios WHERE usuario = '" . USUARIO . "'")->fetch_row()[0] ?? null;
if (!$idUsuario) exit("\nNo existe el usuario '" . USUARIO . "'.\n");

$conn->begin_transaction();
$conn->query("INSERT INTO pagos (fecha, monto, idPedido, idUsuario, idMedioPago)
    SELECT p.entrada, t.saldo, p.id, $idUsuario, $idMedio
    FROM tmpMostrador t JOIN pedidos p ON p.id = t.id WHERE t.saldo > 0.1");
$pagos = $conn->affected_rows;
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pedido', p.id, p.id, 'regularizar',
        JSON_OBJECT('estadoPago', p.estadoPago, 'estadoEntrega', p.estadoEntrega, 'estadoProduccion', p.estadoProduccion,
                    'montoPagado', p.montoPagado, 'observaciones', p.observaciones),
        JSON_OBJECT('pagoSinRegistro', t.saldo), 'Cierre de ventas de Mostrador sin registro de pago, entrega o terminación'
    FROM tmpMostrador t JOIN pedidos p ON p.id = t.id");
$conn->query("UPDATE pedidos p JOIN tmpMostrador t ON t.id = p.id
    SET p.estadoPago = 3, p.estadoEntrega = 3, p.estadoProduccion = 3,
        p.montoPagado = GREATEST(p.montoPagado, p.monto),
        p.observaciones = IF(CHAR_LENGTH(CONCAT(COALESCE(p.observaciones, ''), ' ', t.nota)) <= 255,
                             TRIM(CONCAT(COALESCE(p.observaciones, ''), ' ', t.nota)), p.observaciones)");
$cerrados = $conn->affected_rows;
$conn->commit();
echo "\n$cerrados ventas de Mostrador cerradas, $pagos pagos '" . MEDIO . "' cargados.\n";
tabla($conn, "Botones de Pedidos ahora", $cantidades);
