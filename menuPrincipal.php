<?php 
// No hace falta session_start() aquí si ya lo pusiste en el index.php
include_once('conexion.php');
$conn = conectarPDO();

// 1. Corregimos el ID de usuario para que use el de la sesión real
$idUsuario = isset($_SESSION['idUsuario']) ? $_SESSION['idUsuario'] : 1; // 1 = perfil invitado
?>

<nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand" href="index.php">
      <img src="imagenes/bandejaPintura.gif" class="rounded" width="40px" alt="Corintios">
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navContent">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php
        // Traemos el menú según el usuario
        $sql = "SELECT m.id, m.nombre, m.pagina, m.padre
                FROM menu m
                INNER JOIN permisos p ON m.id = p.idMenu
                WHERE p.idUsuario = :idUsuario AND m.activo = 1
                ORDER BY m.padre ASC, m.orden ASC";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':idUsuario', $idUsuario, PDO::PARAM_INT);
        $stmt->execute();
        $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($menuItems as $item) {
            if ($item['padre'] == null) {
                $hijos = array_filter($menuItems, function($h) use ($item) {
                    return $h['padre'] == $item['id'];
                });

                if (count($hijos) > 0) {
                    echo '<li class="nav-item dropdown">';
                    echo '  <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">' . ucfirst($item['nombre']) . '</a>';
                    echo '  <ul class="dropdown-menu border-0 shadow">';
                    foreach ($hijos as $hijo) {
                        echo '<li><a class="dropdown-item link-menu" href="#" data-pagina="' . $hijo['pagina'] . '">' . ucfirst($hijo['nombre']) . '</a></li>';
                    }
                    echo '  </ul></li>';
                } else {
                    echo '<li class="nav-item"><a class="nav-link link-menu" href="#" data-pagina="' . $item['pagina'] . '">' . ucfirst($item['nombre']) . '</a></li>';
                }
            }
        }
        ?>
      </ul>

      <div class="d-flex align-items-center">
        <div class="input-group input-group-sm me-3" style="width: 250px;">
          <input type="text" id="inpBusquedaGlobal" class="form-control" placeholder="Pedido # o Cliente">
          <button class="btn btn-outline-success" type="button" id="btnBusquedaGlobal">
            <i class="bi bi-search"></i>
          </button>
        </div>

        <div class="text-end me-2">
          <small class="d-block fw-bold text-success" style="line-height: 1;">
            <?php echo isset($_SESSION['nombre']) ? $_SESSION['nombre'] : "Visitante"; ?>
          </small>
          <small class="text-muted" style="font-size: 0.7rem;">
             <?php echo isset($_SESSION['apellido']) ? $_SESSION['apellido'] : ""; ?>
          </small>
        </div>
        
        <?php
        // CORRECCIÓN DE LA FOTO: Verificamos la variable correcta definida en ajaxIngreso
        $foto = (!empty($_SESSION['foto']) && file_exists($_SESSION['foto'])) 
                ? $_SESSION['foto'] 
                : "usuarios/inicio_bak.jpg"; 
        ?>
        <img src="<?php echo $foto; ?>?t=<?php echo time(); ?>" 
             class="rounded-circle border shadow-sm" 
             width="40px" height="40px" 
             style="object-fit: cover; background: #eee;">
      </div>
    </div>
  </div>
</nav>

<script>
$(document).ready(function() {
    // 1. Carga de Páginas del Menú
    $('.link-menu').off('click').click(function(e) {
        e.preventDefault();
        var pagina = $(this).data('pagina');
        if(pagina) {
            $('#contenido').fadeOut(100, function(){
                $(this).load(pagina).fadeIn(100);
            });
            window.history.pushState({}, '', 'index.php?pagina=' + pagina);
        }
    });

    // 2. Lógica de Búsqueda Global (Pedido o Cliente)
    $('#btnBusquedaGlobal').click(function() {
        let busqueda = $('#inpBusquedaGlobal').val();
        if(busqueda.length > 0) {
            // Mandamos la búsqueda a tu página de pedidos con un parámetro extra
            $('#contenido').load('pedidos/pedidos.php?buscar=' + busqueda);
        }
    });

    // Permitir buscar al presionar 'Enter'
    $('#inpBusquedaGlobal').keypress(function(e) {
        if(e.which == 13) { $('#btnBusquedaGlobal').click(); }
    });
});
</script>