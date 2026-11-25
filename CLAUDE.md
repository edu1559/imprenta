# CLAUDE.md — Imprenta Corintios

## Resumen del proyecto
Aplicación web de gestión para una imprenta: productos y precios, clientes, pedidos/órdenes de trabajo, finanzas (caja, medios de pago, cierres), insumos y catálogo de productos.

## Stack
- Backend: PHP puro (sin framework)
- Base de datos: MySQL / MySQLi
- Frontend: Bootstrap 5, jQuery, Chart.js, Select2 — todo vía CDN, sin build step ni bundler
- Servidor local de desarrollo: Apache

## Estado de producción
- **En producción desde el 05/10/2026** en `imprentacorintios.com.ar`; los empleados lo usan a diario. Este código (rama `main`) es lo que corre en el servidor: salvo `conexion.php`, el código de producción coincide con HEAD una vez desplegado cada commit.
- La versión vieja (`www.imprentacorintios.com/imprenta1`) ya no es la de uso diario.
- Todo cambio afecta a usuarios reales: arreglos chicos y acotados, nada de refactors o renombres grandes sin preguntar.
- **La base de producción es la fuente de verdad.** La base local es una copia (dump de prod) con datos reales: nunca subir un dump local a producción, y tras pruebas locales borrar solo las filas creadas por la prueba.

## Infraestructura y deploy
- Hosting: droplet de DigitalOcean (Ubuntu, Apache, PHP 8.3), cuenta personal del dueño del proyecto. Acceso: `ssh root@imprentacorintios.com.ar`; la app está en `/var/www/html/imprenta`.
- **En el servidor no hay git**: el código se sube como archivos (scp/tar por ssh), desde la máquina de casa o la del IUA. No hay CI/CD.
- **No ejecutar nada que escriba en producción (subir archivos, correr SQL o scripts) sin confirmación explícita cada vez.**
- Procedimiento de deploy: respaldar antes en el servidor (`/root/respaldos/<fecha>/<tema>/`), subir solo los archivos del commit, dejarlos `www-data:www-data` 644 (tar por ssh los deja como root) y verificar con md5 contra el local.
- `conexion.php` del servidor tiene las credenciales reales: **nunca sobrescribirlo**.
- Cambios de esquema: scripts fechados en `sql/` (`AAAA-MM-DD_tema.sql`), se corren una sola vez en prod; los que no son idempotentes no deben volver a correrse. Cambios de datos: scripts CLI con dry-run primero y `--aplicar` después del OK.
- El PHP de producción **no tiene mbstring ni gd**: no usar funciones `mb_*` ni de imágenes GD.
- `fotoUsuarios/` debe ser escribible por Apache (`www-data`).

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