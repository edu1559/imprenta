¡Perfecto! Vamos a darle un lavado de cara a contactos.php. Tenés una base sólida, pero el diseño está un poco "apretado" y la tabla tiene anchos fijos que hoy en día, con Bootstrap, conviene manejar de forma más elástica.
¿Qué vamos a mejorar en contactos.php?

    Diseño de la Tabla: Quitaremos los width fijos en los <td> y usaremos clases de Bootstrap para que se adapte mejor.

    Buscador Reactivo: Arreglaremos el problema de que al borrar la búsqueda no vuelve a mostrar todos los contactos.

    Visualización: Agregaremos un efecto visual a los botones y un diseño más limpio para que no parezca una planilla de Excel vieja.

Aquí tenés la versión mejorada del archivo:
contactos/contactos.php (Versión Pro)
PHP

<?php
include_once('../conexion.php');
$conn = conectar();

// Limpiamos variables de entrada
$cadena = isset($_GET['cadena']) ? mysqli_real_escape_string($conn, $_GET['cadena']) : null;
$ultimos = isset($_GET['ultimos']) ? true : false;

// Construcción de la SQL
$sql = "SELECT id, apellido, nombre, telefono, correo, notas FROM contactos";

if ($cadena) {
    $sql .= " WHERE apellido LIKE '%$cadena%' OR nombre LIKE '%$cadena%' OR telefono LIKE '%$cadena%'";
}

if ($ultimos) {
    $sql .= " ORDER BY id DESC";
} else {
    $sql .= " ORDER BY apellido ASC";
}

$sql .= " LIMIT 20"; // Subimos a 20 para llenar mejor la pantalla
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
                        <tr>
                            <th class="text-muted">ID</th>
                            <th>Cliente</th>
                            <th>Contacto Directo</th>
                            <th>Correo Electrónico</th>
                            <th>Notas</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (mysqli_num_rows($result) > 0) {
                            while ($row = mysqli_fetch_assoc($result)) {
                                echo "<tr>
                                    <td class='text-muted small'>#{$row['id']}</td>
                                    <td>
                                        <div class='fw-bold text-dark'>{$row['apellido']}, {$row['nombre']}</div>
                                    </td>
                                    <td>
                                        <div class='small'><i class='bi bi-telephone text-muted me-1'></i> {$row['telefono']}</div>
                                    </td>
                                    <td>
                                        <div class='small text-muted'>{$row['correo']}</div>
                                    </td>
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