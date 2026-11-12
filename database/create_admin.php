<?php
// Uso (solo consola):  php database/create_admin.php
// Crea el usuario ADMIN pidiendo la contraseña por teclado; nunca queda en el repositorio.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Solo por consola.'); }

$host = '127.0.0.1'; $db = 'bookgo_barberia'; $user = 'root'; $pass = '';

echo "Correo del ADMIN: ";
$correo = trim(fgets(STDIN));
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) exit("Correo inválido.\n");

echo "Contraseña (mín. 8 caracteres): ";
$clave = rtrim(fgets(STDIN), "\r\n");
require_once __DIR__ . '/../includes/password_policy.php';
if ($faltan = errores_password($clave)) exit(mensaje_password($faltan) . "\n");

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$stmt = $pdo->prepare(
  "INSERT INTO usuarios (rol_id, nombre, apellido, correo, password_hash)
   VALUES (1, 'Administrador', 'Barbería', :correo, :hash)");
$stmt->execute([':correo' => $correo, ':hash' => password_hash($clave, PASSWORD_DEFAULT)]);
echo "ADMIN creado correctamente.\n";
