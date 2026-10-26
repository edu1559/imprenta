<?php
include_once(__DIR__ . '/../sesion.php');
include_once(__DIR__ . '/../conexion.php');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

exigirPermisoAjax('insumos/procesos.php');
$opcion = $_POST['opcion'] ?? '';
$id     = (int)($_POST['id'] ?? 0);
$idUsuario = (int)$_SESSION['idUsuario'];
$conn = conectar();

// Texto del formulario: null si está vacío; false si se pasa del largo de la columna.
function textoCampo($nombre, $largo) {
    $valor = trim($_POST[$nombre] ?? '');
    if ($valor === '') return null;
    return preg_match_all('/./su', $valor) > $largo ? false : $valor;
}

// Número del formulario (acepta coma decimal): null si está vacío; false si no es un número >= 0.
function numero($valor) {
    $valor = str_replace(',', '.', trim((string)$valor));
    if ($valor === '') return null;
    return is_numeric($valor) && (float)$valor >= 0 ? (float)$valor : false;
}

function existe($conn, $tabla, $id) {
    $stmt = $conn->prepare("SELECT 1 FROM $tabla WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_row();
}

switch ($opcion) {

// Alta (id 0) o edición de un proceso con sus tramos de precio y sus insumos, todo junto:
// los renglones se reemplazan enteros.
case 'guardarProceso':
    $nombre = textoCampo('nombre', 80);
    $tipo   = textoCampo('tipo', 30);
    $unidad = textoCampo('unidad', 20);
    $observaciones = textoCampo('observaciones', 255);
    if ($nombre === null || $unidad === null) { echo "❌ Faltan el nombre o la unidad."; break; }
    if (in_array(false, [$nombre, $tipo, $unidad, $observaciones], true)) { echo "❌ Algún dato es demasiado largo."; break; }

    $idMaquina = (int)($_POST['idMaquina'] ?? 0) ?: null;
    if ($idMaquina && !existe($conn, 'maquinas', $idMaquina)) { echo "❌ La máquina elegida no existe."; break; }
    $toner = $idMaquina && in_array($_POST['toner'] ?? '', ['negro', 'todos'], true) ? $_POST['toner'] : 'no';

    $idComplejidad = (int)($_POST['idComplejidad'] ?? 0);
    if (!existe($conn, 'complejidades', $idComplejidad)) { echo "❌ Falta elegir la complejidad."; break; }
    $valorHora = numero($_POST['valorHora'] ?? '');
    $tiempoPreparacion = numero($_POST['tiempoPreparacion'] ?? '');
    $tiempoPorUnidad = numero($_POST['tiempoPorUnidad'] ?? '');
    if (in_array(false, [$valorHora, $tiempoPreparacion, $tiempoPorUnidad], true)) { echo "❌ El valor hora y los tiempos tienen que ser números (o quedar vacíos)."; break; }
    $tiempoPreparacion = round($tiempoPreparacion ?? 0, 2);
    $tiempoPorUnidad = round($tiempoPorUnidad ?? 0, 4);
    $valorHora = $valorHora === null ? null : round($valorHora, 2);

    // Tramos: el primero arranca en 1 y no se repite el "desde".
    $tramos = [];
    foreach ($_POST['tramos'] ?? [] as $t) {
        $desde = trim((string)($t['desde'] ?? ''));
        $arranque = numero($t['arranque'] ?? '');
        $porUnidad = numero($t['porUnidad'] ?? '');
        if ($desde === '' && $arranque === null && $porUnidad === null) continue;
        if (!ctype_digit($desde) || (int)$desde < 1) { echo "❌ El \"desde\" de cada tramo tiene que ser una cantidad entera."; break 2; }
        if ($arranque === false || $porUnidad === false) { echo "❌ Revisá los precios de los tramos: tienen que ser números."; break 2; }
        if (isset($tramos[(int)$desde])) { echo "❌ Hay dos tramos que arrancan en $desde."; break 2; }
        $tramos[(int)$desde] = [round($arranque ?? 0, 2), round($porUnidad ?? 0, 4)];
    }
    ksort($tramos);
    if (!$tramos) { echo "❌ Falta el precio: cargá al menos un tramo desde 1."; break; }
    if (array_key_first($tramos) !== 1) { echo "❌ El primer tramo tiene que arrancar en 1."; break; }
    foreach ($tramos as [$arranque, $porUnidad]) {
        if ($arranque <= 0 && $porUnidad <= 0) { echo "❌ Cada tramo necesita un arranque o un precio por unidad."; break 2; }
    }

    // Insumos: ni papel ni toner (el papel se elige aparte; el toner sale de la máquina).
    $insumos = [];
    foreach ($_POST['insumos'] ?? [] as $i) {
        $idInsumo = (int)($i['idInsumo'] ?? 0);
        $cantidad = numero($i['cantidad'] ?? '');
        if (!$idInsumo && $cantidad === null) continue;
        if (!$idInsumo) { echo "❌ Hay un renglón de insumos sin elegir el insumo."; break 2; }
        if (!$cantidad) { echo "❌ La cantidad de cada insumo tiene que ser un número mayor que cero."; break 2; }
        if (isset($insumos[$idInsumo])) { echo "❌ Un insumo está repetido."; break 2; }
        $stmt = $conn->prepare("SELECT c.ficha FROM insumos i INNER JOIN insumosCategorias c ON c.id = i.idCategoria WHERE i.id = ?");
        $stmt->bind_param('i', $idInsumo);
        $stmt->execute();
        $ficha = $stmt->get_result()->fetch_row()[0] ?? null;
        if (!$ficha) { echo "❌ Un insumo elegido no existe."; break 2; }
        if ($ficha === 'toner') { echo "❌ El toner no se carga como insumo: elegí la máquina y qué toner gasta."; break 2; }
        $insumos[$idInsumo] = round($cantidad, 4);
    }

    $ventaSuelta = empty($_POST['ventaSuelta']) ? 0 : 1;
    $activo = empty($_POST['activo']) ? 0 : 1;

    // fechaPrecios avisa cuando los precios quedaron viejos: se renueva solo si cambiaron los tramos.
    $cambianPrecios = true;
    if ($id) {
        if (!existe($conn, 'procesos', $id)) { echo "❌ No se encontró el proceso."; break; }
        $stmt = $conn->prepare("SELECT desde, arranque, porUnidad FROM procesosPrecios WHERE idProceso = ? ORDER BY desde");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $anteriores = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $t) {
            $anteriores[(int)$t['desde']] = [round((float)$t['arranque'], 2), round((float)$t['porUnidad'], 4)];
        }
        $cambianPrecios = $anteriores != $tramos;
    }

    $conn->begin_transaction();
    try {
        if ($id === 0) {
            $stmt = $conn->prepare("INSERT INTO procesos (nombre, tipo, idMaquina, toner, unidad, valorHora, idComplejidad, tiempoPreparacion,
                                                          tiempoPorUnidad, ventaSuelta, fechaPrecios, observaciones, activo, fechaCarga, idUsuario)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, NOW(), ?)");
            $stmt->bind_param('ssissdiddisii', $nombre, $tipo, $idMaquina, $toner, $unidad, $valorHora, $idComplejidad, $tiempoPreparacion,
                              $tiempoPorUnidad, $ventaSuelta, $observaciones, $activo, $idUsuario);
            $stmt->execute();
            $id = $conn->insert_id;
        } else {
            $stmt = $conn->prepare("UPDATE procesos SET nombre = ?, tipo = ?, idMaquina = ?, toner = ?, unidad = ?, valorHora = ?, idComplejidad = ?,
                                           tiempoPreparacion = ?, tiempoPorUnidad = ?, ventaSuelta = ?, observaciones = ?, activo = ?"
                                   . ($cambianPrecios ? ", fechaPrecios = CURDATE()" : "") . "
                                    WHERE id = ?");
            $stmt->bind_param('ssissdiddisii', $nombre, $tipo, $idMaquina, $toner, $unidad, $valorHora, $idComplejidad, $tiempoPreparacion,
                              $tiempoPorUnidad, $ventaSuelta, $observaciones, $activo, $id);
            $stmt->execute();
        }

        foreach (['procesosPrecios', 'procesosInsumos'] as $tabla) {
            $stmt = $conn->prepare("DELETE FROM $tabla WHERE idProceso = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
        }
        $stmt = $conn->prepare("INSERT INTO procesosPrecios (idProceso, desde, arranque, porUnidad) VALUES (?, ?, ?, ?)");
        foreach ($tramos as $desde => [$arranque, $porUnidad]) {
            $stmt->bind_param('iidd', $id, $desde, $arranque, $porUnidad);
            $stmt->execute();
        }
        $stmt = $conn->prepare("INSERT INTO procesosInsumos (idProceso, idInsumo, cantidad) VALUES (?, ?, ?)");
        foreach ($insumos as $idInsumo => $cantidad) {
            $stmt->bind_param('iid', $id, $idInsumo, $cantidad);
            $stmt->execute();
        }
        $conn->commit();
        echo "✅ Proceso guardado.";
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        echo "❌ Error al guardar.";
    }
    break;

// Por ahora nada usa los procesos (los presupuestadores vienen después): se borra con sus renglones.
case 'borrarProceso':
    if (!existe($conn, 'procesos', $id)) { echo "❌ No se encontró el proceso."; break; }
    $conn->begin_transaction();
    try {
        foreach (['procesosPrecios', 'procesosInsumos'] as $tabla) {
            $stmt = $conn->prepare("DELETE FROM $tabla WHERE idProceso = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
        }
        $stmt = $conn->prepare("DELETE FROM procesos WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $conn->commit();
        echo "✅ Proceso borrado.";
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        echo "❌ Error al borrar.";
    }
    break;

// Niveles de complejidad: se editan todos juntos; un renglón nuevo (id 0) se agrega.
case 'guardarComplejidades':
    $niveles = [];
    foreach ($_POST['niveles'] ?? [] as $n) {
        $nombre = trim($n['nombre'] ?? '');
        $factor = numero($n['factor'] ?? '');
        $idNivel = (int)($n['id'] ?? 0);
        if ($nombre === '' && $factor === null && !$idNivel) continue;
        if ($nombre === '' || preg_match_all('/./su', $nombre) > 30) { echo "❌ Cada nivel necesita un nombre (hasta 30 letras)."; break 2; }
        if (!$factor || $factor > 9) { echo "❌ El factor de \"$nombre\" tiene que ser un número entre 0 y 9 (1 = valor hora normal)."; break 2; }
        if (isset($niveles[strtolower($nombre)])) { echo "❌ El nivel \"$nombre\" está repetido."; break 2; }
        $niveles[strtolower($nombre)] = [$idNivel, $nombre, round($factor, 2)];
    }
    if (!$niveles) { echo "❌ Tiene que quedar al menos un nivel."; break; }

    $conn->begin_transaction();
    try {
        $orden = 0;
        foreach ($niveles as [$idNivel, $nombre, $factor]) {
            $orden++;
            if ($idNivel) {
                $stmt = $conn->prepare("UPDATE complejidades SET nombre = ?, factor = ?, orden = ? WHERE id = ?");
                $stmt->bind_param('sdii', $nombre, $factor, $orden, $idNivel);
            } else {
                $stmt = $conn->prepare("INSERT INTO complejidades (nombre, factor, orden) VALUES (?, ?, ?)");
                $stmt->bind_param('sdi', $nombre, $factor, $orden);
            }
            $stmt->execute();
        }
        $conn->commit();
        echo "✅ Niveles guardados.";
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        echo "❌ Error al guardar (¿dos niveles con el mismo nombre?).";
    }
    break;

// Solo si ningún proceso lo usa.
case 'borrarComplejidad':
    $stmt = $conn->prepare("SELECT COUNT(*) FROM procesos WHERE idComplejidad = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($n = $stmt->get_result()->fetch_row()[0]) { echo "❌ Hay $n procesos con este nivel: cambiales el nivel antes de borrarlo."; break; }
    $stmt = $conn->prepare("DELETE FROM complejidades WHERE id = ?");
    $stmt->bind_param('i', $id);
    echo $stmt->execute() && $stmt->affected_rows === 1 ? "✅ Nivel borrado." : "❌ No se encontró el nivel.";
    break;

default:
    echo "❌ Opción desconocida.";
}
