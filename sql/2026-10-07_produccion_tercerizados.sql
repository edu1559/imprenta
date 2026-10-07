-- Trabajos que se mandan a otros proveedores (produccion/tercerizados.php). Cada fila es
-- una parte de un trabajo (el plastificado de las tapas, el encuadernado), no el pedido
-- entero; idPedido queda vacío cuando no es de un pedido puntual (chapas de offset).
-- El estado sale de las fechas: sin fechaEnviado está para llevar, después en el
-- proveedor, listo para retirar y recibido. "pagado" es solo un tilde: el pago se sigue
-- cargando como compra. Necesita sql/2026-10-07_produccion_pizarra.sql (menú 46).
CREATE TABLE tercerizados (
    id INT NOT NULL AUTO_INCREMENT,
    idPedido INT DEFAULT NULL,
    idProveedor INT NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    precio DECIMAL(12,2) DEFAULT NULL,
    fechaCarga DATETIME NOT NULL,
    idUsuario INT NOT NULL,
    fechaEnviado DATETIME DEFAULT NULL,
    fechaPrometida DATE DEFAULT NULL,
    fechaListo DATETIME DEFAULT NULL,
    fechaRecibido DATETIME DEFAULT NULL,
    pagado TINYINT(1) NOT NULL DEFAULT 0,
    fechaPagado DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_pedido (idPedido),
    KEY idx_proveedor (idProveedor)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Proveedores que se ofrecen al cargar un trabajo: los contactos con este tilde (se
-- pone en la edición del contacto). Arrancan marcados los cuatro habituales: Bold,
-- Garini, Te Imprimo Yo y M&M Encuadernaciones.
ALTER TABLE contactos ADD COLUMN esTercerizado TINYINT(1) NOT NULL DEFAULT 0 AFTER esGenerico;
UPDATE contactos SET esTercerizado = 1 WHERE id IN (12697, 6016, 11651, 2444);

UPDATE menu SET orden = 3 WHERE id = 48;
INSERT INTO menu (id, nombre, pagina, padre, orden, activo) VALUES
    (49, 'tercerizados', 'produccion/tercerizados.php', 46, 2, 1);

INSERT INTO permisos (idMenu, idUsuario)
    SELECT 49, u.id FROM usuarios u WHERE u.id <> 1;
