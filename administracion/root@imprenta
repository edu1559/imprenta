<?php
include_once ('../conexion.php');
$conn = conectar();

// 1. Obtenemos los padres para los selectores
$padres = array();
$sql = "SELECT id, nombre FROM menu WHERE padre IS NULL ORDER BY nombre ASC";
$result = mysqli_query($conn, $sql);
while($row = mysqli_fetch_assoc($result)){    
    $padres[$row['id']] = $row['nombre'];
};

// 2. Filtro de menú seleccionado
$smenu = isset($_GET['smenu']) ? intval($_GET['smenu']) : 1;
?>

<div class="container-fluid mt-3 px-4">
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-menu-app fs-1 text-success me-3"></i>
        <h2 class="mb-0">Administración del Menú</h2>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <span class="me-2 fw-bold text-muted">Filtrar por:</span>
                        <select id="filtroPadre" class="form-select form-select-sm" style="width: auto;">
                            <?php foreach ($padres as $clave => $valor){
                                $selected = ($clave == $smenu) ? "selected" : "";
                                echo "<option value='$clave' $selected>$valor</option>";
                            } ?>
                        </select>
                    </div>
                    <button class="btn btn-primary btn-sm" id="btnNuevoMenu">
                        <i class="bi bi-plus-lg"></i> Nuevo Ítem
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">ID</th>
                                    <th>Nombre del Ítem</th>
                                    <th>Archivo / Página</th>
                                    <th>Orden</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                
                                $sql = "SELECT id, nombre, pagina, orden FROM menu WHERE padre = $smenu ORDER BY orden ASC, nombre ASC";
                                $res = mysqli_query($conn, $sql);
                                while($row = mysqli_fetch_assoc($res)){ ?>
                                    <tr>
                                        <td class="ps-3 text-muted small"><?php echo $row['id']; ?></td>
                                        <td class="fw-bold"><?php echo $row['nombre']; ?></td>
                                        <td><code><?php echo $row['pagina']; ?></code></td>
                                        <td class="fw-bold"><?php echo $row['orden']; ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary editarMenu" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-nombre="<?php echo $row['nombre']; ?>"
                                                    data-pagina="<?php echo $row['pagina']; ?>"
                                                    data-orden="<?php echo $row['orden']; ?>"
                                                    data-padre="<?php echo $smenu; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger borrarMenu" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-nombre="<?php echo $row['nombre']; ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm border-0 d-none" id="panelEdicion">
                <div class="card-header bg-dark text-white py-3" id="tituloForm">
                    <i class="bi bi-plus-circle me-2"></i>Nuevo Ítem de Menú
                </div>
                <div class="card-body">
                    <form id="formMenu">
                        <input type="hidden" id="inputId">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Nombre en el menú:</label>
                            <input type="text" class="form-control" id="inputNombre" placeholder="Ej: Listado de Precios">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Archivo PHP:</label>
                            <input type="text" class="form-control" id="inputPagina" placeholder="Ej: ventas/precios.php">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Menú Superior (Padre):</label>
                            <select id="selectPadre" class="form-select">
                                <?php foreach($padres as $id => $nombre) {
                                    echo "<option value='$id'>$nombre</option>";
                                } ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Orden de aparición:</label>
                            <input type="number" class="form-control" id="inputOrden" placeholder=" Ej: 1">
                        </div>
                        <hr>
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="button" class="btn btn-light border" id="btnCancelar">Cancelar</button>
                            <button type="button" class="btn btn-success" id="btnGuardar">Guardar Ítem</button>
                        </div>
                    </form>
                </div>
            </div>

            <div id="placeholderEdicion" class="text-center py-5 text-muted bg-light rounded border border-dashed">
                <i class="bi bi-arrow-left-circle fs-1"></i>
                <p>Seleccione editar o cree un ítem nuevo.</p>
            </div>
        </div>
    </div>
</div>

<script>
// Filtro de Padre
$('#filtroPadre').change(function(){
    var v_smenu = $(this).val();
    $('#contenido').load('administracion/menu.php?smenu=' + v_smenu);
});

// Botón Nuevo Ítem
$('#btnNuevoMenu').click(function(){
    $('#formMenu')[0].reset();
    $('#inputId').val('');
    $('#selectPadre').val($('#filtroPadre').val());
    $('#tituloForm').html('<i class="bi bi-plus-circle me-2"></i>Nuevo Ítem de Menú');
    $('#panelEdicion').removeClass('d-none');
    $('#placeholderEdicion').addClass('d-none');
});

// Botón Editar
$('.editarMenu').click(function(){
    var d = $(this).data(); // Esto lee todos los atributos 'data-' automáticamente
    
    $('#inputId').val(d.id);
    $('#inputNombre').val(d.nombre);
    $('#inputPagina').val(d.pagina);
    $('#selectPadre').val(d.padre);
    $('#inputOrden').val(d.orden); // Ahora leerá el valor correcto
    
    $('#tituloForm').html('<i class="bi bi-pencil-square me-2"></i>Editando: ' + d.nombre);
    $('#panelEdicion').removeClass('d-none');
    $('#placeholderEdicion').addClass('d-none');
});


// Botón Cancelar
$('#btnCancelar').click(function(){
    $('#panelEdicion').addClass('d-none');
    $('#placeholderEdicion').removeClass('d-none');
});

// Guardar / Actualizar
$('#btnGuardar').click(function(){
    var id = $('#inputId').val();
    var v_orden = $('#inputOrden').val();
    var opcion = (id == '') ? 'agregarMenu' : 'actualizarMenu';
    
    var v_url = "administracion/ajaxMenu.php?opcion=" + opcion + 
                "&id=" + id + 
                "&nombre=" + encodeURI($('#inputNombre').val()) + 
                "&pagina=" + encodeURI($('#inputPagina').val()) + 
                "&orden=" + encodeURI($('#inputOrden').val()) + 
                "&padre=" + $('#selectPadre').val();
   
    $.get(v_url, function(res){
        alert(res);
        $('#contenido').load('administracion/menu.php?smenu=' + $('#filtroPadre').val());
    });
});

// Borrar
$('.borrarMenu').click(function(){
    var id = $(this).data('id');
    var nombre = $(this).data('nombre');
    if(confirm("¿Desea eliminar el ítem '" + nombre + "'?")){
        $.get("administracion/ajaxMenu.php?opcion=borrarMenu&id=" + id, function(res){
            alert(res);
            $('#contenido').load('administracion/menu.php?smenu=' + $('#filtroPadre').val());
        });
    }
});
</script>