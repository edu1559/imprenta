<?php
include_once(__DIR__ . '/../sesion.php'); exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
$conn = conectar();
$puedeModificar = puedeModificar($conn);

$idMedio = $_GET['idMedio'] ?? die("Falta ID");

// Traemos todos los medios para el <select> de cambio rápido
$resMedios = mysqli_query($conn, "SELECT id, medio FROM mediosPago WHERE visible=1");
$listaMedios = mysqli_fetch_all($resMedios, MYSQLI_ASSOC);

// Consulta mejorada: agregamos idTipoPedido
$sql = "SELECT 
            p.id as idPago,
            p.idPedido,
            p.monto,
            date_format(p.fecha, '%d-%H:%i hs') as hora,
            c.apellido,
            pe.detalle,
            pe.idTipoPedido, -- Agregado para lógica de colores y resta
            pe.estadoProduccion, pe.estadoEntrega, pe.estadoPago,
            u.usuario
        FROM pagos p
        INNER JOIN pedidos pe ON p.idPedido = pe.id
        INNER JOIN contactos c ON pe.idContacto = c.id
        INNER JOIN usuarios u ON p.idUsuario = u.id
        INNER JOIN cierreMedios cm ON p.idMedioPago = cm.idMedio
        WHERE p.idMedioPago = $idMedio 
          AND p.fecha >= cm.fechaUC
        ORDER BY p.monto DESC, p.fecha DESC";

$result = mysqli_query($conn, $sql);

// Movimientos entre medios de la caja abierta de este medio (retiros, depósitos, dólares)
$stmt = $conn->prepare("SELECT m.*, date_format(m.fecha, '%d-%H:%i hs') AS hora,
                               mo.medio AS origen, md.medio AS destino, u.usuario
                        FROM movimientosMedios m
                        INNER JOIN cierreMedios cm ON cm.idMedio = ?
                        LEFT JOIN cierreMedios mo ON mo.idMedio = m.idMedioOrigen
                        LEFT JOIN cierreMedios md ON md.idMedio = m.idMedioDestino
                        LEFT JOIN usuarios u ON u.id = m.idUsuario
                        WHERE (m.idMedioOrigen = cm.idMedio OR m.idMedioDestino = cm.idMedio)
                          AND m.fecha >= cm.fechaUC
                        ORDER BY m.fecha DESC");
$idMedioInt = (int)$idMedio;
$stmt->bind_param('i', $idMedioInt);
$stmt->execute();
$movimientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle shadow-sm" style="font-size: 0.85rem;">
        <thead class="table-warning text-dark">
            <tr>
                <th>Día-hs</th>
                <th>Pedido</th>
                <th>Cliente / Detalle</th>
                <th class="text-end">Monto</th>
                <th>Mover a...</th>
                <th class="text-end">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalDetalle = 0;
            if(mysqli_num_rows($result) > 0 || $movimientos):
                while($f = mysqli_fetch_assoc($result)): 
                    
                    // Lógica Contable: Si es Egreso (2), restamos. Si es Venta (1), sumamos.
                    if($f['idTipoPedido'] == 2) {
                        $totalDetalle -= $f['monto'];
                        $claseFila = 'border-start border-danger border-4 bg-danger bg-opacity-10'; // Rojo para gastos
                        $simbolo = '-';
                        $colorMonto = 'text-danger';
                    } else {
                        $totalDetalle += $f['monto'];
                        $claseFila = 'border-start border-success border-4'; // Verde para ventas
                        $simbolo = '+';
                        $colorMonto = 'text-success';
                    }

                    // Acá todos los pagos son de caja abierta (posteriores al
                    // último cierre); solo falta ver si el pedido está cerrado.
                    if (!$puedeModificar) {
                        $bloqueo = 'No tenés permiso para modificar pagos.';
                    } elseif (pedidoCerrado($f)) {
                        $bloqueo = 'El pedido está cerrado. Usá un pago de ajuste desde Pagos del pedido.';
                    } else {
                        $bloqueo = null;
                    }
            ?>
            <tr class="<?php echo $claseFila; ?>">
                <td class="text-muted small"><?php echo $f['hora']; ?></td>
                <td>
                    <span class="fw-bold">#<?php echo $f['idPedido']; ?></span>
                    <span class="idPago d-none"><?php echo $f['idPago']; ?></span>
                </td>
                <td>
                    <div class="fw-bold text-truncate" style="max-width: 140px;"><?php echo $f['apellido']; ?></div>
                    <div class="text-muted small text-truncate" style="max-width: 140px;"><?php echo $f['detalle']; ?></div>
                </td>
                <td class="text-end fw-bold <?php echo $colorMonto; ?>">
                    <?php echo $simbolo; ?>$<?php echo number_format($f['monto'], 0, ',', '.'); ?>
                </td>
                <td>
                    <select class="form-select form-select-sm selMedioRapido" style="font-size: 0.7rem; padding: 0.1rem 0.3rem;" data-original="<?php echo $idMedio; ?>" <?php echo $bloqueo ? 'disabled title="' . htmlspecialchars($bloqueo) . '"' : ''; ?>>
                        <?php foreach($listaMedios as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo ($m['id'] == $idMedio) ? 'selected' : ''; ?>>
                                <?php echo $m['medio']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="text-end">
                    <?php if ($bloqueo): ?>
                    <i class="bi bi-lock text-muted" title="<?php echo htmlspecialchars($bloqueo); ?>"></i>
                    <?php else: ?>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary btn-sm btnEditarPagoDetalle" data-id="<?php echo $f['idPago']; ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger btn-sm btnBorrarPagoDetalle" data-id="<?php echo $f['idPago']; ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php foreach ($movimientos as $mv):
                $entra = (int)$mv['idMedioDestino'] === $idMedioInt;
                $totalDetalle += $entra ? $mv['monto'] : -$mv['monto'];
                $dolares = $mv['usd'] !== null ? 'US$ ' . number_format($mv['usd'], 2, ',', '.') . ($mv['cotizacion'] !== null ? ' a $' . number_format($mv['cotizacion'], 2, ',', '.') : '') : '';
            ?>
            <tr class="border-start border-info border-4 bg-info bg-opacity-10">
                <td class="text-muted small"><?php echo $mv['hora']; ?></td>
                <td><i class="bi bi-arrow-left-right text-info" title="Movimiento entre medios #<?php echo $mv['id']; ?>"></i></td>
                <td>
                    <div class="fw-bold text-truncate" style="max-width: 140px;"><?php echo htmlspecialchars($mv['origen'] . ' → ' . $mv['destino']); ?></div>
                    <div class="text-muted small text-truncate" style="max-width: 140px;" title="<?php echo htmlspecialchars(trim($mv['nota'] . ' ' . $dolares) . ' · ' . $mv['usuario']); ?>">
                        <?php echo htmlspecialchars(trim($mv['nota'] . ' ' . $dolares) ?: $mv['usuario']); ?>
                    </div>
                </td>
                <td class="text-end fw-bold <?php echo $entra ? 'text-success' : 'text-danger'; ?>">
                    <?php echo $entra ? '+' : '-'; ?>$<?php echo number_format($mv['monto'], 0, ',', '.'); ?>
                </td>
                <td></td>
                <td class="text-end">
                    <?php if ($puedeModificar): ?>
                    <button class="btn btn-outline-danger btn-sm btnBorrarMovimiento" data-id="<?php echo $mv['id']; ?>">
                        <i class="bi bi-trash"></i>
                    </button>
                    <?php else: ?>
                    <i class="bi bi-lock text-muted" title="No tenés permiso para borrar movimientos."></i>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php else: ?>
            <tr>
                <td colspan="6" class="text-center py-4 text-muted">No hay movimientos nuevos.</td>
            </tr>
            <?php endif; ?>
        </tbody>
        <tfoot class="table-light">
            <tr class="fw-bold h6">
                <td colspan="3" class="text-end">BALANCE NETO:</td>
                <td class="text-end <?php echo ($totalDetalle < 0) ? 'text-danger' : 'text-primary'; ?>">
                    $<?php echo number_format($totalDetalle, 0, ',', '.'); ?>
                </td>
                <td></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<script>
$('.selMedioRapido').change(function() {
    let $sel = $(this);
    let idPago = $sel.closest('tr').find('.idPago').text();
    let fila = $sel.closest('tr');

    let motivo = pedirMotivo('Vas a mover el pago #' + idPago + ' a ' + $sel.find('option:selected').text().trim() + '.');
    if (motivo === null) {
        $sel.val($sel.data('original'));
        return;
    }

    $.post('finanzas/ajaxCierre.php', {
        opcion: 'cambiaMedio',
        idPago: idPago,
        idMedio: $sel.val(),
        motivo: motivo
    }, function(r) {
        if (r.trim() !== 'OK') {
            alert(r);
            $sel.val($sel.data('original'));
            return;
        }
        fila.fadeOut(300, function() {
            // Actualizamos el tablero general para que los saldos coincidan
            $('#contenido').load('finanzas/cierre.php');
        });
    });
});

// Pide el motivo (obligatorio). Devuelve null si se cancela.
function pedirMotivo(mensaje) {
    let motivo = prompt(mensaje + '\n\nMotivo (obligatorio):');
    if (motivo === null) return null;
    if (motivo.trim() === '') {
        alert('Tenés que indicar el motivo.');
        return null;
    }
    return motivo.trim();
}

// Editar un pago puntual desde el detalle del medio
$('.btnEditarPagoDetalle').click(function() {
    let idPago = $(this).data('id');

    if ($('#modalUniversal').length === 0) {
        console.error("No existe el div #modalUniversal en el index.");
        return;
    }

    $('#modalUniversal .modal-content').load('finanzas/modalEditarPago.php?idPago=' + idPago, function(response, status) {
        if (status !== "error") {
            $('#modalUniversal').modal('show');
        }
    });
});

// Borrar un movimiento entre medios
$('.btnBorrarMovimiento').click(function() {
    let motivo = pedirMotivo('Vas a ELIMINAR este movimiento entre medios.');
    if (motivo === null) return;
    $.post('finanzas/ajaxCierre.php', { opcion: 'borrarMovimiento', id: $(this).data('id'), motivo: motivo }, function(r) {
        if (r.trim() !== 'OK') alert(r);
        $('#contenido').load('finanzas/cierre.php');
    });
});

// Borrar un pago puntual desde el detalle del medio
$('.btnBorrarPagoDetalle').click(function() {
    let idPago = $(this).data('id');
    let motivo = pedirMotivo('Vas a ELIMINAR el pago #' + idPago + '.');
    if (motivo === null) return;
    $.post('finanzas/ajaxCierre.php', { opcion: 'borrarPago', id: idPago, motivo: motivo }, function(r) {
        alert(r);
        $('#contenido').load('finanzas/cierre.php');
    });
});
</script>