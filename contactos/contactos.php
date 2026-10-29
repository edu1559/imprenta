<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../celular.php');
$conn = conectar();

const CANTIDAD_CONTACTOS = 20;

$cadena = trim($_GET['cadena'] ?? '');
$ultimos = isset($_GET['ultimos']);

function h($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES);
}

// 1) Qué contactos mostrar, ya en orden:
//    - búsqueda: los que coinciden, primero los que pidieron más recientemente;
//    - "Ver últimos": los últimos cargados;
//    - de entrada: los que hicieron los últimos pedidos.
if ($cadena !== '') {
    $patron = '%' . $cadena . '%';
    $stmt = $conn->prepare("SELECT c.id FROM contactos c
        WHERE c.apellido LIKE ? OR c.nombre LIKE ? OR c.telefono LIKE ? OR c.celular LIKE ? OR c.correo LIKE ? OR c.cuit LIKE ?
        ORDER BY (SELECT MAX(p.id) FROM pedidos p WHERE p.idContacto = c.id) DESC, c.apellido, c.nombre
        LIMIT " . CANTIDAD_CONTACTOS);
    $stmt->bind_param("ssssss", $patron, $patron, $patron, $patron, $patron, $patron);
    $stmt->execute();
    $resIds = $stmt->get_result();
    $titulo = 'Resultados de la búsqueda';
} elseif ($ultimos) {
    $resIds = mysqli_query($conn, "SELECT id FROM contactos ORDER BY id DESC LIMIT " . CANTIDAD_CONTACTOS);
    $titulo = 'Últimos contactos cargados';
} else {
    $resIds = mysqli_query($conn, "SELECT idContacto AS id FROM pedidos WHERE idContacto IS NOT NULL
        GROUP BY idContacto ORDER BY MAX(id) DESC LIMIT " . CANTIDAD_CONTACTOS);
    $titulo = 'Contactos con pedidos más recientes';
}
$ids = [];
while ($fila = mysqli_fetch_row($resIds)) $ids[] = (int)$fila[0];

// 2) Datos y resumen de pedidos de esos contactos. "Pendiente" = pedido no anulado y no cerrado,
//    igual que en Pedidos. conPedidos cuenta también los anulados: con alguno no se puede borrar.
$contactos = [];
if ($ids) {
    $lista = implode(',', $ids);
    $abierto = "p.anulado = 0 AND NOT (p.estadoProduccion = 3 AND p.estadoEntrega = 3 AND p.estadoPago = 3)";
    $res = mysqli_query($conn, "SELECT c.id, c.apellido, c.nombre, c.telefono, c.celular, c.correo, c.cuit, c.notas, c.esEmpresa, c.esGenerico,
            COUNT(p.id) AS conPedidos,
            COALESCE(SUM(p.anulado = 0), 0) AS pedidos,
            COALESCE(SUM($abierto), 0) AS pendientes,
            COALESCE(SUM(IF($abierto, GREATEST(p.monto - p.montoPagado, 0), 0)), 0) AS saldo,
            MAX(p.entrada) AS ultimoPedido
        FROM contactos c LEFT JOIN pedidos p ON p.idContacto = c.id
        WHERE c.id IN ($lista)
        GROUP BY c.id
        ORDER BY FIELD(c.id, $lista)");
    while ($fila = mysqli_fetch_assoc($res)) $contactos[] = $fila;
}
?>

<div class="container-fluid py-3 px-4">
    <div class="d-flex justify-content-between align-items-center mb-3 bg-white p-3 shadow-sm rounded">
        <div>
            <h3 class="text-success mb-0 fw-bold">
                <i class="bi bi-people-fill me-2"></i> Gestión de Contactos
            </h3>
            <small class="text-muted">Administración de clientes y proveedores</small>
        </div>
        <button class='btn btn-primary shadow-sm btnContactoNuevo'>
            <i class="bi bi-person-plus-fill me-1"></i> Nuevo Contacto
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <div class="input-group" style="max-width: 480px;">
                    <span class="input-group-text bg-success text-white"><i class="bi bi-search"></i></span>
                    <input type="text" id="buscar" class="form-control" placeholder="Apellido, nombre, teléfono, correo o CUIT..."
                           value="<?php echo h($cadena); ?>" aria-label="Buscar">
                    <button class="btn btn-success" type="button" id="btnBuscarContactos">Buscar</button>
                </div>
                <button type="button" class="btn btn-sm btn-info text-white" id="btnUltimos">
                    <i class="bi bi-clock-history me-1"></i> Ver últimos cargados
                </button>
                <span class="text-muted small ms-auto"><?php echo $titulo; ?></span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0" id="tbContactos">
                    <thead class="table-light">
                        <tr class="table-primary">
                            <th class="text-muted">ID</th>
                            <th>Contacto</th>
                            <th>Celular / Teléfono</th>
                            <th>Correo electrónico</th>
                            <th class="text-center">Pedidos</th>
                            <th>Último pedido</th>
                            <th>Notas</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($contactos as $row) {
                            $iconoEmpresa = $row['esEmpresa'] ? "<i class='bi bi-building text-secondary me-1' title='Empresa o institución'></i>" : "";
                            $nombre = h($row['apellido']) . (trim((string)$row['nombre']) !== '' ? ', ' . h($row['nombre']) : '');
                            $cuit = trim((string)$row['cuit']) !== ''
                                ? "<div class='small text-muted'>CUIT " . h($row['cuit']) . "</div>"
                                : '';
                            // Celular como enlace a WhatsApp (abre el chat con el saludo) y debajo el teléfono fijo, si hay
                            $telefonos = '';
                            if ($row['celular']) {
                                $enlace = h(enlaceWhatsApp($row['celular'], 'Hola ' . nombreSaludo($row['apellido'], $row['nombre']) . ', '));
                                $telefonos .= "<div class='small'><a href='$enlace' target='_blank' rel='noopener' class='text-success text-decoration-none' title='Escribir por WhatsApp'><i class='bi bi-whatsapp me-1'></i>" . mostrarCelular($row['celular']) . "</a></div>";
                            }
                            if (trim((string)$row['telefono']) !== '') {
                                $telefonos .= "<div class='small text-muted'><i class='bi bi-telephone me-1'></i>" . h($row['telefono']) . "</div>";
                            }
                            $correo = trim((string)$row['correo']) !== ''
                                ? "<a href='mailto:" . h($row['correo']) . "' class='small text-muted text-decoration-none'><i class='bi bi-envelope me-1'></i>" . h($row['correo']) . "</a>"
                                : '';
                            if ($telefonos === '' && $correo === '' && !$row['esGenerico']) {
                                $telefonos = "<span class='badge text-bg-warning'><i class='bi bi-exclamation-triangle me-1'></i>Sin teléfono ni correo</span>";
                            }

                            $pedidos = "<span class='badge text-bg-light border'>" . (int)$row['pedidos'] . "</span>";
                            if ($row['pendientes'] > 0) {
                                $pedidos .= " <span class='badge text-bg-danger' title='Pedidos sin cerrar'>" . (int)$row['pendientes'] . " pend.</span>";
                                if ($row['saldo'] > 0) {
                                    $pedidos .= "<div class='small text-danger'>Debe $" . number_format($row['saldo'], 0, ',', '.') . "</div>";
                                }
                            }
                            $ultimoPedido = $row['ultimoPedido'] ? "<span class='small'>" . date('d/m/Y', strtotime($row['ultimoPedido'])) . "</span>" : '';

                            // Solo se puede borrar un contacto sin ningún pedido (ni anulado).
                            $botonBorrar = $row['conPedidos'] == 0
                                ? "<button class='btn btn-sm btn-light border btnContactoBorrar' title='Eliminar' data-id='{$row['id']}'>
                                        <i class='bi bi-trash text-danger'></i>
                                   </button>"
                                : '';

                            echo "<tr>
                                <td class='text-muted small'>#{$row['id']}</td>
                                <td>
                                    <div class='fw-bold text-dark'>{$iconoEmpresa}{$nombre}</div>
                                    {$cuit}
                                </td>
                                <td>{$telefonos}</td>
                                <td>{$correo}</td>
                                <td class='text-center'>{$pedidos}</td>
                                <td>{$ultimoPedido}</td>
                                <td>
                                    <div class='text-truncate' style='max-width: 180px;' title='" . h($row['notas']) . "'>
                                        <small>" . h($row['notas']) . "</small>
                                    </div>
                                </td>
                                <td>
                                    <div class='d-flex justify-content-center gap-1'>
                                        <button class='btn btn-sm btn-light border btnContactoPedidos' title='Ver Historial de Pedidos' data-id='{$row['id']}'>
                                            <i class='bi bi-clipboard2-data text-primary'></i>
                                        </button>
                                        <button class='btn btn-sm btn-light border btnContactoEditar' title='Editar Perfil' data-id='{$row['id']}'>
                                            <i class='bi bi-pencil-square text-success'></i>
                                        </button>
                                        {$botonBorrar}
                                    </div>
                                </td>
                            </tr>";
                        }
                        if (!$contactos) {
                            echo "<tr><td colspan='8' class='text-center py-4 text-muted'>No se encontraron contactos.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modalUniversal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"> <div class="modal-content">
            </div>
    </div>
</div>
</div>

<script>
    // Búsqueda como en Pedidos: con el botón o con Enter
    function buscarContactos() {
        $('#contenido').load('contactos/contactos.php?cadena=' + encodeURIComponent($('#buscar').val().trim()));
    }
    $('#btnBuscarContactos').click(buscarContactos);
    $('#buscar').on('keydown', function(e) {
        if (e.key === 'Enter') buscarContactos();
    });

    $('#btnUltimos').click(function() {
        $('#contenido').load('contactos/contactos.php?ultimos=true');
    });

    // Delegación de eventos para los botones (más seguro)
    $(document).on('click', '.btnContactoEditar', function(){
        $('#modalUniversal .modal-content').load('contactos/modalContactosEditar.php?idContacto=' + $(this).data('id'), function(){
            $('#modalUniversal').modal('show');
        });
    });

    $(document).on('click', '.btnContactoPedidos', function(){
        // IMPORTANTE: Aquí vamos a usar 'modal-lg' para que el de pedidos sea ancho
        $('#modalUniversal .modal-dialog').addClass('modal-lg');
        $('#modalUniversal .modal-content').load('contactos/modalContactosPedidos.php?idContacto=' + $(this).data('id'), function(){
            $('#modalUniversal').modal('show');
        });
    });

    $(document).on('click', '.btnContactoBorrar', function(){
        $('#modalUniversal .modal-content').load('contactos/modalContactosBorrar.php?idContacto=' + $(this).data('id'), function(){
            $('#modalUniversal').modal('show');
        });
    });

    $('.btnContactoNuevo').on('click', function(){
        $('#modalUniversal .modal-content').load('contactos/modalContactoNuevo.php', function(){
            $('#modalUniversal').modal('show');
        });
    });

    // Resetear el tamaño del modal cuando se cierra para que los demás no queden gigantes
    $('#modalUniversal').on('hidden.bs.modal', function () {
        $(this).find('.modal-dialog').removeClass('modal-lg');
    });
</script>
