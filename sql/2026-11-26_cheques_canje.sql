-- Cheques sin pedido: cómo entró cada cheque.
--   pago    = cobro de un pedido (todos los anteriores)
--   canje   = un contacto nos dio el cheque y le dimos el mismo monto en efectivo/transferencia
--             (idMovimientoEntrada: movimientosMedios medio -> cheques)
--   inicial = ya estaba en la imprenta (carga inicial): no mueve plata
-- La salida por canje usa las columnas que ya están: idContactoDestino + idMedioDestino + idMovimiento.
-- Se puede correr más de una vez.

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cheques' AND COLUMN_NAME = 'origen');
SET @sql := IF(@existe = 0,
               'ALTER TABLE cheques ADD COLUMN origen enum(''pago'',''canje'',''inicial'') NOT NULL DEFAULT ''pago'' AFTER tipo,
                                    ADD COLUMN idMovimientoEntrada int DEFAULT NULL AFTER idContactoEntrega',
               'SELECT ''cheques.origen ya existe''');
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;
