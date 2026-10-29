<?php
// Pasa los teléfonos que quedaron en las notas ("Dom: ... | Tel2: 156123456 | Tel3: 4681234",
// herencia del sistema viejo) a los campos celular y telefono del contacto.
//
//   php sql/limpieza/celularesDesdeNotas.php             solo muestra lo que haría
//   php sql/limpieza/celularesDesdeNotas.php --aplicar   modifica la base
//
// Reglas (mismas que celularesDesdeTelefono.php, ver normalizarCelular() en celular.php):
//   - Se miran Tel2 y Tel3, en ese orden.
//   - El primero que sea celular va a contactos.celular, solo si el contacto no tiene celular.
//   - El primero que sea fijo (7 dígitos, 351-4xx, otra característica sin 15) va a
//     contactos.telefono, solo si el contacto no tiene teléfono.
//   - Lo que se pasó se quita de las notas, salvo que tenga texto agregado
//     ("156692255 Lita"): ahí la nota queda como estaba, para no perder el dato.
//   - Lo ilegible ("asdfsadf") no se toca; un "Tel3: -" o vacío se quita al limpiar la nota.
// Sin --aplicar deja el detalle en sql/limpieza/telefonos_notas_propuestos.csv.
// Con --aplicar guarda antes celular, telefono y notas de los contactos que cambia en
// sql/limpieza/respaldo_AAAA-MM-DD_telefonos_notas.csv.
require __DIR__ . '/comun.php';
require __DIR__ . '/../../celular.php';

// Fijo reconocible: 7 dígitos (Córdoba) o 10 con característica, con o sin 0 adelante.
function esFijo($texto) {
    $d = digitos($texto);
    if (str_starts_with($d, '0')) $d = substr($d, 1);
    return strlen($d) === 7 || (strlen($d) === 10 && preg_match('/^[123]/', $d));
}

// El valor es solo el número, sin nombres ni aclaraciones pegadas.
function soloNumero($texto) {
    return preg_match('/^[\d\s\-.()\/]+$/', $texto) === 1;
}

$r = $conn->query("SELECT id, apellido, nombre, celular, telefono, notas
    FROM contactos
    WHERE notas REGEXP 'Tel[23]:' AND (COALESCE(celular, '') = '' OR COALESCE(TRIM(telefono), '') = '')");

$cambios = [];
$csv = $aplicar ? null : fopen(__DIR__ . '/telefonos_notas_propuestos.csv', 'w');
if ($csv) {
    fwrite($csv, "\xEF\xBB\xBF");
    fputcsv($csv, ['id', 'contacto', 'notas antes', 'celular nuevo', 'se ve como', 'telefono nuevo', 'notas despues'], ';');
}
while ($c = $r->fetch_assoc()) {
    // Las notas son segmentos separados por " | "; los teléfonos son "Tel2: valor".
    $segmentos = preg_split('/\s*\|\s*/', $c['notas']);
    $celular = null;
    $telefono = null;
    $quitar = [];
    foreach ($segmentos as $i => $seg) {
        // El valor termina en el salto de línea: lo que sigue es otra nota.
        if (!preg_match('/^Tel[23]:\s*([^\n]*)(\n.*)?$/s', $seg, $m)) continue;
        $valor = trim($m[1]);
        $resto = $m[2] ?? '';
        $usado = false;
        if ($celular === null && vacio($c['celular']) && ($n = normalizarCelular($valor, true))) {
            $celular = $n;
            $usado = true;
        } elseif ($telefono === null && vacio($c['telefono']) && !normalizarCelular($valor, true) && esFijo($valor) && soloNumero($valor)) {
            $telefono = $valor;
            $usado = true;
        }
        // También se quita un "Tel3: -" o "Tel3:" vacío, que no dice nada.
        if (($usado && soloNumero($valor)) || preg_match('/^[\s\-.]*$/', $valor)) $quitar[$i] = ltrim($resto);
    }
    if ($celular === null && $telefono === null) continue;

    foreach ($quitar as $i => $resto) {
        if ($resto === '') unset($segmentos[$i]); else $segmentos[$i] = $resto;
    }
    $notas = implode(' | ', $segmentos);
    $cambios[$c['id']] = ['celular' => $celular, 'telefono' => $telefono, 'notas' => $notas, 'antes' => $c];

    if ($csv) fputcsv($csv, [$c['id'], trim($c['apellido'] . ' ' . $c['nombre']), $c['notas'], $celular ?? '',
                             $celular ? mostrarCelular($celular) : '', $telefono ?? '', $notas], ';');
}

$conCelular = count(array_filter($cambios, fn($x) => $x['celular'] !== null));
$conTelefono = count(array_filter($cambios, fn($x) => $x['telefono'] !== null));
$notasCambian = count(array_filter($cambios, fn($x) => $x['notas'] !== $x['antes']['notas']));
echo count($cambios) . " contactos cambian: $conCelular reciben celular, $conTelefono reciben teléfono fijo; a $notasCambian se les limpia la nota.\n";

if (!$aplicar) {
    fclose($csv);
    echo "\nNo se modificó nada. Detalle en sql/limpieza/telefonos_notas_propuestos.csv\n";
    echo "Para aplicar, repetir el comando agregando --aplicar\n";
    exit;
}

$respaldo = fopen(__DIR__ . '/respaldo_' . date('Y-m-d') . '_telefonos_notas.csv', 'w');
fwrite($respaldo, "\xEF\xBB\xBF");
fputcsv($respaldo, ['id', 'celular', 'telefono', 'notas'], ';');
foreach ($cambios as $id => $x) fputcsv($respaldo, [$id, $x['antes']['celular'], $x['antes']['telefono'], $x['antes']['notas']], ';');
fclose($respaldo);

$conn->begin_transaction();
// Las condiciones repiten las del SELECT por si alguien editó el contacto mientras tanto.
$stmt = $conn->prepare("UPDATE contactos SET
        celular  = IF(? IS NOT NULL AND COALESCE(celular, '') = '', ?, celular),
        telefono = IF(? IS NOT NULL AND COALESCE(TRIM(telefono), '') = '', ?, telefono),
        notas    = ?
    WHERE id = ? AND notas = ?");
$cambiados = 0;
foreach ($cambios as $id => $x) {
    $stmt->bind_param('sssssis', $x['celular'], $x['celular'], $x['telefono'], $x['telefono'], $x['notas'], $id, $x['antes']['notas']);
    $stmt->execute();
    $cambiados += $stmt->affected_rows;
}
$conn->commit();
echo "\n$cambiados contactos actualizados. Respaldo en sql/limpieza/respaldo_" . date('Y-m-d') . "_telefonos_notas.csv\n";
