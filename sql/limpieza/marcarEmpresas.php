<?php
// Marca esEmpresa = 1 en los contactos que son una empresa o institución.
//
//   php sql/limpieza/marcarEmpresas.php             solo muestra lo que haría
//   php sql/limpieza/marcarEmpresas.php --aplicar   modifica la base
//
// Marca solo los casos claros: CUIT de persona jurídica (30, 33 o 34) o una
// palabra inequívoca en el nombre (SRL, Colegio, Municipalidad...). Los dudosos
// (palabras como "Casa" o "Centro", o ex proveedores sin otra señal) no se marcan:
// quedan listados en sql/limpieza/empresas_propuesta.csv para revisarlos.
// No desmarca nada: lo que se marque a mano desde la aplicación se respeta.
require __DIR__ . '/comun.php';

$claras = 's a|s r l|srl|sas|s a s|s h|ltda|coop|cooperativa|fundacion|asociacion|asoc|colegio|instituto|club|municipalidad|'
        . 'iglesia|parroquia|universidad|facultad|escuela|sindicato|consorcio|ministerio|hospital|clinica|sanatorio|'
        . 'hnos|hermanos|e hijos|cia|inmobiliaria|camara|federacion|mutual|obra social|gobierno|secretaria|comuna|'
        . 'laboratorio|consultora|distribuidora|editorial|libreria|imprenta|grafica|farmacia|hotel|empresa|seguros|'
        . 'transporte|expreso|construcciones|restaurante|optica|supermercado|veterinaria|ferreteria|panaderia|'
        . 'impresiones|publicidad|producciones|comunicaciones|sistemas|servicios|agencia|banco';
$dudosas = 'sa|casa|centro|bar|estudio|taller|direccion|jardin|revista|periodico|diario|radio|grupo|diseno|disenos';

$r = $conn->query("SELECT c.*, COALESCE(p.n, 0) pedidos FROM contactos c
    LEFT JOIN (SELECT idContacto, COUNT(*) n FROM pedidos GROUP BY idContacto) p ON p.idContacto = c.id
    WHERE c.esEmpresa = 0 ORDER BY c.id");
$marcar = []; $filas = []; $n = ['claro' => 0, 'dudoso' => 0]; $porMotivo = [];
while ($c = $r->fetch_assoc()) {
    if (esGenerico($c)) continue;
    $nombre = norm($c['apellido'] . ' ' . $c['nombre']);
    $cuit = digitos($c['cuit']);
    $cuitValido = strlen($cuit) == 11;
    $motivos = []; $nivel = null;
    if ($cuitValido && preg_match('/^3[034]/', $cuit)) { $motivos[] = 'CUIT de persona jurídica'; $nivel = 'claro'; }
    if (preg_match("/(^| )($claras)( |$)/", $nombre, $m)) { $motivos[] = 'palabra: ' . trim($m[0]); $nivel = 'claro'; }
    elseif (preg_match('/ sa$/', norm($c['apellido']))) { $motivos[] = 'palabra: sa'; $nivel = 'claro'; }
    // un CUIT de persona física (20, 23, 24, 27) contradice la palabra: a revisar
    if ($nivel == 'claro' && $cuitValido && preg_match('/^2[0347]/', $cuit)) { $motivos[] = 'pero CUIT de persona'; $nivel = 'dudoso'; }
    if (!$nivel && preg_match("/(^| )($dudosas)( |$)/", $nombre, $m)) { $motivos[] = 'palabra: ' . trim($m[0]); $nivel = 'dudoso'; }
    if (!$nivel && $c['tipo'] == 2) { $motivos[] = 'era proveedor'; $nivel = 'dudoso'; }
    if (!$nivel) continue;

    $n[$nivel]++;
    $clave = preg_replace('/^palabra: .*/', 'palabra', $motivos[0]);
    $porMotivo[$nivel][$clave] = ($porMotivo[$nivel][$clave] ?? 0) + 1;
    if ($nivel == 'claro') $marcar[] = (int)$c['id'];
    $filas[] = [$nivel == 'claro' ? 'SE MARCA' : 'a revisar', implode(', ', $motivos), $c['id'], $c['apellido'], $c['nombre'],
                $c['cuit'], $c['telefono'], $c['tipo'] == 2 ? 'proveedor' : '', $c['pedidos']];
}

foreach ($porMotivo as $nivel => $ms) foreach ($ms as $m => $k) echo "$nivel - $m: $k\n";

if ($aplicar) {
    foreach (array_chunk($marcar, 500) as $ids) {
        $conn->query("UPDATE contactos SET esEmpresa = 1 WHERE id IN (" . implode(',', $ids) . ")");
    }
    echo "\n", count($marcar), " contactos marcados como empresa. {$n['dudoso']} dudosos sin marcar.\n";
} else {
    usort($filas, fn($a, $b) => strcmp($a[0], $b[0]) ?: $b[8] <=> $a[8]);
    $csv = fopen(__DIR__ . '/empresas_propuesta.csv', 'w');
    fwrite($csv, "\xEF\xBB\xBF");
    fputcsv($csv, ['propuesta', 'motivo', 'id', 'apellido', 'nombre', 'cuit', 'telefono', 'tipoViejo', 'pedidos'], ';');
    foreach ($filas as $f) fputcsv($csv, $f, ';');
    fclose($csv);
    echo "\n{$n['claro']} contactos a marcar como empresa, {$n['dudoso']} dudosos a revisar. No se modificó nada.\n";
    echo "Para aplicar, repetir el comando agregando --aplicar\n";
}
