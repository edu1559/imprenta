 <?php session_start(); 
 
// Si no existe la sesión, definimos valores por defecto para el visitante
if (!isset($_SESSION['idUsuario'])) {
    $_SESSION['idUsuario'] = 1; // ID 0 para visitantes
    $_SESSION['idPerfil'] = 2;  // Perfil 0 para visitantes
    $_SESSION['nombre'] = "Visitante";
}; 
 ?>
 <!DOCTYPE html>
 
<html lang="sp">

<head>
  <title>Imprenta Corintios</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- link a bootstrap -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"></script>
  <!-- link a jquery -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <!-- link a awesome -->
         <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
   <!-- hoja de estilo propia -->     
  <link href="proyectos.css" rel="stylesheet">
  <!-- iconos de bootstrap -->   
   <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">  
  <!-- Bootstrap Datepicker CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">

  <!-- Bootstrap Datepicker JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
  <!-- chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <!-- select2 -->
   <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />



</head>

<body>

<div id="menuPrincipal">
<?php 


// Si no existe la sesión, definimos valores por defecto para el visitante
if (!isset($_SESSION['idUsuario'])) {
    $_SESSION['idUsuario'] = 1; // ID 0 para visitantes
    $_SESSION['idPerfil'] = 2;  // Perfil 0 para visitantes
    $_SESSION['nombre'] = "Visitante";
}


include_once('menuPrincipal.php'); 




?></div>	

<script>
$(document).ready(function() {  
});
</script>

<div class="container-fluid p-3 bg-dark text-white text-center">

  <h1>Imprenta Corintios 13</h1>
  <p></p>
</div>

<div id="contenido">

 <?php 
 
 if(isset($_GET['pagina'])){
	$pagina = $_GET['pagina'];
 }else{
	$pagina = 'ingreso/bienvenida.php';
 };
	include_once($pagina);
 
 ?>
</div>
	<div id="mensaje" class="text-center fs-sm text-danger"></div>
 <footer class="container-fluid bg-black text-white p-3 text-center">
    <p>Imprenta Corintios13 - Luis Agote 2028 - corintios@imprentacorintios.com.ar</p>
 </footer>

<div class="modal fade" id="modalUniversal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
</div>

<!-- Modal secundario: historial de pedidos de un cliente. Vive aparte de
     #modalUniversal para poder abrirse ENCIMA de él (por ejemplo, mientras se
     está cargando un pedido nuevo) sin perder lo que ya se cargó en el otro. -->
<div class="modal fade" id="modalHistorialCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-body text-center p-5">
                <div class="spinner-border text-primary"></div>
            </div>
        </div>
    </div>
</div>

<script>
    // Punto único para abrir el historial de un cliente desde cualquier pantalla
    // (Pedidos, el combo de búsqueda, el modal de Nuevo Pedido, etc.)
    function abrirModalHistorial(idContacto) {
        $('#modalHistorialCliente .modal-content').html(
            '<div class="modal-body text-center p-5"><div class="spinner-border text-primary"></div></div>'
        );
        $('#modalHistorialCliente').modal('show');
        $('#modalHistorialCliente .modal-content').load('contactos/modalContactosPedidos.php?idContacto=' + idContacto);
    }

    // Bootstrap no apila z-index automáticamente cuando hay dos modales propios
    // abiertos a la vez (no es el mismo caso que un modal-de-modal nativo de BS5).
    // Si #modalHistorialCliente se abre con #modalUniversal ya abierto, lo subimos
    // por encima de su backdrop para que se vea como un modal "de segundo piso".
    $('#modalHistorialCliente').on('show.bs.modal', function () {
        if ($('#modalUniversal').hasClass('show')) {
            $(this).css('z-index', 1070);
            setTimeout(function () {
                $('.modal-backdrop').not('.backdrop-historial').last()
                    .addClass('backdrop-historial')
                    .css('z-index', 1065);
            }, 0);
        }
    });
    $('#modalHistorialCliente').on('hidden.bs.modal', function () {
        $('.modal-backdrop.backdrop-historial').remove();
        $(this).css('z-index', '');
    });
</script>

 <div class="modal fade" id="modalExpiracion" data-bs-backdrop="static" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center p-4">
                <i class="bi bi-exclamation-triangle text-warning display-4"></i>
                <h5 class="mt-3">Sesión Expirada</h5>
                <p class="text-muted small">Por seguridad, tu sesión se ha cerrado automáticamente por inactividad.</p>
                <a href="index.php?pagina=ingreso/ingreso.php" class="btn btn-primary w-100">Volver a Ingresar</a>
            </div>
        </div>
    </div>
</div>

<script>

    let tiempoInactividad = 40 * 60 * 1000; // 40 Minutos en milisegundos
    let timeoutSesion;

    function reiniciarContador() {
        clearTimeout(timeoutSesion);
        timeoutSesion = setTimeout(function() {
            // Cuando se cumple el tiempo, avisamos y cerramos
            $('#modalExpiracion').modal('show');
            // Opcional: Llamar a ajaxIngreso.php?opcion=cerrarSesion por detrás
        }, tiempoInactividad);
    }

    // Reiniciar cuando el usuario se mueve o hace click
    $(document).on('click mousemove keypress', function() {
        reiniciarContador();
    });

    reiniciarContador(); // Iniciar al cargar

   

</script>

</body>
</html> 
