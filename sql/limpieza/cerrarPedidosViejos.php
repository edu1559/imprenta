<?php
// Cierra los pedidos viejos que quedaron abiertos porque no se registró el pago,
// la entrega o la terminación, para que solo queden abiertos los realmente pendientes.
//
//   php sql/limpieza/cerrarPedidosViejos.php             solo muestra lo que haría
//   php sql/limpieza/cerrarPedidosViejos.php --aplicar   modifica la base
//
// Reglas (acordadas el 2/10/2026):
//   Ventas   - anteriores al año pasado: se cierran todas.
//            - del año pasado hasta hace dos meses: se cierran, salvo los trabajos
//              grandes (monto >= $500.000) y los que deben $100.000 o más, que
//              quedan como están para verlos con los encargados.
//            - de los últimos dos meses: quedan como están.
//   Compras  - se cierran todas, salvo las de los últimos dos meses.
//   Presupuestos - los anteriores a este año se anulan ("Presupuesto vencido"),
//              salvo los que tienen pagos.
//
// Cerrar = terminado, entregado y pagado. Si falta plata se carga un pago por la
// diferencia con el medio "sinRegistro" y la fecha del pedido (necesita
// sql/2026-10-02_medioPago_sinRegistro.sql). El estado anterior queda en la tabla
// modificaciones (accion 'regularizar') y una nota corta en observaciones.
// Sin --aplicar deja en sql/limpieza/pedidos_quedan_abiertos.csv los que no se cierran.
require __DIR__ . '/comun.php';

const MONTO_TRABAJO_GRANDE = 500000;
const SALDO_GRANDE = 100000;
const USUARIO = 'edu';              // a nombre de quién quedan los pagos y el registro
const MEDIO = 'sinRegistro';

$anio = (int)date('Y');
$inicioExcepcion    = ($anio - 1) . '-01-01';                              // antes de esto se cierra todo
$inicioRecientes    = date('Y-m-01', strtotime('first day of -2 months')); // desde acá no se toca nada
$inicioPresupuestos = "$anio-01-01";
$mesCierre = date('m/Y');

$abierto = "NOT (p.estadoPago = 3 AND p.estadoEntrega = 3 AND p.estadoProduccion = 3)";
$saldo   = "GREATEST(p.monto - p.montoPagado, 0)";

$conn->query("CREATE TEMPORARY TABLE tmpCierre (PRIMARY KEY (id)) AS
    SELECT p.id, p.idTipoPedido tipo, $saldo saldo,
        CASE
          WHEN p.idTipoPedido = 3 THEN
            CASE WHEN p.entrada >= '$inicioPresupuestos' THEN 'dejar: presupuesto de este año'
                 WHEN p.montoPagado > 0.1 OR EXISTS (SELECT 1 FROM pagos pg WHERE pg.idPedido = p.id) THEN 'dejar: presupuesto con pagos'
                 ELSE 'anular' END
          WHEN p.entrada >= '$inicioRecientes' THEN 'dejar: reciente'
          WHEN p.idTipoPedido = 2 OR p.entrada < '$inicioExcepcion' THEN 'cerrar'
          WHEN p.monto >= " . MONTO_TRABAJO_GRANDE . " THEN 'dejar: trabajo grande'
          WHEN $saldo >= " . SALDO_GRANDE . " THEN 'dejar: saldo grande'
          ELSE 'cerrar'
        END accion,
        CONCAT('[Cierre $mesCierre sin registro de: ', CONCAT_WS(', ',
            IF(p.estadoPago <> 3 OR $saldo > 0.1, 'pago', NULL), IF(p.estadoEntrega <> 3, 'entrega', NULL),
            IF(p.estadoProduccion <> 3, 'terminación', NULL)), ']') nota
    FROM pedidos p
    WHERE p.anulado = 0 AND p.idTipoPedido IN (1, 2, 3)
      AND ($abierto OR (p.idTipoPedido <> 3 AND $saldo > 0.1))");

function tabla($conn, $titulo, $sql) {
    echo "\n$titulo\n";
    $r = $conn->query($sql);
    while ($f = $r->fetch_assoc()) echo '  ', implode(' | ', array_map(fn($k, $x) => "$k: $x", array_keys($f), $f)), "\n";
}
$plata = fn($campo) => "CONCAT('$', FORMAT($campo, 0, 'es_AR'))";
$nombreTipo = "ELT(t.tipo, 'ventas', 'compras', 'presupuestos')";

echo "Se cierra todo lo anterior a $inicioExcepcion; no se toca nada desde $inicioRecientes.\n";
tabla($conn, "Qué se hace con los pedidos pendientes", "SELECT $nombreTipo tipo, t.accion, COUNT(*) pedidos,
        {$plata('SUM(t.saldo)')} saldo FROM tmpCierre t GROUP BY t.tipo, t.accion ORDER BY t.tipo, t.accion");
tabla($conn, "Pagos '" . MEDIO . "' que se cargarían, por año del pedido", "SELECT $nombreTipo tipo,
        IF(YEAR(p.entrada) <= 2020, 'hasta 2020', YEAR(p.entrada)) periodo, COUNT(*) pagos, {$plata('SUM(t.saldo)')} total
    FROM tmpCierre t JOIN pedidos p ON p.id = t.id WHERE t.accion = 'cerrar' AND t.saldo > 0.1 GROUP BY t.tipo, 2 ORDER BY t.tipo, 2");
tabla($conn, "Totales", "SELECT SUM(accion = 'cerrar') seCierran, SUM(accion = 'cerrar' AND saldo > 0.1) conPagoSinRegistro,
        {$plata("SUM(IF(accion = 'cerrar', saldo, 0))")} totalSinRegistro, SUM(accion = 'anular') presupuestosAnulados,
        SUM(accion LIKE 'dejar%') quedanPendientes FROM tmpCierre");

if (!$aplicar) {
    $r = $conn->query("SELECT t.accion, $nombreTipo tipo, p.id, DATE(p.entrada) entrada, TRIM(CONCAT(c.apellido, ' ', COALESCE(c.nombre, ''))) contacto,
            p.detalle, p.monto, p.montoPagado, t.saldo, p.estadoPago, p.estadoEntrega, p.estadoProduccion
        FROM tmpCierre t JOIN pedidos p ON p.id = t.id LEFT JOIN contactos c ON c.id = p.idContacto
        WHERE t.accion LIKE 'dejar%' AND t.accion NOT IN ('dejar: reciente', 'dejar: presupuesto de este año')
        ORDER BY t.accion, t.saldo DESC");
    $csv = fopen(__DIR__ . '/pedidos_quedan_abiertos.csv', 'w');
    fwrite($csv, "\xEF\xBB\xBF");
    $n = 0;
    while ($f = $r->fetch_assoc()) {
        if (!$n++) fputcsv($csv, array_keys($f), ';');
        $f['detalle'] = preg_replace('/\s+/', ' ', (string)$f['detalle']);
        fputcsv($csv, $f, ';');
    }
    fclose($csv);
    echo "\nNo se modificó nada. $n pedidos que quedan abiertos para revisar: sql/limpieza/pedidos_quedan_abiertos.csv\n";
    echo "Para aplicar, repetir el comando agregando --aplicar\n";
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
    FROM tmpCierre t JOIN pedidos p ON p.id = t.id WHERE t.accion = 'cerrar' AND t.saldo > 0.1");
$pagos = $conn->affected_rows;
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pedido', p.id, p.id, 'regularizar',
        JSON_OBJECT('estadoPago', p.estadoPago, 'estadoEntrega', p.estadoEntrega, 'estadoProduccion', p.estadoProduccion,
                    'montoPagado', p.montoPagado, 'observaciones', p.observaciones),
        JSON_OBJECT('pagoSinRegistro', t.saldo), 'Cierre de pedidos viejos sin registro de pago, entrega o terminación'
    FROM tmpCierre t JOIN pedidos p ON p.id = t.id WHERE t.accion = 'cerrar'");
$conn->query("UPDATE pedidos p JOIN tmpCierre t ON t.id = p.id
    SET p.estadoPago = 3, p.estadoEntrega = 3, p.estadoProduccion = 3,
        p.montoPagado = GREATEST(p.montoPagado, p.monto),
        p.observaciones = IF(CHAR_LENGTH(CONCAT(COALESCE(p.observaciones, ''), ' ', t.nota)) <= 255,
                             TRIM(CONCAT(COALESCE(p.observaciones, ''), ' ', t.nota)), p.observaciones)
    WHERE t.accion = 'cerrar'");
$cerrados = $conn->affected_rows;
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pedido', p.id, p.id, 'anular', CONCAT('$', p.monto), NULL, 'Presupuesto vencido'
    FROM tmpCierre t JOIN pedidos p ON p.id = t.id WHERE t.accion = 'anular'");
$conn->query("UPDATE pedidos p JOIN tmpCierre t ON t.id = p.id SET p.anulado = 1 WHERE t.accion = 'anular'");
$anulados = $conn->affected_rows;
$conn->commit();
echo "\n$cerrados pedidos cerrados, $pagos pagos '" . MEDIO . "' cargados, $anulados presupuestos anulados.\n";
