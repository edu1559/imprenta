-- Insumos, etapa 1: catálogo de insumos por categoría, precios por proveedor y máquinas.
-- Reemplaza la tabla papeles (se copian sus filas a insumos; la tabla vieja queda sin uso
-- hasta borrarla más adelante). El stock (movimientos, mínimos, inventario) es la etapa 2.
-- Se puede correr más de una vez: no duplica nada.

-- Máquinas de la imprenta. El toner se engancha a la máquina en la que se usa; más adelante
-- los procesos (costo por copia, etc.) también van a usar esta tabla.
CREATE TABLE IF NOT EXISTS maquinas (
    id            int NOT NULL AUTO_INCREMENT,
    tipo          varchar(30) NOT NULL,            -- fotocopiadora, impresora, abrochadora, intercaladora...
    nombre        varchar(50) NOT NULL,            -- como la llaman en la imprenta
    marca         varchar(50) DEFAULT NULL,
    modelo        varchar(50) DEFAULT NULL,
    observaciones varchar(255) DEFAULT NULL,
    activo        tinyint(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ficha: qué datos técnicos pide el formulario para los insumos de la categoría.
CREATE TABLE IF NOT EXISTS insumosCategorias (
    id     int NOT NULL AUTO_INCREMENT,
    nombre varchar(40) NOT NULL,
    ficha  enum('papel','toner','encuadernado','general') NOT NULL DEFAULT 'general',
    orden  int NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uk_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO insumosCategorias (nombre, ficha, orden) VALUES
    ('Papeles', 'papel', 1),
    ('Tapas', 'papel', 2),
    ('Toner', 'toner', 3),
    ('Encuadernado', 'encuadernado', 4),
    ('Varios', 'general', 5);

-- Se compra en unidadCompra (Resma, Caja, Cartucho) que trae unidadesPorCompra de unidadUso
-- (500 hojas, 100 rulos). El precio se carga por unidadCompra; el costo por unidadUso sale de
-- dividir, y es lo que van a usar los presupuestadores.
CREATE TABLE IF NOT EXISTS insumos (
    id                int NOT NULL AUTO_INCREMENT,
    idCategoria       int NOT NULL,
    nombre            varchar(80) NOT NULL,
    tipo              varchar(40) DEFAULT NULL,    -- obra, ilustración, autoadhesivo / plástico, metálico...
    largo             int unsigned DEFAULT NULL,   -- mm (papeles y tapas)
    ancho             int unsigned DEFAULT NULL,   -- mm
    gramaje           int unsigned DEFAULT NULL,   -- g/m²
    color             varchar(30) DEFAULT NULL,
    idMaquina         int DEFAULT NULL,            -- toner
    rendimiento       int unsigned DEFAULT NULL,   -- copias por cartucho (toner)
    medida            varchar(30) DEFAULT NULL,    -- rulos: 12 mm, 3/8...
    unidadCompra      varchar(20) NOT NULL DEFAULT 'Unidad',
    unidadesPorCompra int unsigned NOT NULL DEFAULT 1,
    unidadUso         varchar(20) NOT NULL DEFAULT 'unidad',
    observaciones     varchar(255) DEFAULT NULL,
    activo            tinyint(1) NOT NULL DEFAULT 1,
    fechaCarga        datetime NOT NULL,
    idUsuario         int DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_categoria (idCategoria),
    KEY idx_maquina (idMaquina)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historial de precios: cada vez que un proveedor cambia el precio se carga una fila nueva.
-- El precio vigente de un proveedor es su última fila (por fecha y, en el mismo día, por id).
CREATE TABLE IF NOT EXISTS insumosPrecios (
    id            int NOT NULL AUTO_INCREMENT,
    idInsumo      int NOT NULL,
    idProveedor   int NOT NULL,                    -- contactos.id
    precio        decimal(12,2) NOT NULL,          -- por unidadCompra
    fecha         date NOT NULL,
    observaciones varchar(255) DEFAULT NULL,
    fechaCarga    datetime NOT NULL,
    idUsuario     int DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_insumo (idInsumo, idProveedor, fecha),
    KEY idx_proveedor (idProveedor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Papeles viejos → insumos (categoría Papeles), con su precio si el proveedor existe.
SET @papeles = (SELECT id FROM insumosCategorias WHERE nombre = 'Papeles');
INSERT INTO insumos (idCategoria, nombre, largo, ancho, gramaje, unidadCompra, unidadesPorCompra, unidadUso, fechaCarga)
    SELECT @papeles, p.nombre, p.largo, p.ancho, p.gramaje, p.formato, COALESCE(NULLIF(p.hojasPaquete, 0), 500), 'hoja', NOW()
    FROM papeles p
    WHERE NOT EXISTS (SELECT 1 FROM insumos i WHERE i.idCategoria = @papeles AND i.nombre = p.nombre);

INSERT INTO insumosPrecios (idInsumo, idProveedor, precio, fecha, observaciones, fechaCarga)
    SELECT i.id, p.idProveedor, p.precioPaquete, CURDATE(), 'Copiado de la tabla papeles', NOW()
    FROM papeles p
    INNER JOIN insumos i ON i.idCategoria = @papeles AND i.nombre = p.nombre
    INNER JOIN contactos c ON c.id = p.idProveedor
    WHERE p.precioPaquete IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM insumosPrecios ip WHERE ip.idInsumo = i.id);

-- Menú: el grupo "insumos" y su página "papeles" pasan a la página nueva; se suma "máquinas".
SET @grupo = (SELECT id FROM menu WHERE padre IS NULL AND nombre = 'insumos' ORDER BY id LIMIT 1);
UPDATE menu SET pagina = 'insumos/insumos.php', activo = 1 WHERE id = @grupo;
UPDATE menu SET nombre = 'insumos', pagina = 'insumos/insumos.php', orden = 1
    WHERE padre = @grupo AND pagina = 'insumos/papeles.php';
INSERT INTO menu (nombre, pagina, padre, orden, activo)
    SELECT 'insumos', 'insumos/insumos.php', @grupo, 1, 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM menu WHERE padre = @grupo AND pagina = 'insumos/insumos.php');
INSERT INTO menu (nombre, pagina, padre, orden, activo)
    SELECT 'máquinas', 'insumos/maquinas.php', @grupo, 2, 1 FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM menu WHERE padre = @grupo AND pagina = 'insumos/maquinas.php');

-- Lo usan administradores y trabajadores. Cargar y editar exige tener la página en los
-- permisos (ver exigirPermisoAjax en sesion.php): para sacárselo a alguien, se le quita
-- desde Contactos > Permisos (ojo: si este script se vuelve a correr, se lo devuelve).
INSERT IGNORE INTO perfilesMenu (idPerfil, idMenu)
    SELECT pf.id, m.id FROM perfiles pf INNER JOIN menu m ON m.id = @grupo OR m.padre = @grupo
    WHERE pf.id IN (1, 4);
INSERT IGNORE INTO permisos (idUsuario, idMenu)
    SELECT u.id, m.id FROM usuarios u INNER JOIN menu m ON m.id = @grupo OR m.padre = @grupo
    WHERE u.idPerfil IN (1, 4);
