-- El detalle del pedido pasa de 255 a 1000 caracteres: guardar uno más largo
-- fallaba con "Data too long for column 'detalle'" y el pedido no se cargaba.
-- Se mantiene latin1 como el resto de la tabla. MySQL reescribe la tabla (unos
-- segundos): mientras tanto se pueden leer pedidos pero no guardarlos.
ALTER TABLE pedidos MODIFY detalle VARCHAR(1000) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL;
