-- Pedidos web: los hace un cliente desde clientes/nuevoPedido.php y la imprenta los aprueba
-- o los rechaza desde Pedidos. Va después de sql/2026-10-14_menu_clientes.sql.
--
-- estadoWeb: NULL = cargado por la imprenta (todos los anteriores), 1 = pendiente de revisión,
--            2 = aprobado, 3 = rechazado (además queda anulado = 1, así ningún reporte lo cuenta).
-- notaCliente: lo que la imprenta le dice al cliente (motivo del rechazo, o un aviso al aprobar).
--              A diferencia de observaciones, el cliente SÍ la ve.
-- Mientras está pendiente, el pedido tiene monto 0 y los tres estados en 0: no aparece en la
-- pizarra ni en los filtros de pendientes, y no toca caja ni saldos.
-- Aplicar una sola vez (el ALTER no se puede repetir; el resto sí).

ALTER TABLE pedidos
    ADD COLUMN estadoWeb TINYINT DEFAULT NULL AFTER anulado,
    ADD COLUMN notaCliente VARCHAR(255) DEFAULT NULL AFTER estadoWeb,
    ADD INDEX idx_pedidos_estadoWeb (estadoWeb);

INSERT INTO origen (origen) SELECT 'web' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM origen WHERE origen = 'web');

-- Las dos páginas nuevas, dentro del grupo "mis pedidos".
SET @grupo = (SELECT id FROM menu WHERE pagina = 'clientes/misPedidos.php' AND padre IS NULL ORDER BY id LIMIT 1);
INSERT INTO menu (nombre, pagina, padre, orden, activo)
    SELECT 'nuevo pedido', 'clientes/nuevoPedido.php', @grupo, 2, 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM menu WHERE pagina = 'clientes/nuevoPedido.php');
INSERT INTO menu (nombre, pagina, padre, orden, activo)
    SELECT 'pendientes de aprobación', 'clientes/pendientes.php', @grupo, 3, 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM menu WHERE pagina = 'clientes/pendientes.php');

-- Al perfil "cliente" y a los clientes que ya tienen usuario.
INSERT IGNORE INTO perfilesMenu (idPerfil, idMenu)
    SELECT 3, id FROM menu WHERE pagina IN ('clientes/nuevoPedido.php', 'clientes/pendientes.php');
INSERT IGNORE INTO permisos (idUsuario, idMenu)
    SELECT u.id, m.id FROM usuarios u
    INNER JOIN menu m ON m.pagina IN ('clientes/nuevoPedido.php', 'clientes/pendientes.php')
    WHERE u.idPerfil = 3;
