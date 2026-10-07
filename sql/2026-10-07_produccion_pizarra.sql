-- Pizarra de producción (produccion/pizarra.php): cada pedido puede tener un responsable
-- de producción, distinto del que lo cargó (idUsuario) y del que lo cobra (pagos.idUsuario).
-- fechaTerminado guarda cuándo pasó a "Terminado", para las estadísticas por empleado
-- (produccion/estadisticas.php); en los pedidos terminados antes de este cambio queda vacía.
ALTER TABLE pedidos
    ADD COLUMN idResponsable INT DEFAULT NULL AFTER idUsuario,
    ADD COLUMN fechaTerminado DATETIME DEFAULT NULL AFTER idResponsable,
    ADD INDEX idx_responsable (idResponsable),
    ADD INDEX idx_fechaTerminado (fechaTerminado);

-- Menú "produccion" con sus dos páginas, visible para todos los trabajadores
-- (todos los usuarios menos el visitante, id 1). Va después de "pedidos": se corren
-- un lugar los que le siguen.
UPDATE menu SET orden = orden + 1 WHERE padre IS NULL AND orden >= 3;
INSERT INTO menu (id, nombre, pagina, padre, orden, activo) VALUES
    (46, 'produccion',   'produccion/pizarra.php',      NULL, 3, 1),
    (47, 'pizarra',      'produccion/pizarra.php',      46,   1, 1),
    (48, 'estadisticas', 'produccion/estadisticas.php', 46,   2, 1);

INSERT INTO permisos (idMenu, idUsuario)
    SELECT m.id, u.id FROM menu m INNER JOIN usuarios u ON u.id <> 1 WHERE m.id IN (46, 47, 48);
