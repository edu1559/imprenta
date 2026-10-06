<?php
include_once('../conexion.php');
$conn = conectarPDO();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limite = 50;

// Cada palabra tiene que aparecer en apellido, nombre o teléfono, así
// "romero pam" encuentra a "ROMERO, Pamela" entre los muchos Romero.
$palabras = preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY);
$condiciones = [];
$params = [];
foreach ($palabras as $i => $palabra) {
    $condiciones[] = "(c.apellido LIKE :p{$i}a OR c.nombre LIKE :p{$i}b OR c.telefono LIKE :p{$i}c)";
    $params[":p{$i}a"] = $params[":p{$i}b"] = $params[":p{$i}c"] = "%$palabra%";
}
$where = $condiciones ? implode(' AND ', $condiciones) : '1=1';

// Total de coincidencias (sin el LIMIT), para mostrarlo arriba de la lista.
$stmtTotal = $conn->prepare("SELECT COUNT(*) FROM contactos c WHERE $where");
$stmtTotal->execute($params);
$total = (int)$stmtTotal->fetchColumn();

// Traemos saldo pendiente + cantidad de pedidos para poder mostrarlos junto
// al nombre (evita abrir el historial solo para saber si el cliente debe plata).
$sql = "SELECT c.id, CONCAT(c.apellido, ', ', c.nombre) AS text, c.telefono,
               COUNT(p.id) AS pedidos,
               COALESCE(SUM(p.monto - p.montoPagado), 0) AS saldo
        FROM contactos c
        LEFT JOIN pedidos p ON p.idContacto = c.id AND p.anulado = 0
        WHERE $where
        GROUP BY c.id, c.apellido, c.nombre, c.telefono
        ORDER BY c.apellido ASC, c.nombre ASC
        LIMIT $limite";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($data as &$row) {
    $row['saldo'] = (float)$row['saldo'];
    $row['pedidos'] = (int)$row['pedidos'];
    $row['total'] = $total;
}
unset($row);

// Al final de la lista ofrecemos siempre crear el cliente desde el mismo combo
// (lo consume pedidos/modalPedidoNuevo.php): que haya otros con el mismo apellido
// no quiere decir que sea uno de ellos.
if ($q !== '') {
    $data[] = [
        'id' => 'NEW',
        'text' => 'Crear cliente nuevo: "' . $q . '"',
        'nombreNuevo' => $q,
        'isNew' => true,
    ];
}

echo json_encode($data);
