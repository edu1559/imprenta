<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
$conn = conectar();

// Pizarra de producción: las ventas sin terminar, repartidas en una columna por
// responsable. Cualquier trabajador puede tomar un pedido, pasárselo a otro o
// marcarlo terminado (produccion/ajaxProduccion.php).
$yo = (int)$_SESSION['idUsuario'];

$trabajadores = [];
$res = mysqli_query($conn, "SELECT id, usuario FROM usuarios
                            WHERE id <> " . ID_USUARIO_VISITANTE . " AND idPerfil <> " . ID_PERFIL_VISITANTE . "
                            ORDER BY usuario");
while ($u = mysqli_fetch_assoc($res)) $trabajadores[(int)$u['id']] = $u['usuario'];

$res = mysqli_query($conn, "SELECT p.id, CONCAT(c.apellido, ' ', COALESCE(c.nombre, '')) AS contacto,
                                   p.detalle, p.observaciones, p.entrada, p.prometido,
                                   p.estadoProduccion, p.idResponsable, u.usuario AS cargo,
                                   -- Partes del trabajo que están afuera (produccion/tercerizados.php), separadas por '|'
                                   (SELECT GROUP_CONCAT(CONCAT(IF(t.fechaListo IS NOT NULL, 'L', IF(t.fechaEnviado IS NOT NULL, 'E', 'P')), pr.apellido) SEPARATOR '|')
                                    FROM tercerizados t INNER JOIN contactos pr ON pr.id = t.idProveedor
                                    WHERE t.idPedido = p.id AND t.fechaRecibido IS NULL) AS tercerizados
                            FROM pedidos p
                            INNER JOIN contactos c ON c.id = p.idContacto
                            LEFT JOIN usuarios u ON u.id = p.idUsuario
                            WHERE p.anulado = 0 AND p.idTipoPedido = 1 AND p.estadoProduccion IN (1, 2)
                            ORDER BY (p.prometido IS NULL OR p.prometido < '1000-01-01'), p.prometido, p.id");

// Columnas: sin asignar (clave 0), la mía y la de cada trabajador que tenga pedidos.
$columnas = [0 => [], $yo => []];
while ($p = mysqli_fetch_assoc($res)) {
    $columnas[(int)$p['idResponsable']][] = $p;
}
$total = array_sum(array_map('count', $columnas));

// Plazo según la fecha prometida: [clase del borde, texto, clase del texto].
function plazoPedido($prometido) {
    $t = $prometido ? strtotime($prometido) : false;
    if (!$t || $t < 0) return ['border-secondary', 'sin fecha', 'text-muted'];
    $dias = (int)floor((strtotime(date('Y-m-d', $t)) - strtotime(date('Y-m-d'))) / 86400);
    $fecha = date('d/m', $t);
    if ($dias < 0)   return ['border-danger',  "$fecha · vencido hace " . (-$dias) . ($dias === -1 ? ' día' : ' días'), 'text-danger fw-bold'];
    if ($dias === 0) return ['border-warning', "$fecha · hoy", 'text-warning-emphasis fw-bold'];
    if ($dias === 1) return ['border-warning', "$fecha · mañana", 'text-warning-emphasis'];
    return ['border-success', "$fecha · en $dias días", 'text-success'];
}

// Aviso en la tarjeta por cada parte tercerizada sin recibir, según la letra que arma la consulta.
const TERCERIZADO_EN_TARJETA = [
    'P' => ['bg-secondary',         'Llevar a'],
    'E' => ['bg-warning text-dark', 'Está en'],
    'L' => ['bg-success',           'Retirar de'],
];

$opcionesResponsable = "<option value='0'>Sin asignar</option>";
foreach ($trabajadores as $id => $usuario) {
    $opcionesResponsable .= "<option value='$id'>" . htmlspecialchars($usuario) . "</option>";
}
?>

<div class="container-fluid py-3 px-4" id="pizarra">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 bg-white p-3 shadow-sm rounded">
        <div>
            <h3 class="text-success mb-0 fw-bold"><i class="bi bi-kanban me-2"></i> Pizarra de producción</h3>
            <small class="text-muted"><?= $total ?> pedidos sin terminar, <?= count($columnas[0]) ?> sin asignar</small>
        </div>
        <div class="input-group input-group-sm" style="width: 280px;">
            <span class="input-group-text"><i class="bi bi-funnel"></i></span>
            <input type="text" id="filtroPizarra" class="form-control" placeholder="Filtrar por cliente, detalle o #">
        </div>
    </div>

    <div id="mensajePizarra"></div>

    <div class="d-flex gap-3 overflow-auto pb-3 align-items-start">
        <?php foreach ($columnas as $idResponsable => $pedidos):
            if ($idResponsable === 0) {
                $titulo = 'Sin asignar';
                $claseTitulo = 'bg-secondary text-white';
            } else {
                $titulo = $trabajadores[$idResponsable] ?? "Usuario $idResponsable";
                $claseTitulo = $idResponsable === $yo ? 'bg-success text-white' : 'bg-dark text-white';
            }
        ?>
        <div class="card border-0 shadow-sm flex-shrink-0" style="width: 320px;">
            <div class="card-header py-2 d-flex justify-content-between align-items-center <?= $claseTitulo ?>">
                <span class="fw-bold"><?= htmlspecialchars(ucfirst($titulo)) ?><?= $idResponsable === $yo ? ' (yo)' : '' ?></span>
                <span class="badge bg-light text-dark"><?= count($pedidos) ?></span>
            </div>
            <div class="card-body p-2 bg-light overflow-auto" style="max-height: 72vh;">
                <?php if (!$pedidos): ?>
                    <div class="text-center text-muted small py-4">Nada por ahora.</div>
                <?php endif; ?>
                <?php foreach ($pedidos as $p):
                    [$claseBorde, $textoPlazo, $clasePlazo] = plazoPedido($p['prometido']);
                ?>
                <div class="card mb-2 border-0 border-start border-4 <?= $claseBorde ?> shadow-sm tarjetaPedido" data-id="<?= (int)$p['id'] ?>">
                    <div class="card-body p-2">
                        <div class="d-flex justify-content-between align-items-baseline">
                            <span class="fw-bold buscable">#<?= (int)$p['id'] ?></span>
                            <span class="small <?= $clasePlazo ?>"><i class="bi bi-calendar-event"></i> <?= $textoPlazo ?></span>
                        </div>
                        <div class="fw-semibold text-truncate buscable" title="<?= htmlspecialchars($p['contacto']) ?>"><?= htmlspecialchars($p['contacto']) ?></div>
                        <div class="small buscable" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"
                             title="<?= htmlspecialchars($p['detalle']) ?>"><?= htmlspecialchars($p['detalle']) ?></div>
                        <?php if (trim((string)$p['observaciones']) !== ''): ?>
                        <div class="small text-muted fst-italic text-truncate" title="<?= htmlspecialchars($p['observaciones']) ?>"><?= htmlspecialchars($p['observaciones']) ?></div>
                        <?php endif; ?>
                        <?php foreach (array_filter(explode('|', (string)$p['tercerizados'])) as $tercerizado):
                            [$claseTercerizado, $textoTercerizado] = TERCERIZADO_EN_TARJETA[$tercerizado[0]];
                        ?>
                        <span class="badge <?= $claseTercerizado ?>"><i class="bi bi-truck"></i> <?= $textoTercerizado . ' ' . htmlspecialchars(trim(substr($tercerizado, 1), ' -')) ?></span>
                        <?php endforeach; ?>
                        <div class="small text-muted mb-2">
                            Entró el <?= date('d/m', strtotime($p['entrada'])) ?> · cargó <?= htmlspecialchars($p['cargo'] ?? '-') ?>
                        </div>
                        <div class="d-flex flex-wrap gap-1 align-items-center">
                            <?php if ($idResponsable !== $yo): ?>
                            <button type="button" class="btn btn-success btn-sm text-nowrap btnTomar" title="Pasa a mi columna">Lo tomo</button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-success btn-sm btnTerminar" title="Marcar terminado"><i class="bi bi-check-lg"></i></button>
                            <select class="form-select form-select-sm selResponsable" style="flex: 1 1 110px; min-width: 110px;" data-actual="<?= $idResponsable ?>" title="Responsable de producción">
                                <?= str_replace("value='$idResponsable'", "value='$idResponsable' selected", $opcionesResponsable) ?>
                            </select>
                            <button type="button" class="btn btn-outline-dark btn-sm btnTercerizar" title="Mandar una parte a un proveedor"><i class="bi bi-truck"></i></button>
                            <button type="button" class="btn btn-outline-dark btn-sm btnEditar" title="Editar pedido"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-outline-dark btn-sm btnOrden" title="Orden de trabajo"><i class="bi bi-printer"></i></button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    // El modal de edición recarga "la lista actual" al guardar: que vuelva a la pizarra.
    var urlListaActual = 'produccion/pizarra.php';

    function accionPizarra(datos) {
        $.post('produccion/ajaxProduccion.php', datos, function(res) {
            // Se recarga siempre: si falló, es porque la pizarra ya no estaba al día.
            $('#contenido').load(urlListaActual, function() {
                if (res.trim().indexOf('✅') !== 0) {
                    $('#mensajePizarra').html($('<div class="alert alert-warning py-2">').text(res));
                }
            });
        });
    }

    // De "Sin asignar" se toma solo si sigue libre; el de otro se lo paso a mi columna.
    $('#pizarra').on('click', '.btnTomar', function() {
        var $tarjeta = $(this).closest('.tarjetaPedido');
        if ($tarjeta.find('.selResponsable').data('actual') == 0) {
            accionPizarra({ opcion: 'tomar', idPedido: $tarjeta.data('id') });
        } else {
            accionPizarra({ opcion: 'asignar', idPedido: $tarjeta.data('id'), idResponsable: <?= $yo ?> });
        }
    });

    $('#pizarra').on('change', '.selResponsable', function() {
        accionPizarra({ opcion: 'asignar', idPedido: $(this).closest('.tarjetaPedido').data('id'), idResponsable: $(this).val() });
    });

    $('#pizarra').on('click', '.btnTerminar', function() {
        var id = $(this).closest('.tarjetaPedido').data('id');
        if (confirm('¿Marcar el pedido #' + id + ' como terminado?')) accionPizarra({ opcion: 'terminar', idPedido: id });
    });

    $('#pizarra').on('click', '.btnEditar', function() {
        var id = $(this).closest('.tarjetaPedido').data('id');
        $('#modalUniversal .modal-content').load('pedidos/modalEditarPedido.php?idPedido=' + id, function() {
            $('#modalUniversal').modal('show');
        });
    });

    $('#pizarra').on('click', '.btnTercerizar', function() {
        var id = $(this).closest('.tarjetaPedido').data('id');
        $('#modalUniversal .modal-content').load('produccion/modalTercerizado.php?idPedido=' + id, function() {
            $('#modalUniversal').modal('show');
        });
    });

    $('#pizarra').on('click', '.btnOrden', function() {
        var id = $(this).closest('.tarjetaPedido').data('id');
        window.open('pedidos/modalOrden.php?idPedido=' + id + '&imprimir=true', '_blank', 'width=900,height=800');
    });

    $('#filtroPizarra').on('input', function() {
        var texto = $(this).val().toLowerCase();
        $('#pizarra .tarjetaPedido').each(function() {
            $(this).toggle($(this).find('.buscable').text().toLowerCase().indexOf(texto) !== -1);
        });
    });
</script>
