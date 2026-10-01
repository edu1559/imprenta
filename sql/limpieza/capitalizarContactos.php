<?php
// Deja apellido y nombre de los contactos con la primera letra de cada palabra en
// mayúscula y el resto en minúscula ("PEREZ juan carlos" -> "Perez Juan Carlos").
// De paso repara los acentos rotos ("LÃ³pez" -> "López"), también en las notas, y quita
// espacios sobrantes.
//
//   php sql/limpieza/capitalizarContactos.php             solo muestra lo que haría
//   php sql/limpieza/capitalizarContactos.php --aplicar   modifica la base
//   php sql/limpieza/capitalizarContactos.php --soloAcentos [--aplicar]
//                                    solo repara los acentos rotos, sin tocar las mayúsculas
//
// Sin --aplicar deja el detalle en sql/limpieza/nombres_propuesta.csv.
// Con --aplicar guarda el apellido, nombre y notas anteriores en contactosNombresAnteriores.
//
// Reglas:
//   - "de", "del", "la", "y"... quedan en minúscula, salvo al principio.
//   - Las siglas de $siglas y los números romanos quedan en mayúscula.
//   - Si el campo mezcla mayúsculas y minúsculas, lo que ya está bien no se toca
//     ("Barrio Las Flores") y lo escrito en mayúscula a propósito se respeta: en el
//     nombre de una empresa, cualquier palabra en mayúscula (LEP, FASTA) y las marcas
//     con mayúscula interna (UtilCenter); en una persona, solo las de hasta 3 letras
//     que no estén junto a otra palabra en mayúscula (JM, TN) y las del tipo McDonald.
//     El campo nombre de una empresa es la persona de contacto: lleva reglas de persona.
//   - Si el campo está todo en mayúscula no se puede saber qué era una sigla: solo
//     se respetan las de $siglas y las palabras cortas sin vocales (GNC, CPC).
// No toca a los genéricos (Mostrador, Devoluciones, Gastos Varios, Visitante).
require __DIR__ . '/comun.php';
mb_internal_encoding('UTF-8');
$soloAcentos = in_array('--soloAcentos', $argv, true);

$siglas = ['sa', 'srl', 'sas', 'sh', 'saic', 'sacif', 'sacifia', 'ute', 'cuit', 'dni', 'ipem', 'iua', 'lssi', 'cba', 'gnc',
           'unc', 'utn', 'ucc', 'afip', 'epec', 'cpc', 'sos', 'pc', 'ipet', 'cenma', 'faud'];
$romanos = ['ii', 'iii', 'iv', 'vi', 'vii', 'viii', 'ix', 'xi', 'xii', 'xiii', 'xiv', 'xv', 'xvi', 'xx', 'xxi', 'xxiii'];
$particulas = ['de', 'del', 'la', 'las', 'los', 'el', 'y', 'e', 'o', 'u', 'en', 'con', 'por', 'para', 'sin', 'al', 'da', 'do', 'dos', 'di', 'van', 'von'];

// "LÃ³pez" es "López" en UTF-8 leído como si fuera Latin-1 y vuelto a guardar: se deshace.
// Devuelve el texto igual si no era eso.
function deshacerLatin1($s) {
    for ($vuelta = 0; $vuelta < 3 && preg_match('/[ÃÂï]/u', $s); $vuelta++) {
        $bytes = '';
        foreach (mb_str_split($s) as $ch) {
            $b = mb_ord($ch) < 256 ? chr(mb_ord($ch)) : @iconv('UTF-8', 'Windows-1252', $ch);
            if ($b === false || $b === '') return $s;
            $bytes .= $b;
        }
        if ($bytes === $s || !mb_check_encoding($bytes, 'UTF-8')) return $s;
        $s = $bytes;
    }
    return $s;
}

function repararAcentos($s) {
    $r = deshacerLatin1($s);
    // si el texto mezcla acentos rotos con otros bien escritos, se repara palabra por palabra
    if ($r === $s && preg_match('/[ÃÂï]/u', $s)) $r = implode(' ', array_map('deshacerLatin1', explode(' ', $s)));
    return preg_replace_callback('/[\p{L}\x{FFFD}]*\x{FFFD}[\p{L}\x{FFFD}]*/u', 'reponerLetra', $r);
}

// Donde el acento se guardó como "ï¿½" la letra se perdió; se repone en las palabras conocidas.
function reponerLetra($m) {
    static $letra = ['gonz?lez' => 'á', 'l?pez' => 'ó', 'ram?n' => 'ó', 'automec?nica' => 'á', 'villafa?e' => 'ñ', 'mar?a' => 'í',
                     'jos?' => 'é', 'a?rea' => 'é', 'nu?ez' => 'ñ', 'rub?n' => 'é', 'd?az' => 'í', 'le?n' => 'ó', 'c?ceres' => 'á',
                     'agust?n' => 'í', 'filosof?a' => 'í', 'per?n' => 'ó', 'port?n' => 'ó', 'c?rdoba' => 'ó', 'mass?' => 'é',
                     'b?' => 'º', 'vi?a' => 'ñ', 'm?nica' => 'ó', 'odont?loga' => 'ó', 'asociaci?n' => 'ó',
                     'administraci?n' => 'ó', 'andalgal?' => 'á', 'r?o' => 'í'];
    $clave = str_replace("\u{FFFD}", '?', mb_strtolower($m[0]));
    if (!isset($letra[$clave])) return $m[0];
    $antes = mb_substr($m[0], max(0, mb_strpos($m[0], "\u{FFFD}") - 1), 1);
    $mayus = $antes !== "\u{FFFD}" && $antes === mb_strtoupper($antes) && mb_strlen($m[0]) > 1 && mb_strpos($m[0], "\u{FFFD}") > 0
             && mb_substr($m[0], 1) === mb_strtoupper(mb_substr($m[0], 1));
    return str_replace("\u{FFFD}", $mayus ? mb_strtoupper($letra[$clave]) : $letra[$clave], $m[0]);
}

// Primera letra en mayúscula, también después de un guion, apóstrofo, punto o paréntesis.
function titulo($palabra) {
    return preg_replace_callback('/(^|[-\'’(.\/"¿¡,;:&+])(\p{Ll})/u', fn($m) => $m[1] . mb_strtoupper($m[2]), mb_strtolower($palabra));
}

// Devuelve [texto nuevo, palabras en mayúscula que se dejaron por posible sigla].
// $razonSocial: el campo es el nombre de una empresa (el apellido de un contacto esEmpresa).
function capitalizar($s, $razonSocial) {
    global $siglas, $romanos, $particulas;
    $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
    $dejadas = [];
    $palabras = explode(' ', $s);
    $letras = array_map(fn($p) => preg_replace('/[^\p{L}]/u', '', $p), $palabras);
    // las siglas no cuentan para saber si el campo está todo en mayúscula o en minúscula:
    // "CEMATI saic" es un campo en mayúscula, igual que "CEMATI SAIC"
    $resto = implode(' ', array_filter($letras, fn($l) => !in_array(mb_strtolower($l), $siglas, true)));
    $todoMayus = $resto === mb_strtoupper($resto);
    $parejo = $todoMayus || $resto === mb_strtolower($resto);
    $enMayus = array_map(fn($l) => mb_strlen($l) >= 2 && $l === mb_strtoupper($l), $letras);
    // palabra larga en mayúscula: lo que tenga al lado en mayúscula es parte de la frase, no una sigla
    $larga = fn($i) => isset($letras[$i]) && $enMayus[$i] && mb_strlen($letras[$i]) >= 4;
    foreach ($palabras as $i => $p) {
        if ($letras[$i] === '') continue;
        $clave = mb_strtolower($letras[$i]);
        $largo = mb_strlen($letras[$i]);
        $sinPuntos = strpos($p, '.') === false;

        if ($sinPuntos && (in_array($clave, $siglas, true) && ($i > 0 || !in_array($clave, ['sa', 'sh'], true))
                           || in_array($clave, $romanos, true) && $i > 0)) {
            $palabras[$i] = mb_strtoupper($p);
        } elseif ($sinPuntos && in_array($clave, $particulas, true)) {
            if ($parejo) {
                // "E" y "O" sueltas en un campo todo en mayúscula pueden ser una inicial
                $palabras[$i] = $i > 0 && ($largo > 1 || $clave == 'y' || !$todoMayus) ? mb_strtolower($p) : titulo($p);
            } elseif ($enMayus[$i] && !$razonSocial) {
                $palabras[$i] = $i > 0 ? mb_strtolower($p) : titulo($p);
            }                                    // si no, queda como está: "Barrio Las Flores", "de Ponce"
        } elseif ($todoMayus && $largo >= 2 && $largo <= 5 && !preg_match('/[aeiouáéíóúü]/u', $clave)) {
            continue;                            // sin vocales: sigla
        } elseif (!$parejo && $enMayus[$i] && ($razonSocial || $largo <= 3 && !$larga($i - 1) && !$larga($i + 1))) {
            if ($largo > 3) $dejadas[] = $p;
        } elseif (!$parejo && preg_match($razonSocial ? '/^\p{Lu}.*\p{Ll}/u' : '/^\p{Lu}(\p{Ll}+(\p{Lu}\p{Ll}+)+|\p{Ll}\p{Lu})$/u', $letras[$i])) {
            continue;                            // UtilCenter, McDonald, MyM
        } else {
            $palabras[$i] = titulo($p);
        }
    }
    return [implode(' ', $palabras), $dejadas];
}

$r = $conn->query("SELECT * FROM contactos ORDER BY id");
$cambios = []; $filas = []; $n = ['cambia' => 0, 'revisar' => 0]; $porMotivo = [];
while ($c = $r->fetch_assoc()) {
    if (esGenerico($c)) continue;
    $nuevo = []; $motivos = []; $revisar = [];
    foreach (['apellido', 'nombre'] as $campo) {
        $viejo = (string)$c[$campo];
        $reparado = repararAcentos($viejo);
        if ($reparado !== $viejo) $motivos['acentos rotos'] = 1;
        if (strpos($reparado, "\u{FFFD}") !== false) {   // la letra se perdió y la palabra no es de las conocidas
            $revisar[] = 'acento irrecuperable en ' . $campo;
            $nuevo[$campo] = $viejo;
            continue;
        }
        if ($soloAcentos) { $nuevo[$campo] = $reparado; continue; }
        $limpio = trim(preg_replace('/\s+/u', ' ', $reparado));
        if ($limpio !== $reparado) $motivos['espacios'] = 1;
        [$nuevo[$campo], $dejadas] = capitalizar($limpio, $c['esEmpresa'] && $campo == 'apellido');
        if ($nuevo[$campo] !== $limpio) {
            $motivos[$limpio === mb_strtoupper($limpio) ? 'todo mayúscula' : ($limpio === mb_strtolower($limpio) ? 'todo minúscula' : 'mezclado')] = 1;
        }
        if ($dejadas) $revisar[] = 'se deja en mayúscula: ' . implode(' ', $dejadas);
    }
    // en las notas solo se reparan los acentos
    $nuevo['notas'] = repararAcentos((string)$c['notas']);
    if (strpos($nuevo['notas'], "\u{FFFD}") !== false) {
        $revisar[] = 'acento irrecuperable en notas';
        $nuevo['notas'] = (string)$c['notas'];
    } elseif ($nuevo['notas'] !== (string)$c['notas']) {
        $motivos['acentos rotos en notas'] = 1;
    }
    $cambia = $nuevo['apellido'] !== (string)$c['apellido'] || $nuevo['nombre'] !== (string)$c['nombre']
           || $nuevo['notas'] !== (string)$c['notas'];
    if (!$cambia && !$revisar) continue;

    if ($cambia) {
        $n['cambia']++;
        $cambios[$c['id']] = $nuevo;
        foreach ($motivos as $m => $_) $porMotivo[$m] = ($porMotivo[$m] ?? 0) + 1;
    }
    if ($revisar) $n['revisar']++;
    $filas[] = [$cambia ? 'SE CAMBIA' : 'a revisar', implode(', ', array_merge(array_keys($motivos), $revisar)), $c['id'],
                $c['apellido'], $c['nombre'], $nuevo['apellido'], $nuevo['nombre'], $c['esEmpresa'] ? 'empresa' : '',
                ...(preg_grep('/notas/', array_merge(array_keys($motivos), $revisar)) ? [$c['notas'], $nuevo['notas']] : ['', ''])];
}

foreach ($porMotivo as $m => $k) echo "$m: $k\n";

if ($aplicar) {
    $conn->query("CREATE TABLE IF NOT EXISTS contactosNombresAnteriores (
        id INT NOT NULL, apellido VARCHAR(50) NULL, nombre VARCHAR(50) NULL, fechaCambio DATETIME NOT NULL, KEY (id))");
    if (!$conn->query("SHOW COLUMNS FROM contactosNombresAnteriores LIKE 'notas'")->num_rows) {
        $conn->query("ALTER TABLE contactosNombresAnteriores ADD COLUMN notas VARCHAR(371) NULL AFTER nombre");
    }
    $conn->begin_transaction();
    $respaldo = $conn->prepare("INSERT INTO contactosNombresAnteriores SELECT id, apellido, nombre, notas, NOW() FROM contactos WHERE id = ?");
    $cambio = $conn->prepare("UPDATE contactos SET apellido = ?, nombre = ?, notas = IF(? = COALESCE(notas, ''), notas, ?) WHERE id = ?");
    foreach ($cambios as $id => $nuevo) {
        $respaldo->bind_param('i', $id);
        $respaldo->execute();
        $cambio->bind_param('ssssi', $nuevo['apellido'], $nuevo['nombre'], $nuevo['notas'], $nuevo['notas'], $id);
        $cambio->execute();
    }
    $conn->commit();
    echo "\n{$n['cambia']} contactos corregidos (los datos anteriores quedan en contactosNombresAnteriores). {$n['revisar']} para revisar a mano.\n";
} else {
    usort($filas, fn($a, $b) => strcmp($b[0], $a[0]) ?: strcmp($a[1], $b[1]) ?: $a[2] <=> $b[2]);
    $csv = fopen(__DIR__ . '/nombres_propuesta.csv', 'w');
    fwrite($csv, "\xEF\xBB\xBF");
    fputcsv($csv, ['propuesta', 'motivo', 'id', 'apellido', 'nombre', 'apellidoNuevo', 'nombreNuevo', 'empresa', 'notas', 'notasNuevas'], ';');
    foreach ($filas as $f) fputcsv($csv, $f, ';');
    fclose($csv);
    echo "\n{$n['cambia']} contactos a corregir, {$n['revisar']} con algo para revisar a mano. No se modificó nada.\n";
    echo "Para aplicar, repetir el comando agregando --aplicar\n";
}
