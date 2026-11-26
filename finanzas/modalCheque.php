<?php
// Ficha de un cheque (se abre desde Finanzas → Cheques): datos, pagos hechos con él y acciones.
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../auditoria.php');
include_once(__DIR__ . '/../cheques.php');
$conn = conectar();

$c = cargarCheque($conn, (int)($_GET['idCheque'] ?? 0));
if (!$c) die('<div class="modal-body">No se encontró el cheque.</div>');
$id = (int)$c['id'];
$puedeModificar = puedeModificar($conn);
$pagos = pagosDelCheque($conn, $id);
$disponible = disponibleCheque($conn, $id);
$entrego = $c['idContactoEntrega'] ? nombreContacto($conn, $c['idContactoEntrega']) : null;
$destino = $c['idContactoDestino'] ? nombreContacto($conn, $c['idContactoDestino']) : null;
$medioDestino = $c['idMedioDestino'] ? mysqli_fetch_row(mysqli_query($conn, "SELECT medio FROM mediosPago WHERE id = " . (int)$c['idMedioDestino']))[0] : null;
$porCompra = (bool)array_filter($pagos, fn($p) => (int)$p['idTipoPedido'] === TIPO_PEDIDO_COMPRA && $p['monto'] > 0);

// medios donde se puede depositar o canjear (los de caja, menos cheques)
$medios = mediosParaCheque($conn);
$movEntrada = $c['idMovimientoEntrada'] ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM movimientosMedios WHERE id = " . (int)$c['idMovimientoEntrada'])) : null;
$entrada = ORIGENES_CHEQUE[$c['origen']] . ($movEntrada ? ': le dimos ' . pesosCheque($movEntrada['monto']) . ' en ' . ($medios[(int)$movEntrada['idMedioOrigen']] ?? '?') : '');

// historia del cheque (registro de cambios)
$historia = mysqli_fetch_all(mysqli_query($conn, "SELECT m.*, u.usuario FROM modificaciones m LEFT JOIN usuarios u ON u.id = m.idUsuario
                                                  WHERE m.entidad = 'cheque' AND m.idEntidad = $id ORDER BY m.fecha, m.id"), MYSQLI_ASSOC);

$h = fn($t) => htmlspecialchars((string)$t);
$colorEstado = ['tenemos' => 'bg-primary', 'entregado' => 'bg-success', 'rechazado' => 'bg-danger'][$c['estado']];
?>
<div class="modal-header bg-dark text-white">
    <h5 class="modal-title">
        Cheque #<?php echo $id; ?>: <?php echo $h(TIPOS_CHEQUE[$c['tipo']] . ' ' . $c['banco'] . ' N° ' . $c['numero']); ?>
        <span class="badge <?php echo $colorEstado; ?> ms-2"><?php echo ESTADOS_CHEQUE[$c['estado']]; ?></span>
    </h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body bg-light">
    <div class="row g-2 mb-3 small">
        <div class="col-md-3"><div class="text-muted">Monto</div><div class="h5 fw-bold mb-0"><?php echo pesosCheque($c['monto']); ?></div>
            <?php if ($c['estado'] === 'tenemos' && $disponible > 0.009): ?><div class="text-danger">sin asignar a pedidos: <?php echo pesosCheque($disponible); ?></div><?php endif; ?>
        </div>
        <div class="col-md-3"><div class="text-muted">Fecha de pago</div><div class="fw-bold"><?php echo date('d/m/Y', strtotime($c['fechaPago'])); ?></div>
            <div class="text-muted">se presenta hasta el <?php echo date('d/m/Y', strtotime($c['fechaPago'] . ' +' . DIAS_VALIDEZ_CHEQUE . ' days')); ?></div>
        </div>
        <div class="col-md-3"><div class="text-muted">Titular</div><div><?php echo $h($c['titular'] ?: '—'); ?></div>
            <?php if ($c['cuitTitular'] !== ''): ?><div class="text-muted">CUIT <?php echo $h($c['cuitTitular']); ?></div><?php endif; ?>
        </div>
        <div class="col-md-3"><div class="text-muted">Lo entregó</div><div><?php echo $h($entrego ?? '—'); ?></div>
            <div class="text-muted">cargado el <?php echo date('d/m/Y H:i', strtotime($c['fechaCarga'])); ?></div>
            <div class="text-muted">entró por: <?php echo $h($entrada); ?></div>
        </div>
        <?php if ($c['estado'] !== 'tenemos'): ?>
        <div class="col-12">
            <div class="alert <?php echo $c['estado'] === 'rechazado' ? 'alert-danger' : 'alert-success'; ?> py-1 mb-0">
                <?php if ($c['estado'] === 'rechazado'): ?>Rechazado<?php else: [$salida, $detalle] = describirSalida($c, $destino, $medioDestino); ?><?php echo $salida; ?> <strong><?php echo $h($detalle); ?></strong><?php endif; ?>
                <?php if ($c['fechaEstado']): ?> el <?php echo date('d/m/Y H:i', strtotime($c['fechaEstado'])); ?><?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($c['nota'] !== ''): ?><div class="col-12"><span class="text-muted">Nota:</span> <?php echo $h($c['nota']); ?></div><?php endif; ?>
    </div>

    <?php if ($pagos || $c['origen'] === 'pago'): ?>
    <h6 class="border-bottom pb-1">Pagos hechos con este cheque</h6>
    <table class="table table-sm small mb-3">
        <tbody>
        <?php if (!$pagos): ?><tr><td class="text-muted">Ninguno.</td></tr><?php endif; ?>
        <?php foreach ($pagos as $p): $compra = (int)$p['idTipoPedido'] === TIPO_PEDIDO_COMPRA; ?>
            <tr class="<?php echo $p['monto'] < 0 ? 'text-danger' : ''; ?>">
                <td><?php echo date('d/m/Y H:i', strtotime($p['fecha'])); ?></td>
                <td>Pedido #<?php echo $p['idPedido']; ?> <span class="badge <?php echo $compra ? 'bg-danger' : 'bg-success'; ?>"><?php echo $compra ? 'compra' : 'venta'; ?></span></td>
                <td><?php echo $h($p['contacto']); ?> <span class="text-muted"><?php echo $h(textoCorto($p['detalle'], 60)); ?></span></td>
                <td class="text-end fw-bold"><?php echo pesosCheque($p['monto']); ?><?php echo $p['monto'] < 0 ? ' (rechazo)' : ''; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($historia): ?>
    <h6 class="border-bottom pb-1">Historia</h6>
    <table class="table table-sm small mb-3">
        <tbody>
        <?php foreach ($historia as $m): $dm = describirModificacion($m, $medios); ?>
            <tr>
                <td class="text-nowrap"><?php echo date('d/m/Y H:i', strtotime($m['fecha'])); ?></td>
                <td><i class="bi <?php echo $dm['icono']; ?>"></i> <?php echo $h($dm['texto']); ?>
                    <?php if ($dm['motivo']): ?><div class="text-muted"><?php echo $h($dm['motivo']); ?></div><?php endif; ?></td>
                <td class="text-muted"><?php echo $h($m['usuario'] ?? ''); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($c['estado'] === 'tenemos'): ?>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="card border-success h-100"><div class="card-body p-2">
                <div class="fw-bold small mb-1"><i class="bi bi-arrow-left-right"></i> Canjear</div>
                <div class="small text-muted mb-1">Se lo damos a alguien que nos da <?php echo pesosCheque($c['monto']); ?> en otro medio. Para pagar una compra cargada, cargá el pago de la compra con medio cheques.</div>
                <select id="chqContacto" class="form-select form-select-sm mb-1" style="width:100%"></select>
                <div class="input-group input-group-sm mb-1">
                    <span class="input-group-text">Nos da la plata en</span>
                    <select id="chqMedioCanje" class="form-select form-select-sm">
                        <?php foreach ($medios as $mid => $m): ?><option value="<?php echo $mid; ?>"><?php echo $h($m); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <input type="text" id="chqNotaEntregar" class="form-control form-control-sm mb-1" maxlength="255" placeholder="Nota (opcional)">
                <button class="btn btn-success btn-sm w-100" id="btnChqEntregar">Canjear</button>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card border-primary h-100"><div class="card-body p-2">
                <div class="fw-bold small mb-1"><i class="bi bi-bank"></i> Depositar</div>
                <div class="small text-muted mb-1">Pasa <?php echo pesosCheque($c['monto']); ?> de cheques al medio elegido (queda como movimiento entre medios).</div>
                <select id="chqMedio" class="form-select form-select-sm mb-1">
                    <?php foreach ($medios as $mid => $m): ?><option value="<?php echo $mid; ?>"><?php echo $h($m); ?></option><?php endforeach; ?>
                </select>
                <input type="text" id="chqNotaDepositar" class="form-control form-control-sm mb-1" maxlength="255" placeholder="Nota (opcional)">
                <button class="btn btn-primary btn-sm w-100" id="btnChqDepositar">Depositar</button>
            </div></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($puedeModificar): ?>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <?php if ($c['estado'] !== 'rechazado'): ?>
            <button class="btn btn-outline-danger btn-sm" id="btnChqRechazar"><i class="bi bi-x-octagon"></i> Rechazado</button>
            <?php if ($c['origen'] !== 'pago'): ?>
            <div class="input-group input-group-sm" style="width:auto" title="Si el cheque rebota, quien nos lo dio nos devuelve la plata">
                <span class="input-group-text">al rechazar, nos devolvieron la plata en</span>
                <select id="chqMedioDevolucion" class="form-select form-select-sm">
                    <option value="">todavía no</option>
                    <?php foreach ($medios as $mid => $m): ?><option value="<?php echo $mid; ?>"><?php echo $h($m); ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($c['estado'] === 'entregado' && !$porCompra): ?>
            <button class="btn btn-outline-warning btn-sm" id="btnChqVolver"><i class="bi bi-arrow-counterclockwise"></i> Volver a cartera</button>
        <?php endif; ?>
        <?php if ($c['estado'] === 'tenemos' && $c['origen'] !== 'pago' && !$pagos): ?>
            <button class="btn btn-outline-danger btn-sm" id="btnChqBorrar"><i class="bi bi-trash"></i> Borrar (cargado por error)</button>
        <?php endif; ?>
        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#panelChqEditar"><i class="bi bi-pencil"></i> Corregir datos</button>
    </div>
    <?php if ($c['estado'] === 'entregado' && $porCompra): ?>
        <div class="small text-muted mt-1">Se entregó con el pago de una compra: para deshacerlo, borrá ese pago desde la compra y el cheque vuelve a cartera.</div>
    <?php endif; ?>

    <div class="collapse mt-2" id="panelChqEditar">
        <div class="card card-body p-2">
            <div class="row g-2 small">
                <div class="col-md-2"><label>Tipo</label>
                    <select class="form-select form-select-sm" id="edChTipo">
                        <?php foreach (TIPOS_CHEQUE as $v => $t): ?><option value="<?php echo $v; ?>" <?php echo $c['tipo'] === $v ? 'selected' : ''; ?>><?php echo $t; ?></option><?php endforeach; ?>
                    </select></div>
                <div class="col-md-3"><label>Número</label><input class="form-control form-control-sm" id="edChNumero" maxlength="30" value="<?php echo $h($c['numero']); ?>"></div>
                <div class="col-md-3"><label>Banco</label><input class="form-control form-control-sm" id="edChBanco" maxlength="60" value="<?php echo $h($c['banco']); ?>"></div>
                <div class="col-md-4"><label>Fecha de pago</label><input type="date" class="form-control form-control-sm" id="edChFechaPago" value="<?php echo $c['fechaPago']; ?>"></div>
                <div class="col-md-5"><label>Titular</label><input class="form-control form-control-sm" id="edChTitular" maxlength="100" value="<?php echo $h($c['titular']); ?>"></div>
                <div class="col-md-3"><label>CUIT titular</label><input class="form-control form-control-sm" id="edChCuit" maxlength="20" value="<?php echo $h($c['cuitTitular']); ?>"></div>
                <div class="col-md-4"><label>Nota</label><input class="form-control form-control-sm" id="edChNota" maxlength="255" value="<?php echo $h($c['nota']); ?>"></div>
                <div class="col-12 text-muted">El monto no se corrige acá: está atado a los pagos o al canje. Si está mal, borrá el pago (o el cheque, si fue canje o carga inicial) y cargalo de nuevo.</div>
                <div class="col-12 text-end"><button class="btn btn-secondary btn-sm" id="btnChqEditar">Guardar corrección</button></div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
(function() {
    const ID = <?php echo $id; ?>;
    const SIN_ASIGNAR = <?php echo $c['estado'] === 'tenemos' ? $disponible : 0; ?>;

    function enviar(datos, $btn) {
        $btn.prop('disabled', true);
        $.post('finanzas/ajaxCheques.php', $.extend({ idCheque: ID }, datos), function(r) {
            alert($('<div>').html(r).text().trim());
            $btn.prop('disabled', false);
            if (r.trim().indexOf('✅') === 0) {
                $('#modalUniversal').modal('hide');
                if (window.recargarCheques) window.recargarCheques();
            }
        }).fail(function() {
            alert('Error del servidor: no se guardó.');
            $btn.prop('disabled', false);
        });
    }
    function pedirMotivo(mensaje) {
        let m = prompt(mensaje + '\n\nMotivo (obligatorio):');
        if (m === null) return null;
        if (m.trim() === '') { alert('Tenés que indicar el motivo.'); return null; }
        return m.trim();
    }
    // si parte del cheque no está asignada a ningún pedido, esa plata no figura como cobrada
    function confirmarSinAsignar() {
        if (SIN_ASIGNAR < 0.01) return true;
        return confirm('Este cheque tiene $' + SIN_ASIGNAR.toLocaleString('es-AR') + ' sin asignar a ningún pedido: esa parte no figura como cobrada y se va a ver como diferencia en la caja.\n\n¿Seguir igual?');
    }

    if ($('#chqContacto').length) {
        $('#chqContacto').select2({
            dropdownParent: $('#modalUniversal'),
            placeholder: 'Buscar contacto (3 letras)...',
            minimumInputLength: 3,
            ajax: {
                url: 'contactos/buscarContactos.php', dataType: 'json', delay: 250,
                data: function(params) { return { q: params.term }; },
                processResults: function(data) { return { results: data.filter(function(c) { return c.id !== 'NEW'; }) }; }
            }
        });
    }

    $('#btnChqEntregar').click(function() {
        let idContacto = $('#chqContacto').val();
        if (!idContacto) { alert('Elegí a quién se le entrega el cheque.'); return; }
        if (!confirmarSinAsignar()) return;
        if (!confirm('¿Canjear el cheque con ' + $('#chqContacto option:selected').text() + '?\n\nEntran <?php echo pesosCheque($c['monto']); ?> en ' +
                     $('#chqMedioCanje option:selected').text() + ' (movimiento entre medios).')) return;
        enviar({ opcion: 'canjear', idContacto: idContacto, idMedio: $('#chqMedioCanje').val(), nota: $('#chqNotaEntregar').val() }, $(this));
    });

    $('#btnChqDepositar').click(function() {
        if (!confirmarSinAsignar()) return;
        if (!confirm('¿Depositar el cheque en ' + $('#chqMedio option:selected').text() + '?')) return;
        enviar({ opcion: 'depositar', idMedio: $('#chqMedio').val(), nota: $('#chqNotaDepositar').val() }, $(this));
    });

    $('#btnChqRechazar').click(function() {
        let devolucion = $('#chqMedioDevolucion').val() || '';
        let texto = <?php echo $c['origen'] === 'pago'
            ? "'Los pagos hechos con él se revierten (los pedidos vuelven a tener deuda)'"
            : "(devolucion ? 'Entra lo que nos devolvieron en ' + $('#chqMedioDevolucion option:selected').text()
                           : 'Todavía no nos devolvieron la plata: va a faltar en cheques en el cierre de caja')"; ?>;
        let motivo = pedirMotivo('Vas a marcar el cheque #' + ID + ' como RECHAZADO.\n' + texto +
            '<?php echo $c['idMovimiento'] ? ($c['idContactoDestino'] ? ' y se revierte el canje (le devolvemos la plata)' : ' y se revierte el depósito') : ''; ?>.');
        if (motivo) enviar({ opcion: 'rechazar', motivo: motivo, idMedioDevolucion: devolucion }, $(this));
    });

    $('#btnChqVolver').click(function() {
        let motivo = pedirMotivo('El cheque #' + ID + ' vuelve a cartera<?php echo $c['idMovimiento'] ? ' y se borra el movimiento del ' . ($c['idContactoDestino'] ? 'canje' : 'depósito') : ''; ?>.');
        if (motivo) enviar({ opcion: 'volverCartera', motivo: motivo }, $(this));
    });

    $('#btnChqBorrar').click(function() {
        let motivo = pedirMotivo('Vas a BORRAR el cheque #' + ID + '<?php echo $c['idMovimientoEntrada'] ? ' y el movimiento del canje (vuelve la plata que le dimos)' : ''; ?>.');
        if (motivo) enviar({ opcion: 'borrar', motivo: motivo }, $(this));
    });

    $('#btnChqEditar').click(function() {
        let motivo = pedirMotivo('Corregir los datos del cheque #' + ID + '.');
        if (!motivo) return;
        enviar({ opcion: 'editar', motivo: motivo, cheque: {
            tipo: $('#edChTipo').val(), numero: $('#edChNumero').val(), banco: $('#edChBanco').val(),
            fechaPago: $('#edChFechaPago').val(), titular: $('#edChTitular').val(),
            cuitTitular: $('#edChCuit').val(), nota: $('#edChNota').val()
        } }, $(this));
    });
})();
</script>
