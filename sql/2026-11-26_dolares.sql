-- Dólares en US$: el medio "dolares" lleva su saldo en US$ y se valúa en pesos con la cotización
-- cargada a mano (oficial, promedio entre compra y venta). Ver dolares.php.
--   cotizacionesDolar:            historial de cotizaciones
--   cierreMedios.usdUC/cotizacionUC: US$ contados y cotización del último cierre de cada medio
--                                   (solo se usan en dolares)
--   cierre.dolaresUsd/cotizacionDolar: lo mismo en el historial de cierres totales
-- Carga inicial: los $11.654.000 de dolares son US$ 7.800 (cotización 1494,1026). Solo si todavía
-- no hay US$ cargados. Se puede correr más de una vez.

CREATE TABLE IF NOT EXISTS cotizacionesDolar (
    id         int NOT NULL AUTO_INCREMENT,
    fecha      datetime NOT NULL,
    compra     decimal(12,2) DEFAULT NULL,
    venta      decimal(12,2) DEFAULT NULL,
    valor      decimal(12,4) NOT NULL,          -- $ por US$ (promedio entre compra y venta)
    nota       varchar(255) NOT NULL DEFAULT '',
    idUsuario  int NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cierreMedios' AND COLUMN_NAME = 'usdUC');
SET @sql := IF(@existe = 0,
               'ALTER TABLE cierreMedios ADD COLUMN usdUC decimal(12,2) DEFAULT NULL, ADD COLUMN cotizacionUC decimal(12,4) DEFAULT NULL',
               'SELECT ''cierreMedios.usdUC ya existe''');
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;

SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cierre' AND COLUMN_NAME = 'dolaresUsd');
SET @sql := IF(@existe = 0,
               'ALTER TABLE cierre ADD COLUMN dolaresUsd decimal(12,2) DEFAULT NULL, ADD COLUMN cotizacionDolar decimal(12,4) DEFAULT NULL',
               'SELECT ''cierre.dolaresUsd ya existe''');
PREPARE s FROM @sql;
EXECUTE s;
DEALLOCATE PREPARE s;

-- carga inicial (una sola vez)
UPDATE cierreMedios SET usdUC = 7800, cotizacionUC = ROUND(montoUC / 7800, 4)
WHERE medio = 'dolares' AND usdUC IS NULL;

INSERT INTO cotizacionesDolar (fecha, compra, venta, valor, nota, idUsuario)
SELECT NOW(), NULL, NULL, cm.cotizacionUC, 'Carga inicial: el saldo en pesos dividido US$ 7.800', 0
FROM cierreMedios cm
WHERE cm.medio = 'dolares' AND cm.cotizacionUC IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM cotizacionesDolar);
