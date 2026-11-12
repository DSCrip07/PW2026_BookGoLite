<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$u = requiere_rol('CLIENTE');
$reservaId = (int) ($_REQUEST['reserva'] ?? 0);

// La reserva debe ser del cliente de la sesión y estar REALIZADA.
$st = db()->prepare(
    "SELECT r.id, r.servicio_id, s.nombre AS servicio
       FROM reservas r JOIN servicios s ON s.id = r.servicio_id
      WHERE r.id = :id AND r.cliente_id = :c AND r.estado = 'REALIZADA'");
$st->execute([':id' => $reservaId, ':c' => $u['id']]);
$r = $st->fetch();
if (!$r) {
    flash('error', 'Solo puede reseñar sus propias reservas con estado REALIZADA.');
    redirect('mis_reservas.php');
}

$errores = [];
$calif = 5;
$coment = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $calif  = filter_var($_POST['calificacion'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    $coment = trim((string) ($_POST['comentario'] ?? ''));
    if ($calif === false)                                   $errores[] = 'La calificación debe ser un número de 1 a 5.';
    if (mb_strlen($coment) < 3 || mb_strlen($coment) > 500) $errores[] = 'El comentario debe tener entre 3 y 500 caracteres.';

    if (!$errores) {
        try {
            db()->prepare('INSERT INTO resenas (reserva_id, cliente_id, calificacion, comentario)
                           VALUES (:r, :c, :k, :t)')
                ->execute([':r' => $reservaId, ':c' => $u['id'], ':k' => $calif, ':t' => $coment]);
            flash('exito', '¡Gracias por su reseña!');
            redirect('servicio.php?id=' . (int) $r['servicio_id']);
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {               // reserva_id UNIQUE
                flash('error', 'Esa reserva ya tiene una reseña.');
                redirect('mis_reservas.php');
            }
            throw $ex;
        }
    }
}

$titulo = 'Reseñar';
require __DIR__ . '/../includes/header.php';
?>
<section class="panel form-narrow">
  <h1>Reseñar: <?= e($r['servicio']) ?></h1>
  <?php foreach ($errores as $m): ?><div class="alert alert-error" role="alert"><?= e($m) ?></div><?php endforeach; ?>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="reserva" value="<?= (int) $reservaId ?>">
    <label for="calificacion">Calificación</label>
    <select id="calificacion" name="calificacion">
      <?php for ($i = 5; $i >= 1; $i--): ?>
        <option value="<?= $i ?>" <?= (int) $calif === $i ? 'selected' : '' ?>><?= str_repeat('★', $i) ?> (<?= $i ?>)</option>
      <?php endfor; ?>
    </select>
    <label for="comentario">Comentario</label>
    <textarea id="comentario" name="comentario" rows="4" required minlength="3" maxlength="500"><?= e($coment) ?></textarea>
    <div class="actions">
      <button type="submit">Publicar reseña</button>
      <a href="<?= e(url('mis_reservas.php')) ?>">Cancelar</a>
    </div>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
