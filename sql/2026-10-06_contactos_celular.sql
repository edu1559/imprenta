-- Celular para WhatsApp, guardado como 549 + característica + número (ver celular.php).
-- telefono queda como texto libre (fijos, internos, notas) y se amplía porque 15
-- caracteres cortaba números; correo se amplía porque 35 cortaba direcciones.
-- Se mantiene latin1 como el resto de la tabla.
ALTER TABLE contactos
    ADD COLUMN celular VARCHAR(20) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL AFTER telefono,
    MODIFY telefono VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
    MODIFY correo VARCHAR(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
    ADD INDEX idx_celular (celular);
