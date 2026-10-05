<?php
// Segunda tanda para que en Pedidos queden abiertos solo los trabajos realmente pendientes.
//
//   php sql/limpieza/limpiarAbiertos.php             solo muestra lo que haría
//   php sql/limpieza/limpiarAbiertos.php --aplicar   modifica la base
//
// Reglas (acordadas el 5/10/2026):
//   1. Presupuestos anteriores al 1/8/2026 y pedidos de los tipos viejos 4/5/6 que
//      siguen abiertos se anulan ("Presupuesto vencido" / "Tipo de pedido viejo"),
//      salvo los que tienen algún pago con monto: Finanzas no cuenta los pagos de
//      los anulados, así que esos van a la lista para revisar.
//   2. Ventas de agosto 2026 con saldo menor a $20.000 se cierran (terminado,
//      entregado y pagado); la plata faltante va como pago "sinRegistro" con la
//      fecha del pedido, como en cerrarPedidosViejos.php.
// El estado anterior queda en modificaciones y una nota corta en observaciones.
// Deja en sql/limpieza/pedidos_abiertos_para_revisar.csv todo lo que sigue abierto,
// con una columna para que los encargados marquen si sigue pendiente.
require __DIR__ . '/comun.php';

const INICIO_RECIENTES = '2026-08-01';
const FIN_AGOSTO = '2026-09-01';
const SALDO_CHICO = 20000;
const USUARIO = 'edu';              // a nombre de quién quedan los pagos y el registro
const MEDIO = 'sinRegistro';

$abierto = "NOT (p.estadoPago = 3 AND p.estadoEntrega = 3 AND p.estadoProduccion = 3)";
$saldo   = "GREATEST(p.monto - p.montoPagado, 0)";
$conPago = "EXISTS (SELECT 1 FROM pagos g WHERE g.idPedido = p.id AND g.monto > 0.1)";

$conn->query("CREATE TEMPORARY TABLE tmpAbiertos (PRIMARY KEY (id)) AS
    SELECT p.id, $saldo saldo,
        CASE
          WHEN p.idTipoPedido >= 3 AND p.entrada < '" . INICIO_RECIENTES . "' AND NOT $conPago
            THEN IF(p.idTipoPedido = 3, 'anular: presupuesto vencido', 'anular: tipo de pedido viejo')
          WHEN p.idTipoPedido = 1 AND p.entrada >= '" . INICIO_RECIENTES . "' AND p.entrada < '" . FIN_AGOSTO . "'
               AND $saldo < " . SALDO_CHICO . " THEN 'cerrar'
          ELSE 'dejar'
        END accion,
        CONCAT('[Cierre " . date('m/Y') . " sin registro de: ', CONCAT_WS(', ',
            IF(p.estadoPago <> 3 OR $saldo > 0.1, 'pago', NULL), IF(p.estadoEntrega <> 3, 'entrega', NULL),
            IF(p.estadoProduccion <> 3, 'terminación', NULL)), ']') nota
    FROM pedidos p
    WHERE p.anulado = 0 AND $abierto");

function tabla($conn, $titulo, $sql) {
    echo "\n$titulo\n";
    $r = $conn->query($sql);
    while ($f = $r->fetch_assoc()) echo '  ', implode(' | ', array_map(fn($k, $x) => "$k: $x", array_keys($f), $f)), "\n";
}
$cantidades = "SELECT SUM(NOT (estadoProduccion = 3 AND estadoEntrega = 3 AND estadoPago = 3)) abiertos,
        SUM(estadoProduccion IN (1,2)) sinTerminar, SUM(estadoPago IN (1,2)) sinPagar, SUM(estadoEntrega IN (1,2)) sinEntregar
    FROM pedidos WHERE anulado = 0";

// Lo que sigue abierto, para que los encargados marquen qué está pendiente de verdad.
function exportarRevision($conn) {
    $r = $conn->query("SELECT p.id, ELT(p.idTipoPedido, 'venta', 'compra', 'presupuesto', 'tipo 4', 'tipo 5', 'tipo 6') tipo,
            DATE(p.entrada) entrada, TRIM(CONCAT(c.apellido, ' ', COALESCE(c.nombre, ''))) contacto, p.detalle,
            p.monto, p.montoPagado, GREATEST(p.monto - p.montoPagado, 0) saldo,
            ELT(p.estadoProduccion + 1, '-', 'sin terminar', 'en proceso', 'terminado') produccion,
            ELT(p.estadoEntrega + 1, '-', 'sin entregar', 'parcial', 'entregado') entrega,
            ELT(p.estadoPago + 1, '-', 'sin pagar', 'parcial', 'pagado') pago,
            '' AS `¿sigue pendiente? (SI/NO)`, '' AS comentario
        FROM pedidos p LEFT JOIN contactos c ON c.id = p.idContacto
        WHERE p.anulado = 0 AND NOT (p.estadoPago = 3 AND p.estadoEntrega = 3 AND p.estadoProduccion = 3)
        ORDER BY p.idTipoPedido, p.entrada DESC");
    $csv = fopen(__DIR__ . '/pedidos_abiertos_para_revisar.csv', 'w');
    fwrite($csv, "\xEF\xBB\xBF");
    $n = 0;
    while ($f = $r->fetch_assoc()) {
        if (!$n++) fputcsv($csv, array_keys($f), ';');
        $f['detalle'] = preg_replace('/\s+/', ' ', (string)$f['detalle']);
        fputcsv($csv, $f, ';');
    }
    fclose($csv);
    return $n;
}

tabla($conn, "Qué se hace con los pedidos abiertos", "SELECT ELT(p.idTipoPedido, 'ventas', 'compras', 'presupuestos', 'tipo 4', 'tipo 5', 'tipo 6') tipo,
        t.accion, COUNT(*) pedidos, CONCAT('$', FORMAT(SUM(IF(t.accion = 'cerrar', t.saldo, 0)), 0, 'es_AR')) pagoSinRegistro
    FROM tmpAbiertos t JOIN pedidos p ON p.id = t.id GROUP BY p.idTipoPedido, t.accion ORDER BY p.idTipoPedido, t.accion");
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
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pedido', p.id, p.id, 'anular', CONCAT('$', p.monto), NULL,
        IF(t.accion = 'anular: presupuesto vencido', 'Presupuesto vencido', 'Tipo de pedido viejo')
    FROM tmpAbiertos t JOIN pedidos p ON p.id = t.id WHERE t.accion LIKE 'anular%'");
$conn->query("UPDATE pedidos p JOIN tmpAbiertos t ON t.id = p.id SET p.anulado = 1 WHERE t.accion LIKE 'anular%'");
$anulados = $conn->affected_rows;

$conn->query("INSERT INTO pagos (fecha, monto, idPedido, idUsuario, idMedioPago)
    SELECT p.entrada, t.saldo, p.id, $idUsuario, $idMedio
    FROM tmpAbiertos t JOIN pedidos p ON p.id = t.id WHERE t.accion = 'cerrar' AND t.saldo > 0.1");
$pagos = $conn->affected_rows;
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pedido', p.id, p.id, 'regularizar',
        JSON_OBJECT('estadoPago', p.estadoPago, 'estadoEntrega', p.estadoEntrega, 'estadoProduccion', p.estadoProduccion,
                    'montoPagado', p.montoPagado, 'observaciones', p.observaciones),
        JSON_OBJECT('pagoSinRegistro', t.saldo), 'Cierre de ventas chicas de agosto sin registro de pago, entrega o terminación'
    FROM tmpAbiertos t JOIN pedidos p ON p.id = t.id WHERE t.accion = 'cerrar'");
$conn->query("UPDATE pedidos p JOIN tmpAbiertos t ON t.id = p.id
    SET p.estadoPago = 3, p.estadoEntrega = 3, p.estadoProduccion = 3,
        p.montoPagado = GREATEST(p.montoPagado, p.monto),
        p.observaciones = IF(CHAR_LENGTH(CONCAT(COALESCE(p.observaciones, ''), ' ', t.nota)) <= 255,
                             TRIM(CONCAT(COALESCE(p.observaciones, ''), ' ', t.nota)), p.observaciones)
    WHERE t.accion = 'cerrar'");
$cerrados = $conn->affected_rows;
$conn->commit();

echo "\n$anulados pedidos anulados, $cerrados ventas cerradas, $pagos pagos '" . MEDIO . "' cargados.\n";
tabla($conn, "Botones de Pedidos ahora", $cantidades);
$n = exportarRevision($conn);
echo "\n$n pedidos siguen abiertos: sql/limpieza/pedidos_abiertos_para_revisar.csv\n";
