<?php
// Control de sesión compartido. index.php asigna el usuario 1 ("visitante")
// cuando nadie ingresó; eso NO cuenta como trabajador logueado.

if (session_status() === PHP_SESSION_NONE) session_start();

const ID_USUARIO_VISITANTE = 1;
const ID_PERFIL_VISITANTE = 2;
const ID_PERFIL_CLIENTE = 3;

// Solo estos perfiles son trabajadores de la imprenta (1 Administrador, 4 Trabajador).
// Un cliente que ingresa por la web (perfil 3) NO es trabajador: no ve el sistema interno.
const PERFILES_TRABAJADOR = [1, 4];

// Lo único que puede ver quien no ingresó. Todo lo demás exige un trabajador logueado.
const PAGINAS_PUBLICAS = ['ingreso/bienvenida.php', 'ingreso/ingreso.php', 'ingreso/contactenos.php',
                          'contactenos.php', 'productos/productos.php', 'productos/imagenesProductos.php'];

// Las claves se guardan con password_hash() y se comprueban con password_verify()
// (ingreso/ajaxIngreso.php). Las nuevas tienen que tener al menos este largo.
const LARGO_MINIMO_CLAVE = 6;

function errorClaveNueva($clave) {
    return preg_match_all('/./su', $clave) < LARGO_MINIMO_CLAVE   // caracteres, no bytes (sin depender de mbstring)
        ? "La clave tiene que tener al menos " . LARGO_MINIMO_CLAVE . " caracteres."
        : null;
}

function usuarioLogueado() {
    $id = (int)($_SESSION['idUsuario'] ?? 0);
    $perfil = (int)($_SESSION['idPerfil'] ?? ID_PERFIL_VISITANTE);
    return $id > 0 && $id !== ID_USUARIO_VISITANTE && in_array($perfil, PERFILES_TRABAJADOR, true);
}

function clienteLogueado() {
    $id = (int)($_SESSION['idUsuario'] ?? 0);
    return $id > 0 && $id !== ID_USUARIO_VISITANTE && (int)($_SESSION['idPerfil'] ?? 0) === ID_PERFIL_CLIENTE;
}

// Para archivos ajax: sin trabajador logueado se corta con 401.
// index.php captura el 401 y muestra el aviso de sesión expirada.
function exigirTrabajadorAjax() {
    if (usuarioLogueado()) return;
    http_response_code(401);
    echo "⛔ Tu sesión expiró o no ingresaste. Volvé a ingresar para continuar.";
    exit;
}

// Para páginas y modales: sin trabajador logueado se muestra un aviso en su lugar.
function exigirTrabajadorPagina() {
    if (usuarioLogueado()) return;
    avisoSoloTrabajadores();
    exit;
}

function avisoSoloTrabajadores() {
    if (clienteLogueado()) {
        echo '<div class="container my-5"><div class="alert alert-warning text-center p-4">
                <i class="bi bi-lock fs-1 d-block mb-2"></i>
                No tenés acceso a esta sección. Consultanos si lo necesitás.
              </div></div>';
        return;
    }
    echo '<div class="container my-5"><div class="alert alert-warning text-center p-4">
            <i class="bi bi-lock fs-1 d-block mb-2"></i>
            Esta sección es solo para trabajadores de la imprenta.<br>
            <a href="index.php?pagina=ingreso/ingreso.php" class="btn btn-primary mt-3">Ingresar</a>
          </div></div>';
}

// Páginas del sitio de clientes (perfil 3): todo lo que está en esta carpeta. Cada una filtra
// por $_SESSION['idUsuario'], que es el id del contacto del cliente (usuarios.id = contactos.id).
const CARPETA_CLIENTES = 'clientes/';

// Páginas que el usuario de la sesión tiene en sus permisos de menú (activas). Se leen de la
// base una vez por pedido, así un cambio de permisos rige al instante.
function paginasConPermiso() {
    static $paginas = null;
    if ($paginas === null) {
        include_once(__DIR__ . '/conexion.php');
        $stmt = conectarPDO()->prepare("SELECT m.pagina FROM permisos p INNER JOIN menu m ON m.id = p.idMenu
                                        WHERE p.idUsuario = ? AND m.activo = 1");
        $stmt->execute([(int)($_SESSION['idUsuario'] ?? 0)]);
        $paginas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    return $paginas;
}

// Quién puede abrir una página que se carga en index.php: el trabajador todas; el cliente las
// públicas y las de clientes/ que tenga en sus permisos; quien no ingresó, solo las públicas.
// Un cliente nunca abre una página interna, aunque por error se le dé el permiso. El menú usa
// lo mismo, así no muestra un enlace que después no se puede abrir.
function puedeVerPagina($pagina) {
    if (usuarioLogueado() || in_array($pagina, PAGINAS_PUBLICAS, true)) return true;
    return clienteLogueado() && str_starts_with($pagina, CARPETA_CLIENTES)
        && in_array($pagina, paginasConPermiso(), true);
}

// Para los ajax de clientes/: hace falta un cliente logueado con permiso a la página
// desde la que se usa (así, sacarle la página del menú también le saca la acción).
function exigirClienteAjax($pagina) {
    if (clienteLogueado() && puedeVerPagina($pagina)) return;
    http_response_code(401);
    echo "⛔ Tu sesión expiró o no tenés acceso. Volvé a ingresar para continuar.";
    exit;
}

// Para las páginas de clientes/, con su propio __FILE__ (se cargan desde index.php o sueltas
// desde el menú, así que no alcanza con lo que controla index.php).
function exigirClientePagina($archivo) {
    $pagina = CARPETA_CLIENTES . basename($archivo);
    if (clienteLogueado() && puedeVerPagina($pagina)) return;
    if (clienteLogueado()) {
        avisoSoloTrabajadores();
    } else {
        echo '<div class="container my-5"><div class="alert alert-warning text-center p-4">
                <i class="bi bi-lock fs-1 d-block mb-2"></i>
                Para ver tus pedidos tenés que ingresar con tu usuario.<br>
                <a href="index.php?pagina=ingreso/ingreso.php" class="btn btn-primary mt-3">Ingresar</a>
              </div></div>';
    }
    exit;
}
