-- Medio de pago para cerrar pedidos viejos de los que no quedó registrado el pago
-- (sql/limpieza/cerrarPedidosViejos.php). No es plata real: Finanzas no lo cuenta
-- como ingreso ni egreso, y va oculto para que no se pueda elegir al cobrar.
INSERT INTO mediosPago (medio, logo, visible) VALUES ('sinRegistro', '', 0);
