<?php
// Común a los scripts de limpieza de contactos: solo consola, conexión y funciones de comparación.
if (php_sapi_name() !== 'cli') { http_response_code(403); exit; }

include __DIR__ . '/../../conexion.php';
$conn = conectar();
$conn->set_charset('utf8mb4');
$aplicar = in_array('--aplicar', $argv, true);

// Texto sin acentos, mayúsculas ni signos, para comparar nombres.
function norm($s) {
    $s = strtolower(strtr(trim((string)$s), ['Á'=>'á','É'=>'é','Í'=>'í','Ó'=>'ó','Ú'=>'ú','Ü'=>'ü','Ñ'=>'ñ']));
    $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n~']);
    $s = preg_replace('/[^a-z0-9~ ]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}
function digitos($s) { return preg_replace('/\D/', '', (string)$s); }
function vacio($s) { return trim((string)$s) === ''; }

// Contactos genéricos (Mostrador, Devoluciones, Visitante): agrupan pedidos de
// clientes sin identificar, no se fusionan ni se borran nunca. Se reconocen por la
// columna esGenerico y, antes de unificarGenericos.php, por el prefijo "AA".
function esGenerico($c) {
    return !empty($c['esGenerico']) || $c['id'] == 1 || preg_match('/^aa( |$)/', norm($c['apellido']));
}

// Columnas de contactos, para copiar filas a una tabla de respaldo. Si a la tabla
// de respaldo le falta alguna (contactos sumó una columna después), se la agrega.
function columnasContactos($conn, $respaldo) {
    $tiene = array_column($conn->query("SHOW COLUMNS FROM $respaldo")->fetch_all(MYSQLI_ASSOC), 'Field');
    $cols = [];
    foreach ($conn->query("SHOW COLUMNS FROM contactos")->fetch_all(MYSQLI_ASSOC) as $c) {
        $cols[] = $c['Field'];
        if (!in_array($c['Field'], $tiene, true)) $conn->query("ALTER TABLE $respaldo ADD COLUMN `{$c['Field']}` {$c['Type']} NULL");
    }
    return implode(', ', $cols);
}
