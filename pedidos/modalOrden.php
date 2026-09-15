<?php
include_once('../conexion.php');
$conn = conectar();

if (!isset($_GET['idPedido'])) { die("Falta ID de Pedido"); }

$idPedido = $_GET['idPedido'];

// Recuperar datos del pedido
$sql = "SELECT trim(detalle) as detalle, trim(observaciones) as observaciones, monto, 
               date_format(entrada, '%d/%c/%y %H hs') as entrada, 
               date_format(prometido, '%d/%c/%y %H hs') as prometido, 
               idContacto, idUsuario, montopagado 
        FROM pedidos WHERE id = $idPedido";
$result = mysqli_query($conn, $sql);
$myrow0 = mysqli_fetch_assoc($result);

$detalle        = $myrow0['detalle'];
$observaciones  = $myrow0['observaciones'];
$monto          = $myrow0['monto'];
$entrada        = $myrow0['entrada'];
$prometido      = $myrow0['prometido'];
$idContacto     = $myrow0['idContacto'];
$idUsuario      = $myrow0['idUsuario'];
$montopagado    = $myrow0['montopagado'];
$saldo          = $monto - $montopagado;

$claseSaldo = ($saldo <= 0.1) ? 'text-success fw-bold' : 'text-danger fw-bold';
$textoSaldo = ($saldo <= 0.1) ? 'PAGADO' : '$ ' . number_format($saldo, 2);

// Recuperar datos del cliente
$sql = "SELECT trim(apellido) as apellido, trim(nombre) as nombre, telefono 
        FROM contactos WHERE id = $idContacto";
$result = mysqli_query($conn, $sql);
$myrow = mysqli_fetch_assoc($result);

$apellido = $myrow['apellido'];
$nombre   = $myrow['nombre'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden Nº <?php echo $idPedido; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f0f0; font-family: 'Ubuntu', sans-serif; color: #000; }
        
        /* Contenedor principal */
        #contenedorPrincipal { 
            max-width: 800px; 
            margin: 20px auto; 
            background-color: white; 
            padding: 30px; 
            box-shadow: 0 0 10px rgba(0,0,0,0.1); 
        }

        .caja-texto, .caja-obs { min-height: 60px; background-color: #f8f9fa !important; border: 1px solid #dee2e6; padding: 10px; border-radius: 5px; }
        .linea-corte { border-top: 2px dashed #000; margin: 30px 0; }
        
        .sello-pagado { 
            border: 3px solid #198754; color: #198754; font-size: 1.5rem; 
            font-weight: bold; padding: 5px 10px; transform: rotate(-10deg); 
            display: <?php echo ($saldo <= 0.1) ? 'inline-block' : 'none'; ?>;
        }

        @media print {
            body { 
                    background: white !important; 
                }
            .no-print {
                     display: none !important; 
                }
            #contenedorPrincipal { 
                    position: absolute; left: 0; top: 0; width: 100% !important; 
                    margin: 0 !important; padding: 10mm !important; box-shadow: none !important; 
                }
            .table-light { 
                    background-color: #f8f9fa !important; /*  : exact; */
                }
            /* Forzar que se vean los fondos grises de las cajas */
            .bg-light, .caja-texto, .caja-obs { 
                    background-color: #f8f9fa !important; 
                    -webkit-print-color-adjust: exact; 
                }
        }
    </style>
</head>
<body>

    <div class="text-center p-3 no-print">
        <button class="btn btn-success btn-lg" onclick="window.print()">
            <i class="bi bi-printer"></i> IMPRIMIR ORDEN
        </button>
        <button class="btn btn-secondary btn-lg" onclick="window.close()">Cerrar</button>
    </div>

    <div id="contenedorPrincipal">
        
        <div class="section-cliente">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <img src="../imagenes/bandejaPintura.gif" style="width:80px;">
                <div class="text-center">
                    <h5 class="mb-0 fw-bold">IMPRENTA CORINTIOS 13</h5>
                    <small>Luis Agote 2028 - Tel 351 7613626</small>
                </div>
                <div class="text-end">
                    <div class="sello-pagado mb-1">PAGADO</div>
                    <h4 class="fw-bold mb-0">Nº <?php echo $idPedido; ?></h4>
                </div>
            </div>

            <div class="mb-2">
                <span class="fw-bold">Cliente:</span>
                <div class="p-2 border rounded bg-light"><?php echo $apellido . " " . $nombre; ?></div>
            </div>
            <div class="mb-2">
                <span class="fw-bold">Detalle:</span>
                <div class="caja-texto"><?php echo nl2br($detalle); ?></div>
            </div>

            <div class="row mt-3">
                <div class="col-6">
                    <table class="table table-sm table-bordered">
                        <tr><th class="table-light">Prometido</th><td><?php echo $prometido;?></td></tr>
                        <tr><th class="table-light">Recibió</th><td>Usuario <?php echo $idUsuario;?></td></tr>
                    </table>
                </div>
                <div class="col-6">
                    <table class="table table-sm table-bordered">
                        <tr><th class="table-light">Total</th><td class="text-end">$ <?php echo number_format($monto, 2);?></td></tr>
                        <tr class="<?php echo ($saldo <= 0.1) ? 'table-success' : 'table-warning'; ?>">
                            <th class="fw-bold">SALDO</th>
                            <td class="text-end <?php echo $claseSaldo; ?>"><?php echo $textoSaldo; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="linea-corte"></div>

        <div class="section-imprenta">
            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                <img src="../imagenes/bandejaPintura.gif" style="width:80px;">
                <h5 class="mb-0 fw-bold">ORDEN INTERNA Nº <?php echo $idPedido; ?></h5>
                <div class="text-end small">Entrada: <?php echo $entrada;?></div>
            </div>
            <div class="mb-2">
                <span class="fw-bold">Cliente:</span>
                <div class="p-1 border rounded bg-light small"><?php echo $apellido . " " . $nombre; ?></div>
            </div>
            <div class="mb-2">
                <span class="fw-bold small">Detalle:</span>
                <div class="caja-texto small"><?php echo nl2br($detalle); ?></div>
            </div>
            <?php if(!empty($observaciones)): ?>
            <div class="mb-2">
                <span class="fw-bold small">Observaciones:</span>
                <div class="caja-obs small text-danger"><?php echo nl2br($observaciones); ?></div>
            </div>
            <?php endif; ?>
            <div class="row mt-3">
                <div class="col-6">
                    <table class="table table-sm table-bordered">
                        <tr><th class="table-light">Prometido</th><td><?php echo $prometido;?></td></tr>
                        <tr><th class="table-light">Recibió</th><td>Usuario <?php echo $idUsuario;?></td></tr>
                    </table>
                </div>
                <div class="col-6">
                    <table class="table table-sm table-bordered">
                        <tr><th class="table-light">Total</th><td class="text-end">$ <?php echo number_format($monto, 2);?></td></tr>
                        <tr class="<?php echo ($saldo <= 0.1) ? 'table-success' : 'table-warning'; ?>">
                            <th class="fw-bold">SALDO</th>
                            <td class="text-end <?php echo $claseSaldo; ?>"><?php echo $textoSaldo; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>
        /*
        // Auto-disparar impresión si viene el parámetro
        window.onload = function() {
            const params = new URLSearchParams(window.location.search);
            if (params.has('imprimir')) {
                setTimeout(() => { window.print(); }, 800);
            }
        };
        */
    </script>
</body>
</html>
