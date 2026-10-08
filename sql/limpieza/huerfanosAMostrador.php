<?php
// Pasa a Mostrador los pedidos que apuntan a un contacto que no existe (ni en
// contactos, ni fusionado, ni borrado): se perdió en el sistema viejo.
//
//   php sql/limpieza/huerfanosAMostrador.php             solo muestra lo que haría
//   php sql/limpieza/huerfanosAMostrador.php --aplicar   modifica la base
//
// Regla (acordada el 8/10/2026): todos van al contacto genérico Mostrador. El
// contacto anterior queda en modificaciones (accion 'regularizar').
require __DIR__ . '/comun.php';

const MOSTRADOR = 908;
const USUARIO = 'edu';              // a nombre de quién queda el registro

$hay = fn($t) => $conn->query("SHOW TABLES LIKE '$t'")->num_rows > 0;
$conn->query("CREATE TEMPORARY TABLE tmpHuerfanos (PRIMARY KEY (id)) AS
    SELECT p.id, p.idContacto FROM pedidos p LEFT JOIN contactos c ON c.id = p.idContacto
    WHERE c.id IS NULL"
    . ($hay('contactosFusionados') ? " AND p.idContacto NOT IN (SELECT id FROM contactosFusionados)" : "")
    . ($hay('contactosBorrados') ? " AND p.idContacto NOT IN (SELECT id FROM contactosBorrados)" : ""));

$r = $conn->query("SELECT t.idContacto, COUNT(*) n, DATE(MIN(p.entrada)) desde, DATE(MAX(p.entrada)) hasta, SUM(p.anulado) anulados
    FROM tmpHuerfanos t JOIN pedidos p ON p.id = t.id GROUP BY t.idContacto");
$total = 0;
while ($f = $r->fetch_assoc()) {
    echo "  contacto {$f['idContacto']}: {$f['n']} pedidos ({$f['desde']} a {$f['hasta']}, {$f['anulados']} anulados)\n";
    $total += $f['n'];
}

if (!$aplicar) {
    echo "\n$total pedidos pasarían a Mostrador. No se modificó nada. Para aplicar, repetir el comando agregando --aplicar\n";
    exit;
}

$idUsuario = $conn->query("SELECT id FROM usuarios WHERE usuario = '" . USUARIO . "'")->fetch_row()[0] ?? null;
if (!$idUsuario) exit("\nNo existe el usuario '" . USUARIO . "'.\n");

$conn->begin_transaction();
$conn->query("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pedido', t.id, t.id, 'regularizar',
        JSON_OBJECT('idContacto', t.idContacto), JSON_OBJECT('idContacto', " . MOSTRADOR . "),
        'Contacto inexistente: el pedido pasa a Mostrador'
    FROM tmpHuerfanos t");
$conn->query("UPDATE pedidos p JOIN tmpHuerfanos t ON t.id = p.id SET p.idContacto = " . MOSTRADOR);
$n = $conn->affected_rows;
$conn->commit();
echo "\n$n pedidos pasados a Mostrador.\n";
