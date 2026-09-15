  <?php
   // Conectar a la base de datos

        include_once ('../conexion.php');
        $conn = conectar();

    // array asociativo $productos['idProducto']= array (nombre,foto,caracteristicas,enlace,color,orden);

        $productos = array();
        $sql = "select id,nombre,foto,caracteristicas
                from productos";
        $result = mysqli_query($conn,$sql);

        while($myrow = mysqli_fetch_row($result)){
            $productos[$myrow[0]] = array($myrow[1],$myrow[2],$myrow[3],$myrow[4]);
        };  

        if (isset($_GET['idProducto'])){
            $idProducto = $_GET['idProducto'];
        }else{
            $idProducto = array_key_first($productos);
        };
        //  print_r($productos);
    
        $nombre = $productos[$idProducto][0];
        $foto = $productos[$idProducto][1];
        $caracteristicas = $productos[$idProducto][2];
        $enlace = $productos[$idProducto][3];
        /*
        echo "<hr> 
                idProducto=". $idProducto. " <br>
                nombre=". $nombre . "  <br>
                caracteristicas= " . $caracteristicas . " <br>
                foto= " . $foto . " <br>
                enlace= " . $enlace . "<hr>";
        */
        ?>


<!-- selector de Productos -->
	
<div class="col-sm-5 p-3 m-3 bg-light rounded border form-group" id="panelSeleccion">
    <label for="selProductos" class="form-group ">Seleccione Producto</label>
    <select  id="selProductos" class="form-select" style="width:300px">
                    <?php	
                            foreach ($productos as $clave => $valor){
                                    echo "<option value=\"".$clave. "\"";
                                            if($clave == $idProducto){
                                                    echo " selected ";
                                            };
                                    echo ">". $valor[0]. "</option>";
                            };
                    ?>
            </select>
			
    </div>
        <!--            
    
               <div class="col-12 col-sm-6 col-md-4 col-lg-3 d-flex align-items-stretch"> 
       <!-- align-items-stretch hace que todas las cards de una fila tengan la misma altura -->
               
       <div class="card w-100"> 
       <!-- w-100 asegura que la card ocupe la columna --> 
                <img src=" <?php echo $foto; ?>" class="card-img-top" alt=" <?php echo $nombre;  ?>" style="width:100px;height:100px">
                <!-- imagen con su texto descriptivo -->
      
                <div class="card-body d-flex flex-column"> 
                  <!-- d-flex y flex-column para alinear el botón abajo -->
      
                <h5 class="card-title text-center"> 
                    <?php echo $nombre; ?> 
                </h5>
      
      <p class="card-text"> <?php echo $caracteristicas; ?></p>
      
                <!-- Si tienes un enlace específico para la lista de precios, úsalo en href -->
                <!-- Si no, puedes dejar '#' o quitar el href si el botón hace otra acción (ej. con JS)-->
      
                <a href="<?php echo $enlace; ?>" class="btn btn-primary mt-auto w-100"> Ver Lista Precios</a>

      <!-- mt-auto empuja el botón al final, w-100 lo hace ancho completo-->
                </div> <!-- card body-->
                </div> <!-- Cierre card-->
                </div> <!-- Cierre col-* -->
    


<script>


     $('#selProductos').change(function(){
        v_idProducto = $(this).val();
        v_url = "productos/imagenesProductos.php?idProducto=" + v_idProducto;
        $('#contenido').load(v_url);

     });
  
     

    
</script>
