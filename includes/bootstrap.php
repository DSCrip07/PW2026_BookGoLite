<?php
declare(strict_types=1);
/**
 * Arranque común: configuración, PDO, sesión segura, cabeceras HTTP,
 * helpers de salida (XSS), CSRF y control de acceso.
 */
const APP_NAME = 'Navaja & Estilo';

ini_set('display_errors', '0');      // nunca mostrar errores internos al usuario
ini_set('log_errors', '1');

function config(string $clave): mixed
{
    static $cfg = null;
    $cfg ??= require __DIR__ . '/../config/config.php';
    return $cfg[$clave];
}

/** Conexión PDO (consultas preparadas reales, excepciones activadas). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            config('db_host'), config('db_port'), config('db_name'));
        $pdo = new PDO($dsn, config('db_user'), config('db_pass'), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// Errores no controlados: mensaje genérico, detalle solo al log del servidor.
set_exception_handler(function (Throwable $e): void {
    error_log($e);
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>Error</title>'
       . '<p>Ocurrió un error inesperado. Intente nuevamente más tarde.</p>';
});

// ---- Cabeceras de seguridad -----------------------------------------------
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; "
     . "script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

// ---- Sesión segura ----------------------------------------------------------
session_name('BOOKGOSESS');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']),   // Secure solo cuando hay HTTPS
]);
ini_set('session.use_strict_mode', '1');
session_start();

// ---- Helpers ------------------------------------------------------------------
/** Escapa salida HTML (XSS). */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $ruta = ''): string
{
    return config('base_url') . '/' . ltrim($ruta, '/');
}

function redirect(string $ruta): never
{
    header('Location: ' . url($ruta));
    exit;
}

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flash_todos(): array
{
    $m = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $m;
}

// ---- CSRF -----------------------------------------------------------------------
function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Rechaza la petición POST si el token no es válido. */
function csrf_check(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        http_response_code(403);
        exit('Solicitud rechazada: token de seguridad inválido. Recargue la página e intente de nuevo.');
    }
}

// ---- Autenticación / autorización --------------------------------------------------
/** Usuario actual, validado contra la BD en cada petición (estado ACTIVO). */
function usuario_actual(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    $cache = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare(
            'SELECT u.id, u.nombre, u.apellido, u.correo, u.estado, r.nombre AS rol
               FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = :id');
        $st->execute([':id' => $_SESSION['uid']]);
        $u = $st->fetch();
        if ($u && $u['estado'] === 'ACTIVO') {
            $cache = $u;
        } else {
            cerrar_sesion();
        }
    }
    return $cache;
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function requiere_login(): array
{
    $u = usuario_actual();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

/** Valida rol en el servidor (aunque el usuario escriba la URL directamente). */
function requiere_rol(string $rol): array
{
    $u = requiere_login();
    if ($u['rol'] !== $rol) {
        http_response_code(403);
        $titulo = 'Acceso denegado';
        require __DIR__ . '/header.php';
        echo '<section class="panel"><h1>Acceso denegado</h1>'
           . '<p>No tiene permisos para ver esta página.</p></section>';
        require __DIR__ . '/footer.php';
        exit;
    }
    return $u;
}
