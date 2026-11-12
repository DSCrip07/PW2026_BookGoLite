<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare(
    "SELECT s.*, c.nombre AS categoria FROM servicios s
       JOIN categorias c ON c.id = s.categoria_id
      WHERE s.id = :id AND s.estado = 'ACTIVO' AND c.estado = 'ACTIVO'");
$st->execute([':id' => $id]);
$s = $st->fetch();
if (!$s) {
    http_response_code(404);
    $titulo = 'No encontrado';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="panel"><h1>Servicio no encontrado</h1><a href="' . e(url('catalogo.php')) . '">Volver al catálogo</a></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// Horarios futuros activos
$h = db()->prepare(
    "SELECT id, fecha, hora_inicio, hora_fin, cupos_totales, cupos_disponibles
       FROM horarios
      WHERE servicio_id = :id AND estado = 'ACTIVO' AND TIMESTAMP(fecha, hora_inicio) > NOW()
      ORDER BY fecha, hora_inicio");
$h->execute([':id' => $id]);
$horarios = $h->fetchAll();

// Reseñas del servicio (a través de la reserva)
$r = db()->prepare(
    "SELECT rs.calificacion, rs.comentario, rs.fecha, u.nombre
       FROM resenas rs
       JOIN reservas rv ON rv.id = rs.reserva_id
       JOIN usuarios u  ON u.id = rs.cliente_id
      WHERE rv.servicio_id = :id ORDER BY rs.fecha DESC");
$r->execute([':id' => $id]);
$resenas = $r->fetchAll();
$prom = $resenas ? array_sum(array_column($resenas, 'calificacion')) / count($resenas) : 0;

$u = usuario_actual();
$titulo = $s['nombre'];
require __DIR__ . '/../includes/header.php';
?>
<p><a href="<?= e(url('catalogo.php')) ?>">← Volver al catálogo</a></p>
<section class="panel">
  <span class="badge"><?= e($s['categoria']) ?></span>
  <h1><?= e($s['nombre']) ?></h1>
  <p><?= e($s['descripcion']) ?></p>
  <p><span class="precio">$<?= e(number_format((float) $s['precio'], 2)) ?></span>
     · <?= e($s['duracion_min']) ?> minutos</p>
  <?php if ($resenas): ?>
    <p><span class="estrellas"><?= str_repeat('★', (int) round($prom)) . str_repeat('☆', 5 - (int) round($prom)) ?></span>
       <?= e(number_format($prom, 1)) ?> (<?= count($resenas) ?> reseñas)</p>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>Horarios disponibles</h2>
  <?php if (!$horarios): ?>
    <p class="muted">No hay horarios futuros disponibles por ahora.</p>
  <?php else: ?>
  <div class="tabla-wrap"><table>
    <thead><tr><th>Fecha</th><th>Inicio</th><th>Fin</th><th>Cupos totales</th><th>Cupos disponibles</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($horarios as $x): ?>
      <tr>
        <td><?= e($x['fecha']) ?></td>
        <td><?= e(substr($x['hora_inicio'], 0, 5)) ?></td>
        <td><?= e(substr($x['hora_fin'], 0, 5)) ?></td>
        <td><?= (int) $x['cupos_totales'] ?></td>
        <td><?= (int) $x['cupos_disponibles'] > 0
              ? '<span class="badge badge-ok">' . (int) $x['cupos_disponibles'] . '</span>'
              : '<span class="badge badge-off">Agotado</span>' ?></td>
        <td>
          <?php if ((int) $x['cupos_disponibles'] > 0 && $u && $u['rol'] === 'CLIENTE'): ?>
            <form class="inline" method="post" action="<?= e(url('reservar.php')) ?>"><?= csrf_field() ?>
              <input type="hidden" name="servicio_id" value="<?= (int) $s['id'] ?>">
              <input type="hidden" name="horario_id" value="<?= (int) $x['id'] ?>">
              <button class="btn-sm" type="submit">Reservar</button></form>
          <?php elseif ((int) $x['cupos_disponibles'] > 0 && !$u): ?>
            <a href="<?= e(url('login.php')) ?>">Inicie sesión para reservar</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>

<section class="panel">
  <h2>Reseñas</h2>
  <?php if (!$resenas): ?><p class="muted">Aún no hay reseñas para este servicio.</p><?php endif; ?>
  <?php foreach ($resenas as $x): ?>
    <p><span class="estrellas"><?= str_repeat('★', (int) $x['calificacion']) . str_repeat('☆', 5 - (int) $x['calificacion']) ?></span>
       <strong><?= e($x['nombre']) ?></strong> <span class="muted">· <?= e(substr($x['fecha'], 0, 10)) ?></span><br>
       <?= e($x['comentario']) ?></p>
  <?php endforeach; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
