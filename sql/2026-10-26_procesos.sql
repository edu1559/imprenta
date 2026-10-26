-- Procesos, etapa 1: catálogo de procesos (impresión, corte, plastificado, diseño...) con su
-- precio de venta por cantidad y su costo calculado. Va después de los scripts de insumos.
-- Aplicar una sola vez (el ALTER de maquinas no se puede repetir; el resto sí).
--
-- Un proceso es corto: usa 0 o 1 máquina y se cobra por una unidad (cara, hoja, m², corte,
-- trabajo...). La impresión no lleva papel: el papel se elige aparte (en el presupuestador).
-- El toner tampoco es un insumo del proceso: sale de la máquina (precio / rendimiento).
--
-- Costo = mano de obra + insumos + máquina:
--   fijo       = tiempoPreparacion × valorHora × factor de complejidad
--   por unidad = tiempoPorUnidad × valorHora × factor + insumos + costoPorUso de la máquina + toner

-- Desgaste, service, contador: lo que cuesta cada uso (copia, corte, hoja) aparte del toner.
ALTER TABLE maquinas
    ADD COLUMN costoPorUso decimal(12,4) DEFAULT NULL AFTER modelo;

-- La complejidad o el riesgo del trabajo encarece la mano de obra (trabajos críticos que
-- exigen una concentración especial). Cada proceso tiene un nivel por defecto; al presupuestar
-- se va a poder subir para un trabajo puntual.
CREATE TABLE IF NOT EXISTS complejidades (
    id     int NOT NULL AUTO_INCREMENT,
    nombre varchar(30) NOT NULL,
    factor decimal(4,2) NOT NULL DEFAULT 1.00,      -- multiplica el valor hora
    orden  int NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO complejidades (nombre, factor, orden) VALUES
    ('Normal', 1.00, 1),
    ('Delicado', 1.30, 2),
    ('Crítico', 1.60, 3);

CREATE TABLE IF NOT EXISTS procesos (
    id                int NOT NULL AUTO_INCREMENT,
    nombre            varchar(80) NOT NULL,         -- "Impresión color A4", "Plastificado A3"
    tipo              varchar(30) DEFAULT NULL,     -- impresión, corte, terminación, diseño (para agrupar)
    idMaquina         int DEFAULT NULL,
    toner             enum('no','negro','todos') NOT NULL DEFAULT 'no',  -- qué toner de la máquina gasta
    unidad            varchar(20) NOT NULL,         -- cara, hoja, m², unidad, corte, trabajo, hora
    valorHora         decimal(12,2) DEFAULT NULL,   -- mano de obra de este proceso
    idComplejidad     int NOT NULL,                 -- nivel por defecto
    tiempoPreparacion decimal(8,2) NOT NULL DEFAULT 0,   -- minutos, una vez por trabajo
    tiempoPorUnidad   decimal(10,4) NOT NULL DEFAULT 0,  -- minutos por unidad
    ventaSuelta       tinyint(1) NOT NULL DEFAULT 1,     -- se le vende solo al cliente (si no, solo dentro de productos)
    fechaPrecios      date DEFAULT NULL,            -- última vez que se tocaron los precios
    observaciones     varchar(255) DEFAULT NULL,
    activo            tinyint(1) NOT NULL DEFAULT 1,
    fechaCarga        datetime NOT NULL,
    idUsuario         int DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_maquina (idMaquina)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Precio de venta por tramos de cantidad; cada tramo es una recta:
--   total = arranque + porUnidad × cantidad   (para cantidades desde "desde" hasta el tramo siguiente)
-- Un proceso simple tiene un solo tramo desde 1. El primer tramo siempre arranca en 1.
CREATE TABLE IF NOT EXISTS procesosPrecios (
    id        int NOT NULL AUTO_INCREMENT,
    idProceso int NOT NULL,
    desde     int unsigned NOT NULL,
    arranque  decimal(12,2) NOT NULL DEFAULT 0,
    porUnidad decimal(12,4) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tramo (idProceso, desde)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lo que gasta el proceso por cada unidad suya (film por hoja plastificada, rulo por anillado),
-- en unidad de uso del insumo. Ni papel ni toner: el papel se elige aparte y el toner sale de la máquina.
CREATE TABLE IF NOT EXISTS procesosInsumos (
    id        int NOT NULL AUTO_INCREMENT,
    idProceso int NOT NULL,
    idInsumo  int NOT NULL,
    cantidad  decimal(10,4) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_insumo (idProceso, idInsumo),
    KEY idx_insumo (idInsumo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Página "procesos" dentro del grupo insumos, para administradores y trabajadores (se saca desde Permisos).
SET @grupo = (SELECT id FROM menu WHERE padre IS NULL AND nombre = 'insumos' ORDER BY id LIMIT 1);
INSERT INTO menu (nombre, pagina, padre, orden, activo)
    SELECT 'procesos', 'insumos/procesos.php', @grupo, 4, 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM menu WHERE padre = @grupo AND pagina = 'insumos/procesos.php');

INSERT IGNORE INTO perfilesMenu (idPerfil, idMenu)
    SELECT pf.id, m.id FROM perfiles pf INNER JOIN menu m ON m.pagina = 'insumos/procesos.php'
    WHERE pf.id IN (1, 4);
INSERT IGNORE INTO permisos (idUsuario, idMenu)
    SELECT u.id, m.id FROM usuarios u INNER JOIN menu m ON m.pagina = 'insumos/procesos.php'
    WHERE u.idPerfil IN (1, 4);
