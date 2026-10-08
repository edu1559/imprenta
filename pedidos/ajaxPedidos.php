<?php
session_start();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
$conn = conectar();

// El panel de pedidos es solo para trabajadores: sin sesión no se graba nada.
exigirTrabajadorAjax();
$idUsuario = (int)$_SESSION['idUsuario'];

// Usamos $_POST en lugar de $_GET
if(isset($_POST['opcion'])){
    $opcion = $_POST['opcion'];
} else {
    // Si entran directo al archivo sin datos, detenemos todo
    die("No se recibieron datos");
}


// Largo máximo de los textos del pedido (columnas pedidos.detalle y pedidos.observaciones,
// ver sql/2026-10-06_pedidos_detalle_1000.sql). Los formularios ya los limitan.
const LARGO_DETALLE = 1000;
const LARGO_OBSERVACIONES = 255;

function errorLargoTextos() {
    $largo = fn($campo) => preg_match_all('/./su', (string)($_POST[$campo] ?? ''));
    if ($largo('detalle') > LARGO_DETALLE) return "El detalle no puede tener más de " . LARGO_DETALLE . " caracteres.";
    if ($largo('observaciones') > LARGO_OBSERVACIONES) return "Las observaciones no pueden tener más de " . LARGO_OBSERVACIONES . " caracteres.";
    return null;
}

switch ($opcion){

    case 'agregarPedido':   
        if ($error = errorLargoTextos()) {
            echo "❌ $error";
            break;
        }
        
        // 1. MEJORA DE SEGURIDAD:
        // Usamos mysqli_real_escape_string para evitar que comillas o símbolos rompan la base de datos
        $idContacto = mysqli_real_escape_string($conn, $_POST['idContacto']);
        $idTipoPedido = mysqli_real_escape_string($conn, $_POST['idTipoPedido']);
        $idOrigen = mysqli_real_escape_string($conn, $_POST['idOrigen']);
        $detalle = mysqli_real_escape_string($conn, $_POST['detalle']); // Ya no hace falta urldecode con POST usualmente
        $observaciones = mysqli_real_escape_string($conn, $_POST['observaciones']);
        $idEstadoEntrega = mysqli_real_escape_string($conn, $_POST['idEstadoEntrega']);
        $idEstadoProduccion = mysqli_real_escape_string($conn, $_POST['idEstadoProduccion']);
        $idEstadoPago = mysqli_real_escape_string($conn, $_POST['idEstadoPago']);
        $prometido = mysqli_real_escape_string($conn, $_POST['prometido']);
        $montoPagado = mysqli_real_escape_string($conn, $_POST['montoPagado']);
        $monto = mysqli_real_escape_string($conn, $_POST['monto']);
        $idMedioPago = mysqli_real_escape_string($conn, $_POST['idMedioPago']);

        // Corrección de montos vacíos
        if ($montoPagado == '' || $montoPagado == null ){
            $montoPagado = 0;
        }

        // LÓGICA DE PAGO: Si dice "Pagado" (3), forzamos que lo pagado sea igual al total.
        if($idEstadoPago == 3){
            $montoPagado = $monto;
        }
        if ((float)$montoPagado > (float)$monto + 0.1) {
            echo "❌ Lo pagado ($$montoPagado) no puede ser mayor que el monto del pedido ($$monto).";
            break;
        }
        // El estado de pago sale de lo pagado, no del formulario (como al editar).
        // Sin monto todavía (presupuesto a cotizar) se respeta lo elegido.
        if ((float)$monto > 0) {
            $idEstadoPago = calcularEstadoPago((float)$monto, (float)$montoPagado);
        }

        // Insertamos el PEDIDO
        $sql = "INSERT INTO pedidos (idContacto, idTipoPedido, idOrigen, detalle, observaciones, entrada, estadoEntrega, estadoProduccion, estadoPago, prometido, montoPagado, monto, idMedioPago, idUsuario)          
                VALUES ($idContacto, $idTipoPedido, $idOrigen, '$detalle', '$observaciones', NOW(), $idEstadoEntrega, $idEstadoProduccion, $idEstadoPago, '$prometido', '$montoPagado', '$monto', $idMedioPago, $idUsuario)";
        
        // Comenté los 'echo' de SQL para que no ensucien la pantalla del usuario, 
        // pero puedes descomentarlos si necesitas depurar.
        // echo $sql;     
        
        $result = mysqli_query($conn, $sql);
        
        $mensaje = ""; // Inicializamos la variable

        if ($result) {
            $mensaje .= "<div class='alert alert-success'>Se cargó el pedido correctamente.</div>";
            
            // 2. CORRECCIÓN DE TIPEO:
            // Antes decías $ulitimo_id_pedido (con una i extra)
            $ultimo_id_pedido = mysqli_insert_id($conn);
            marcarFechaTerminado($conn, $ultimo_id_pedido);
            
            // 3. LÓGICA DE PAGOS UNIFICADA:
            // Solo insertamos en la tabla pagos SI hay dinero de por medio.
            // Esto cubre tanto si puso un adelanto parcial O si pagó el total (estado 3).
            // (Tu código anterior insertaba dos veces si era estado 3).
            
            if($montoPagado > 0){
                $sqlPago = "INSERT INTO pagos (fecha, monto, idPedido, idUsuario, idMedioPago) 
                            VALUES (NOW(), '$montoPagado', $ultimo_id_pedido, $idUsuario, $idMedioPago)";
                
                $resultPago = mysqli_query($conn, $sqlPago);
                
                if ($resultPago) {
                    $mensaje .= "<br><small>Pago de $$montoPagado registrado.</small>";
                } else {
                    $mensaje .= "<br><small style='color:red'>Error al registrar el pago.</small>";
                }
            }

        } else {
            // Usamos mysqli_error para saber qué pasó si falla
            $mensaje = "<div class='alert alert-danger'>Error al agregar pedido: " . mysqli_error($conn) . "</div>";
        }
        
        echo $mensaje;
    
    break;


	   case 'actualizarPedido':
    if ($error = errorLargoTextos()) {
        echo "❌ $error";
        break;
    }
    // Recibimos por POST
    $idPedido           = $_POST['idPedido'];
    $idContacto         = $_POST['idContacto'];
    $idTipoPedido       = $_POST['idTipoPedido'];
    $detalle            = mysqli_real_escape_string($conn, $_POST['detalle']);
    $observaciones      = mysqli_real_escape_string($conn, $_POST['observaciones']);
    $prometido          = $_POST['prometido'];
    $idEstadoEntrega    = $_POST['idEstadoEntrega'];
    $idEstadoProduccion = $_POST['idEstadoProduccion'];
    $monto              = (float)$_POST['monto'];
    $idMedioPago        = $_POST['idMedioPago'];

    // El monto pagado ya no se edita acá: solo cambia con pagos (cargar,
    // borrar o ajustar). El estado de pago se deduce de lo pagado.
    $idPedidoInt = (int)$idPedido;
    $actual = cargarPedido($conn, $idPedidoInt);
    if (!$actual) {
        echo "❌ No se encontró el pedido.";
        break;
    }
    if ($error = bloqueoEdicionPedido($actual)) {
        echo "❌ " . $error;
        break;
    }
    $idEstadoPago = calcularEstadoPago($monto, (float)$actual['montoPagado']);

    // Actualización del pedido
    $sql = "UPDATE pedidos SET 
                idContacto = $idContacto,
                idTipoPedido = $idTipoPedido,
                detalle = '$detalle',
                observaciones = '$observaciones',
                prometido = '$prometido',    
                estadoEntrega = $idEstadoEntrega,
                estadoProduccion = $idEstadoProduccion,
                estadoPago = $idEstadoPago,
                monto = '$monto',
                idMedioPago = $idMedioPago
            WHERE id = $idPedido";

    $result = mysqli_query($conn, $sql);

    if ($result) {
        marcarFechaTerminado($conn, $idPedidoInt);
        if (abs((float)$actual['monto'] - $monto) > 0.01) {
            registrarModificacion($conn, 'pedido', $idPedidoInt, $idPedidoInt, 'modificarMonto', $actual['monto'], $monto, 'Editar pedido');
        }
        echo "✅ Pedido #$idPedido actualizado correctamente.";
    } else {
        echo "❌ Error al actualizar: " . mysqli_error($conn);
    }
    break;

case 'cerrarPedido':
    // Los montos salen de la base, no de la página: si el pedido llega dos veces
    // (doble clic, o dos pantallas), el segundo ya no tiene saldo y no carga otro pago.
    $id      = (int)$_POST['idPedido'];
    $idMedio = (int)$_POST['idMedio'];

    mysqli_begin_transaction($conn);
    // bloquea el pedido hasta terminar, así dos cierres simultáneos no leen el mismo saldo
    mysqli_query($conn, "SELECT id FROM pedidos WHERE id = $id FOR UPDATE");
    $actual = cargarPedido($conn, $id);
    if (!$actual || $actual['anulado']) {
        mysqli_rollback($conn);
        echo "❌ El pedido está anulado.";
        break;
    }
    $saldo = round($actual['monto'] - $actual['montoPagado'], 2);

    // "Ya estaba cobrado": el saldo se cobró en su momento pero no se registró. Va como
    // pago sinRegistro con la fecha del pedido, así no suma a la caja de hoy.
    if (!empty($_POST['sinRegistro'])) {
        $idSinRegistro = mysqli_fetch_row(mysqli_query($conn, "SELECT id FROM mediosPago WHERE medio = 'sinRegistro'"))[0] ?? null;
        if (!$idSinRegistro) {
            mysqli_rollback($conn);
            echo "❌ Falta el medio de pago 'sinRegistro'.";
            break;
        }
        if ($saldo > 0.1) {
            $stmt = $conn->prepare("INSERT INTO pagos (fecha, idPedido, monto, idMedioPago, idUsuario)
                                    SELECT entrada, id, ?, ?, ? FROM pedidos WHERE id = ?");
            $stmt->bind_param('diii', $saldo, $idSinRegistro, $idUsuario, $actual['id']);
            $stmt->execute();
        }
        registrarModificacion($conn, 'pedido', $actual['id'], $actual['id'], 'regularizar',
            json_encode(['estadoPago' => $actual['estadoPago'], 'estadoEntrega' => $actual['estadoEntrega'],
                         'estadoProduccion' => $actual['estadoProduccion'], 'montoPagado' => $actual['montoPagado']]),
            json_encode(['pagoSinRegistro' => max($saldo, 0)]), 'Cerrado desde Pedidos: ya estaba cobrado');
        $stmt = $conn->prepare("UPDATE pedidos SET estadoEntrega = 3, estadoProduccion = 3, estadoPago = 3,
                                    montoPagado = GREATEST(montoPagado, monto) WHERE id = ?");
        $stmt->bind_param('i', $actual['id']);
        $cerro = $stmt->execute();
        marcarFechaTerminado($conn, $actual['id']);
        mysqli_commit($conn);
        echo $cerro ? "✅ Pedido cerrado (el saldo quedó como pago sin registro)." : "❌ Error al cerrar pedido.";
        break;
    }

    // "Cobrado ahora": el saldo entra hoy con el medio elegido
    if ($saldo > 0.1) {
        $stmt = $conn->prepare("INSERT INTO pagos (fecha, idPedido, monto, idMedioPago, idUsuario) VALUES (NOW(), ?, ?, ?, ?)");
        $stmt->bind_param('idii', $id, $saldo, $idMedio, $idUsuario);
        echo $stmt->execute() ? "✅ Pago Cargado." : "❌ Error.";
    }

    // Al cerrar, asumimos que el pago se completa
    $stmt = $conn->prepare("UPDATE pedidos SET estadoEntrega = 3, estadoProduccion = 3, estadoPago = 3,
                                montoPagado = GREATEST(montoPagado, monto), idMedioPago = ? WHERE id = ?");
    $stmt->bind_param('ii', $idMedio, $id);
    if ($stmt->execute()) {
        marcarFechaTerminado($conn, $id);
        mysqli_commit($conn);
        echo "✅ Pedido cerrado y pagado.";
    } else {
        mysqli_rollback($conn);
        echo "❌ Error al cerrar pedido.";
    };
    break;


case 'modificarEstado':
        $idPedido    = $_POST['idPedido'];
        $tipo        = $_POST['tipo']; // 'entrega' o 'Produccion'
        $nuevoEstado = $_POST['nuevoEstado'];

        $actual = cargarPedido($conn, (int)$idPedido);
        if (!$actual) {
            echo "No se encontró el pedido.";
            break;
        }
        if ($error = bloqueoEdicionPedido($actual)) {
            echo $error;
            break;
        }

    // Mapeamos el nombre del botón con el nombre real de la columna en la DB
    // Si en el JS dice 'entrega', la columna es 'estadoEntrega'
    // Si en el JS dice 'Produccion', la columna es 'estadoProduccion'
    $columna = ($tipo == 'entrega') ? 'estadoEntrega' : 'estadoProduccion';

    $sql = "UPDATE pedidos SET $columna = $nuevoEstado WHERE id = $idPedido";
    
    if(mysqli_query($conn, $sql)) {
        marcarFechaTerminado($conn, (int)$idPedido);
        echo "Estado actualizado";
    } else {
        echo "Error: " . mysqli_error($conn);
    }

    break;

// -------------------------------------------------------------
// Anular: solo pedidos en proceso y sin pagos (si tiene pagos, primero
// se borran, y eso queda registrado). El pedido queda en la base pero
// deja de aparecer en listados y Finanzas.
// -------------------------------------------------------------
case 'anularPedido':
    $idPedido = (int)$_POST['idPedido'];
    $motivo   = trim($_POST['motivo'] ?? '');

    if (!puedeModificar($conn)) {
        echo "❌ No tenés permiso para anular pedidos. Tiene que ser un administrador o un usuario habilitado, logueado.";
        break;
    }
    if ($motivo === '') {
        echo "❌ Falta el motivo.";
        break;
    }
    $pedido = cargarPedido($conn, $idPedido);
    if (!$pedido) {
        echo "❌ No se encontró el pedido.";
        break;
    }
    if ($pedido['anulado']) {
        echo "❌ El pedido ya está anulado.";
        break;
    }
    if (pedidoCerrado($pedido)) {
        echo "❌ El pedido está cerrado y no se puede anular.";
        break;
    }
    if ($pedido['cantPagos'] > 0) {
        echo "❌ El pedido tiene pagos. Primero borralos (desde Pagos del pedido) y después anulalo.";
        break;
    }

    mysqli_begin_transaction($conn);
    try {
        $stmt = $conn->prepare("UPDATE pedidos SET anulado = 1 WHERE id = ?");
        $stmt->bind_param('i', $idPedido);
        $stmt->execute();
        registrarModificacion($conn, 'pedido', $idPedido, $idPedido, 'anular', '$' . $pedido['monto'], null, $motivo);
        mysqli_commit($conn);
        echo "✅ Pedido #$idPedido anulado.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "❌ Error al anular: " . $e->getMessage();
    }
    break;

// -------------------------------------------------------------
// Reabrir un pedido cerrado: solo administradores, con motivo. La entrega
// vuelve a "Pendiente", así deja de estar cerrado y le vuelven a aplicar
// las reglas de un pedido en proceso.
// -------------------------------------------------------------
case 'reabrirPedido':
    $idPedido = (int)$_POST['idPedido'];
    $motivo   = trim($_POST['motivo'] ?? '');

    if (!esAdministrador($conn)) {
        echo "❌ Solo un administrador logueado puede reabrir un pedido.";
        break;
    }
    if ($motivo === '') {
        echo "❌ Falta el motivo.";
        break;
    }
    $pedido = cargarPedido($conn, $idPedido);
    if (!$pedido || $pedido['anulado']) {
        echo "❌ No se encontró el pedido.";
        break;
    }
    if (!pedidoCerrado($pedido)) {
        echo "❌ El pedido no está cerrado.";
        break;
    }

    mysqli_begin_transaction($conn);
    try {
        $stmt = $conn->prepare("UPDATE pedidos SET estadoEntrega = 1 WHERE id = ?");
        $stmt->bind_param('i', $idPedido);
        $stmt->execute();
        $reabierto = ['estadoEntrega' => 1] + $pedido;
        registrarModificacion($conn, 'pedido', $idPedido, $idPedido, 'reabrir',
                              describirEstados($pedido), describirEstados($reabierto), $motivo);
        mysqli_commit($conn);
        echo "✅ Pedido #$idPedido reabierto.";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "❌ Error al reabrir: " . $e->getMessage();
    }
    break;
};

// Motivo por el que un pedido no se puede editar (null si se puede).
function bloqueoEdicionPedido($pedido) {
    if ($pedido['anulado']) return "El pedido está anulado.";
    if (pedidoCerrado($pedido)) return "El pedido está cerrado. Para modificarlo, un administrador tiene que reabrirlo.";
    return null;
}



	   
       
    
    

