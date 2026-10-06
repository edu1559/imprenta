<?php
// Completa contactos.celular a partir del teléfono que ya estaba cargado.
//
//   php sql/limpieza/celularesDesdeTelefono.php             solo muestra lo que haría
//   php sql/limpieza/celularesDesdeTelefono.php --aplicar   modifica la base
//
// Reglas (acordadas el 6/10/2026, ver normalizarCelular() en celular.php):
//   - característica + 15 + número          → 549 + característica + número
//   - 15 + 7 dígitos (Córdoba sin 351)       → 549 351 + número
//   - 351 + 7 dígitos que no empiezan con 4  → 549 + número
//   - fijos (7 dígitos sueltos, 351-4xx, otras características sin 15) → no se tocan
// Solo completa los que no tienen celular; el campo telefono no se modifica.
// Necesita sql/2026-10-06_contactos_celular.sql. Sin --aplicar deja en
// sql/limpieza/celulares_propuestos.csv lo que cargaría y lo que no pudo interpretar.
require __DIR__ . '/comun.php';
require __DIR__ . '/../../celular.php';

$r = $conn->query("SELECT c.id, c.apellido, c.nombre, c.telefono,
        (SELECT MAX(p.entrada) FROM pedidos p WHERE p.idContacto = c.id) ultimoPedido
    FROM contactos c
    WHERE COALESCE(TRIM(c.telefono), '') <> '' AND COALESCE(c.celular, '') = ''");

$propuestos = [];
$sinCelular = 0;
$csv = $aplicar ? null : fopen(__DIR__ . '/celulares_propuestos.csv', 'w');
if ($csv) {
    fwrite($csv, "\xEF\xBB\xBF");
    fputcsv($csv, ['id', 'contacto', 'telefono', 'celular', 'se ve como', 'ultimo pedido'], ';');
}
while ($c = $r->fetch_assoc()) {
    $celular = normalizarCelular($c['telefono'], true);
    if ($celular) $propuestos[$c['id']] = $celular; else $sinCelular++;
    if ($csv) fputcsv($csv, [$c['id'], trim($c['apellido'] . ' ' . $c['nombre']), $c['telefono'],
                             $celular ?? '', $celular ? mostrarCelular($celular) : 'queda sin celular', $c['ultimoPedido']], ';');
}

$recientes = $conn->query("SELECT COUNT(DISTINCT idContacto) FROM pedidos WHERE entrada >= '2025-01-01'")->fetch_row()[0];
$recientesConCelular = 0;
if ($propuestos) {
    $ids = implode(',', array_map('intval', array_keys($propuestos)));
    $recientesConCelular = $conn->query("SELECT COUNT(DISTINCT idContacto) FROM pedidos
        WHERE entrada >= '2025-01-01' AND idContacto IN ($ids)")->fetch_row()[0];
}
echo count($propuestos) . " contactos tendrían celular; $sinCelular con teléfono quedan sin celular (fijos o ilegibles).\n";
echo "De los $recientes clientes con pedidos desde 2025, $recientesConCelular tendrían celular.\n";

if (!$aplicar) {
    fclose($csv);
    echo "\nNo se modificó nada. Detalle en sql/limpieza/celulares_propuestos.csv\n";
    echo "Para aplicar, repetir el comando agregando --aplicar\n";
    exit;
}

$conn->begin_transaction();
$stmt = $conn->prepare("UPDATE contactos SET celular = ? WHERE id = ? AND COALESCE(celular, '') = ''");
$cargados = 0;
foreach ($propuestos as $id => $celular) {
    $stmt->bind_param('si', $celular, $id);
    $stmt->execute();
    $cargados += $stmt->affected_rows;
}
$conn->commit();
echo "\n$cargados celulares cargados.\n";
