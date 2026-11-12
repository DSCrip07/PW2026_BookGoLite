<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
requiere_rol('ADMIN');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $accion = (string) ($_POST['accion'] ?? '');
    $id     = (int) ($_POST['id'] ?? 0);
    try {
        if ($accion === 'crear' || $accion === 'editar') {
            $servicio = (int) ($_POST['servicio_id'] ?? 0);
            $fecha    = (string) ($_POST['fecha'] ?? '');
            $ini      = (string) ($_POST['hora_inicio'] ?? '');
            $fin      = (string) ($_POST['hora_fin'] ?? '');
            $cupos    = filter_var($_POST['cupos_totales'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);

            $err = [];
            $c = $pdo->prepare("SELECT COUNT(*) FROM servicios WHERE id = :id AND estado = 'ACTIVO'");
            $c->execute([':id' => $servicio]);
            if (!(int) $c->fetchColumn()) $err[] = 'Seleccione un servicio activo válido.';

            $d  = DateTime::createFromFormat('!Y-m-d', $fecha);
            $hi = DateTime::createFromFormat('!H:i', $ini);
            $hf = DateTime::createFromFormat('!H:i', $fin);
            if (!$d || $d->format('Y-m-d') !== $fecha)  $err[] = 'Fecha inválida.';
            if (!$hi || !$hf)                           $err[] = 'Hora inválida.';
            if ($cupos === false)                       $err[] = 'Los cupos deben ser un entero entre 1 y 100.';
            if ($hi && $hf && $hf <= $hi)               $err[] = 'La hora final debe ser posterior a la inicial.';
            if ($d && $hi && $d->format('Y-m-d') . ' ' . $hi->format('H:i') <= date('Y-m-d H:i')) {
                $err[] = 'No se pueden crear horarios en el pasado.';
            }

            if ($err) {
                foreach ($err as $m) flash('error', $m);
            } elseif ($accion === 'crear') {
                $pdo->prepare('INSERT INTO horarios (servicio_id, fecha, hora_inicio, hora_fin, cupos_totales, cupos_disponibles)
                               VALUES (:s, :f, :i, :e, :c, :c2)')
                    ->execute([':s' => $servicio, ':f' => $fecha, ':i' => $ini, ':e' => $fin, ':c' => $cupos, ':c2' => $cupos]);
                flash('exito', 'Horario creado.');
            } else {
                // Los cupos ya reservados (total - disponibles) se conservan; el nuevo total no puede ser menor.
                $u = $pdo->prepare('UPDATE horarios
                       SET servicio_id = :s, fecha = :f, hora_inicio = :i, hora_fin = :e,
                           cupos_disponibles = :c - (cupos_totales - cupos_disponibles),
                           cupos_totales = :c2
                     WHERE id = :id AND (cupos_totales - cupos_disponibles) <= :c3');
                $u->execute([':s' => $servicio, ':f' => $fecha, ':i' => $ini, ':e' => $fin,
                             ':c' => $cupos, ':c2' => $cupos, ':c3' => $cupos, ':id' => $id]);
                $u->rowCount() ? flash('exito', 'Horario actualizado.')
                               : flash('error', 'No se actualizó: los cupos totales no pueden ser menores a los ya reservados (o no hubo cambios).');
            }
        } elseif ($accion === 'estado') {
            $pdo->prepare("UPDATE horarios SET estado = IF(estado='ACTIVO','INACTIVO','ACTIVO') WHERE id = :id")
                ->execute([':id' => $id]);
            flash('exito', 'Estado del horario actualizado.');
        } elseif ($accion === 'eliminar') {
            $c = $pdo->prepare('SELECT COUNT(*) FROM reservas WHERE horario_id = :id');
            $c->execute([':id' => $id]);
            if ((int) $c->fetchColumn() > 0) {
                flash('error', 'El horario tiene reservas: no se elimina físicamente. Desactívelo en su lugar.');
            } else {
                $pdo->prepare('DELETE FROM horarios WHERE id = :id')->execute([':id' => $id]);
                flash('exito', 'Horario eliminado.');
            }
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            flash('error', 'No se pudo completar la operación por restricciones de integridad.');
        } else {
            throw $ex;
        }
    }
    redirect('admin/horarios.php');
}

$editar = null;
if (isset($_GET['editar'])) {
    $st = $pdo->prepare('SELECT * FROM horarios WHERE id = :id');
    $st->execute([':id' => (int) $_GET['editar']]);
    $editar = $st->fetch() ?: null;
}
$servicios = $pdo->query("SELECT id, nombre FROM servicios WHERE estado = 'ACTIVO' ORDER BY nombre")->fetchAll();
$horarios = $pdo->query(
    "SELECT h.*, s.nombre AS servicio,
            (SELECT COUNT(*) FROM reservas r WHERE r.horario_id = h.id) AS n_reservas
       FROM horarios h JOIN servicios s ON s.id = h.servicio_id
      ORDER BY h.fecha DESC, h.hora_inicio DESC")->fetchAll();

$titulo = 'Horarios';
require __DIR__ . '/../../includes/header.php';
?>
<h1>Horarios</h1>

<section class="panel">
  <h2><?= $editar ? 'Editar horario' : 'Nuevo horario' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'crear' ?>">
    <?php if ($editar): ?><input type="hidden" name="id" value="<?= (int) $editar['id'] ?>"><?php endif; ?>
    <div class="row">
      <div><label for="servicio_id">Servicio</label>
        <select id="servicio_id" name="servicio_id" required>
          <?php foreach ($servicios as $s): ?>
            <option value="<?= (int) $s['id'] ?>" <?= ($editar['servicio_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="fecha">Fecha</label>
        <input id="fecha" name="fecha" type="date" required min="<?= e(date('Y-m-d')) ?>" value="<?= e($editar['fecha'] ?? '') ?>"></div>
      <div><label for="hora_inicio">Hora inicio</label>
        <input id="hora_inicio" name="hora_inicio" type="time" required value="<?= e(substr($editar['hora_inicio'] ?? '', 0, 5)) ?>"></div>
      <div><label for="hora_fin">Hora fin</label>
        <input id="hora_fin" name="hora_fin" type="time" required value="<?= e(substr($editar['hora_fin'] ?? '', 0, 5)) ?>"></div>
      <div><label for="cupos_totales">Cupos totales</label>
        <input id="cupos_totales" name="cupos_totales" type="number" min="1" max="100" required value="<?= e($editar['cupos_totales'] ?? '') ?>"></div>
    </div>
    <div class="actions">
      <button type="submit"><?= $editar ? 'Guardar cambios' : 'Crear horario' ?></button>
      <?php if ($editar): ?><a href="<?= e(url('admin/horarios.php')) ?>">Cancelar</a><?php endif; ?>
    </div>
  </form>
</section>

<section class="panel">
  <div class="tabla-wrap"><table>
    <thead><tr><th>Servicio</th><th>Fecha</th><th>Inicio</th><th>Fin</th><th>Cupos totales</th><th>Disponibles</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($horarios as $h): ?>
      <tr>
        <td><?= e($h['servicio']) ?></td>
        <td><?= e($h['fecha']) ?></td>
        <td><?= e(substr($h['hora_inicio'], 0, 5)) ?></td>
        <td><?= e(substr($h['hora_fin'], 0, 5)) ?></td>
        <td><?= (int) $h['cupos_totales'] ?></td>
        <td><?= (int) $h['cupos_disponibles'] ?></td>
        <td><span class="badge <?= $h['estado'] === 'ACTIVO' ? 'badge-ok' : 'badge-off' ?>"><?= e($h['estado']) ?></span></td>
        <td>
          <a class="btn btn-sm btn-outline" href="<?= e(url('admin/horarios.php?editar=' . (int) $h['id'])) ?>">Editar</a>
          <form class="inline" method="post"><?= csrf_field() ?>
            <input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
            <button class="btn-sm btn-outline" type="submit"><?= $h['estado'] === 'ACTIVO' ? 'Desactivar' : 'Activar' ?></button></form>
          <form class="inline" method="post"><?= csrf_field() ?>
            <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
            <button class="btn-sm btn-danger" type="submit">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
