<?php
include_once('../conexion.php');
$conn = conectarPDO();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$busqueda = "%$q%";

// Buscamos por apellido, nombre o teléfono, y traemos saldo pendiente + cantidad
// de pedidos para poder mostrarlos junto al nombre (evita abrir el historial
// solo para saber si el cliente debe plata).
$sql = "SELECT c.id, CONCAT(c.apellido, ', ', c.nombre) AS text, c.telefono,
               COUNT(p.id) AS pedidos,
               COALESCE(SUM(p.monto - p.montoPagado), 0) AS saldo
        FROM contactos c
        LEFT JOIN pedidos p ON p.idContacto = c.id
        WHERE c.apellido LIKE :busqueda OR c.nombre LIKE :busqueda OR c.telefono LIKE :busqueda
        GROUP BY c.id, c.apellido, c.nombre, c.telefono
        ORDER BY c.apellido ASC
        LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->bindParam(':busqueda', $busqueda, PDO::PARAM_STR);
$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($data as &$row) {
    $row['saldo'] = (float)$row['saldo'];
    $row['pedidos'] = (int)$row['pedidos'];
}
unset($row);

// Si no encontramos a nadie, ofrecemos crear el cliente directamente desde
// el mismo combo (lo consume pedidos/modalPedidoNuevo.php y pedidos/pedidos.php).
if (empty($data) && $q !== '') {
    $data[] = [
        'id' => 'NEW',
        'text' => 'Crear cliente nuevo: "' . $q . '"',
        'nombreNuevo' => $q,
        'isNew' => true,
    ];
}

echo json_encode($data);
