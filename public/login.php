<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

const MAX_INTENTOS = 5;   // intentos fallidos permitidos antes de bloquear
const BLOQUEO_MIN  = 5;   // minutos de bloqueo

if (usuario_actual()) {
    redirect('index.php');
}

$error      = '';
$correo     = '';
$bloqueoSeg = 0;          // segundos que faltan para poder intentar con el correo bloqueado
$bloqueoCorreo = '';      // correo al que corresponde el bloqueo mostrado

// Aviso informativo: se recuerda el último correo bloqueado para mostrar la cuenta regresiva
// al recargar. NO bloquea el formulario: se puede iniciar sesión con otra cuenta.
if (!empty($_SESSION['bloqueo']['hasta']) && $_SESSION['bloqueo']['hasta'] > time()) {
    $bloqueoSeg    = $_SESSION['bloqueo']['hasta'] - time();
    $bloqueoCorreo = (string) $_SESSION['bloqueo']['correo'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $bloqueoSeg = 0;          // el bloqueo se evalúa de nuevo para el correo que se envía
    $bloqueoCorreo = '';
    $correo = trim((string) ($_POST['correo'] ?? ''));
    $clave  = (string) ($_POST['clave'] ?? '');

    $st = db()->prepare(
        'SELECT u.id, u.password_hash, u.estado, u.intentos_fallidos, r.nombre AS rol,
                IFNULL(TIMESTAMPDIFF(SECOND, NOW(), u.bloqueado_hasta), 0) AS bloqueo_seg
           FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.correo = :c');
    $st->execute([':c' => $correo]);
    $u = $st->fetch();

    // Hash falso para que el tiempo de respuesta no delate si el correo existe.
    $hash    = $u['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuJ9o3a2r6R9xH0o5m1q0b8vKQ5T7yO9W';
    $claveOk = password_verify($clave, $hash);

    // El contador por sesión se aplica a CUALQUIER correo (exista o no), así el
    // mensaje y la cuenta regresiva son iguales y no revelan si la cuenta existe.
    $clavesSesion = hash('sha256', mb_strtolower($correo));
    if (count($_SESSION['intentos'] ?? []) > 50) {
        $_SESSION['intentos'] = [];
    }
    $ses = $_SESSION['intentos'][$clavesSesion] ?? ['n' => 0, 'hasta' => 0];

    // Segundos restantes: el mayor entre el bloqueo de la BD (cuenta) y el de la sesión.
    $seg = max($u ? max(0, (int) $u['bloqueo_seg']) : 0, $ses['hasta'] - time(), 0);

    if ($seg === 0 && $u && $claveOk && $u['estado'] === 'ACTIVO') {
        db()->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = :id')
            ->execute([':id' => $u['id']]);
        unset($_SESSION['intentos'][$clavesSesion], $_SESSION['bloqueo']);
        session_regenerate_id(true);          // evita fijación de sesión
        $_SESSION['uid']  = (int) $u['id'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        redirect($u['rol'] === 'ADMIN' ? 'admin/index.php' : 'catalogo.php');
    }

    if ($seg === 0) {                         // intento fallido: se cuenta
        $n = ($u ? (int) $u['intentos_fallidos'] : (int) $ses['n']) + 1;
        if ($n >= MAX_INTENTOS) {
            $seg = BLOQUEO_MIN * 60;
            if ($u) {
                db()->prepare('UPDATE usuarios SET intentos_fallidos = 0,
                               bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ' . BLOQUEO_MIN . ' MINUTE) WHERE id = :id')
                    ->execute([':id' => $u['id']]);
            }
            $ses = ['n' => 0, 'hasta' => time() + $seg];
        } else {
            if ($u) {
                db()->prepare('UPDATE usuarios SET intentos_fallidos = :n WHERE id = :id')
                    ->execute([':n' => $n, ':id' => $u['id']]);
            }
            $ses['n'] = $n;
            $resta = MAX_INTENTOS - $n;
            $error = 'Correo o contraseña incorrectos. Le ' . ($resta === 1 ? 'queda 1 intento' : "quedan $resta intentos")
                   . ' antes del bloqueo temporal.';
        }
    }
    $_SESSION['intentos'][$clavesSesion] = $ses;

    if ($seg > 0) {
        $bloqueoSeg    = $seg;
        $bloqueoCorreo = $correo;
        $_SESSION['bloqueo'] = ['correo' => $correo, 'hasta' => time() + $seg];
        $error = '';
    } else {
        unset($_SESSION['bloqueo']);   // este correo no está bloqueado: no se muestra aviso
    }
}

$titulo = 'Iniciar sesión';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel form-narrow">
  <h1>Iniciar sesión</h1>
  <?php if ($bloqueoSeg > 0): ?>
    <div class="alert alert-error" role="alert">
      Demasiados intentos fallidos con <strong><?= e($bloqueoCorreo) ?></strong>: ese acceso está bloqueado temporalmente.
      Podrá intentar con ese correo de nuevo en
      <strong id="cuenta-regresiva" data-segundos="<?= (int) $bloqueoSeg ?>"><?= e(sprintf('%02d:%02d', intdiv($bloqueoSeg, 60), $bloqueoSeg % 60)) ?></strong>.
      Mientras tanto puede iniciar sesión con otra cuenta.
    </div>
  <?php elseif ($error): ?>
    <div class="alert alert-error" role="alert"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <label for="correo">Correo electrónico</label>
    <input id="correo" name="correo" type="email" required maxlength="120" value="<?= e($correo) ?>">
    <label for="clave">Contraseña</label>
    <input id="clave" name="clave" type="password" required>
    <div class="actions">
      <button id="btn-entrar" type="submit">Entrar</button>
      <a href="<?= e(url('registro.php')) ?>">Crear una cuenta</a>
    </div>
  </form>
</section>
<?php if ($bloqueoSeg > 0): ?>
  <script src="<?= e(url('js/bloqueo.js')) ?>"></script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
