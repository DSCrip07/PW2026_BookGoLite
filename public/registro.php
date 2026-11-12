<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/password_policy.php';

if (usuario_actual()) {
    redirect('index.php');
}

$errores = [];
$d = ['nombre' => '', 'apellido' => '', 'correo' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $d['nombre']   = trim((string) ($_POST['nombre'] ?? ''));
    $d['apellido'] = trim((string) ($_POST['apellido'] ?? ''));
    $d['correo']   = trim((string) ($_POST['correo'] ?? ''));
    $clave  = (string) ($_POST['clave'] ?? '');
    $clave2 = (string) ($_POST['clave2'] ?? '');

    foreach (['nombre' => 'El nombre', 'apellido' => 'El apellido'] as $k => $et) {
        if (!preg_match('/^[\p{L} \'-]{2,60}$/u', $d[$k])) {
            $errores[] = "$et debe tener entre 2 y 60 letras.";
        }
    }
    if (!filter_var($d['correo'], FILTER_VALIDATE_EMAIL) || strlen($d['correo']) > 120) {
        $errores[] = 'Ingrese un correo electrónico válido.';
    }
    if ($faltan = errores_password($clave)) {
        $errores[] = mensaje_password($faltan);
    }
    if ($clave !== $clave2) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        try {
            // El registro público SOLO crea CLIENTE (rol_id = 2 fijado en el servidor).
            db()->prepare('INSERT INTO usuarios (rol_id, nombre, apellido, correo, password_hash)
                           VALUES (2, :n, :a, :c, :h)')
                ->execute([':n' => $d['nombre'], ':a' => $d['apellido'], ':c' => $d['correo'],
                           ':h' => password_hash($clave, PASSWORD_DEFAULT)]);
            flash('exito', 'Cuenta creada. Ya puede iniciar sesión.');
            redirect('login.php');
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {      // correo duplicado (UNIQUE)
                $errores[] = 'Ese correo ya está registrado.';
            } else {
                throw $ex;
            }
        }
    }
}

$titulo = 'Registro';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel form-narrow">
  <h1>Crear cuenta</h1>
  <?php foreach ($errores as $m): ?><div class="alert alert-error" role="alert"><?= e($m) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <div class="row">
      <div><label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" required maxlength="60" value="<?= e($d['nombre']) ?>"></div>
      <div><label for="apellido">Apellido</label>
        <input id="apellido" name="apellido" required maxlength="60" value="<?= e($d['apellido']) ?>"></div>
    </div>
    <label for="correo">Correo electrónico</label>
    <input id="correo" name="correo" type="email" required maxlength="120" value="<?= e($d['correo']) ?>">
    <label for="clave">Contraseña</label>
    <input id="clave" name="clave" type="password" required minlength="8" autocomplete="new-password">
    <small class="muted">Mínimo 8 caracteres, con mayúscula, minúscula, número y un carácter especial (! @ # $ % &amp; *).</small>
    <label for="clave2">Repetir contraseña</label>
    <input id="clave2" name="clave2" type="password" required minlength="8" autocomplete="new-password">
    <div class="actions">
      <button type="submit">Registrarme</button>
      <a href="<?= e(url('login.php')) ?>">Ya tengo cuenta</a>
    </div>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
