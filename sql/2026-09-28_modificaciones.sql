-- Registro de auditoría de pedidos y pagos (quién borró/modificó qué y por qué)
-- y permiso por usuario para hacerlo. Aplicar una sola vez.

CREATE TABLE IF NOT EXISTS modificaciones (
  id              INT NOT NULL AUTO_INCREMENT,
  fecha           DATETIME NOT NULL,
  idUsuario       INT NOT NULL,
  entidad         VARCHAR(10) NOT NULL,      -- 'pedido' | 'pago' | 'cierre'
  idEntidad       INT NOT NULL,              -- id del pedido, pago o cierre tocado
  idPedido        INT DEFAULT NULL,          -- pedido relacionado (para buscar todo lo de un pedido)
  accion          VARCHAR(20) NOT NULL,      -- 'borrar' | 'modificarMonto' | 'cambiarMedio' | 'ajuste' | 'anular' | 'reabrir' | ...
  valorAnterior   TEXT DEFAULT NULL,
  valorNuevo      TEXT DEFAULT NULL,
  motivo          VARCHAR(255) NOT NULL,
  PRIMARY KEY (id),
  KEY idx_modificaciones_pedido (idPedido),
  KEY idx_modificaciones_entidad (entidad, idEntidad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Los administradores (idPerfil = 1) siempre pueden; esta marca habilita a trabajadores puntuales.
ALTER TABLE usuarios ADD COLUMN puedeModificar TINYINT(1) NOT NULL DEFAULT 0;
