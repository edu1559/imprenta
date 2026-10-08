-- Índice por pedido en pagos: casi todas las consultas buscan los pagos de un pedido
-- (saldo, detalle de pagos, Finanzas, cierre) y sin índice recorrían la tabla entera.
-- InnoDB lo crea sin bloquear la tabla: se puede correr con el sistema en uso.
ALTER TABLE pagos ADD INDEX idx_pedido (idPedido), ALGORITHM=INPLACE, LOCK=NONE;
