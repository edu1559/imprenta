-- Marca los contactos que son una empresa o institución (no una persona).
-- Reemplaza la idea de usar 'tipo', que en imprenta1 era 1=cliente / 2=proveedor
-- y ya no se usa: un mismo contacto puede ser las dos cosas.
-- En una empresa la razón social va en 'apellido' y 'nombre' es la persona de contacto.
ALTER TABLE contactos ADD COLUMN esEmpresa TINYINT(1) NOT NULL DEFAULT 0;
