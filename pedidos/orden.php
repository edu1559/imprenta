<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>ORDEN DE TRABAJO</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 12mm;
            padding: 5mm;
            width: 210mm; /* Ancho de una hoja A4 */
            height: 297mm; /* Alto de una hoja A4 */
            margin: 0 auto; /* Centrar en la página */
            text-align:center;
            
        }
         textarea {
            font-family: Arial, sans-serif;
            margin: 1mm;
            padding: 1mm;
            width: 180mm; /* Ancho de una hoja A4 */
            height: 5mm; /* Alto de una hoja A4 */
            margin: 0 auto; /* Centrar en la página */
            text-align:center;
            font-size:12pt;
            
        }
        .page {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }
        .section {
            width: 100%;
            height: 50%; /* Cada sección ocupa la mitad de la hoja A4 */
            padding: 20px;
            box-sizing: border-box;
            border: 1px solid #000;
        }
        .client-section {
            background-color:rgb(247, 238, 238);
        }
        .print-section {
            background-color:rgb(250, 246, 246);
        }
        .header {
            text-align: center;
         /*   border-bottom: 1px solid #000; */
            padding: 10px;
        }
        .header img {
            width: 100px;
            height: 50px;
        }
        .footer {
            text-align: center;
            border-top: 1px solid #000;
            padding: 10px;
        }
        .button-container {
            text-align: center;
            margin: 20px 0;
        }
        .button-container button {
            margin: 0 10px;
            padding: 10px 20px;
            cursor: pointer;
        }
        @media print {
            .button-container {
                display: none; /* Ocultar botones al imprimir */
            }
            .section {
                border: none; /* Quitar bordes al imprimir */
            }
        }
        p {
            font-size:10pt;
            text-align:center
        }
        table {
                float:left;
                margin-left:30mm;
                background-color:white;
                margin-top:5mm;
                text-align:left;
                padding:2mm;

        }
    </style>
    <script>
        function imprimir() {
            if (confirm("¿Imprimir orden?")) {
                window.print();
            }
        }
    </script>
</head>
<body>
    <?php
    include_once('../conexion.php');
    $conn = conectar();

    if (isset($_GET['idPedido'])) {
        $idPedido = $_GET['idPedido'];

        // Recuperar datos del pedido
        $sql = "SELECT
                    trim(detalle) as detalle,
                    trim(observaciones) as observaciones,
                    monto,
                    date_format(entrada, '%d/%c/%y %H hs') as entrada,
                    date_format(prometido, '%d/%c/%y %H hs') as prometido,
                    idContacto,
                    idUsuario,
                    montopagado
                FROM pedidos
                WHERE id = $idPedido";
        $result = mysqli_query($conn, $sql);
        $myrow0 = mysqli_fetch_assoc($result);

        $detalle = $myrow0['detalle'];
        $observaciones = $myrow0['observaciones'];
        $monto = $myrow0['monto'];
        $entrada = $myrow0['entrada'];
        $prometido = $myrow0['prometido'];
        $idContacto = $myrow0['idContacto'];
        $idUsuario = $myrow0['idUsuario'];
        $montopagado = $myrow0['montopagado'];

        $saldo = $monto - $montopagado;

        // Recuperar datos del cliente
        $sql = "SELECT
                    trim(apellido) as apellido,
                    trim(nombre) as nombre,
                    telefono
                
                FROM contactos
                WHERE id = $idContacto";
        $result = mysqli_query($conn, $sql);
        $myrow = mysqli_fetch_assoc($result);

        $apellido = $myrow['apellido'];
        $nombre = $myrow['nombre'];
        $telefono = $myrow['telefono'];
      
    ?>
    <div class="page">


        <!-- Sección para el Cliente -->
        <div class="section client-section">
            <div class="header">
                <img src="../imagenes/bandejaPintura.gif" style="width:100px;height:80px;padding:1em;float:left" >
			      <h5 style="text-align:center;color:white;">
				  IMPRENTA CORINTIOS 13<br>
				  Luis Agote 2028 - Tel  351 4872965 /351 7613626 corintios@imprentacorintios.com.ar 
				  </h5>
                   <h3 style="text-align:center">Comprobante para el Cliente -  Orden de Trabajo Nº <?php echo $idPedido; ?></h3>
            </div>
           
            
            <p>Apellido/Empresa:</p> 
            <textarea><?php echo $apellido . " " . $nombre; ?></textarea>
            <p>Detalle:</p> 
            <textarea style="height:20mm"><?php echo $detalle; ?></textarea>
            <p>Observaciones:</p> 
            <textarea style="height:10mm"><?php echo $observaciones; ?></textarea>
           
             <table>
                            <tr><td>Entrada:</td><td><?php echo $entrada;?></td></tr>
                            <tr><td>Prometido:</td><td><?php echo $prometido;?></td></tr>
                            <tr><td>Recibió:</td><td><?php echo $idUsuario;?></td></tr>
             </table>
             <table>
                            <tr><td>Monto:</td><td><?php echo $monto;?></td></tr>
                            <tr><td>Pagado:</td><td><?php echo $montoPagado;?></td></tr>
                            <tr><td>Saldo:</td><td><?php echo $saldo;?></td></tr>
             </table>   
            
            

           
       <div class="footer">
            
            </div>
        </div>



        <!-- Sección para la Imprenta -->
        <div class="section print-section">

            <div class="header">
                <img src="../imagenes/bandejaPintura.gif" style="width:100px;height:80px;padding:1em;float:left" >
			      <h3 style="text-align:center">Comprobante para Imprenta-  Orden de Trabajo Nº <?php echo $idPedido; ?></h3>
            </div>

            <p>Apellido/Empresa:</p> 
            <textarea><?php echo $apellido . " " . $nombre; ?></textarea>
            <p>Detalle:</p> 
            <textarea style="height:20mm"><?php echo $detalle; ?></textarea>
            <p>Observaciones:</p> 
            <textarea style="height:10mm"><?php echo $observaciones; ?></textarea>
           
             <table style="float:left;margin-left:30mm;background-color:white;margin-top:5mm">
                            <tr><td>Entrada:</td><td><?php echo $entrada;?></td></tr>
                            <tr><td>Prometido:</td><td><?php echo $prometido;?></td></tr>
                            <tr><td>Recibió:</td><td><?php echo $idUsuario;?></td></tr>
             </table>
             <table style="float:left;margin-left:30mm;background-color:white;margin-top:5mm">
                            <tr><td>Monto:</td><td><?php echo $monto;?></td></tr>
                            <tr><td>Pagado:</td><td><?php echo $montoPagado;?></td></tr>
                            <tr><td>Saldo:</td><td><?php echo $saldo;?></td></tr>
             </table>   
            </div>
        </div>
    </div>

    <div class="button-container">
        <button onclick="imprimir()">Imprimir</button>
        <button onclick="window.close()">Cerrar</button>
    </div>
    <?php
    }
    ?>
</body>
</html>