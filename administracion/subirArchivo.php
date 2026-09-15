
<?php
include_once('../conexion.php'); // Asegúrate que la ruta sea correcta
$conn = conectar();

/* array productos[idProducto]= array(nombre,foto) */
$productos = array();

$sql = "SELECT id, nombre, foto 
        FROM productos";
$result = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($result)) { // Usar fetch_assoc es más legible
    $productos[$row['id']] = ['nombre' => $row['nombre'], 'foto' => $row['foto']];
}
mysqli_close($conn); // Cerrar conexión cuando ya no se necesita

// Determinar producto seleccionado (igual que tu lógica)
if (isset($_GET['idProducto']) && isset($productos[$_GET['idProducto']])) {
    $idProductoSeleccionado = $_GET['idProducto'];
} else {
    // Asegurarse que $productos no esté vacío antes de buscar la primera clave
    $idProductoSeleccionado = !empty($productos) ? array_key_first($productos) : null;
}

$nombreSeleccionado = $idProductoSeleccionado ? $productos[$idProductoSeleccionado]['nombre'] : 'N/A';
$fotoSeleccionada = $idProductoSeleccionado ? $productos[$idProductoSeleccionado]['foto'] : 'ruta/a/imagen/default.jpg'; // Poner una imagen por defecto

// --- Eliminamos el bloque de procesamiento de $_FILES de aquí ---
// --- Ese bloque debe ir en administracion/subirArchivo.php ---

?>


    <style>
        /* Estilo para la previsualización */
        #imagenProductoPreview {
            max-width: 200px; /* Ajusta según necesites */
            max-height: 200px; /* Ajusta según necesites */
            margin-top: 10px;
            display: block; /* Para que el margen funcione */
            border: 1px solid #ccc; /* Opcional */
        }
    </style>


<div class="container mt-4">
    <div class="row">

        <div class="col-md-6 mb-3">
            <div class="p-3 bg-light rounded border">
                <label for="selProductos" class="form-label">Seleccione Producto</label>
                <select id="selProductos" class="form-select">
                    <?php if (empty($productos)): ?>
                        <option value="">No hay productos</option>
                    <?php else: ?>
                        <?php foreach ($productos as $id => $datos): ?>
                            <option value="<?php echo $id; ?>"
                                    data-foto="<?php echo htmlspecialchars($datos['foto']); ?>" <?php echo ($id == $idProductoSeleccionado) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($datos['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <?php if ($idProductoSeleccionado): // Mostrar formulario solo si hay un producto ?>
                <form id="formProducto" enctype="multipart/form-data" method="POST" action="administracion/ajaxSubirArchivo.php">
                    <h4>Modificar Imagen para: <span id="nombreProductoSeleccionado"><?php echo htmlspecialchars($nombreSeleccionado); ?></span></h4>

                    <input type="hidden" name="idProducto" id="hiddenIdProducto" value="<?php echo $idProductoSeleccionado; ?>">

                    <div class="mb-3">
                        <label class="form-label">Imagen Actual:</label>
                        <img src="<?php echo htmlspecialchars($fotoSeleccionada); ?>"
                             alt="Imagen de <?php echo htmlspecialchars($nombreSeleccionado); ?>"
                             id="imagenProductoPreview"
                             class="img-thumbnail"
                             onerror="this.onerror=null; this.src='ruta/a/imagen/error.png';"> </div>

                    <div class="mb-3">
                        <label for="archivo" class="form-label">Seleccionar nueva imagen:</label>
                        <input type="file" name="archivo" id="archivo" class="form-control" accept="image/*">
                         </div>

                    <button type="submit" class="btn btn-primary">Guardar Nueva Imagen</button>
                </form>
            <?php else: ?>
                <p>Seleccione un producto para ver o cambiar su imagen.</p>
            <?php endif; ?>
        </div>
    </div>
</div>



<script>
$(document).ready(function() {

    // 1. Cuando cambia la selección del producto
    $('#selProductos').change(function() {
        const selectedOption = $(this).find('option:selected');
        const idProducto = selectedOption.val();
        const nombreProducto = selectedOption.text();
        const fotoProducto = selectedOption.data('foto'); // Obtener URL de data-attribute

        // Actualizar el ID en el campo oculto del formulario
        $('#hiddenIdProducto').val(idProducto);

        // Actualizar el nombre mostrado
        $('#nombreProductoSeleccionado').text(nombreProducto);

        // Actualizar la imagen de previsualización con la foto actual del producto seleccionado
        $('#imagenProductoPreview').attr('src', fotoProducto)
                                  .attr('alt', 'Imagen de ' + nombreProducto);

        // Limpiar el input de archivo si se había seleccionado uno nuevo
        $('#archivo').val('');
    });

    // 2. Cuando se selecciona un archivo nuevo en el input type="file"
    $('#archivo').change(function(event) {
        const file = event.target.files[0]; // Obtener el archivo seleccionado
        if (file) {
            const reader = new FileReader();

            reader.onload = function(e) {
                // Actualizar la imagen de previsualización con la imagen NUEVA seleccionada
                $('#imagenProductoPreview').attr('src', e.target.result);
            }

            // Leer el archivo como una URL de datos (para mostrarlo en <img>)
            reader.readAsDataURL(file);
        } else {
            // Si el usuario cancela la selección, volver a mostrar la imagen actual del producto
            const fotoActual = $('#selProductos option:selected').data('foto');
            $('#imagenProductoPreview').attr('src', fotoActual);
        }
    });

    // Opcional: Añadir validación antes de enviar (ej: asegurar que se seleccionó un archivo si se intenta cambiar)
    // $('#formProducto').submit(function(event) {
    //     if ($('#archivo').get(0).files.length === 0) {
    //         alert('Por favor, seleccione una imagen para subir.');
    //         event.preventDefault(); // Detener el envío del formulario
    //     }
    // });

});
</script>

</body>
</html>

