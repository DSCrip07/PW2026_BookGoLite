<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$u = usuario_actual();
if ($u && $u['rol'] === 'ADMIN') {
    redirect('admin/index.php');
}

$destacados = db()->query(
    "SELECT s.id, s.nombre, s.descripcion, s.precio, s.duracion_min, c.nombre AS categoria
       FROM servicios s JOIN categorias c ON c.id = s.categoria_id
      WHERE s.estado = 'ACTIVO' AND c.estado = 'ACTIVO'
      ORDER BY s.id LIMIT 3")->fetchAll();

$titulo = 'Inicio';
require __DIR__ . '/../includes/header.php';
?>
<section class="hero">
  <h1>Corte, barba y estilo a tu medida</h1>
  <p>Reserva tu cita en <?= e(APP_NAME) ?> en pocos pasos: elige el servicio, escoge tu horario y listo.</p>
  <a class="btn" href="<?= e(url('catalogo.php')) ?>">Ver servicios</a>
  <?php if (!$u): ?> <a class="btn btn-outline" href="<?= e(url('registro.php')) ?>">Crear cuenta</a><?php endif; ?>
</section>

<h2>Servicios destacados</h2>
<div class="grid">
  <?php foreach ($destacados as $s): ?>
    <article class="card">
      <div class="banda"><?= e($s['categoria']) ?></div>
      <div class="cuerpo">
        <h3><?= e($s['nombre']) ?></h3>
        <p class="muted"><?= e($s['descripcion']) ?></p>
        <span class="precio">$<?= e(number_format((float) $s['precio'], 2)) ?></span>
        <span class="muted"><?= e($s['duracion_min']) ?> min</span>
        <a class="btn btn-sm" href="<?= e(url('servicio.php?id=' . (int) $s['id'])) ?>">Ver detalle</a>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
