<?php
// Cierra los pedidos que están pagados pero figuran sin terminar o sin entregar:
// en la imprenta un trabajo pagado ya se terminó y se entregó, solo faltó marcarlo.
//
//   php sql/limpieza/cerrarPedidosPagados.php             solo muestra lo que haría
//   php sql/limpieza/cerrarPedidosPagados.php --aplicar   modifica la base
//
// Regla (acordada el 5/10/2026): ventas y compras con pago 3 (pagado) pasan a
// terminado y entregado. No se tocan presupuestos ni los tipos viejos 4/5/6.
// No se cargan pagos: solo cambian los estados de producción y entrega.
// El estado anterior queda en modificaciones (accion 'regularizar', que además
// evita que importarUltimosDias.php los vuelva a abrir) y una nota en observaciones.
// Sin --aplicar deja la lista en sql/limpieza/pedidos_pagados_a_cerrar.csv
require __DIR__ . '/comun.php';

const USUARIO = 'edu';              // a nombre de quién queda el registro

$nota = '[Cierre ' . date('m/Y') . ': pagado, sin registro de: ';
$conn->query("CREATE TEMPORARY TABLE tmpPagados (PRIMARY KEY (id)) AS
    SELECT p.id, CONCAT('$nota', CONCAT_WS(', ', IF(p.estadoEntrega <> 3, 'entrega', NULL),
                                         IF(p.estadoProduccion <> 3, 'terminación', NULL)), ']') nota
    FROM pedidos p
    WHERE p.anulado = 0 AND p.idTipoPedido IN (1, 2) AND p.estadoPago = 3
      AND NOT (p.estadoProduccion = 3 AND p.estadoEntrega = 3)");

function tabla($conn, $titulo, $sql) {
    echo "\n$titulo\n";
    $r = $conn->query($sql);
    while ($f = $r->fetch_assoc()) echo '  ', implode(' | ', array_map(fn($k, $x) => "$k: $x", array_keys($f), $f)), "\n";
}
$abierto = "NOT (estadoProduccion = 3 AND estadoEntrega = 3 AND estadoPago = 3)";
$cantidades = "SELECT SUM($abierto) abiertos, SUM(estadoProduccion IN (1,2)) sinTerminar,
        SUM(estadoPago IN (1,2)) sinPagar, SUM(estadoEntrega IN (1,2)) sinEntregar
    FROM pedidos WHERE anulado = 0";

tabla($conn, "Pedidos pagados que se cierran, por mes de entrada", "SELECT ELT(p.idTipoPedido, 'ventas', 'compras') tipo,
        DATE_FORMAT(p.entrada, '%Y-%m') mes, COUNT(*) pedidos
    FROM tmpPagados t JOIN pedidos p ON p.id = t.id GROUP BY 1, 2 ORDER BY 1, 2");
tabla($conn, "Botones de Pedidos antes", $cantidades);
tabla($conn, "Botones de Pedidos después", "SELECT SUM($abierto AND t.id IS NULL) abiertos,
        SUM(estadoProduccion IN (1,2) AND t.id IS NULL) sinTerminar, SUM(estadoPago IN (1,2)) sinPagar,
        SUM(estadoEntrega IN (1,2) AND t.id IS NULL) sinEntregar
    FROM pedidos p LEFT JOIN tmpPagados t ON t.id = p.id WHERE p.anulado = 0");

if (!$aplicar) {
    $r = $conn->query("SELECT p.id, DATE(p.entrada) entrada, TRIM(CONCAT(c.apellido, ' ', COALESCE(c.nombre, ''))) contacto,
            p.detalle, p.monto, p.montoPagado, p.estadoProduccion, p.estadoEntrega
        FROM tmpPagados t JOIN pedidos p ON p.id = t.id LEFT JOIN contactos c ON c.id = p.idContacto
        ORDER BY p.id DESC");
    $csv = fopen(__DIR__ . '/pedidos_pagados_a_cerrar.csv', 'w');
    fwrite($csv, "\xEF\xBB\xBF");
    $n = 0;
    while ($f = $r->fetch_assoc()) {
        if (!$n++) fputcsv($csv, array_keys($f), ';');
        $f['detalle'] = preg_replace('/\s+/', ' ', (string)$f['detalle']);
        fputcsv($csv, $f, ';');
    }
    fclose($csv);
    echo "\nNo se modificó nada. La lista de los $n pedidos está en sql/limpieza/pedidos_pagados_a_cerrar.csv\n";
    echo "Para aplicar, repetir el comando agregando --aplicar\n";
    exit;
}

// --- aplicar
$idUsuario = $conn->query("SELECT id FROM usuarios WHERE usuario = '" . USUARIO . "'")->fetch_row()[0] ?? null;
if (!$idUsuario) exit("\nNo existe el usuario '" . USUARIO . "'.\n");

$conn->begin_transaction();
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pedido', p.id, p.id, 'regularizar',
        JSON_OBJECT('estadoEntrega', p.estadoEntrega, 'estadoProduccion', p.estadoProduccion, 'observaciones', p.observaciones),
        JSON_OBJECT('estadoEntrega', 3, 'estadoProduccion', 3), 'Cierre de pedidos pagados sin registro de entrega o terminación'
    FROM tmpPagados t JOIN pedidos p ON p.id = t.id");
$conn->query("UPDATE pedidos p JOIN tmpPagados t ON t.id = p.id
    SET p.estadoEntrega = 3, p.estadoProduccion = 3,
        p.observaciones = IF(CHAR_LENGTH(CONCAT(COALESCE(p.observaciones, ''), ' ', t.nota)) <= 255,
                             TRIM(CONCAT(COALESCE(p.observaciones, ''), ' ', t.nota)), p.observaciones)");
$cerrados = $conn->affected_rows;
$conn->commit();
echo "\n$cerrados pedidos cerrados.\n";
tabla($conn, "Botones de Pedidos ahora", $cantidades);
