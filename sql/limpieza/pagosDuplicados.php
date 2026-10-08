<?php
// Pone de acuerdo los pagos de cada pedido con su montoPagado (pedidos de antes del
// sistema nuevo, que arrancó el 5/10/2026).
//
//   php sql/limpieza/pagosDuplicados.php             solo muestra lo que haría
//   php sql/limpieza/pagosDuplicados.php --aplicar   modifica la base
//
// Reglas (acordadas el 8/10/2026):
// 1. Pagos duplicados por doble clic en el sistema viejo: igual a otro pago anterior
//    del mismo pedido (monto y medio) dentro de los 60 segundos. Se borran solo si sin
//    ellos los pagos igual cubren el monto del pedido (si no, eran pagos en partes
//    iguales). Cada pago borrado queda completo en modificaciones.
// 2. Pedidos con montoPagado mayor que sus pagos: falta el registro de un pago. Se
//    carga un "sinRegistro" por la diferencia (sin pasar el monto del pedido), con la
//    fecha del pedido. Finanzas no lo suma.
// 3. Si después de eso los pagos no superan el monto, montoPagado y estadoPago se
//    recalculan desde los pagos, como hace el sistema (recalcularPagosPedido).
// Los pedidos con pagos reales mayores que el monto (cobrados de más) no se tocan.
require __DIR__ . '/comun.php';

const DESDE_SISTEMA_NUEVO = '2026-10-05';
const USUARIO = 'edu';              // a nombre de quién quedan los pagos y el registro

$idSR = $conn->query("SELECT id FROM mediosPago WHERE medio = 'sinRegistro'")->fetch_row()[0] ?? null;
if (!$idSR) exit("No existe el medio de pago 'sinRegistro'.\n");
$viejo = "p.anulado = 0 AND p.entrada < '" . DESDE_SISTEMA_NUEVO . "'";

// --- 1. duplicados
$conn->query("CREATE TEMPORARY TABLE tmpPagosA (KEY (idPedido)) AS SELECT id, idPedido, monto, idMedioPago, fecha FROM pagos");
$conn->query("CREATE TEMPORARY TABLE tmpDup (PRIMARY KEY (id)) AS
    SELECT DISTINCT b.id, b.idPedido, b.monto
    FROM pagos b JOIN tmpPagosA a ON a.idPedido = b.idPedido AND a.monto = b.monto AND a.idMedioPago = b.idMedioPago
        AND a.id < b.id AND b.fecha BETWEEN a.fecha AND a.fecha + INTERVAL 60 SECOND
    JOIN pedidos p ON p.id = b.idPedido WHERE $viejo");
$conn->query("CREATE TEMPORARY TABLE tmpDupPedido (PRIMARY KEY (idPedido)) AS
    SELECT d.idPedido, SUM(d.monto) dup FROM tmpDup d GROUP BY d.idPedido");
// solo donde sin los duplicados los pagos igual cubren el monto
$conn->query("DELETE t FROM tmpDupPedido t JOIN pedidos p ON p.id = t.idPedido
    JOIN (SELECT idPedido, SUM(monto) tot FROM pagos GROUP BY idPedido) s ON s.idPedido = t.idPedido
    WHERE s.tot - t.dup < p.monto - 0.5");
$conn->query("DELETE d FROM tmpDup d LEFT JOIN tmpDupPedido t ON t.idPedido = d.idPedido WHERE t.idPedido IS NULL");
[$nDup, $mDup] = $conn->query("SELECT COUNT(*), COALESCE(SUM(monto), 0) FROM tmpDup")->fetch_row();
$nDupPed = $conn->query("SELECT COUNT(*) FROM tmpDupPedido")->fetch_row()[0];
printf("1. Pagos duplicados a borrar: %d (%d pedidos), $ %s\n", $nDup, $nDupPed, number_format($mDup, 2, ',', '.'));
$r = $conn->query("SELECT YEAR(g.fecha) y, COUNT(*) n, SUM(g.monto) m FROM tmpDup d JOIN pagos g ON g.id = d.id GROUP BY y ORDER BY y");
$porAnio = [];
while ($f = $r->fetch_assoc()) $porAnio[] = "{$f['y']}: $ " . number_format($f['m'], 0, ',', '.');
echo "   Por año (bajan los ingresos de Finanzas): ", implode(' | ', $porAnio), "\n";

// pagos de cada pedido sin los duplicados, para los pasos 2 y 3
$conn->query("CREATE TEMPORARY TABLE tmpSuma (PRIMARY KEY (idPedido)) AS
    SELECT g.idPedido, SUM(g.monto) s FROM pagos g LEFT JOIN tmpDup d ON d.id = g.id WHERE d.id IS NULL GROUP BY g.idPedido");
$sumaPagos = "COALESCE(s.s, 0)";
$conSuma = "pedidos p LEFT JOIN tmpSuma s ON s.idPedido = p.id";

// --- 2. falta el registro de un pago
$conn->query("CREATE TEMPORARY TABLE tmpFalta (PRIMARY KEY (id)) AS
    SELECT p.id, LEAST(p.montoPagado, p.monto) - $sumaPagos falta FROM $conSuma
    WHERE $viejo AND LEAST(p.montoPagado, p.monto) - $sumaPagos > 0.5");
[$nFalta, $mFalta] = $conn->query("SELECT COUNT(*), COALESCE(SUM(falta), 0) FROM tmpFalta")->fetch_row();
printf("2. Pedidos a los que les falta un pago: %d, pagos sinRegistro por $ %s\n", $nFalta, number_format($mFalta, 2, ',', '.'));

// --- 3. montoPagado distinto de los pagos (con lo de los pasos 1 y 2), sin pasar el monto
$conn->query("CREATE TEMPORARY TABLE tmpRecalc (PRIMARY KEY (id)) AS
    SELECT p.id, $sumaPagos + COALESCE(f.falta, 0) pagado FROM $conSuma LEFT JOIN tmpFalta f ON f.id = p.id
    WHERE $viejo");
$conn->query("DELETE t FROM tmpRecalc t JOIN pedidos p ON p.id = t.id
    WHERE ABS(t.pagado - p.montoPagado) <= 0.5 OR t.pagado > p.monto + 0.5");
$nRecalc = $conn->query("SELECT COUNT(*) FROM tmpRecalc")->fetch_row()[0];
echo "3. Pedidos a los que se recalcula montoPagado: $nRecalc\n";
tabla_corta($conn, "SELECT p.id, DATE(p.entrada) entrada, p.monto, p.montoPagado, t.pagado nuevo FROM tmpRecalc t JOIN pedidos p ON p.id = t.id ORDER BY p.id LIMIT 10");

$cobradosDeMas = $conn->query("SELECT COUNT(*) FROM $conSuma WHERE $viejo AND $sumaPagos > p.monto + 0.5")->fetch_row()[0];
echo "   Quedan sin tocar $cobradosDeMas pedidos con pagos reales mayores que el monto.\n";

function tabla_corta($conn, $sql) {
    $r = $conn->query($sql);
    while ($f = $r->fetch_assoc()) echo '   ', implode(' | ', array_map(fn($k, $x) => "$k: $x", array_keys($f), $f)), "\n";
}

// pedidos pagados que con los pagos corregidos dejarían de estarlo (no debería haber)
$reabre = $conn->query("SELECT COUNT(*) FROM tmpRecalc t JOIN pedidos p ON p.id = t.id
    WHERE p.estadoPago = 3 AND p.monto - t.pagado > 0.1")->fetch_row()[0];
echo "   Pedidos pagados que dejarían de estarlo: $reabre\n";

if (!$aplicar) {
    echo "No se modificó nada. Para aplicar, repetir el comando agregando --aplicar\n";
    exit;
}

$idUsuario = $conn->query("SELECT id FROM usuarios WHERE usuario = '" . USUARIO . "'")->fetch_row()[0] ?? null;
if (!$idUsuario) exit("\nNo existe el usuario '" . USUARIO . "'.\n");

$conn->begin_transaction();
// 1
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pago', g.id, g.idPedido, 'regularizar',
        JSON_OBJECT('fecha', g.fecha, 'monto', g.monto, 'idPedido', g.idPedido, 'idUsuario', g.idUsuario, 'idMedioPago', g.idMedioPago),
        NULL, 'Pago duplicado (doble clic en el sistema viejo)'
    FROM tmpDup d JOIN pagos g ON g.id = d.id");
$conn->query("DELETE g FROM pagos g JOIN tmpDup d ON d.id = g.id");
$borrados = $conn->affected_rows;
// 2
$conn->query("INSERT INTO pagos (fecha, monto, idPedido, idUsuario, idMedioPago)
    SELECT p.entrada, f.falta, p.id, $idUsuario, $idSR FROM tmpFalta f JOIN pedidos p ON p.id = f.id");
$cargados = $conn->affected_rows;
// 1, 2 y 3: montoPagado y estadoPago desde los pagos, en todos los pedidos tocados
$conn->query("CREATE TEMPORARY TABLE tmpTocados (PRIMARY KEY (id)) AS
    SELECT idPedido id FROM tmpDupPedido UNION SELECT id FROM tmpFalta UNION SELECT id FROM tmpRecalc");
$conn->query("CREATE TEMPORARY TABLE tmpNuevo (PRIMARY KEY (id)) AS
    SELECT t.id, (SELECT COALESCE(SUM(g.monto), 0) FROM pagos g WHERE g.idPedido = t.id) pagado FROM tmpTocados t");
$conn->query("DELETE n FROM tmpNuevo n JOIN pedidos p ON p.id = n.id WHERE n.pagado > p.monto + 0.5");
// como calcularEstadoPago, salvo que con monto 0 cuenta como pagado (no reabre pedidos viejos)
$estado = "IF(p.monto - n.pagado <= 0.1, 3, IF(n.pagado <= 0.1, 1, 2))";
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pedido', p.id, p.id, 'regularizar',
        JSON_OBJECT('montoPagado', p.montoPagado, 'estadoPago', p.estadoPago),
        JSON_OBJECT('montoPagado', n.pagado, 'estadoPago', $estado), 'montoPagado recalculado desde los pagos'
    FROM tmpNuevo n JOIN pedidos p ON p.id = n.id
    WHERE ABS(p.montoPagado - n.pagado) > 0.005 OR p.estadoPago <> $estado");
$conn->query("UPDATE pedidos p JOIN tmpNuevo n ON n.id = p.id SET p.montoPagado = n.pagado, p.estadoPago = $estado");
$recalculados = $conn->affected_rows;
$conn->commit();
echo "\nAplicado: $borrados pagos duplicados borrados, $cargados pagos sinRegistro cargados, $recalculados pedidos con montoPagado o estadoPago corregido.\n";
