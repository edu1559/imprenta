
<!-- Muestra de Productos -->

<?php
    include_once ('../conexion.php');
    $conn = conectar();

    $productos = array();
    $sql = "select id,nombre,foto,caracteristicas,color,enlace,orden
            from productos 
            where idCategoria =1";
    $result = mysqli_query($conn,$sql);

    // Es buena práctica verificar si la consulta fue exitosa
    if ($result) {
        while($myrow = mysqli_fetch_assoc($result)){ // Usar fetch_assoc es más legible
            $productos[$myrow['id']] = array(
                "nombre" => $myrow['nombre'],
                "foto" => $myrow['foto'],
                "caracteristicas" => $myrow['caracteristicas'],
                "color" => $myrow['color'],
                "enlace" => $myrow['enlace'], // Asegúrate si usarás este enlace
                "orden" => $myrow['orden']
            );
        }
        mysqli_free_result($result); // Liberar memoria del resultado
    } else {
        // Manejar el error, por ejemplo:
        echo "Error en la consulta: " . mysqli_error($conn);
    }
    mysqli_close($conn); // Cerrar la conexión cuando ya no se necesite
// print_r($productos);
?>


<div class="container-fluid" style="background-color:#eee; min-height:100vh; padding-bottom: 3rem;">
    <div class="container text-center pt-5 pb-4 h1 display-4">
      <h1 class="display-4">Productos</h1>
    </div>

    <div class="container"> <div class="row g-4">
      
      <?php
           if (!empty($productos)) {
            foreach ($productos as $id => $producto) {
             ?>   
               <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex align-items-stretch"> 
       <!-- align-items-stretch hace que todas las cards de una fila tengan la misma altura -->
               
       <div class="card w-100"> 
       <!-- w-100 asegura que la card ocupe la columna --> 
                <img src=" <?php echo htmlspecialchars($producto['foto'])?> " class="card-img-top" alt=" <?php echo htmlspecialchars($producto['nombre']); ?> ">
                <!-- imagen con su texto descriptivo -->
      
                <div class="card-body d-flex flex-column"> 
                  <!-- d-flex y flex-column para alinear el botón abajo -->
      
                <h5 class="card-title text-center"> 
                    <?php echo htmlspecialchars($producto['nombre']); ?> 
                </h5>

      <p class="card-text"> <?php htmlspecialchars($producto['caracteristicas']); ?></p>

                <!-- Si tienes un enlace específico para la lista de precios, úsalo en href -->
                <!-- Si no, puedes dejar '#' o quitar el href si el botón hace otra acción (ej. con JS)-->
      
                <a href="<?php echo htmlspecialchars($producto['enlace'] ?? '#') ?>" class="btn btn-primary mt-auto w-100"> Ver Lista Precios</a>

      <!-- mt-auto empuja el botón al final, w-100 lo hace ancho completo-->
                </div> <!-- card body-->
                </div> <!-- Cierre card-->
                </div> <!-- Cierre col-* -->
    <?php	
            }
        } else {
            echo "<div class='col-12'><p class='text-center'>No hay productos para mostrar.</p></div>";
        }
  ?>
           
           <?php
           /*
           if (!empty($productos)) {
                foreach ($productos as $id => $producto) {
                    // Definimos las clases de columna para la responsividad
                    // col-12: 1 por fila en extra-pequeño (<576px)
                    // col-sm-6: 2 por fila en pequeño (≥576px)
                    // col-md-4: 3 por fila en mediano (≥768px)
                    // col-lg-3: 4 por fila en grande (≥992px)
                    // Puedes ajustar esto según tu preferencia
                    echo "<div class=\"col-12 col-sm-6 col-md-4 col-lg-3 d-flex align-items-stretch\">"; // align-items-stretch hace que todas las cards de una fila tengan la misma altura
                    echo "    <div class=\"card w-100\">"; // w-100 asegura que la card ocupe la columna
                    // Añadir alt text descriptivo a las imágenes es importante para accesibilidad y SEO
                    echo "        <img src=\"" . htmlspecialchars($producto['foto']) . "\" class=\"card-img-top\" alt=\"". htmlspecialchars($producto['nombre']) ."\">";
                    echo "        <div class=\"card-body d-flex flex-column\">"; // d-flex y flex-column para alinear el botón abajo
                    echo "            <h5 class=\"card-title text-center\">" . htmlspecialchars($producto['nombre']) . "</h5>";
                    echo "            <p class=\"card-text\">" . htmlspecialchars($producto['caracteristicas']) . "</p>";
                    // Si tienes un enlace específico para la lista de precios, úsalo en href
                    // Si no, puedes dejar '#' o quitar el href si el botón hace otra acción (ej. con JS)
                    echo "            <a href=\"" . htmlspecialchars($producto['enlace'] ?? '#') . "\" class=\"btn btn-primary mt-auto w-100\">Ver Lista Precios</a>"; // mt-auto empuja el botón al final, w-100 lo hace ancho completo
                    echo "        </div>"; // Cierre card-body
                    echo "    </div>"; // Cierre card
                    echo "</div>"; // Cierre col-*
                }
            } else {
                echo "<div class='col-12'><p class='text-center'>No hay productos para mostrar.</p></div>";
            }
                */
            ?>
        </div>
      </div>
    </div>