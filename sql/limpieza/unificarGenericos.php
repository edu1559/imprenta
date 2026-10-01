<?php
// Unifica y renombra los contactos genéricos, que ya no necesitan el prefijo "AA"
// (servía para aparecer primero en un combo alfabético que ya no existe):
//
//   Mostrador      ventas a clientes sin identificar. Queda el 908 ("AA Fotocopias
//                  y Libreria") y se le une "AA".
//   Devoluciones   señas y pagos devueltos. Es el 9538 ("AA DEVOLUCIÓN").
//   Gastos Varios  gastos sueltos de la imprenta. Queda el 9430 ("VARIOS") y se le
//                  une "aa devoluciones", cuyo único pedido es un gasto.
//
//   php sql/limpieza/unificarGenericos.php             solo muestra lo que haría
//   php sql/limpieza/unificarGenericos.php --aplicar   modifica la base
//
// Necesita la columna esGenerico (sql/2026-10-01_contactos_esGenerico.sql).
// Las fichas que se unen quedan en contactosFusionados, igual que en fusionarContactos.php.
require __DIR__ . '/comun.php';

// nombresViejos: lo que se espera encontrar en cada id; si no coincide, no se toca nada.
$genericos = [
    ['queda' => 908,  'apellido' => 'Mostrador',     'seUnen' => [11472],
     'nombresViejos' => ['aa fotocopias y libreria', 'aa', 'mostrador']],
    ['queda' => 9538, 'apellido' => 'Devoluciones',  'seUnen' => [],
     'nombresViejos' => ['aa devolucion', 'devoluciones']],
    ['queda' => 9430, 'apellido' => 'Gastos Varios', 'seUnen' => [13798],
     'nombresViejos' => ['varios', 'aa devoluciones', 'gastos varios']],
];
$otros = [1];   // Visitante: genérico que no cambia de nombre

$hayColumna = $conn->query("SHOW COLUMNS FROM contactos LIKE 'esGenerico'")->num_rows > 0;
if ($aplicar && !$hayColumna) {
    exit("Falta la columna esGenerico: aplicar primero sql/2026-10-01_contactos_esGenerico.sql\n");
}

$ficha = function ($id) use ($conn) {
    $c = $conn->query("SELECT * FROM contactos WHERE id = $id")->fetch_assoc();
    if ($c) $c['pedidos'] = $conn->query("SELECT COUNT(*) FROM pedidos WHERE idContacto = $id")->fetch_row()[0];
    return $c;
};

// --- comprobación antes de tocar nada
foreach ($genericos as $g) {
    foreach (array_merge([$g['queda']], $g['seUnen']) as $id) {
        $c = $ficha($id);
        if (!$c && $id != $g['queda']) continue;   // ya se unió en una corrida anterior
        if (!$c) exit("No existe el contacto {$g['queda']}; no se modificó nada.\n");
        if (!in_array(norm($c['apellido']), $g['nombresViejos'], true)) {
            exit("El contacto $id se llama \"{$c['apellido']}\" y no es el genérico esperado; no se modificó nada.\n");
        }
    }
}

if ($aplicar) {
    $conn->query("CREATE TABLE IF NOT EXISTS contactosFusionados LIKE contactos");
    if (!$conn->query("SHOW COLUMNS FROM contactosFusionados LIKE 'idNuevo'")->num_rows) {
        $conn->query("ALTER TABLE contactosFusionados
            ADD COLUMN idNuevo INT NOT NULL, ADD COLUMN fechaFusion DATETIME NOT NULL, ADD KEY (idNuevo)");
    }
    $cols = columnasContactos($conn, 'contactosFusionados');
    $conn->begin_transaction();
}

foreach ($genericos as $g) {
    $queda = $g['queda'];
    $q = $ficha($queda);
    printf("\n%d \"%s\" (%d pedidos) pasa a llamarse \"%s\"\n", $queda, trim($q['apellido'] . ' ' . $q['nombre']), $q['pedidos'], $g['apellido']);
    $total = $q['pedidos'];
    foreach ($g['seUnen'] as $id) {
        $c = $ficha($id);
        if (!$c) { echo "   $id ya estaba unido\n"; continue; }
        printf("   se le une %d \"%s\" (%d pedidos)\n", $id, trim($c['apellido'] . ' ' . $c['nombre']), $c['pedidos']);
        $total += $c['pedidos'];
        if (!$aplicar) continue;
        $conn->query("UPDATE pedidos SET idContacto = $queda WHERE idContacto = $id");
        $conn->query("UPDATE papeles SET idProveedor = $queda WHERE idProveedor = $id");
        $conn->query("UPDATE contactosFusionados SET idNuevo = $queda WHERE idNuevo = $id");
        $conn->query("REPLACE INTO contactosFusionados ($cols, idNuevo, fechaFusion) SELECT $cols, $queda, NOW() FROM contactos WHERE id = $id");
        $conn->query("DELETE FROM contactos WHERE id = $id");
    }
    echo "   total: $total pedidos\n";
    if ($aplicar) {
        $st = $conn->prepare("UPDATE contactos SET apellido = ?, nombre = '', esGenerico = 1, esEmpresa = 0 WHERE id = $queda");
        $st->bind_param('s', $g['apellido']);
        $st->execute();
    }
}
if ($aplicar) {
    $conn->query("UPDATE contactos SET esGenerico = 1 WHERE id IN (" . implode(',', $otros) . ")");
    $conn->commit();
    echo "\nAplicado.\n";
} else {
    echo "\nNo se modificó nada. Para aplicar, repetir el comando agregando --aplicar\n";
}
