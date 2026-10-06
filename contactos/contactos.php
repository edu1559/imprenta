<?php
include_once(__DIR__ . '/../sesion.php');
exigirTrabajadorPagina();
include_once(__DIR__ . '/../conexion.php');
include_once(__DIR__ . '/../celular.php');
$conn = conectar();

// Limpiamos variables de entrada
$cadena = isset($_GET['cadena']) ? mysqli_real_escape_string($conn, $_GET['cadena']) : null;
$ultimos = isset($_GET['ultimos']) ? true : false;

// Construcción de la SQL
$sql = "SELECT id, apellido, nombre, telefono, celular, correo, notas, esEmpresa FROM contactos ";

if ($cadena) {
    $sql .= " WHERE apellido LIKE '%$cadena%' OR nombre LIKE '%$cadena%' OR telefono LIKE '%$cadena%' OR celular LIKE '%$cadena%'";
}

if ($ultimos) {
    $sql .= " ORDER BY id DESC";
} else {
    $sql .= " ORDER BY apellido DESC";
}

$sql .= " LIMIT 20 "; // Subimos a 20 para llenar mejor la pantalla
$result = mysqli_query($conn, $sql);
?>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 shadow-sm rounded">
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
            <div class="row g-3 mb-4">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-success"></i></span>
                        <input type="text" id="buscar" class="form-control border-start-0 ps-0"
                               placeholder="Buscar por apellido, nombre o teléfono..."
                               value="<?php echo $cadena; ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-secondary w-100" id="btnUltimos">
                        <i class="bi bi-clock-history me-1"></i> Ver Últimos
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tbContactos">
                    <thead class="table-light">
                        <tr class="table-primary">
                            <th class="text-muted">ID</th>
                            <th>Contacto</th>
                            <th>Celular / Teléfono</th>
                            <th>Correo Electrónico</th>
                            <th>Notas</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                $iconoEmpresa = $row['esEmpresa'] ? "<i class='bi bi-building text-secondary me-1' title='Empresa o institución'></i>" : "";
                                // Celular como enlace a WhatsApp (abre el chat con el saludo) y debajo el teléfono fijo, si hay
                                $telefonos = '';
                                if ($row['celular']) {
                                    $enlace = htmlspecialchars(enlaceWhatsApp($row['celular'], 'Hola ' . nombreSaludo($row['apellido'], $row['nombre']) . ', '), ENT_QUOTES);
                                    $telefonos .= "<div class='small'><a href='$enlace' target='_blank' rel='noopener' class='text-success text-decoration-none' title='Escribir por WhatsApp'><i class='bi bi-whatsapp me-1'></i>" . mostrarCelular($row['celular']) . "</a></div>";
                                }
                                if (trim((string)$row['telefono']) !== '') {
                                    $telefonos .= "<div class='small text-muted'><i class='bi bi-telephone me-1'></i>" . htmlspecialchars($row['telefono']) . "</div>";
                                }
                                $correo = trim((string)$row['correo']) !== ''
                                    ? "<a href='mailto:" . htmlspecialchars($row['correo'], ENT_QUOTES) . "' class='small text-muted text-decoration-none'><i class='bi bi-envelope me-1'></i>" . htmlspecialchars($row['correo']) . "</a>"
                                    : '';
                                echo "<tr>
                                    <td class='text-muted small'>#{$row['id']}</td>
                                    <td>
                                        <div class='fw-bold text-dark'>{$iconoEmpresa}{$row['apellido']}, {$row['nombre']}</div>
                                    </td>
                                    <td>{$telefonos}</td>
                                    <td>{$correo}</td>
                                    <td>
                                        <div class='text-truncate' style='max-width: 150px;' title='{$row['notas']}'>
                                            <small>{$row['notas']}</small>
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
                                            <button class='btn btn-sm btn-light border btnContactoBorrar' title='Eliminar' data-id='{$row['id']}'>
                                                <i class='bi bi-trash text-danger'></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='text-center py-4 text-muted'>No se encontraron contactos.</td></tr>";
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
    // Buscador mejorado con retraso (debounce) para no saturar el servidor
    var timer;
    $('#buscar').on('keyup', function() {
        var v_cadena = $(this).val();
        clearTimeout(timer);
        timer = setTimeout(function() {
            // Si el campo tiene 3 o más letras, busca. Si está vacío, recarga todo.
            if (v_cadena.length >= 3 || v_cadena.length == 0) {
                $('#contenido').load('contactos/contactos.php?cadena=' + encodeURIComponent(v_cadena));
            }
        }, 500);
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
