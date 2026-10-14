-- Sitio de clientes: grupo de menú "mis pedidos" con las páginas de clientes/ (por ahora
-- solo la lista de pedidos; después, nuevo pedido y pendientes de aprobación).
-- Lo que ve un cliente sale de sus permisos (permisos), que al crear el usuario se copian
-- del perfil "cliente" (perfilesMenu, idPerfil 3). Un cliente igual nunca abre una página
-- interna aunque se le dé el permiso (ver puedeVerPagina en sesion.php).
-- Se puede correr más de una vez: no duplica nada.

-- Grupo y página. Va después de "inicio"; los trabajadores no lo tienen en sus permisos.
INSERT INTO menu (nombre, pagina, padre, orden, activo)
    SELECT 'mis pedidos', 'clientes/misPedidos.php', NULL, 2, 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM menu WHERE pagina = 'clientes/misPedidos.php' AND padre IS NULL);
SET @grupo = (SELECT id FROM menu WHERE pagina = 'clientes/misPedidos.php' AND padre IS NULL ORDER BY id LIMIT 1);

INSERT INTO menu (nombre, pagina, padre, orden, activo)
    SELECT 'pedidos hechos', 'clientes/misPedidos.php', @grupo, 1, 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM menu WHERE pagina = 'clientes/misPedidos.php' AND padre = @grupo);

-- Perfil "cliente": inicio (bienvenida y contáctenos) y el grupo mis pedidos.
INSERT IGNORE INTO perfilesMenu (idPerfil, idMenu)
    SELECT 3, id FROM menu
    WHERE (padre IS NULL AND pagina = 'bienvenida.php')
       OR pagina IN ('ingreso/bienvenida.php', 'ingreso/contactenos.php', 'clientes/misPedidos.php');

-- Los clientes que ya tienen usuario reciben el grupo nuevo (sin tocar el resto de sus permisos).
INSERT IGNORE INTO permisos (idUsuario, idMenu)
    SELECT u.id, m.id FROM usuarios u INNER JOIN menu m ON m.pagina = 'clientes/misPedidos.php'
    WHERE u.idPerfil = 3;

-- "productos" (el catálogo público) estaba bajo el grupo "productos", que está desactivado,
-- y no se veía en ningún menú. Pasa a "inicio", al final, y lo ven también los clientes.
SET @inicio = (SELECT id FROM menu WHERE padre IS NULL AND pagina = 'bienvenida.php' ORDER BY id LIMIT 1);
UPDATE menu SET padre = @inicio,
                orden = (SELECT o FROM (SELECT COALESCE(MAX(orden), 0) + 1 o FROM menu WHERE padre = @inicio) x)
    WHERE pagina = 'productos/productos.php' AND padre <> @inicio;

INSERT IGNORE INTO perfilesMenu (idPerfil, idMenu)
    SELECT 3, id FROM menu WHERE pagina = 'productos/productos.php';
INSERT IGNORE INTO permisos (idUsuario, idMenu)
    SELECT u.id, m.id FROM usuarios u INNER JOIN menu m ON m.pagina = 'productos/productos.php'
    WHERE u.idPerfil = 3;
