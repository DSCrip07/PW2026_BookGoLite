<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
$u = requiere_rol('ADMIN');

$n = fn(string $sql) => (int) db()->query($sql)->fetchColumn();
$stats = [
    'Categorías activas' => $n("SELECT COUNT(*) FROM categorias WHERE estado='ACTIVO'"),
    'Servicios activos'  => $n("SELECT COUNT(*) FROM servicios WHERE estado='ACTIVO'"),
    'Horarios futuros'   => $n("SELECT COUNT(*) FROM horarios WHERE estado='ACTIVO' AND TIMESTAMP(fecha,hora_inicio) > NOW()"),
    'Reservas pendientes'=> $n("SELECT COUNT(*) FROM reservas WHERE estado='PENDIENTE'"),
    'Clientes'           => $n("SELECT COUNT(*) FROM usuarios WHERE rol_id=2"),
];

$titulo = 'Panel de administración';
require __DIR__ . '/../../includes/header.php';
?>
<h1>Panel de administración</h1>
<p class="muted">Bienvenido, <?= e($u['nombre']) ?>.</p>
<div class="stats">
  <?php foreach ($stats as $et => $v): ?>
    <div class="stat"><b><?= $v ?></b><?= e($et) ?></div>
  <?php endforeach; ?>
</div>
<div class="panel">
  <h2>Gestión</h2>
  <div class="actions">
    <a class="btn" href="<?= e(url('admin/categorias.php')) ?>">Categorías</a>
    <a class="btn" href="<?= e(url('admin/servicios.php')) ?>">Servicios</a>
    <a class="btn" href="<?= e(url('admin/horarios.php')) ?>">Horarios</a>
    <a class="btn" href="<?= e(url('admin/reservas.php')) ?>">Reservas</a>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
