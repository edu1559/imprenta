<?php
// Lo que comparten la lista de procesos y su formulario: costos y precios por cantidad.
include_once(__DIR__ . '/comunInsumos.php');

// Unidades sugeridas (se puede escribir otra).
const UNIDADES_PROCESO = ['cara', 'hoja', 'm²', 'unidad', 'corte', 'trabajo', 'hora'];
const TONER_PROCESO = ['no' => 'No gasta toner', 'negro' => 'Solo el negro', 'todos' => 'Todos los colores'];

// Lo que hace falta para costear: costo por unidad de uso de cada insumo (proveedor más barato)
// y toner por copia de cada máquina (precio / rendimiento, sumando los cartuchos activos).
// Un toner sin color o con "negro" en el color cuenta como negro.
function datosDeCosto($conn) {
    $insumos = mysqli_fetch_all(mysqli_query($conn, "SELECT i.id, i.unidadesPorCompra, i.rendimiento, i.idMaquina, i.color, i.activo, c.ficha
                                                     FROM insumos i INNER JOIN insumosCategorias c ON c.id = i.idCategoria"), MYSQLI_ASSOC);
    $vigentes = preciosVigentes($conn);
    $costos = ['insumos' => [], 'toner' => []];
    foreach ($insumos as $i) {
        $mejor = $vigentes[$i['id']][0] ?? null;
        if (!$mejor) continue;
        $porUnidad = $mejor['precio'] / max(1, (int)$i['unidadesPorCompra']);
        $costos['insumos'][$i['id']] = $porUnidad;
        if ($i['ficha'] === 'toner' && $i['activo'] && $i['idMaquina'] && $i['rendimiento']) {
            $porCopia = $porUnidad / $i['rendimiento'];
            $t = &$costos['toner'][$i['idMaquina']];
            $t['todos'] = ($t['todos'] ?? 0) + $porCopia;
            if (trim((string)$i['color']) === '' || stripos($i['color'], 'negro') !== false) {
                $t['negro'] = ($t['negro'] ?? 0) + $porCopia;
            }
            unset($t);
        }
    }
    return $costos;
}

// Costo de un proceso como recta: ['fijo', 'porUnidad', 'faltan' => [lo que no se pudo costear]].
// $p trae los campos de procesos más factor (complejidad) y costoPorUso (máquina);
// $insumos son sus renglones de procesosInsumos [['idInsumo', 'cantidad', 'nombre'], ...].
function costoProceso($p, $insumos, $costos) {
    $faltan = [];
    $valorHora = (float)$p['valorHora'] * (float)$p['factor'];
    if (!$p['valorHora'] && ($p['tiempoPreparacion'] > 0 || $p['tiempoPorUnidad'] > 0)) $faltan[] = 'el valor hora';
    $fijo = $p['tiempoPreparacion'] / 60 * $valorHora;
    $porUnidad = $p['tiempoPorUnidad'] / 60 * $valorHora;
    foreach ($insumos as $i) {
        if (isset($costos['insumos'][$i['idInsumo']])) {
            $porUnidad += $i['cantidad'] * $costos['insumos'][$i['idInsumo']];
        } else {
            $faltan[] = "el precio de {$i['nombre']}";
        }
    }
    if ($p['idMaquina']) {
        $porUnidad += (float)$p['costoPorUso'];
        if ($p['toner'] !== 'no') {
            $toner = $costos['toner'][$p['idMaquina']][$p['toner']] ?? null;
            if ($toner === null) $faltan[] = 'el toner de la máquina (precio y rendimiento)';
            $porUnidad += (float)$toner;
        }
    }
    return ['fijo' => $fijo, 'porUnidad' => $porUnidad, 'faltan' => $faltan];
}

// Precio total para una cantidad: el tramo es el último que arranca en esa cantidad o antes.
// $tramos ordenados por desde. null si no hay tramo (no debería: el primero arranca en 1).
function precioProceso($tramos, $cantidad) {
    $tramo = null;
    foreach ($tramos as $t) {
        if ($t['desde'] <= $cantidad) $tramo = $t;
    }
    return $tramo ? $tramo['arranque'] + $tramo['porUnidad'] * $cantidad : null;
}

// Plata con decimales solo si hacen falta (precios de centavos por copia).
function fmtPrecioUnidad($n) {
    return '$' . number_format((float)$n, abs($n) < 10 && round($n, 2) != $n ? 4 : 2, ',', '.');
}
