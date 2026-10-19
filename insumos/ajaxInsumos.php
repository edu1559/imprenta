<?php
include_once(__DIR__ . '/../sesion.php');
include_once(__DIR__ . '/../conexion.php');

$opcion = $_POST['opcion'] ?? '';
$id     = (int)($_POST['id'] ?? 0);

// Las máquinas se cargan desde su propia página: se pueden permitir por separado.
exigirPermisoAjax(str_ends_with($opcion, 'Maquina') ? 'insumos/maquinas.php' : 'insumos/insumos.php');
$idUsuario = (int)$_SESSION['idUsuario'];
$conn = conectar();

// Texto del formulario: null si está vacío; false si se pasa del largo de la columna.
function textoCampo($nombre, $largo) {
    $valor = trim($_POST[$nombre] ?? '');
    if ($valor === '') return null;
    return preg_match_all('/./su', $valor) > $largo ? false : $valor;
}

// Entero del formulario: null si está vacío; false si no es un entero positivo.
function enteroCampo($nombre) {
    $valor = trim($_POST[$nombre] ?? '');
    if ($valor === '') return null;
    return ctype_digit($valor) && (int)$valor > 0 ? (int)$valor : false;
}

function existe($conn, $tabla, $id) {
    $stmt = $conn->prepare("SELECT 1 FROM $tabla WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_row();
}

function cuenta($conn, $sql, $id) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_row()[0];
}

switch ($opcion) {

// Alta (id 0) o edición de un insumo. Los datos técnicos que no van con la ficha de la
// categoría se guardan vacíos, así no quedan restos al cambiar de categoría.
case 'guardarInsumo':
    $idCategoria = (int)($_POST['idCategoria'] ?? 0);
    $stmt = $conn->prepare("SELECT ficha FROM insumosCategorias WHERE id = ?");
    $stmt->bind_param('i', $idCategoria);
    $stmt->execute();
    $ficha = $stmt->get_result()->fetch_row()[0] ?? null;
    if (!$ficha) { echo "❌ Falta elegir la categoría."; break; }

    $campos = [
        'nombre'            => textoCampo('nombre', 80),
        'tipo'              => textoCampo('tipo', 40),
        'largo'             => enteroCampo('largo'),
        'ancho'             => enteroCampo('ancho'),
        'gramaje'           => enteroCampo('gramaje'),
        'color'             => textoCampo('color', 30),
        'idMaquina'         => enteroCampo('idMaquina'),
        'rendimiento'       => enteroCampo('rendimiento'),
        'medida'            => textoCampo('medida', 30),
        'unidadCompra'      => textoCampo('unidadCompra', 20),
        'unidadesPorCompra' => enteroCampo('unidadesPorCompra'),
        'unidadUso'         => textoCampo('unidadUso', 20),
        'observaciones'     => textoCampo('observaciones', 255),
    ];
    $etiquetas = ['nombre' => 'el nombre', 'tipo' => 'el tipo', 'largo' => 'el largo', 'ancho' => 'el ancho',
                  'gramaje' => 'el gramaje', 'color' => 'el color', 'idMaquina' => 'la máquina', 'rendimiento' => 'el rendimiento',
                  'medida' => 'la medida', 'unidadCompra' => 'la unidad de compra', 'unidadesPorCompra' => 'cuántas trae',
                  'unidadUso' => 'la unidad de uso', 'observaciones' => 'las observaciones'];
    $error = null;
    foreach ($campos as $campo => $valor) {
        if ($valor === false) { $error = "❌ Revisá " . $etiquetas[$campo] . ": es muy largo o no es un número válido."; break; }
    }
    if ($error) { echo $error; break; }
    foreach (['nombre', 'unidadCompra', 'unidadesPorCompra', 'unidadUso'] as $campo) {
        if ($campos[$campo] === null) { $error = "❌ Falta " . $etiquetas[$campo] . "."; break; }
    }
    if ($error) { echo $error; break; }

    $usados = ['papel' => ['tipo', 'largo', 'ancho', 'gramaje', 'color'],
               'toner' => ['idMaquina', 'color', 'rendimiento'],
               'encuadernado' => ['tipo', 'medida', 'color'],
               'general' => ['tipo', 'medida', 'color']][$ficha];
    foreach (['tipo', 'largo', 'ancho', 'gramaje', 'color', 'idMaquina', 'rendimiento', 'medida'] as $campo) {
        if (!in_array($campo, $usados, true)) $campos[$campo] = null;
    }
    if ($campos['idMaquina'] !== null && !existe($conn, 'maquinas', $campos['idMaquina'])) { echo "❌ La máquina elegida no existe."; break; }

    $activo = empty($_POST['activo']) ? 0 : 1;
    $c = $campos;
    if ($id === 0) {
        $stmt = $conn->prepare("INSERT INTO insumos (idCategoria, nombre, tipo, largo, ancho, gramaje, color, idMaquina, rendimiento, medida,
                                                     unidadCompra, unidadesPorCompra, unidadUso, observaciones, activo, fechaCarga, idUsuario)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param('issiiisiississii', $idCategoria, $c['nombre'], $c['tipo'], $c['largo'], $c['ancho'], $c['gramaje'], $c['color'],
                          $c['idMaquina'], $c['rendimiento'], $c['medida'], $c['unidadCompra'], $c['unidadesPorCompra'], $c['unidadUso'],
                          $c['observaciones'], $activo, $idUsuario);
        // El formulario sigue abierto para cargarle los precios: necesita el id nuevo.
        echo $stmt->execute() ? "✅ " . $conn->insert_id : "❌ Error al guardar.";
    } else {
        if (!existe($conn, 'insumos', $id)) { echo "❌ No se encontró el insumo."; break; }
        $stmt = $conn->prepare("UPDATE insumos SET idCategoria = ?, nombre = ?, tipo = ?, largo = ?, ancho = ?, gramaje = ?, color = ?,
                                       idMaquina = ?, rendimiento = ?, medida = ?, unidadCompra = ?, unidadesPorCompra = ?, unidadUso = ?,
                                       observaciones = ?, activo = ?
                                WHERE id = ?");
        $stmt->bind_param('issiiisiississii', $idCategoria, $c['nombre'], $c['tipo'], $c['largo'], $c['ancho'], $c['gramaje'], $c['color'],
                          $c['idMaquina'], $c['rendimiento'], $c['medida'], $c['unidadCompra'], $c['unidadesPorCompra'], $c['unidadUso'],
                          $c['observaciones'], $activo, $id);
        echo $stmt->execute() ? "✅ " . $id : "❌ Error al guardar.";
    }
    break;

// Solo si no tiene precios cargados; si los tiene, se desactiva (así no se pierde el historial).
case 'borrarInsumo':
    if (!existe($conn, 'insumos', $id)) { echo "❌ No se encontró el insumo."; break; }
    if (cuenta($conn, "SELECT COUNT(*) FROM insumosPrecios WHERE idInsumo = ?", $id)) {
        echo "❌ El insumo tiene precios cargados. Si ya no se usa, destildá \"Activo\" en vez de borrarlo.";
        break;
    }
    $stmt = $conn->prepare("DELETE FROM insumos WHERE id = ?");
    $stmt->bind_param('i', $id);
    echo $stmt->execute() ? "✅ Insumo borrado." : "❌ Error al borrar.";
    break;

// Un precio nuevo no pisa el anterior: queda el historial y el vigente es el último.
case 'guardarPrecio':
    $idInsumo    = (int)($_POST['idInsumo'] ?? 0);
    $idProveedor = (int)($_POST['idProveedor'] ?? 0);
    $precio      = trim($_POST['precio'] ?? '');
    $fecha       = trim($_POST['fecha'] ?? '');
    $observaciones = textoCampo('observaciones', 255);

    if (!existe($conn, 'insumos', $idInsumo)) { echo "❌ No se encontró el insumo."; break; }
    if (!existe($conn, 'contactos', $idProveedor)) { echo "❌ Falta elegir el proveedor."; break; }
    if (!is_numeric($precio) || (float)$precio <= 0) { echo "❌ El precio tiene que ser un número mayor que cero."; break; }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha > date('Y-m-d')) { echo "❌ La fecha no es válida (no puede ser futura)."; break; }
    if ($observaciones === false) { echo "❌ Las observaciones no pueden tener más de 255 caracteres."; break; }

    $precio = round((float)$precio, 2);
    $stmt = $conn->prepare("INSERT INTO insumosPrecios (idInsumo, idProveedor, precio, fecha, observaciones, fechaCarga, idUsuario)
                            VALUES (?, ?, ?, ?, ?, NOW(), ?)");
    $stmt->bind_param('iidssi', $idInsumo, $idProveedor, $precio, $fecha, $observaciones, $idUsuario);
    echo $stmt->execute() ? "✅ Precio cargado." : "❌ Error al guardar.";
    break;

// Para corregir un precio mal cargado.
case 'borrarPrecio':
    $stmt = $conn->prepare("DELETE FROM insumosPrecios WHERE id = ?");
    $stmt->bind_param('i', $id);
    echo $stmt->execute() && $stmt->affected_rows === 1 ? "✅ Precio borrado." : "❌ No se encontró el precio.";
    break;

case 'guardarMaquina':
    $tipo   = textoCampo('tipo', 30);
    $nombre = textoCampo('nombre', 50);
    $marca  = textoCampo('marca', 50);
    $modelo = textoCampo('modelo', 50);
    $observaciones = textoCampo('observaciones', 255);
    $activo = empty($_POST['activo']) ? 0 : 1;

    if ($tipo === null || $nombre === null) { echo "❌ Faltan el tipo o el nombre."; break; }
    if (in_array(false, [$tipo, $nombre, $marca, $modelo, $observaciones], true)) { echo "❌ Algún dato es demasiado largo."; break; }

    if ($id === 0) {
        $stmt = $conn->prepare("INSERT INTO maquinas (tipo, nombre, marca, modelo, observaciones, activo) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssi', $tipo, $nombre, $marca, $modelo, $observaciones, $activo);
        echo $stmt->execute() ? "✅ Máquina cargada." : "❌ Error al guardar.";
    } else {
        if (!existe($conn, 'maquinas', $id)) { echo "❌ No se encontró la máquina."; break; }
        $stmt = $conn->prepare("UPDATE maquinas SET tipo = ?, nombre = ?, marca = ?, modelo = ?, observaciones = ?, activo = ? WHERE id = ?");
        $stmt->bind_param('sssssii', $tipo, $nombre, $marca, $modelo, $observaciones, $activo, $id);
        echo $stmt->execute() ? "✅ Máquina actualizada." : "❌ Error al guardar.";
    }
    break;

case 'borrarMaquina':
    if (!existe($conn, 'maquinas', $id)) { echo "❌ No se encontró la máquina."; break; }
    if ($n = cuenta($conn, "SELECT COUNT(*) FROM insumos WHERE idMaquina = ?", $id)) {
        echo "❌ La máquina tiene $n insumos asociados (toner). Si ya no está, destildá \"Activa\" en vez de borrarla.";
        break;
    }
    $stmt = $conn->prepare("DELETE FROM maquinas WHERE id = ?");
    $stmt->bind_param('i', $id);
    echo $stmt->execute() ? "✅ Máquina borrada." : "❌ Error al borrar.";
    break;

default:
    echo "❌ Opción desconocida.";
}
