<?php
// Trae a la base nueva lo que se cargó en el sistema viejo (base imprenta1) después
// de la última importación: contactos, pedidos y pagos nuevos, y los cambios hechos
// sobre pedidos que ya estaban.
//
//   php sql/migracion/importarUltimosDias.php             solo muestra lo que haría
//   php sql/migracion/importarUltimosDias.php --aplicar   modifica la base
//
// Antes hay que cargar en la base local imprenta1 un volcado actual de producción.
//
//   Contactos y pedidos  entran con su mismo id.
//   Pagos                se agregan con id nuevo (los ids que siguen ya los usan los
//                        pagos 'sinRegistro'); la tabla pagosImportados guarda qué id
//                        viejo es cada uno, para no cargarlos dos veces.
//   Pedidos existentes   se copian monto, pagado, estados y fechas del sistema viejo
//                        si cambiaron. No se tocan los que cerró cerrarPedidosViejos.php
//                        ni los anulados; si a uno de esos cerrados le entró un pago
//                        real, se descuenta de su pago 'sinRegistro'.
//
// Después conviene volver a correr los scripts de sql/limpieza/.
require __DIR__ . '/../limpieza/comun.php';

const ID_ORIGEN = 1;         // origen y medio que se pusieron en la importación anterior
const ID_MEDIO_PAGO = 1;     // (el sistema viejo no los registra)
const USUARIO_SI_NO_HAY = 'edu';

// La base vieja guarda el texto en UTF-8 dentro de columnas latin1: se lee en crudo.
$viejo = conectar();
$viejo->set_charset('latin1');
function txt($s) {
    if ($s === null) return null;
    return preg_match('//u', $s) ? $s : iconv('ISO-8859-1', 'UTF-8', $s);
}
// El sistema viejo guarda "sin fecha" como 0000-00-00; en el nuevo es NULL.
function fecha($s) { return $s === null || strpos($s, '0000') === 0 ? null : $s; }
function opcion($nombre) {
    global $argv;
    foreach ($argv as $a) if (strpos($a, "--$nombre=") === 0) return (int)substr($a, strlen($nombre) + 3);
    return null;
}
$uno = fn($sql) => $conn->query($sql)->fetch_row()[0];
$hay = fn($tabla) => $conn->query("SHOW TABLES LIKE '$tabla'")->num_rows > 0;

// --- hasta dónde está cargada la base nueva: el último registro del sistema viejo
// que ya está igual en la nueva (mismo id y misma fecha). Lo que sigue es lo nuevo.
$idSinRegistro = (int)$uno("SELECT COALESCE((SELECT id FROM mediosPago WHERE medio = 'sinRegistro'), 0)");
$hayImportados = $hay('pagosImportados');
$maxPedido = opcion('desdePedido')
    ?? (int)$uno("SELECT COALESCE(MAX(v.idpedido), 0) FROM imprenta1.pedidos v JOIN pedidos n ON n.id = v.idpedido AND n.entrada = v.entrada");
$maxContacto = opcion('desdeContacto')
    ?? (int)$uno("SELECT COALESCE(MAX(v.idcontacto), 0) FROM imprenta1.contactos v JOIN contactos n ON n.id = v.idcontacto AND n.fechacarga = v.fechacarga");
$maxPago = opcion('desdePago') ?? max(
    (int)$uno("SELECT COALESCE(MAX(v.id), 0) FROM imprenta1.pagos v JOIN pagos n ON n.id = v.id AND n.fecha = v.fecha AND n.idPedido = v.idpedido
               WHERE n.idMedioPago <> $idSinRegistro"),
    $hayImportados ? (int)$uno("SELECT COALESCE(MAX(idViejo), 0) FROM pagosImportados") : 0);

$viejoMaxPedido = (int)$uno("SELECT MAX(idpedido) FROM imprenta1.pedidos");
$viejoUltimo = $uno("SELECT MAX(entrada) FROM imprenta1.pedidos");
$nuevoMaxPedido = (int)$uno("SELECT MAX(id) FROM pedidos");
echo "Copia local del sistema viejo: llega al pedido $viejoMaxPedido, del $viejoUltimo.\n";
echo "Ya está en la base nueva hasta: pedido $maxPedido, contacto $maxContacto, pago $maxPago (ids del sistema viejo).\n";
if ($viejoMaxPedido < $nuevoMaxPedido) {
    echo "\nATENCIÓN: la copia de imprenta1 es más vieja que la base nueva (que llega al pedido $nuevoMaxPedido).\n";
    echo "Hay que cargar primero un volcado actual de producción.\n";
    if ($aplicar) exit("No se modificó nada.\n");
}
// pedidos de la base nueva que no vienen del sistema viejo y ocupan ids que éste va a usar
$locales = $conn->query("SELECT id, entrada, LEFT(detalle, 40) detalle FROM pedidos WHERE id > $maxPedido AND id <= $viejoMaxPedido")->fetch_all(MYSQLI_ASSOC);
if ($locales && opcion('desdePedido') === null) {
    echo "\nATENCIÓN: estos pedidos de la base nueva no coinciden con el sistema viejo y ocupan ids que se van a importar:\n";
    foreach ($locales as $l) echo "  {$l['id']} {$l['entrada']} {$l['detalle']}\n";
    if ($aplicar) exit("Hay que resolverlos antes de importar. No se modificó nada.\n");
}

// --- usuario del sistema viejo -> id de usuario en el nuevo, según lo ya importado
$usuarios = [];
$r = $conn->query("SELECT v.usuario, n.idUsuario, COUNT(*) c FROM imprenta1.pedidos v JOIN pedidos n ON n.id = v.idpedido
                   WHERE n.idUsuario > 1 GROUP BY 1, 2 ORDER BY c");
while ($f = $r->fetch_assoc()) $usuarios[strtolower($f['usuario'])] = (int)$f['idUsuario'];   // queda el más frecuente
$idUsuarioSiNoHay = (int)$uno("SELECT id FROM usuarios WHERE usuario = '" . USUARIO_SI_NO_HAY . "'");
$sinUsuario = [];
$idUsuario = function ($nombre) use (&$usuarios, &$sinUsuario, $idUsuarioSiNoHay) {
    $k = strtolower(trim((string)$nombre));
    if (isset($usuarios[$k])) return $usuarios[$k];
    $sinUsuario[$k] = true;
    return $idUsuarioSiNoHay;
};

// --- qué hay nuevo
$contactos = $viejo->query("SELECT * FROM imprenta1.contactos WHERE idcontacto > $maxContacto ORDER BY idcontacto")->fetch_all(MYSQLI_ASSOC);
// se descartan los que ya están (aunque la limpieza les haya cambiado la fecha o el nombre de lugar)
// y los que ya se importaron y después se fusionaron o borraron
$palabras = function ($c) { $t = array_filter(explode(' ', norm(txt($c['apellido']) . ' ' . txt($c['nombre']))), 'strlen'); sort($t); return implode(' ', $t); };
$contactos = array_values(array_filter($contactos, function ($c) use ($conn, $hay, $palabras) {
    $id = (int)$c['idcontacto'];
    foreach (['contactos', 'contactosFusionados', 'contactosBorrados'] as $tabla) {
        if (!$hay($tabla)) continue;
        $n = $conn->query("SELECT apellido, nombre, fechacarga FROM $tabla WHERE id = $id")->fetch_assoc();
        if ($n && ($n['fechacarga'] === $c['fechacarga'] || $palabras($n) === $palabras($c))) return false;
    }
    return true;
}));
$pedidos   = $viejo->query("SELECT * FROM imprenta1.pedidos WHERE idpedido > $maxPedido ORDER BY idpedido")->fetch_all(MYSQLI_ASSOC);
$pagos     = $viejo->query("SELECT * FROM imprenta1.pagos WHERE id > $maxPago ORDER BY id")->fetch_all(MYSQLI_ASSOC);

// pedidos que ya estaban y cambiaron en el sistema viejo (sin los cerrados por nosotros ni los anulados)
$cambiados = $conn->query("SELECT v.idpedido id, n.monto montoN, v.monto, n.montoPagado pagadoN, v.montopagado,
        CONCAT(n.estadoPago, n.estadoEntrega, n.estadoProduccion) estadosN, CONCAT(v.estadopago, v.estadoentrega, v.estadoproceso) estadosV,
        v.estadopago, v.estadoentrega, v.estadoproceso, CAST(v.prometido AS CHAR) prometido, CAST(v.salida AS CHAR) salida, DATE(n.entrada) entrada
    FROM imprenta1.pedidos v JOIN pedidos n ON n.id = v.idpedido
    WHERE v.idpedido <= $maxPedido AND n.anulado = 0
      AND NOT EXISTS (SELECT 1 FROM modificaciones m WHERE m.idPedido = n.id AND m.accion = 'regularizar')
      AND (ABS(v.monto - n.monto) > 0.01 OR ABS(v.montopagado - n.montoPagado) > 0.01 OR v.estadopago <> n.estadoPago
           OR v.estadoentrega <> n.estadoEntrega OR v.estadoproceso <> n.estadoProduccion
           OR NOT (IF(CAST(v.prometido AS CHAR) LIKE '0000%', NULL, v.prometido) <=> n.prometido))
    ORDER BY v.idpedido")->fetch_all(MYSQLI_ASSOC);

// contacto de un pedido nuevo: puede haberse fusionado o borrado en la limpieza
$resolverContacto = function ($id) use ($conn, $hay, $uno, $aplicar, $maxContacto) {
    $id = (int)$id;
    if ($id > $maxContacto) return [$id, "contacto $id todavía no existe"];   // debería venir entre los nuevos
    if ($uno("SELECT COUNT(*) FROM contactos WHERE id = $id")) return [$id, ''];
    if ($hay('contactosFusionados') && ($nuevo = $uno("SELECT COALESCE((SELECT idNuevo FROM contactosFusionados WHERE id = $id), 0)"))) {
        return [(int)$nuevo, "contacto $id fusionado en $nuevo"];
    }
    if ($hay('contactosBorrados') && $uno("SELECT COUNT(*) FROM contactosBorrados WHERE id = $id")) {
        if ($aplicar) {
            $cols = columnasContactos($conn, 'contactosBorrados');
            $conn->query("INSERT INTO contactos ($cols) SELECT $cols FROM contactosBorrados WHERE id = $id");
            $conn->query("DELETE FROM contactosBorrados WHERE id = $id");
        }
        return [$id, "contacto $id se había borrado por vacío: se restaura"];
    }
    return [$id, "contacto $id todavía no existe"];
};

if ($aplicar) {
    $conn->query("CREATE TABLE IF NOT EXISTS pagosImportados (idViejo INT NOT NULL PRIMARY KEY, idNuevo INT NOT NULL)");
    $conn->begin_transaction();
}

echo "\nContactos nuevos: ", count($contactos), "\n";
foreach ($contactos as $c) {
    // un contacto cargado en la base nueva (no viene del sistema viejo) ocupa ese id: se lo corre al final
    $ocupa = $conn->query("SELECT id, apellido, nombre FROM contactos WHERE id = {$c['idcontacto']}")->fetch_assoc();
    if ($ocupa) {
        $libre = max((int)$uno("SELECT MAX(id) FROM contactos"), (int)$uno("SELECT MAX(idcontacto) FROM imprenta1.contactos")) + 1;
        echo "  el id {$ocupa['id']} lo ocupa \"{$ocupa['apellido']}, {$ocupa['nombre']}\", cargado en la base nueva: pasa al id $libre con sus pedidos\n";
        if ($aplicar) {
            $conn->query("UPDATE contactos SET id = $libre WHERE id = {$ocupa['id']}");
            $conn->query("UPDATE pedidos SET idContacto = $libre WHERE idContacto = {$ocupa['id']} AND id <= $maxPedido");
            $conn->query("UPDATE papeles SET idProveedor = $libre WHERE idProveedor = {$ocupa['id']}");
            if ($hay('contactosFusionados')) $conn->query("UPDATE contactosFusionados SET idNuevo = $libre WHERE idNuevo = {$ocupa['id']}");
        }
    }
    $notas = implode(' | ', array_filter([
        trim($c['domicilio']) !== '' ? 'Dom: ' . trim(txt($c['domicilio'])) : '',
        trim($c['telefono2']) !== '' ? 'Tel2: ' . trim($c['telefono2']) : '',
        trim($c['telefono3']) !== '' ? 'Tel3: ' . trim($c['telefono3']) : '',
        trim((string)txt($c['observaciones'])),
    ], 'strlen'));
    printf("  %6d %s, %s\n", $c['idcontacto'], txt($c['apellido']), txt($c['nombre']));
    if (!$aplicar) continue;
    $st = $conn->prepare("INSERT INTO contactos (id, apellido, nombre, telefono, correo, tipoFactura, cuit, fechacarga, tipo, notas)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $ap = txt($c['apellido']); $no = txt($c['nombre']); $notas = substr($notas, 0, 371); $fc = fecha($c['fechacarga']);
    $st->bind_param('issssissis', $c['idcontacto'], $ap, $no, $c['telefono1'], $c['correo'], $c['condicioniva'], $c['cuit'], $fc, $c['tipo'], $notas);
    $st->execute();
}

echo "\nPedidos nuevos: ", count($pedidos), "\n";
$idsNuevos = [];
foreach ($pedidos as $p) {
    $idsNuevos[(int)$p['idpedido']] = true;
    $esContactoNuevo = false;
    foreach ($contactos as $c) if ($c['idcontacto'] == $p['idcontacto']) $esContactoNuevo = true;
    [$idContacto, $aviso] = $esContactoNuevo ? [(int)$p['idcontacto'], ''] : $resolverContacto($p['idcontacto']);
    printf("  %6d %s  %-10s $%12s  pagado $%12s  %s%s\n", $p['idpedido'], substr($p['entrada'], 0, 16), $p['usuario'],
        number_format($p['monto'], 0, ',', '.'), number_format($p['montopagado'], 0, ',', '.'),
        substr(preg_replace('/\s+/', ' ', txt($p['detalle'])), 0, 40), $aviso ? "  [$aviso]" : '');
    $u = $idUsuario($p['usuario']);
    if (!$aplicar) continue;
    $st = $conn->prepare("INSERT INTO pedidos (id, idContacto, idTipoPedido, idOrigen, idMedioPago, detalle, observaciones, entrada, salida,
                                               prometido, monto, montoPagado, estadoPago, estadoEntrega, estadoProduccion, idUsuario)
                          VALUES (?, ?, ?, " . ID_ORIGEN . ", " . ID_MEDIO_PAGO . ", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $det = txt($p['detalle']); $obs = txt($p['observaciones']); $sal = fecha($p['salida']); $prom = fecha($p['prometido']);
    $st->bind_param('iiisssssddiiii', $p['idpedido'], $idContacto, $p['tipo'], $det, $obs, $p['entrada'], $sal, $prom,
        $p['monto'], $p['montopagado'], $p['estadopago'], $p['estadoentrega'], $p['estadoproceso'], $u);
    $st->execute();
}

echo "\nPagos nuevos: ", count($pagos), "\n";
foreach ($pagos as $pg) {
    $idPedido = (int)$pg['idpedido'];
    $aviso = '';
    // pago real sobre un pedido que cerramos con un pago 'sinRegistro': se descuenta de ese pago
    $sr = isset($idsNuevos[$idPedido]) ? null
        : $conn->query("SELECT id, monto FROM pagos WHERE idPedido = $idPedido AND idMedioPago = $idSinRegistro ORDER BY id LIMIT 1")->fetch_assoc();
    if ($sr) $aviso = sprintf("  [pedido cerrado con 'sinRegistro' de $%s: se descuenta]", number_format($sr['monto'], 0, ',', '.'));
    elseif (!isset($idsNuevos[$idPedido]) && !$uno("SELECT COUNT(*) FROM pedidos WHERE id = $idPedido")) $aviso = '  [el pedido no existe en la base nueva]';
    printf("  %6d %s  pedido %6d  %-10s $%12s%s\n", $pg['id'], substr($pg['fecha'], 0, 16), $idPedido, $pg['usuario'],
        number_format($pg['monto'], 0, ',', '.'), $aviso);
    $u = $idUsuario($pg['usuario']);
    if (!$aplicar) continue;
    $st = $conn->prepare("INSERT INTO pagos (fecha, monto, idPedido, idUsuario, idMedioPago) VALUES (?, ?, ?, ?, " . ID_MEDIO_PAGO . ")");
    $st->bind_param('sdii', $pg['fecha'], $pg['monto'], $idPedido, $u);
    $st->execute();
    $conn->query("INSERT INTO pagosImportados (idViejo, idNuevo) VALUES ({$pg['id']}, {$st->insert_id})");
    if ($sr) {
        $resta = min((float)$sr['monto'], (float)$pg['monto']);
        if ($sr['monto'] - $resta <= 0.1) $conn->query("DELETE FROM pagos WHERE id = {$sr['id']}");
        else $conn->query("UPDATE pagos SET monto = monto - $resta WHERE id = {$sr['id']}");
        $conn->query("UPDATE pedidos SET montoPagado = montoPagado + " . ((float)$pg['monto'] - $resta) . " WHERE id = $idPedido");
    }
}

echo "\nPedidos que ya estaban y cambiaron en el sistema viejo: ", count($cambiados), "\n";
foreach ($cambiados as $i => $d) {
    if ($i < 60) printf("  %6d (%s)  monto %s -> %s   pagado %s -> %s   estados %s -> %s\n", $d['id'], $d['entrada'],
        number_format($d['montoN'], 0, ',', '.'), number_format($d['monto'], 0, ',', '.'),
        number_format($d['pagadoN'], 0, ',', '.'), number_format($d['montopagado'], 0, ',', '.'), $d['estadosN'], $d['estadosV']);
    if (!$aplicar) continue;
    $t = $viejo->query("SELECT detalle, observaciones FROM imprenta1.pedidos WHERE idpedido = {$d['id']}")->fetch_assoc();
    $st = $conn->prepare("UPDATE pedidos SET monto = ?, montoPagado = ?, estadoPago = ?, estadoEntrega = ?, estadoProduccion = ?,
                                 prometido = ?, salida = ?, detalle = ?, observaciones = ? WHERE id = ?");
    $det = txt($t['detalle']); $obs = txt($t['observaciones']); $sal = fecha($d['salida']); $prom = fecha($d['prometido']);
    $st->bind_param('ddiiissssi', $d['monto'], $d['montopagado'], $d['estadopago'], $d['estadoentrega'], $d['estadoproceso'],
        $prom, $sal, $det, $obs, $d['id']);
    $st->execute();
}
if (count($cambiados) > 60) echo "  ... y ", count($cambiados) - 60, " más\n";
echo "  (estados: pago, entrega, producción)\n";

if ($sinUsuario) echo "\nUsuarios del sistema viejo sin equivalente (quedan a nombre de " . USUARIO_SI_NO_HAY . "): ", implode(', ', array_keys($sinUsuario)), "\n";

if ($aplicar) {
    $conn->commit();
    echo "\nImportado. Ahora conviene correr los scripts de sql/limpieza/ (capitalizar, fusionar, empresas, cerrar pedidos).\n";
} else {
    echo "\nNo se modificó nada. Para aplicar, repetir el comando agregando --aplicar\n";
}
