-- Insumos, etapa 3: lo que se gastó en cada pedido de venta, cargado desde la pizarra.
-- Va después de sql/2026-10-19_insumos_stock.sql. Se puede correr más de una vez.
--
-- Se puede cargar cualquier insumo menos el toner (se gasta por copias: sale del recuento).
-- Si el insumo controla stock, además se descuenta con un movimiento tipo 'pedido'
-- (idMovimiento). costoUnitario es el costo por unidad de uso del día en que se cargó
-- (el proveedor más barato), para saber después cuánto costó el pedido en insumos.

ALTER TABLE insumosMovimientos MODIFY tipo enum('compra','salida','inventario','pedido') NOT NULL;

CREATE TABLE IF NOT EXISTS pedidosInsumos (
    id            int NOT NULL AUTO_INCREMENT,
    idPedido      int NOT NULL,
    idInsumo      int NOT NULL,
    cantidad      int unsigned NOT NULL,           -- unidad de uso (hojas, rulos)
    costoUnitario decimal(12,4) DEFAULT NULL,      -- por unidad de uso; NULL si no tenía precio cargado
    idMovimiento  int DEFAULT NULL,                -- insumosMovimientos.id, si descontó stock
    fecha         datetime NOT NULL,
    idUsuario     int DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_pedido (idPedido),
    KEY idx_insumo (idInsumo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
