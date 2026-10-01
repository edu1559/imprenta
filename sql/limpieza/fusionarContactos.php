<?php
// Fusiona contactos duplicados "seguros": mismo apellido + nombre (sin mirar
// acentos, mayúsculas ni el orden de las palabras) y además mismo teléfono,
// CUIT o correo.
// Con --mismoNombre fusiona también los grupos de mismo nombre donde nada lo
// contradice: a lo sumo uno tiene teléfono, CUIT o correo. Quedan afuera los
// nombres de una sola palabra y los grupos con datos distintos entre sí.
//
//   php sql/limpieza/fusionarContactos.php                 solo muestra lo que haría
//   php sql/limpieza/fusionarContactos.php --aplicar       modifica la base
//   php sql/limpieza/fusionarContactos.php --mismoNombre [--aplicar]
//
// Sin --aplicar deja el detalle en sql/limpieza/fusion_propuesta.csv.
// De cada grupo queda el contacto con más pedidos (a igualdad, el más viejo).
// Los demás pasan a la tabla contactosFusionados (fila completa + idNuevo) y sus
// pedidos se reasignan al que queda. Se puede correr todas las veces que haga
// falta: si después se importan pedidos que apuntan a un contacto ya fusionado,
// la próxima corrida los reasigna.
require __DIR__ . '/comun.php';
$mismoNombre = in_array('--mismoNombre', $argv, true);

// --- tabla de fusiones y reasignación de lo que haya quedado apuntando a un fusionado
if ($aplicar) {
    $conn->query("CREATE TABLE IF NOT EXISTS contactosFusionados LIKE contactos");
    if (!$conn->query("SHOW COLUMNS FROM contactosFusionados LIKE 'idNuevo'")->num_rows) {
        $conn->query("ALTER TABLE contactosFusionados
            ADD COLUMN idNuevo INT NOT NULL, ADD COLUMN fechaFusion DATETIME NOT NULL, ADD KEY (idNuevo)");
    }
    $cols = columnasContactos($conn, 'contactosFusionados');
}
$hayTabla = $conn->query("SHOW TABLES LIKE 'contactosFusionados'")->num_rows > 0;
if ($hayTabla) {
    // solo si el contacto sigue sin existir: si una importación lo trajo de nuevo, el pedido está bien
    $falta = "LEFT JOIN contactos c ON c.id = f.id WHERE c.id IS NULL";
    $pend = $conn->query("SELECT (SELECT COUNT(*) FROM pedidos p JOIN contactosFusionados f ON f.id = p.idContacto $falta)
                               + (SELECT COUNT(*) FROM papeles p JOIN contactosFusionados f ON f.id = p.idProveedor $falta)")->fetch_row()[0];
    echo "Pedidos/papeles que apuntan a contactos ya fusionados: $pend\n";
    if ($aplicar && $pend) {
        $conn->query("UPDATE pedidos p JOIN contactosFusionados f ON f.id = p.idContacto LEFT JOIN contactos c ON c.id = f.id
                      SET p.idContacto = f.idNuevo WHERE c.id IS NULL");
        $conn->query("UPDATE papeles p JOIN contactosFusionados f ON f.id = p.idProveedor LEFT JOIN contactos c ON c.id = f.id
                      SET p.idProveedor = f.idNuevo WHERE c.id IS NULL");
    }
}

// --- detección
$r = $conn->query("SELECT c.*, COALESCE(p.n, 0) pedidos, p.primero, p.ultimo
    FROM contactos c LEFT JOIN (SELECT idContacto, COUNT(*) n, MIN(entrada) primero, MAX(entrada) ultimo
                                FROM pedidos GROUP BY idContacto) p ON p.idContacto = c.id
    ORDER BY c.id");
$C = []; $porNombre = [];
while ($c = $r->fetch_assoc()) {
    $C[$c['id']] = $c;
    if (esGenerico($c)) continue;
    $palabras = array_filter(explode(' ', norm($c['apellido'] . ' ' . $c['nombre'])), 'strlen');
    if (!$palabras || in_array(implode(' ', $palabras), ['sin apellido', 'sin nombre'], true)) continue;
    sort($palabras);
    $porNombre[implode(' ', $palabras)][] = $c['id'];
}

$fusiones = [];   // [idQueQueda => [idsQueSeVan]]
$motivo = [];     // [idQueQueda => por qué se fusiona]
$aRevisar = 0;
$elegir = function ($lista) use (&$C, &$fusiones, &$motivo) {
    usort($lista, fn($a, $b) => $C[$b]['pedidos'] <=> $C[$a]['pedidos'] ?: $a <=> $b);
    $queda = array_shift($lista);
    $fusiones[$queda] = $lista;
    return $queda;
};
foreach ($porNombre as $nombre => $ids) {
    if (count($ids) < 2) continue;
    // dentro del mismo nombre, se unen los que comparten teléfono, CUIT o correo
    $padre = array_combine($ids, $ids);
    $raiz = function ($x) use (&$padre, &$raiz) { return $padre[$x] == $x ? $x : ($padre[$x] = $raiz($padre[$x])); };
    $visto = []; $conDatos = [];
    foreach ($ids as $id) {
        $c = $C[$id]; $claves = [];
        if (strlen(digitos($c['telefono'])) >= 7) $claves[] = 't' . substr(digitos($c['telefono']), -7);
        if (strlen(digitos($c['cuit'])) >= 10)    $claves[] = 'c' . digitos($c['cuit']);
        if (strpos((string)$c['correo'], '@'))    $claves[] = 'm' . strtolower(trim($c['correo']));
        if ($claves) $conDatos[] = $id;
        foreach ($claves as $k) {
            if (isset($visto[$k])) $padre[$raiz($id)] = $raiz($visto[$k]); else $visto[$k] = $id;
        }
    }
    $sub = [];
    foreach ($ids as $id) $sub[$raiz($id)][] = $id;
    if (count($sub) > 1 && $mismoNombre) {
        // subgrupos que tienen algún teléfono, CUIT o correo: si hay más de uno, los datos difieren
        $subConDatos = count(array_unique(array_map($raiz, $conDatos)));
        if ($subConDatos <= 1 && substr_count($nombre, ' ') >= 1) {
            $motivo[$elegir($ids)] = 'mismo nombre';
            continue;
        }
        $aRevisar++;
    }
    foreach ($sub as $lista) {
        if (count($lista) > 1) $motivo[$elegir($lista)] = 'mismo nombre y dato';
    }
}

// --- fusión
$conn->begin_transaction();
$nSeVan = 0; $nPedidos = 0; $nGrupo = 0;
$csv = null;
if (!$aplicar) {
    $csv = fopen(__DIR__ . '/fusion_propuesta.csv', 'w');
    fwrite($csv, "\xEF\xBB\xBF");
    fputcsv($csv, ['grupo', 'motivo', 'propuesta', 'id', 'apellido', 'nombre', 'telefono', 'correo', 'cuit', 'tipoViejo',
                   'fechaCarga', 'pedidos', 'primerPedido', 'ultimoPedido'], ';');
}
$fila = fn($g, $m, $prop, $c) => [$g, $m, $prop, $c['id'], $c['apellido'], $c['nombre'], $c['telefono'], $c['correo'], $c['cuit'],
    $c['tipo'] == 2 ? 'proveedor' : ($c['tipo'] == 1 ? 'cliente' : $c['tipo']), substr((string)$c['fechacarga'], 0, 10),
    $c['pedidos'], substr((string)$c['primero'], 0, 10), substr((string)$c['ultimo'], 0, 10)];
foreach ($fusiones as $queda => $seVan) {
    $q = $C[$queda];
    $nGrupo++;
    if ($csv) {
        fputcsv($csv, $fila($nGrupo, $motivo[$queda], 'QUEDA', $q), ';');
        foreach ($seVan as $id) fputcsv($csv, $fila($nGrupo, $motivo[$queda], "se une a $queda", $C[$id]), ';');
    }
    $set = [];
    foreach ($seVan as $id) {
        $d = $C[$id];
        // el nombre se toma entero del que lo tenga separado en apellido y nombre
        if (vacio($q['nombre']) && !vacio($d['nombre']) && !vacio($d['apellido'])) {
            $set['apellido'] = $q['apellido'] = $d['apellido'];
            $set['nombre']   = $q['nombre']   = $d['nombre'];
        }
        foreach (['telefono', 'correo', 'cuit', 'notas'] as $campo) {
            if (vacio($q[$campo]) && !vacio($d[$campo])) $set[$campo] = $q[$campo] = $d[$campo];
        }
        if ($d['fechacarga'] && (!$q['fechacarga'] || $d['fechacarga'] < $q['fechacarga'])) {
            $set['fechacarga'] = $q['fechacarga'] = $d['fechacarga'];
        }
        printf("%6d %-45s <- %6d %-45s (%d pedidos)\n", $queda, substr(trim($C[$queda]['apellido'] . ', ' . $C[$queda]['nombre']), 0, 45),
            $id, substr(trim($d['apellido'] . ', ' . $d['nombre']), 0, 45), $d['pedidos']);
        $nSeVan++; $nPedidos += $d['pedidos'];
        if (!$aplicar) continue;

        $conn->query("UPDATE pedidos SET idContacto = $queda WHERE idContacto = $id");
        $conn->query("UPDATE papeles SET idProveedor = $queda WHERE idProveedor = $id");
        $conn->query("UPDATE contactosFusionados SET idNuevo = $queda WHERE idNuevo = $id");
        $conn->query("REPLACE INTO contactosFusionados ($cols, idNuevo, fechaFusion) SELECT $cols, $queda, NOW() FROM contactos WHERE id = $id");
        $conn->query("DELETE FROM contactos WHERE id = $id");
    }
    if ($aplicar && $set) {
        $sql = implode(', ', array_map(fn($k) => "$k = ?", array_keys($set)));
        $st = $conn->prepare("UPDATE contactos SET $sql WHERE id = $queda");
        $st->bind_param(str_repeat('s', count($set)), ...array_values($set));
        $st->execute();
    }
}
$conn->commit();

echo "\n", count($fusiones), " grupos, $nSeVan contactos ", $aplicar ? "fusionados" : "a fusionar", ", $nPedidos pedidos reasignados.\n";
if ($mismoNombre) echo "$aRevisar grupos de mismo nombre quedan para revisar a mano (datos distintos o nombre de una sola palabra).\n";
if (!$aplicar) echo "No se modificó nada. Para aplicar, repetir el comando agregando --aplicar\n";
