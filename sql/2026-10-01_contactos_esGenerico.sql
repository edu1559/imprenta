-- Marca los contactos genéricos: "Mostrador" (ventas a clientes sin identificar,
-- antes "AA Fotocopias y Libreria") y "Devoluciones". No son una persona ni una
-- empresa, y los scripts de limpieza no los fusionan ni los borran.
ALTER TABLE contactos ADD COLUMN esGenerico TINYINT(1) NOT NULL DEFAULT 0;
