<?php
// Saca los pagos "sinRegistro" que sobran: los que se cargaron para cerrar un pedido
// viejo que después recibió su pago real (por ejemplo, al importar los últimos días).
//
//   php sql/limpieza/sinRegistroDeMas.php             solo muestra lo que haría
//   php sql/limpieza/sinRegistroDeMas.php --aplicar   modifica la base
//
// Regla (acordada el 8/10/2026): en un pedido no anulado, los sinRegistro pueden
// cubrir a lo sumo lo que los pagos reales no cubren (monto - pagos reales). Lo que
// pasa de eso se descuenta empezando por el sinRegistro más nuevo: si queda en cero
// se borra, si no se achica. Cada pago tocado queda en modificaciones (entidad
// 'pago', accion 'regularizar') con la fila completa, para poder deshacerlo.
// No cambia montoPagado ni los estados del pedido. Finanzas no suma los sinRegistro,
// así que la caja no cambia.
require __DIR__ . '/comun.php';

const USUARIO = 'edu';              // a nombre de quién queda el registro

$idSR = $conn->query("SELECT id FROM mediosPago WHERE medio = 'sinRegistro'")->fetch_row()[0] ?? null;
if (!$idSR) exit("No existe el medio de pago 'sinRegistro'.\n");

$r = $conn->query("SELECT p.id, DATE(p.entrada) entrada, p.monto, p.montoPagado,
        SUM(IF(g.idMedioPago = $idSR, 0, g.monto)) reales, SUM(IF(g.idMedioPago = $idSR, g.monto, 0)) sr
    FROM pedidos p JOIN pagos g ON g.idPedido = p.id
    WHERE p.anulado = 0
    GROUP BY p.id
    HAVING sr > GREATEST(p.monto - reales, 0) + 0.5
    ORDER BY p.id");

$cambios = [];   // [idPago => monto nuevo (0 = se borra)]
$nPedidos = 0; $total = 0; $queda = 0; $muestra = [];
$pagosSR = $conn->prepare("SELECT id, monto FROM pagos WHERE idPedido = ? AND idMedioPago = $idSR ORDER BY fecha DESC, id DESC");
while ($p = $r->fetch_assoc()) {
    $sobra = round($p['sr'] - max($p['monto'] - $p['reales'], 0), 2);
    $nPedidos++; $total += $sobra;
    $pagosSR->bind_param('i', $p['id']); $pagosSR->execute();
    foreach ($pagosSR->get_result()->fetch_all(MYSQLI_ASSOC) as $g) {
        if ($sobra <= 0.005) break;
        $saca = min($sobra, $g['monto']);
        $cambios[$g['id']] = round($g['monto'] - $saca, 2);
        $sobra = round($sobra - $saca, 2);
    }
    $nuevoTotal = $p['reales'] + max($p['monto'] - $p['reales'], 0);
    if (abs($nuevoTotal - $p['montoPagado']) > 0.5) $queda++;
    if (count($muestra) < 10) $muestra[] = sprintf("  pedido %6d (%s) monto %10.2f  reales %10.2f  sinRegistro %10.2f -> %10.2f",
        $p['id'], $p['entrada'], $p['monto'], $p['reales'], $p['sr'], max($p['monto'] - $p['reales'], 0));
}

echo implode("\n", $muestra), $muestra ? "\n  ...\n" : '';
$borrar = count(array_filter($cambios, fn($m) => $m < 0.005));
printf("\n%d pedidos con sinRegistro de más: $ %s en total. %d pagos se borran y %d se achican.\n",
    $nPedidos, number_format($total, 2, ',', '.'), $borrar, count($cambios) - $borrar);
if ($queda) echo "$queda de esos pedidos siguen con montoPagado distinto de la suma de pagos (pagos reales de más; van en otro paso).\n";

if (!$aplicar) {
    echo "No se modificó nada. Para aplicar, repetir el comando agregando --aplicar\n";
    exit;
}

$idUsuario = $conn->query("SELECT id FROM usuarios WHERE usuario = '" . USUARIO . "'")->fetch_row()[0] ?? null;
if (!$idUsuario) exit("\nNo existe el usuario '" . USUARIO . "'.\n");

$conn->begin_transaction();
$registro = $conn->prepare("INSERT INTO modificaciones (fecha, idUsuario, entidad, idEntidad, idPedido, accion, valorAnterior, valorNuevo, motivo)
    SELECT NOW(), $idUsuario, 'pago', g.id, g.idPedido, 'regularizar',
        JSON_OBJECT('fecha', g.fecha, 'monto', g.monto, 'idPedido', g.idPedido, 'idUsuario', g.idUsuario, 'idMedioPago', g.idMedioPago),
        IF(? = 0, NULL, JSON_OBJECT('monto', ?)), 'Pago sinRegistro de más: el pedido ya tenía pagos reales'
    FROM pagos g WHERE g.id = ?");
$achicar = $conn->prepare("UPDATE pagos SET monto = ? WHERE id = ?");
$borrarPago = $conn->prepare("DELETE FROM pagos WHERE id = ?");
foreach ($cambios as $id => $monto) {
    $registro->bind_param('ddi', $monto, $monto, $id); $registro->execute();
    if ($monto < 0.005) { $borrarPago->bind_param('i', $id); $borrarPago->execute(); }
    else { $achicar->bind_param('di', $monto, $id); $achicar->execute(); }
}
$conn->commit();
echo "Aplicado: $borrar pagos borrados, ", count($cambios) - $borrar, " achicados.\n";
