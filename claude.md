# CLAUDE.md — Imprenta Corintios

## Resumen del proyecto
Aplicación web de gestión para una imprenta: productos y precios, clientes, pedidos/órdenes de trabajo, finanzas (caja, medios de pago, cierres), insumos y catálogo de productos.

## Stack
- Backend: PHP puro (sin framework)
- Base de datos: MySQL / MySQLi
- Frontend: Bootstrap 5, jQuery, Chart.js, Select2 — todo vía CDN, sin build step ni bundler
- Servidor local de desarrollo: Apache

## Estado de producción
- Dominio asignado: `imprentacorintios.com.ar` — **todavía no está en uso**.
- La versión que se usa actualmente en el día a día es una **versión vieja**, alojada en `http://www.imprentacorintios.com/imprenta1`. Esta carpeta de trabajo es una versión más nueva, todavía no promovida a ese dominio/uso real.
- Al proponer cambios, tener en cuenta que hay una versión en producción activa separada de este código — no asumir que este código es exactamente lo que los usuarios están usando hoy.

## Infraestructura y deploy
- Hosting: DigitalOcean, cuenta personal (acceso exclusivo del dueño del proyecto).
- Deploy: manual, vía `scp` desde línea de comandos, tanto desde la máquina de casa como desde la del trabajo (IUA).
- No hay CI/CD ni proceso automatizado de despliegue todavía.
- **No ejecutar comandos de deploy (scp, subida de archivos al hosting) sin confirmación explícita** — el destino es producción real.

## Estructura del proyecto
- `administracion/` — gestión de productos y precios
- `contactos/` — clientes, usuarios, perfiles y permisos
- `pedidos/` — órdenes de trabajo, PDFs de órdenes
- `finanzas/` — caja, medios de pago, cierres, indicadores
- `insumos/` — papeles/materiales
- `productos/` — catálogo de productos e imágenes
- `sistema/` — ayuda, editor interno, menú de navegación
- `ingreso/` — login/logout y manejo de sesión
- `sql/` — dumps y scripts SQL (esquema disperso en varios archivos, sin migraciones formales)
- `backup/` — copia antigua de módulos, no es código activo

## Cosas a evitar / advertencias
- `conexion.php` / `conexionImprenta.php` tienen credenciales hardcodeadas (`root/root`) — son solo para desarrollo local. **Nunca** subir credenciales reales de producción a git.
- Hay archivos `.bak` y duplicados con sufijos numéricos (`contactos1.php`, `pedidos1.php`, etc.) — antes de crear un archivo nuevo, verificar si ya existe una versión similar para no sumar más duplicados.
- (22/09/26) Se limpiaron ~19 archivos .php huérfanos (sin ninguna referencia real, verificado cruzando código y tabla `menu`): scripts de debug (`phpInfo.php`, `administracion/phpini.php`), el archivo suelto `administrcion` (typo, ya no existe), duplicados exactos (`sistema/fundamentos.php`/`menuNav.php`), y versiones viejas superadas de pedidos/finanzas/contactos. Antes de asumir que un archivo nuevo "no se usa", repetir esa verificación (grep de su nombre en todo el proyecto + su `pagina` en la tabla `menu`) en vez de guiarse solo por la fecha o el nombre.

## Convenciones (a definir/mantener)
- Evitar crear nuevos archivos con sufijos numéricos como forma de versionado; usar git para eso.
- Nombrar archivos de forma consistente con el módulo al que pertenecen.

## Notas de entorno
- Se trabaja desde dos máquinas (casa y trabajo en el IUA). El código se sincroniza vía git — este archivo debe mantenerse actualizado y comiteado junto con el código.