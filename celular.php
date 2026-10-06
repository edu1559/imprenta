<?php
// Celulares de contactos (columna contactos.celular) y enlaces de WhatsApp.
//
// Se guardan como los necesita WhatsApp: 549 + característica + número, sin 0 ni 15
// (10 dígitos después del 549). Ej: "0351 15 532-9898" → "5493515329898".
// Sin característica se asume Córdoba (351).

const CARACTERISTICA_LOCAL = '351';

// Devuelve el celular normalizado, o null si no se puede interpretar.
// $desdeTelefono: para convertir el campo telefono viejo, donde 7 dígitos sueltos
// o 351-4xx son fijos y un número de 10 dígitos solo cuenta si es de Córdoba.
function normalizarCelular($texto, $desdeTelefono = false) {
    $nacional = numeroNacional(preg_replace('/\D/', '', (string)$texto), $desdeTelefono);
    // Campo con varios números o texto pegado ("155905344 - 155", "156547324(marga"):
    // se prueba con el primer número que aparezca.
    if ($nacional === null && preg_match('#^\D*([\d\s.\-()]{7,}?)(\s+[-/]|[/,;]|[a-zA-Z]|$)#', (string)$texto, $m)) {
        $nacional = numeroNacional(preg_replace('/\D/', '', $m[1]), $desdeTelefono);
    }
    return $nacional === null ? null : '549' . $nacional;
}

// Los 10 dígitos nacionales (característica + número), o null.
function numeroNacional($d, $desdeTelefono) {
    if (strlen($d) === 13 && str_starts_with($d, '549')) $d = substr($d, 3);
    elseif (strlen($d) >= 12 && str_starts_with($d, '54')) $d = substr($d, 2);
    if (str_starts_with($d, '0')) $d = substr($d, 1);

    // Característica + 15 + número ("351 15 5329898", "11 15 1234 5678")
    if (strlen($d) === 12) {
        foreach ([3, 4, 2] as $largo) {
            if (substr($d, $largo, 2) === '15' && preg_match('/^[123]/', $d)) return substr($d, 0, $largo) . substr($d, $largo + 2);
        }
        return null;
    }
    // 15 + 7 dígitos: celular de Córdoba sin característica
    if (strlen($d) === 9 && str_starts_with($d, '15')) return CARACTERISTICA_LOCAL . substr($d, 2);
    if (strlen($d) === 10 && preg_match('/^[123]/', $d)) {
        if ($desdeTelefono && (!str_starts_with($d, CARACTERISTICA_LOCAL) || $d[3] === '4')) return null;
        return $d;
    }
    // 7 dígitos: en el formulario de celular se asume Córdoba; en el teléfono viejo son fijos
    if (strlen($d) === 7 && !$desdeTelefono) return CARACTERISTICA_LOCAL . $d;
    return null;
}

// "5493515329898" → "351 532-9898"
function mostrarCelular($celular) {
    if (!preg_match('/^549(\d{10})$/', (string)$celular, $m)) return (string)$celular;
    $n = $m[1];
    $largoCaracteristica = str_starts_with($n, '11') ? 2 : 3;
    return substr($n, 0, $largoCaracteristica) . ' ' . substr($n, $largoCaracteristica, -4) . '-' . substr($n, -4);
}

function enlaceWhatsApp($celular, $mensaje = '') {
    return 'https://wa.me/' . $celular . ($mensaje !== '' ? '?text=' . rawurlencode($mensaje) : '');
}

// Primer nombre para el saludo; a una empresa (o sin nombre) se la saluda por el apellido.
function nombreSaludo($apellido, $nombre) {
    $nombre = trim((string)$nombre);
    return $nombre !== '' ? explode(' ', $nombre)[0] : trim((string)$apellido);
}
