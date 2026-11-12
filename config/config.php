<?php
// Configuración por defecto (XAMPP local). Sin secretos: para cambiarla cree
// config/config.local.php (ignorado por git) que devuelva un array con las claves a sobrescribir.
$config = [
    'db_host'  => '127.0.0.1',
    'db_port'  => 3306,
    'db_name'  => 'bookgo_barberia',
    'db_user'  => 'root',
    'db_pass'  => '',
    // Prefijo de URL. '' si el servidor apunta a la carpeta public/ (php -S ... -t public).
    // En XAMPP htdocs sería p. ej. '/PW2026_BookGoLite/public'
    // null = se detecta solo (funciona con php -S y con XAMPP/htdocs).
    'base_url' => null,
];

if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_merge($config, require __DIR__ . '/config.local.php');
}
if ($config['base_url'] === null) {
    // Ruta de public/ respecto a la raíz web (DOCUMENT_ROOT). Ej.: '/PW2026_BookGoLite/public' o ''.
    $raiz   = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $public = str_replace('\\', '/', (string) realpath(__DIR__ . '/../public'));
    $config['base_url'] = ($raiz !== '' && str_starts_with(strtolower($public), strtolower($raiz)))
        ? rtrim(substr($public, strlen($raiz)), '/')
        : '';
}
return $config;
