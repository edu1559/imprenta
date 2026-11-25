<?php
// Cheques (físicos y echeqs), ver sql/2026-11-25_cheques.sql.
// - Un cheque ENTRA con un pago (medio "cheques") de una venta: se cargan sus datos, o se elige uno
//   que ya tenemos si el mismo cheque paga varios pedidos. Lo entrega el cliente del pedido.
// - SALE (estado "entregado"): con un pago de una COMPRA en cheques (se le da al proveedor de la
//   compra), desde la página Cheques entregándolo a un contacto, o depositándolo en un medio
//   (movimiento entre medios cheques → banco).
// - Si se RECHAZA, los pagos hechos con él se revierten con pagos negativos de hoy: el cliente
//   vuelve a deber (y en una compra, volvemos a deberle al proveedor). Si estaba depositado, se
//   revierte también el depósito.
// Desde los formularios los datos llegan en $_POST['cheque'].
// Usa registrarModificacion() de auditoria.php.

const ESTADOS_CHEQUE = ['tenemos' => 'En cartera', 'entregado' => 'Entregado', 'rechazado' => 'Rechazado'];
const TIPOS_CHEQUE   = ['fisico' => 'Físico', 'echeq' => 'Echeq'];
const TIPO_PEDIDO_COMPRA = 2;
const DIAS_VALIDEZ_CHEQUE = 30;   // se puede presentar hasta 30 días después de su fecha de pago

function idMedioCheques($conn) {
    static $id = null;
    if ($id === null) {
        $fila = mysqli_fetch_row(mysqli_query($conn, "SELECT id FROM mediosPago WHERE medio = 'cheques'"));
        $id = $fila ? (int)$fila[0] : 0;
    }
    return $id;
}

function esMedioCheques($conn, $idMedio) {
    return idMedioCheques($conn) > 0 && (int)$idMedio === idMedioCheques($conn);
}

function pedidoEsCompra($conn, $idPedido) {
    $fila = mysqli_fetch_row(mysqli_query($conn, "SELECT idTipoPedido FROM pedidos WHERE id = " . (int)$idPedido));
    return $fila && (int)$fila[0] === TIPO_PEDIDO_COMPRA;
}

// Lo que queda de un cheque sin asignar a pagos de ventas ($sinPago: no contar ese pago, al editarlo).
// Los pagos de compras no cuentan: son la salida del cheque, no su entrada.
function disponibleCheque($conn, $idCheque, $sinPago = 0) {
    $stmt = $conn->prepare("SELECT c.monto - COALESCE((SELECT SUM(pg.monto) FROM pagos pg
                                                        INNER JOIN pedidos pe ON pe.id = pg.idPedido
                                                        WHERE pg.idCheque = c.id AND pg.id <> ?
                                                          AND pe.idTipoPedido <> " . TIPO_PEDIDO_COMPRA . "), 0)
                            FROM cheques c WHERE c.id = ?");
    $stmt->bind_param('ii', $sinPago, $idCheque);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_row();
    return $fila ? round((float)$fila[0], 2) : 0.0;
}

// Cheques en cartera con lo que les queda sin asignar a ventas.
function chequesEnCartera($conn) {
    $res = mysqli_query($conn, "SELECT c.id, c.tipo, c.numero, c.banco, c.titular, c.fechaPago, c.monto,
                                       c.monto - COALESCE(SUM(CASE WHEN pe.idTipoPedido <> " . TIPO_PEDIDO_COMPRA . " THEN pg.monto END), 0) AS disponible
                                FROM cheques c
                                LEFT JOIN pagos pg ON pg.idCheque = c.id
                                LEFT JOIN pedidos pe ON pe.id = pg.idPedido
                                WHERE c.estado = 'tenemos'
                                GROUP BY c.id
                                ORDER BY c.fechaPago, c.id");
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

function describirCheque($c) {
    return (TIPOS_CHEQUE[$c['tipo']] ?? 'Cheque') . ' ' . $c['banco'] . ' N° ' . $c['numero']
         . (($c['titular'] ?? '') !== '' ? ' (' . $c['titular'] . ')' : '')
         . ', pago ' . date('d/m/Y', strtotime($c['fechaPago']));
}

function pesosCheque($n) {
    return '$' . number_format((float)$n, 2, ',', '.');
}

function nombreContacto($conn, $idContacto) {
    $fila = mysqli_fetch_row(mysqli_query($conn, "SELECT CONCAT_WS(' ', apellido, nombre) FROM contactos WHERE id = " . (int)$idContacto));
    return $fila ? trim($fila[0]) : null;
}

// Datos del cheque nuevo, normalizados y validados. Devuelve [datos, error].
function validarDatosCheque($ch) {
    $d = [
        'tipo'        => array_key_exists($ch['tipo'] ?? '', TIPOS_CHEQUE) ? $ch['tipo'] : '',
        'numero'      => trim($ch['numero'] ?? ''),
        'banco'       => trim($ch['banco'] ?? ''),
        'titular'     => trim($ch['titular'] ?? ''),
        'cuitTitular' => trim($ch['cuitTitular'] ?? ''),
        'monto'       => round((float)($ch['monto'] ?? 0), 2),
        'fechaPago'   => trim($ch['fechaPago'] ?? ''),
    ];
    if ($d['tipo'] === '')   return [null, 'Elegí si el cheque es físico o echeq.'];
    if ($d['numero'] === '') return [null, 'Falta el número del cheque.'];
    if ($d['banco'] === '')  return [null, 'Falta el banco del cheque.'];
    $fecha = DateTime::createFromFormat('Y-m-d', $d['fechaPago']);
    if (!$fecha || $fecha->format('Y-m-d') !== $d['fechaPago']) return [null, 'La fecha de pago del cheque no es válida.'];
    if ($d['monto'] <= 0) return [null, 'Falta el monto del cheque.'];
    foreach (['numero' => 30, 'banco' => 60, 'titular' => 100, 'cuitTitular' => 20] as $campo => $largo) {
        if (preg_match_all('/./su', $d[$campo]) > $largo) return [null, "El campo $campo del cheque es demasiado largo."];
    }
    return [$d, null];
}

// Id de otro cheque vigente con el mismo número y banco (o null).
function chequeRepetido($conn, $numero, $banco, $salvoId = 0) {
    $stmt = $conn->prepare("SELECT id FROM cheques WHERE numero = ? AND banco = ? AND estado <> 'rechazado' AND id <> ?");
    $stmt->bind_param('ssi', $numero, $banco, $salvoId);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_row();
    return $fila ? (int)$fila[0] : null;
}

// Valida el cheque que llega del formulario para un pago de $montoPago.
// Venta: cheque nuevo o uno que tenemos con monto disponible. Compra: uno que tenemos, que se
// entrega entero al proveedor (el pago tiene que ser por el monto del cheque).
// Devuelve ['error' => texto] o ['idCheque' => id, 'entregar' => bool] o ['nuevo' => datos].
function chequeDelFormulario($conn, $montoPago, $esCompra = false) {
    $ch = $_POST['cheque'] ?? null;
    if (!is_array($ch)) return ['error' => 'Faltan los datos del cheque.'];

    if ($esCompra && empty($ch['idCheque'])) {
        return ['error' => 'Para pagar una compra con cheque, elegí uno de los que tenemos en cartera.'];
    }
    if (!empty($ch['idCheque'])) {
        $c = cargarCheque($conn, (int)$ch['idCheque']);
        if (!$c) return ['error' => 'No se encontró el cheque elegido.'];
        if ($c['estado'] !== 'tenemos') return ['error' => 'Ese cheque ya no está en cartera.'];
        if ($esCompra) {
            if (abs($montoPago - (float)$c['monto']) > 0.009) {
                return ['error' => 'El cheque se entrega entero: el pago de la compra tiene que ser por ' . pesosCheque($c['monto']) . '.'];
            }
            return ['idCheque' => (int)$c['id'], 'entregar' => true];
        }
        $disponible = disponibleCheque($conn, (int)$c['id']);
        if ($montoPago > $disponible + 0.009) {
            return ['error' => 'Al cheque elegido le quedan ' . pesosCheque($disponible) . ' sin asignar.'];
        }
        return ['idCheque' => (int)$c['id'], 'entregar' => false];
    }

    [$d, $error] = validarDatosCheque($ch);
    if ($error) return ['error' => $error];
    if ($montoPago > $d['monto'] + 0.009) return ['error' => 'El pago no puede ser mayor que el monto del cheque.'];
    if ($id = chequeRepetido($conn, $d['numero'], $d['banco'])) {
        return ['error' => "Ese cheque ya está cargado (cheque #$id). Si paga otro pedido, elegilo en \"Un cheque que ya tenemos\"."];
    }
    return ['nuevo' => $d];
}

// Graba lo validado por chequeDelFormulario para el pago $idPago (del pedido $idPedido, del
// contacto $idContacto): inserta el cheque nuevo, o entrega el existente si es una compra.
function guardarChequeDelPago($conn, $validado, $idPago, $idPedido, $idContacto, $idUsuario) {
    if (isset($validado['nuevo'])) {
        $d = $validado['nuevo'];
        $idEntrega = $idContacto ? (int)$idContacto : null;
        $stmt = $conn->prepare("INSERT INTO cheques (tipo, numero, banco, titular, cuitTitular, monto, fechaPago,
                                                     idContactoEntrega, fechaCarga, idUsuario)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param('sssssdsii', $d['tipo'], $d['numero'], $d['banco'], $d['titular'], $d['cuitTitular'],
                          $d['monto'], $d['fechaPago'], $idEntrega, $idUsuario);
        $stmt->execute();
        $idCheque = (int)$conn->insert_id;
    } else {
        $idCheque = (int)$validado['idCheque'];
        if ($validado['entregar']) {
            $stmt = $conn->prepare("UPDATE cheques SET estado = 'entregado', fechaEstado = NOW(), idContactoDestino = ?
                                    WHERE id = ? AND estado = 'tenemos'");
            $stmt->bind_param('ii', $idContacto, $idCheque);
            $stmt->execute();
            registrarModificacion($conn, 'cheque', $idCheque, $idPedido, 'entregar', null,
                                  'a ' . (nombreContacto($conn, $idContacto) ?? "contacto $idContacto") . " (pago de la compra #$idPedido)",
                                  'Pago de una compra');
        }
    }
    $stmt = $conn->prepare("UPDATE pagos SET idCheque = ? WHERE id = ?");
    $stmt->bind_param('ii', $idCheque, $idPago);
    $stmt->execute();
}

function cargarCheque($conn, $idCheque) {
    $stmt = $conn->prepare("SELECT * FROM cheques WHERE id = ?");
    $stmt->bind_param('i', $idCheque);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Pagos hechos con el cheque, con su pedido y contacto.
function pagosDelCheque($conn, $idCheque) {
    $res = mysqli_query($conn, "SELECT pg.id, pg.fecha, pg.monto, pg.idPedido, pe.idTipoPedido, pe.detalle,
                                       CONCAT_WS(' ', c.apellido, c.nombre) AS contacto
                                FROM pagos pg
                                INNER JOIN pedidos pe ON pe.id = pg.idPedido
                                LEFT JOIN contactos c ON c.id = pe.idContacto
                                WHERE pg.idCheque = " . (int)$idCheque . "
                                ORDER BY pg.fecha, pg.id");
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

// Antes de borrar un pago con cheque: motivo por el que no se puede (o null).
function bloqueoBorrarPagoCheque($conn, $pago) {
    $c = cargarCheque($conn, (int)$pago['idCheque']);
    if (!$c) return null;
    if ((float)$pago['monto'] < 0) return 'Es la reversión de un cheque rechazado: no se puede borrar.';
    if (pedidoEsCompra($conn, $pago['idPedido'])) {
        return $c['estado'] === 'entregado' ? null : "El cheque de este pago está {$c['estado']}: no se puede borrar el pago.";
    }
    return $c['estado'] === 'tenemos' ? null : "El cheque de este pago ya no está en cartera ({$c['estado']}): no se puede borrar el pago.";
}

// Después de borrar un pago con cheque. Compra: el cheque vuelve a cartera. Venta: si el cheque
// ya no paga nada, era un error de carga y se borra. Devuelve el texto para el registro, o null.
function despuesDeBorrarPagoCheque($conn, $pago, $motivo) {
    $c = cargarCheque($conn, (int)$pago['idCheque']);
    if (!$c) return null;
    if (pedidoEsCompra($conn, $pago['idPedido'])) {
        mysqli_query($conn, "UPDATE cheques SET estado = 'tenemos', fechaEstado = NOW(), idContactoDestino = NULL WHERE id = " . (int)$c['id']);
        registrarModificacion($conn, 'cheque', $c['id'], $pago['idPedido'], 'volverCartera', 'entregado',
                              'en cartera (se borró el pago de la compra)', $motivo);
        return 'el cheque #' . $c['id'] . ' volvió a cartera';
    }
    $quedan = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM pagos WHERE idCheque = " . (int)$c['id']))[0];
    if ($quedan > 0) return null;
    mysqli_query($conn, "DELETE FROM cheques WHERE id = " . (int)$c['id']);
    return 'se borró el cheque: ' . describirCheque($c) . ' por ' . pesosCheque($c['monto']);
}
