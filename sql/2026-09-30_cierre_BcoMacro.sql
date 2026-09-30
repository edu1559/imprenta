-- Las transferencias entran al Banco Macro: la columna del historial pasa a
-- llamarse igual que el medio (BcoMacro). 'suma' es GENERADA y depende de la
-- columna, así que se quita y se vuelve a crear con el nombre nuevo.
-- 'suma' ahora incluye invMacro, igual que el Total de la planilla de cierres.
ALTER TABLE cierre DROP COLUMN suma;
ALTER TABLE cierre RENAME COLUMN transferencia TO BcoMacro;
ALTER TABLE cierre ADD COLUMN suma DECIMAL(12,2) GENERATED ALWAYS AS (
    COALESCE(efectivo,0) + COALESCE(BcoMacro,0) + COALESCE(mercadoPago,0) + COALESCE(cheques,0)
  + COALESCE(invMacro,0) + COALESCE(dolares,0) + COALESCE(brubank,0) + COALESCE(naranjaX,0)
) STORED AFTER naranjaX;

-- El cierre total guarda cada medio en la columna con su mismo nombre.
UPDATE cierreMedios SET medio = 'invMacro' WHERE idMedio = 5;
