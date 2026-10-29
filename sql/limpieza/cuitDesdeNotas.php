<?php
// Pasa al campo cuit el CUIT/CUIL escrito en las notas ("CUIT: 30714494844", "Cuit Nº 33-54614686-9").
//
//   php sql/limpieza/cuitDesdeNotas.php             solo muestra lo que haría
//   php sql/limpieza/cuitDesdeNotas.php --aplicar   modifica la base
//
// Reglas:
//   - Solo contactos con el campo cuit vacío y "cuit" o "cuil" en las notas.
//   - Se toman los números de 11 dígitos (con o sin guiones o espacios) cuyo dígito
//     verificador es correcto, para no confundirlos con un teléfono.
//   - Si la nota tiene un solo CUIT válido, se carga como 30-71449484-4.
//     Si tiene varios distintos (empresa e instituto, por ejemplo) no se elige: queda para revisar.
//   - Las notas no se modifican: suelen decir además la condición (Responsable inscripto).
// Sin --aplicar deja el detalle en sql/limpieza/cuit_notas_propuestos.csv.
// Con --aplicar deja en sql/limpieza/respaldo_AAAA-MM-DD_cuit_notas.csv los ids que completó.
require __DIR__ . '/comun.php';

// Dígito verificador de AFIP: módulo 11 con los pesos 5 4 3 2 7 6 5 4 3 2.
function cuitValido($d) {
    if (strlen($d) !== 11 || !in_array(substr($d, 0, 2), ['20', '23', '24', '27', '30', '33', '34'], true)) return false;
    $suma = 0;
    foreach ([5, 4, 3, 2, 7, 6, 5, 4, 3, 2] as $i => $peso) $suma += $d[$i] * $peso;
    $dv = 11 - $suma % 11;
    if ($dv === 11) $dv = 0;
    return $dv !== 10 && $dv === (int)$d[10];
}

$r = $conn->query("SELECT id, apellido, nombre, notas FROM contactos
    WHERE notas REGEXP 'cuit|cuil' AND COALESCE(TRIM(cuit), '') = ''");

$propuestos = [];
$aRevisar = 0;
$csv = $aplicar ? null : fopen(__DIR__ . '/cuit_notas_propuestos.csv', 'w');
if ($csv) {
    fwrite($csv, "\xEF\xBB\xBF");
    fputcsv($csv, ['id', 'contacto', 'notas', 'cuit', 'resultado'], ';');
}
while ($c = $r->fetch_assoc()) {
    preg_match_all('/(?<!\d)\d{2}[\s.\-]?\d{8}[\s.\-]?\d(?!\d)/', $c['notas'], $m);
    $validos = array_values(array_unique(array_filter(array_map('digitos', $m[0]), 'cuitValido')));
    if (count($validos) === 1) {
        $d = $validos[0];
        $cuit = substr($d, 0, 2) . '-' . substr($d, 2, 8) . '-' . substr($d, 10);
        $propuestos[$c['id']] = $cuit;
        $resultado = 'se carga';
    } else {
        $cuit = implode(' / ', $validos);
        $resultado = $validos ? 'varios CUIT: revisar a mano' : 'sin CUIT válido';
        $aRevisar++;
    }
    if ($csv) fputcsv($csv, [$c['id'], trim($c['apellido'] . ' ' . $c['nombre']), $c['notas'], $cuit, $resultado], ';');
}

echo count($propuestos) . " contactos reciben CUIT; $aRevisar quedan sin cargar (varios CUIT o ninguno válido).\n";

if (!$aplicar) {
    fclose($csv);
    echo "\nNo se modificó nada. Detalle en sql/limpieza/cuit_notas_propuestos.csv\n";
    echo "Para aplicar, repetir el comando agregando --aplicar\n";
    exit;
}

$archivo = 'respaldo_' . date('Y-m-d') . '_cuit_notas.csv';
$respaldo = fopen(__DIR__ . '/' . $archivo, 'w');
fputcsv($respaldo, ['id', 'cuit cargado'], ';');
$conn->begin_transaction();
$stmt = $conn->prepare("UPDATE contactos SET cuit = ? WHERE id = ? AND COALESCE(TRIM(cuit), '') = ''");
$cargados = 0;
foreach ($propuestos as $id => $cuit) {
    $stmt->bind_param('si', $cuit, $id);
    $stmt->execute();
    if ($stmt->affected_rows) {
        $cargados++;
        fputcsv($respaldo, [$id, $cuit], ';');
    }
}
$conn->commit();
fclose($respaldo);
echo "\n$cargados CUIT cargados. Ids en sql/limpieza/$archivo (antes tenían el campo vacío).\n";
