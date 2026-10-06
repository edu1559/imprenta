<?php
// Control de sesión compartido. index.php asigna el usuario 1 ("visitante")
// cuando nadie ingresó; eso NO cuenta como trabajador logueado.

if (session_status() === PHP_SESSION_NONE) session_start();

const ID_USUARIO_VISITANTE = 1;
const ID_PERFIL_VISITANTE = 2;

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
    return $id > 0 && $id !== ID_USUARIO_VISITANTE && $perfil !== ID_PERFIL_VISITANTE;
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
    echo '<div class="container my-5"><div class="alert alert-warning text-center p-4">
            <i class="bi bi-lock fs-1 d-block mb-2"></i>
            Esta sección es solo para trabajadores de la imprenta.<br>
            <a href="index.php?pagina=ingreso/ingreso.php" class="btn btn-primary mt-3">Ingresar</a>
          </div></div>';
}
