-- Insumos, etapa 2: stock. Va después de sql/2026-10-19_insumos.sql.
--
-- El stock sale de los movimientos: compras (entran), salidas sueltas y ajustes de inventario
-- (se cuenta lo que hay y se registra la diferencia). Lo consumido en un período es lo que
-- compró menos lo que aumentó el stock. insumos.stockActual es la suma de los movimientos,
-- guardada para no sumar cada vez; se actualiza en la misma transacción que el movimiento.
-- Todo en unidad de uso (hojas): es lo que después va a descontar un pedido. Las pantallas
-- lo muestran en unidad de compra más sueltas (12 resmas y 250 hojas).
-- Aplicar una sola vez (el ALTER no se puede repetir; el resto sí).

ALTER TABLE insumos
    ADD COLUMN controlaStock tinyint(1) NOT NULL DEFAULT 0 AFTER unidadUso,
    ADD COLUMN stockMinimo   int unsigned DEFAULT NULL AFTER controlaStock,   -- en unidad de uso; NULL = sin aviso
    ADD COLUMN stockActual   int NOT NULL DEFAULT 0 AFTER stockMinimo;

-- Por ahora se controlan papeles y toner.
UPDATE insumos i INNER JOIN insumosCategorias c ON c.id = i.idCategoria
    SET i.controlaStock = 1
    WHERE c.nombre IN ('Papeles', 'Toner');

CREATE TABLE IF NOT EXISTS insumosMovimientos (
    id            int NOT NULL AUTO_INCREMENT,
    idInsumo      int NOT NULL,
    fecha         datetime NOT NULL,
    tipo          enum('compra','salida','inventario') NOT NULL,
    cantidad      int NOT NULL,                    -- unidad de uso; + entra, − sale (en inventario, la diferencia)
    contado       int DEFAULT NULL,                -- inventario: lo que se contó
    idPedido      int DEFAULT NULL,                -- compra: el pedido de compra (tipo 2), si se sabe
    idProveedor   int DEFAULT NULL,                -- compra: contactos.id
    observaciones varchar(255) DEFAULT NULL,
    idUsuario     int DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_insumo (idInsumo, id),
    KEY idx_pedido (idPedido)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Página "stock" dentro del grupo insumos, para administradores y trabajadores (se saca desde Permisos).
SET @grupo = (SELECT id FROM menu WHERE padre IS NULL AND nombre = 'insumos' ORDER BY id LIMIT 1);
INSERT INTO menu (nombre, pagina, padre, orden, activo)
    SELECT 'stock', 'insumos/stock.php', @grupo, 2, 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM menu WHERE padre = @grupo AND pagina = 'insumos/stock.php');
UPDATE menu SET orden = 3 WHERE padre = @grupo AND pagina = 'insumos/maquinas.php';

INSERT IGNORE INTO perfilesMenu (idPerfil, idMenu)
    SELECT pf.id, m.id FROM perfiles pf INNER JOIN menu m ON m.pagina = 'insumos/stock.php'
    WHERE pf.id IN (1, 4);
INSERT IGNORE INTO permisos (idUsuario, idMenu)
    SELECT u.id, m.id FROM usuarios u INNER JOIN menu m ON m.pagina = 'insumos/stock.php'
    WHERE u.idPerfil IN (1, 4);
