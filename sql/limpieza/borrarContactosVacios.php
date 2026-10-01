<?php
// Borra los contactos "vacíos": sin pedidos y sin teléfono, correo, CUIT ni notas
// (solo tienen un nombre). No toca a los que eran proveedores (tipo 2), a los
// genéricos ("AA ...", Visitante) ni a los que recibieron una fusión.
//
//   php sql/limpieza/borrarContactosVacios.php             solo muestra lo que haría
//   php sql/limpieza/borrarContactosVacios.php --aplicar   modifica la base
//
// Las fichas borradas quedan completas en la tabla contactosBorrados.
// Sin --aplicar deja el detalle en sql/limpieza/borrado_propuesto.csv.
if (php_sapi_name() !== 'cli') { http_response_code(403); exit; }

include __DIR__ . '/../../conexion.php';
$conn = conectar();
$conn->set_charset('utf8mb4');
$aplicar = in_array('--aplicar', $argv, true);

$hayFusion = $conn->query("SHOW TABLES LIKE 'contactosFusionados'")->num_rows > 0;
$vacio = fn($campo) => "TRIM(COALESCE(c.$campo, '')) = ''";
$donde = "NOT EXISTS (SELECT 1 FROM pedidos p WHERE p.idContacto = c.id)
      AND NOT EXISTS (SELECT 1 FROM papeles pa WHERE pa.idProveedor = c.id)
      AND {$vacio('telefono')} AND {$vacio('correo')} AND {$vacio('cuit')} AND {$vacio('notas')}
      AND COALESCE(c.tipo, 0) <> 2
      AND c.id <> 1 AND LOWER(TRIM(COALESCE(c.apellido, ''))) NOT REGEXP '^aa( |$)'"
      . ($hayFusion ? " AND NOT EXISTS (SELECT 1 FROM contactosFusionados f WHERE f.idNuevo = c.id)" : "");

$r = $conn->query("SELECT c.id, c.apellido, c.nombre, c.fechacarga FROM contactos c WHERE $donde ORDER BY c.id");
$filas = $r->fetch_all(MYSQLI_ASSOC);

$porAnio = [];
foreach ($filas as $f) {
    $a = $f['fechacarga'] ? substr($f['fechacarga'], 0, 4) : 'sin fecha';
    $porAnio[$a] = ($porAnio[$a] ?? 0) + 1;
}
ksort($porAnio);
foreach ($porAnio as $a => $n) echo "$a: $n\n";

if ($aplicar) {
    $conn->query("CREATE TABLE IF NOT EXISTS contactosBorrados LIKE contactos");
    if (!$conn->query("SHOW COLUMNS FROM contactosBorrados LIKE 'fechaBorrado'")->num_rows) {
        $conn->query("ALTER TABLE contactosBorrados ADD COLUMN fechaBorrado DATETIME NOT NULL");
    }
    $conn->begin_transaction();
    $conn->query("REPLACE INTO contactosBorrados SELECT c.*, NOW() FROM contactos c WHERE $donde");
    $conn->query("DELETE c FROM contactos c WHERE $donde");
    $borrados = $conn->affected_rows;
    $conn->commit();
    echo "\n$borrados contactos borrados (guardados en contactosBorrados).\n";
} else {
    $csv = fopen(__DIR__ . '/borrado_propuesto.csv', 'w');
    fwrite($csv, "\xEF\xBB\xBF");
    fputcsv($csv, ['id', 'apellido', 'nombre', 'fechaCarga'], ';');
    foreach ($filas as $f) fputcsv($csv, $f, ';');
    fclose($csv);
    echo "\n", count($filas), " contactos a borrar. No se modificó nada. Para aplicar, repetir el comando agregando --aplicar\n";
}
