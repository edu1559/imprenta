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
//   php sql/limpieza/fusionarContactos.php --revisados=planilla.csv [--aplicar]
//
// --mismoNombre deja los grupos dudosos en sql/limpieza/duplicados_a_revisar.csv.
// --revisados fusiona los grupos de una planilla así, ya revisada a mano (columnas
// grupo e id; los ids que ya no existen se saltean). En esos grupos, si teléfono,
// celular, correo o CUIT difieren, queda el del contacto más reciente (último
// pedido; sin pedidos, el cargado después) y las notas se juntan.
//
// Sin --aplicar deja el detalle en sql/limpieza/fusion_propuesta.csv.
// De cada grupo queda el contacto con más pedidos (a igualdad, el más viejo).
// Los demás pasan a la tabla contactosFusionados (fila completa + idNuevo) y sus
// pedidos se reasignan al que queda. Se puede correr todas las veces que haga
// falta: si después se importan pedidos que apuntan a un contacto ya fusionado,
// la próxima corrida los reasigna.
require __DIR__ . '/comun.php';
$mismoNombre = in_array('--mismoNombre', $argv, true);
$revisados = null;
foreach ($argv as $a) if (strpos($a, '--revisados=') === 0) $revisados = substr($a, 12);
if ($revisados && !is_readable($revisados)) exit("No se puede leer $revisados\n");

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
    // fusiones hechas antes de que se copiara el celular: se recupera del fusionado
    if ($conn->query("SHOW COLUMNS FROM contactosFusionados LIKE 'celular'")->num_rows) {
        $sinCel = "FROM contactos c JOIN contactosFusionados f ON f.idNuevo = c.id
                   WHERE TRIM(COALESCE(c.celular, '')) = '' AND TRIM(COALESCE(f.celular, '')) <> ''";
        echo "Celulares a recuperar de contactos ya fusionados: ",
            $conn->query("SELECT COUNT(DISTINCT c.id) $sinCel")->fetch_row()[0], "\n";
        if ($aplicar) $conn->query("UPDATE contactos c JOIN contactosFusionados f ON f.idNuevo = c.id SET c.celular = f.celular
                                    WHERE TRIM(COALESCE(c.celular, '')) = '' AND TRIM(COALESCE(f.celular, '')) <> ''");
    }
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
$revisar = [];   // grupos de mismo nombre que no se fusionan solos: [[ids]]
// usuarios.id es el id de su contacto: un contacto que es usuario siempre queda,
// y si en el grupo hay dos usuarios no se fusiona (va a revisión manual)
$esUsuario = array_flip(array_column($conn->query("SELECT id FROM usuarios")->fetch_all(), 0));
$elegir = function ($lista) use (&$C, &$fusiones, &$motivo, &$revisar, $esUsuario) {
    if (count(array_intersect_key(array_flip($lista), $esUsuario)) > 1) { $revisar[] = $lista; return null; }
    usort($lista, fn($a, $b) => isset($esUsuario[$b]) <=> isset($esUsuario[$a])
                             ?: $C[$b]['pedidos'] <=> $C[$a]['pedidos'] ?: $a <=> $b);
    $queda = array_shift($lista);
    $fusiones[$queda] = $lista;
    return $queda;
};
foreach ($revisados ? [] : $porNombre as $nombre => $ids) {
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
            if ($q = $elegir($ids)) $motivo[$q] = 'mismo nombre';
            continue;
        }
        $revisar[] = $ids;
    }
    foreach ($sub as $lista) {
        if (count($lista) > 1 && ($q = $elegir($lista))) $motivo[$q] = 'mismo nombre y dato';
    }
}
if ($revisados) {
    $f = fopen($revisados, 'r');
    $enc = fgetcsv($f, 0, ';');
    $enc[0] = preg_replace('/^\xEF\xBB\xBF/', '', $enc[0]);
    $grupos = [];
    while (($linea = fgetcsv($f, 0, ';')) !== false) {
        if (count($linea) < count($enc)) continue;
        $x = array_combine($enc, $linea);
        if (isset($C[$x['id']])) $grupos[$x['grupo']][] = (int)$x['id'];
    }
    foreach ($grupos as $ids) {
        if (count($ids) > 1 && ($q = $elegir($ids))) $motivo[$q] = 'revisado a mano';
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
    if ($revisados) {
        // datos que difieren: queda el del contacto más reciente
        $grupo = array_merge([$queda], $seVan);
        usort($grupo, fn($a, $b) => [(string)$C[$b]['ultimo'], $b] <=> [(string)$C[$a]['ultimo'], $a]);
        foreach (['telefono', 'celular', 'correo', 'cuit'] as $campo) {
            if (!array_key_exists($campo, $q)) continue;
            foreach ($grupo as $id) {
                if (vacio($C[$id][$campo])) continue;
                if ($C[$id][$campo] !== $q[$campo]) $set[$campo] = $q[$campo] = $C[$id][$campo];
                break;
            }
        }
        $notas = array_unique(array_filter(array_map(fn($id) => trim((string)$C[$id]['notas']), $grupo), 'strlen'));
        if (implode(' / ', $notas) !== trim((string)$q['notas'])) $set['notas'] = $q['notas'] = implode(' / ', $notas);
    }
    foreach ($seVan as $id) {
        $d = $C[$id];
        // el nombre se toma entero del que lo tenga separado en apellido y nombre
        if (vacio($q['nombre']) && !vacio($d['nombre']) && !vacio($d['apellido'])) {
            $set['apellido'] = $q['apellido'] = $d['apellido'];
            $set['nombre']   = $q['nombre']   = $d['nombre'];
        }
        foreach (['telefono', 'celular', 'correo', 'cuit', 'notas'] as $campo) {
            if (array_key_exists($campo, $q) && vacio($q[$campo]) && !vacio($d[$campo])) $set[$campo] = $q[$campo] = $d[$campo];
        }
        foreach (['esEmpresa', 'esTercerizado'] as $campo) {
            if (array_key_exists($campo, $q) && !$q[$campo] && $d[$campo]) $set[$campo] = $q[$campo] = 1;
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
    if ($revisados) printf("       queda: tel %s | cel %s | %s | cuit %s\n", $q['telefono'], $q['celular'] ?? '', $q['correo'], $q['cuit']);
    if ($aplicar && $set) {
        $sql = implode(', ', array_map(fn($k) => "$k = ?", array_keys($set)));
        $st = $conn->prepare("UPDATE contactos SET $sql WHERE id = $queda");
        $st->bind_param(str_repeat('s', count($set)), ...array_values($set));
        $st->execute();
    }
}
$conn->commit();

echo "\n", count($fusiones), " grupos, $nSeVan contactos ", $aplicar ? "fusionados" : "a fusionar", ", $nPedidos pedidos reasignados.\n";
if ($mismoNombre) echo count($revisar), " grupos de mismo nombre quedan para revisar a mano (datos distintos o nombre de una sola palabra).\n";
if (!$aplicar) echo "No se modificó nada. Para aplicar, repetir el comando agregando --aplicar\n";

// --- planilla para la revisión a mano: en la columna unirA se escribe el id del
// contacto con el que se une cada fila (vacía = queda como está)
if ($mismoNombre && !$aplicar) {
    $csv = fopen(__DIR__ . '/duplicados_a_revisar.csv', 'w');
    fwrite($csv, "\xEF\xBB\xBF");
    fputcsv($csv, ['grupo', 'unirA', 'id', 'apellido', 'nombre', 'telefono', 'celular', 'correo', 'cuit', 'empresa',
                   'pedidos', 'primerPedido', 'ultimoPedido', 'ultimoDetalle', 'notas'], ';');
    $det = $conn->prepare("SELECT detalle FROM pedidos WHERE idContacto = ? ORDER BY entrada DESC LIMIT 1");
    $corto = fn($t) => preg_replace('/^(.{0,80}).*$/us', '$1', trim(preg_replace('/\s+/', ' ', (string)$t)));
    foreach ($revisar as $g => $ids) {
        sort($ids);
        foreach ($ids as $id) {
            $c = $C[$id];
            $det->bind_param('i', $id); $det->execute();
            $d = $det->get_result()->fetch_row()[0] ?? '';
            fputcsv($csv, [$g + 1, '', $id, $c['apellido'], $c['nombre'], $c['telefono'], $c['celular'] ?? '', $c['correo'], $c['cuit'],
                $c['esEmpresa'] ?? 0 ? 'sí' : '', $c['pedidos'], substr((string)$c['primero'], 0, 10), substr((string)$c['ultimo'], 0, 10),
                $corto($d), $corto($c['notas'])], ';');
        }
        fputcsv($csv, [], ';');
    }
    fclose($csv);
    echo "Grupos para revisar a mano en sql/limpieza/duplicados_a_revisar.csv\n";
}
