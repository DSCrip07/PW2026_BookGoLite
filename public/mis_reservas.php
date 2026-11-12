<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/reservas.php';

$u = requiere_rol('CLIENTE');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['accion'] ?? '') === 'cancelar') {
        // Se pasa el id del cliente de la SESIÓN: no puede tocar reservas ajenas.
        $error = cancelar_reserva(db(), (int) ($_POST['id'] ?? 0), (int) $u['id']);
        $error ? flash('error', $error) : flash('exito', 'Reserva cancelada. Se devolvió el cupo.');
    }
    redirect('mis_reservas.php');
}

// Propiedad: SIEMPRE se filtra por el id del usuario de la sesión, nunca por un id recibido.
$st = db()->prepare(
    "SELECT r.id, r.estado, s.nombre AS servicio, h.fecha, h.hora_inicio, h.hora_fin,
            (TIMESTAMP(h.fecha, h.hora_inicio) > NOW()) AS futura,
            (SELECT COUNT(*) FROM resenas rs WHERE rs.reserva_id = r.id) AS tiene_resena
       FROM reservas r
       JOIN servicios s ON s.id = r.servicio_id
       JOIN horarios h  ON h.id = r.horario_id
      WHERE r.cliente_id = :cid
      ORDER BY h.fecha DESC, h.hora_inicio DESC");
$st->execute([':cid' => $u['id']]);
$reservas = $st->fetchAll();

$clase = ['PENDIENTE' => 'badge-pend', 'CONFIRMADA' => 'badge-ok', 'REALIZADA' => 'badge-ok', 'CANCELADA' => 'badge-off'];

$titulo = 'Mis reservas';
require __DIR__ . '/../includes/header.php';
?>
<h1>Mis reservas</h1>
<section class="panel">
  <?php if (!$reservas): ?>
    <p class="muted">Todavía no tiene reservas. <a href="<?= e(url('catalogo.php')) ?>">Explorar servicios</a></p>
  <?php else: ?>
  <div class="tabla-wrap"><table>
    <thead><tr><th>#</th><th>Servicio</th><th>Fecha</th><th>Horario</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($reservas as $r): ?>
      <tr>
        <td><?= (int) $r['id'] ?></td>
        <td><?= e($r['servicio']) ?></td>
        <td><?= e($r['fecha']) ?></td>
        <td><?= e(substr($r['hora_inicio'], 0, 5)) ?> – <?= e(substr($r['hora_fin'], 0, 5)) ?></td>
        <td><span class="badge <?= e($clase[$r['estado']] ?? '') ?>"><?= e($r['estado']) ?></span></td>
        <td>
          <?php if (in_array($r['estado'], ['PENDIENTE', 'CONFIRMADA'], true) && (int) $r['futura']): ?>
            <form class="inline" method="post"><?= csrf_field() ?>
              <input type="hidden" name="accion" value="cancelar"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn-sm btn-danger" type="submit">Cancelar</button></form>
          <?php elseif ($r['estado'] === 'REALIZADA' && !(int) $r['tiene_resena']): ?>
            <a class="btn btn-sm" href="<?= e(url('resenar.php?reserva=' . (int) $r['id'])) ?>">Reseñar</a>
          <?php elseif ($r['estado'] === 'REALIZADA'): ?>
            <span class="muted">Reseñada</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
