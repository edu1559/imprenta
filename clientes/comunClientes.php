<?php
// Formatos que comparten las páginas de clientes/.

function plata($monto) {
    $m = round((float)$monto, 2);
    return '$' . number_format($m, floor($m) == $m ? 0 : 2, ',', '.');
}

function fecha($valor) {
    return $valor ? date('d/m/Y', strtotime($valor)) : '';
}
