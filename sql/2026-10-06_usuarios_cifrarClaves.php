<?php
// Cifra las claves de usuarios que todavía están guardadas tal cual.
//
//   php sql/2026-10-06_usuarios_cifrarClaves.php             solo cuenta cuántas cifraría
//   php sql/2026-10-06_usuarios_cifrarClaves.php --aplicar   las cifra
//
// Desde el 6/10/2026 las claves se guardan con password_hash() y el ingreso las
// comprueba con password_verify() (ingreso/ajaxIngreso.php). Se puede correr más
// de una vez: las que ya están cifradas no se tocan. Si no se corre, cada clave se
// cifra igual la primera vez que ese usuario ingresa.
if (php_sapi_name() !== 'cli') { http_response_code(403); exit; }

include __DIR__ . '/../conexion.php';
$conn = conectar();
$aplicar = in_array('--aplicar', $argv, true);

$sinCifrar = [];
$r = $conn->query("SELECT id, usuario, clave FROM usuarios");
while ($u = $r->fetch_assoc()) {
    if (password_get_info((string)$u['clave'])['algo'] === null) $sinCifrar[] = $u;
}

echo count($sinCifrar) . " usuarios con la clave sin cifrar: "
   . implode(', ', array_column($sinCifrar, 'usuario')) . "\n";
if (!$aplicar) {
    echo "No se modificó nada. Para cifrarlas, repetir el comando agregando --aplicar\n";
    exit;
}

$stmt = $conn->prepare("UPDATE usuarios SET clave = ? WHERE id = ?");
foreach ($sinCifrar as $u) {
    $hash = password_hash((string)$u['clave'], PASSWORD_DEFAULT);
    $stmt->bind_param('si', $hash, $u['id']);
    $stmt->execute();
}
echo count($sinCifrar) . " claves cifradas.\n";
