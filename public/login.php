<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

const MAX_INTENTOS = 5;
const BLOQUEO_MIN  = 5;

if (usuario_actual()) {
    redirect('index.php');
}

$error  = '';
$correo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $correo = trim((string) ($_POST['correo'] ?? ''));
    $clave  = (string) ($_POST['clave'] ?? '');
    $generico = 'Credenciales inválidas o cuenta bloqueada temporalmente. Intente de nuevo más tarde.';

    $st = db()->prepare(
        'SELECT u.id, u.password_hash, u.estado, u.intentos_fallidos,
                (u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW()) AS bloqueado, r.nombre AS rol
           FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.correo = :c');
    $st->execute([':c' => $correo]);
    $u = $st->fetch();

    // Hash falso para que el tiempo de respuesta no delate si el correo existe.
    $hash = $u['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuJ9o3a2r6R9xH0o5m1q0b8vKQ5T7yO9W';
    $claveOk = password_verify($clave, $hash);

    // Se compara en SQL (NOW()) para no depender de la zona horaria de PHP.
    $bloqueado = $u && (int) $u['bloqueado'] === 1;

    if ($u && !$bloqueado && $claveOk && $u['estado'] === 'ACTIVO') {
        db()->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id')
            ->execute([':id' => $u['id']]);
        session_regenerate_id(true);          // evita fijación de sesión
        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        redirect($u['rol'] === 'ADMIN' ? 'admin/index.php' : 'catalogo.php');
    }

    if ($u && !$bloqueado) {                   // contar intento fallido
        $n = (int) $u['intentos_fallidos'] + 1;
        if ($n >= MAX_INTENTOS) {
            db()->prepare('UPDATE usuarios SET intentos_fallidos = 0,
                           bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ' . BLOQUEO_MIN . ' MINUTE) WHERE id = :id')
                ->execute([':id' => $u['id']]);
        } else {
            db()->prepare('UPDATE usuarios SET intentos_fallidos = :n WHERE id = :id')
                ->execute([':n' => $n, ':id' => $u['id']]);
        }
    }
    $error = $generico;
}

$titulo = 'Iniciar sesión';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel form-narrow">
  <h1>Iniciar sesión</h1>
  <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <label for="correo">Correo electrónico</label>
    <input id="correo" name="correo" type="email" required maxlength="120" value="<?= e($correo) ?>">
    <label for="clave">Contraseña</label>
    <input id="clave" name="clave" type="password" required>
    <div class="actions">
      <button type="submit">Entrar</button>
      <a href="<?= e(url('registro.php')) ?>">Crear una cuenta</a>
    </div>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
