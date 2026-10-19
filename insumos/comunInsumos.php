<?php
// Lo que comparten la lista de insumos y su formulario.

// Con la inflación, un precio de más de esto conviene volver a consultarlo.
const DIAS_PRECIO_VIEJO = 60;

function fmtPesos($n) { return '$' . number_format((float)$n, 2, ',', '.'); }

// Último precio de cada proveedor, por insumo (o de un solo insumo), del más barato al más caro:
// [idInsumo => [['id', 'idProveedor', 'proveedor', 'precio', 'fecha'], ...]]
function preciosVigentes($conn, $idInsumo = null) {
    $sql = "SELECT ip.id, ip.idInsumo, ip.idProveedor, ip.precio, ip.fecha,
                   TRIM(CONCAT(c.apellido, ' ', COALESCE(c.nombre, ''))) AS proveedor
            FROM insumosPrecios ip
            INNER JOIN contactos c ON c.id = ip.idProveedor
            WHERE NOT EXISTS (SELECT 1 FROM insumosPrecios n
                              WHERE n.idInsumo = ip.idInsumo AND n.idProveedor = ip.idProveedor
                                AND (n.fecha > ip.fecha OR (n.fecha = ip.fecha AND n.id > ip.id)))"
         . ($idInsumo ? " AND ip.idInsumo = " . (int)$idInsumo : '')
         . " ORDER BY ip.idInsumo, ip.precio";
    $vigentes = [];
    foreach (mysqli_fetch_all(mysqli_query($conn, $sql), MYSQLI_ASSOC) as $p) {
        $vigentes[$p['idInsumo']][] = $p;
    }
    return $vigentes;
}

// Resumen de los datos técnicos, por ejemplo "Obra · 297 × 210 mm · 75 g".
function detalleTecnico($i) {
    $partes = [$i['tipo']];
    if ($i['largo'] && $i['ancho']) $partes[] = "{$i['largo']} × {$i['ancho']} mm";
    if ($i['gramaje']) $partes[] = "{$i['gramaje']} g";
    if ($i['medida']) $partes[] = $i['medida'];
    if ($i['color']) $partes[] = $i['color'];
    if (!empty($i['maquina'])) $partes[] = 'para ' . $i['maquina'];
    if ($i['rendimiento']) $partes[] = number_format($i['rendimiento'], 0, ',', '.') . ' copias';
    return implode(' · ', array_filter($partes));
}

// hoja → hojas, unidad → unidades (alcanza para las unidades de uso).
function plural($palabra) {
    return preg_match('/[aeiou]$/i', $palabra) ? "{$palabra}s" : "{$palabra}es";
}

// "Resma de 500 hojas", o solo "Cartucho" si trae uno.
function presentacion($i) {
    if ((int)$i['unidadesPorCompra'] === 1) return $i['unidadCompra'];
    return "{$i['unidadCompra']} de " . number_format($i['unidadesPorCompra'], 0, ',', '.') . ' ' . plural($i['unidadUso']);
}

// Costo de una unidad de uso (por hoja, por rulo) y, si tiene rendimiento, por copia.
function costoPorUnidad($i, $precio) {
    $porUnidad = $precio / max(1, (int)$i['unidadesPorCompra']);
    $texto = (int)$i['unidadesPorCompra'] === 1 ? '' : fmtPesos($porUnidad) . " por {$i['unidadUso']}";
    if ($i['rendimiento']) {
        $porCopia = $porUnidad / $i['rendimiento'];
        $texto .= ($texto ? ' · ' : '') . '$' . number_format($porCopia, $porCopia < 1 ? 4 : 2, ',', '.') . ' por copia';
    }
    return $texto ?: '-';
}
