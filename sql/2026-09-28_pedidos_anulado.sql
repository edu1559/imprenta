-- Pedidos anulados: quedan en la base pero no aparecen en listados ni en Finanzas.
-- El motivo y el autor quedan en la tabla modificaciones (accion = 'anular').
ALTER TABLE pedidos ADD COLUMN anulado TINYINT(1) NOT NULL DEFAULT 0;
