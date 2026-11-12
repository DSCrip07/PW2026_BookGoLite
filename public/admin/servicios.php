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
            $cat    = (int) ($_POST['categoria_id'] ?? 0);
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $desc   = trim((string) ($_POST['descripcion'] ?? ''));
            $precio = $_POST['precio'] ?? '';
            $dur    = $_POST['duracion_min'] ?? '';

            $err = [];
            $c = $pdo->prepare("SELECT COUNT(*) FROM categorias WHERE id = :id AND estado = 'ACTIVO'");
            $c->execute([':id' => $cat]);
            if (!(int) $c->fetchColumn())                     $err[] = 'Seleccione una categoría activa válida.';
            if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 100) $err[] = 'El nombre debe tener entre 2 y 100 caracteres.';
            if (mb_strlen($desc) > 1000)                      $err[] = 'La descripción es demasiado larga.';
            if (!is_numeric($precio) || (float) $precio < 0 || (float) $precio > 9999.99)
                                                              $err[] = 'El precio debe ser un número entre 0 y 9999.99 (no negativo).';
            if (filter_var($dur, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 600]]) === false)
                                                              $err[] = 'La duración debe ser un entero mayor que 0 (máx. 600 min).';

            if ($err) {
                foreach ($err as $m) flash('error', $m);
            } elseif ($accion === 'crear') {
                $pdo->prepare('INSERT INTO servicios (categoria_id, nombre, descripcion, precio, duracion_min)
                               VALUES (:c, :n, :d, :p, :m)')
                    ->execute([':c' => $cat, ':n' => $nombre, ':d' => $desc, ':p' => $precio, ':m' => (int) $dur]);
                flash('exito', 'Servicio creado.');
            } else {
                $pdo->prepare('UPDATE servicios SET categoria_id = :c, nombre = :n, descripcion = :d,
                               precio = :p, duracion_min = :m WHERE id = :id')
                    ->execute([':c' => $cat, ':n' => $nombre, ':d' => $desc, ':p' => $precio,
                               ':m' => (int) $dur, ':id' => $id]);
                flash('exito', 'Servicio actualizado.');
            }
        } elseif ($accion === 'estado') {
            $pdo->prepare("UPDATE servicios SET estado = IF(estado='ACTIVO','INACTIVO','ACTIVO') WHERE id = :id")
                ->execute([':id' => $id]);
            flash('exito', 'Estado del servicio actualizado.');
        } elseif ($accion === 'eliminar') {
            $c = $pdo->prepare('SELECT (SELECT COUNT(*) FROM horarios WHERE servicio_id = :a)
                                     + (SELECT COUNT(*) FROM reservas WHERE servicio_id = :b)');
            $c->execute([':a' => $id, ':b' => $id]);
            if ((int) $c->fetchColumn() > 0) {
                flash('error', 'El servicio tiene horarios o reservas: no se elimina físicamente. Desactívelo en su lugar.');
            } else {
                $pdo->prepare('DELETE FROM servicios WHERE id = :id')->execute([':id' => $id]);
                flash('exito', 'Servicio eliminado.');
            }
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            flash('error', 'No se pudo completar la operación por restricciones de integridad.');
        } else {
            throw $ex;
        }
    }
    redirect('admin/servicios.php');
}

$editar = null;
if (isset($_GET['editar'])) {
    $st = $pdo->prepare('SELECT * FROM servicios WHERE id = :id');
    $st->execute([':id' => (int) $_GET['editar']]);
    $editar = $st->fetch() ?: null;
}
$categorias = $pdo->query("SELECT id, nombre FROM categorias WHERE estado = 'ACTIVO' ORDER BY nombre")->fetchAll();
$servicios = $pdo->query(
    'SELECT s.*, c.nombre AS categoria FROM servicios s JOIN categorias c ON c.id = s.categoria_id
      ORDER BY c.nombre, s.nombre')->fetchAll();

$titulo = 'Servicios';
require __DIR__ . '/../../includes/header.php';
?>
<h1>Servicios</h1>

<section class="panel">
  <h2><?= $editar ? 'Editar servicio' : 'Nuevo servicio' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'crear' ?>">
    <?php if ($editar): ?><input type="hidden" name="id" value="<?= (int) $editar['id'] ?>"><?php endif; ?>
    <div class="row">
      <div><label for="categoria_id">Categoría</label>
        <select id="categoria_id" name="categoria_id" required>
          <?php foreach ($categorias as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= ($editar['categoria_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
          <?php endforeach; ?>
        </select></div>
      <div><label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" required maxlength="100" value="<?= e($editar['nombre'] ?? '') ?>"></div>
      <div><label for="precio">Precio ($)</label>
        <input id="precio" name="precio" type="number" step="0.01" min="0" max="9999.99" required value="<?= e($editar['precio'] ?? '') ?>"></div>
      <div><label for="duracion_min">Duración (min)</label>
        <input id="duracion_min" name="duracion_min" type="number" min="1" max="600" required value="<?= e($editar['duracion_min'] ?? '') ?>"></div>
    </div>
    <label for="descripcion">Descripción</label>
    <textarea id="descripcion" name="descripcion" rows="3" maxlength="1000"><?= e($editar['descripcion'] ?? '') ?></textarea>
    <div class="actions">
      <button type="submit"><?= $editar ? 'Guardar cambios' : 'Crear servicio' ?></button>
      <?php if ($editar): ?><a href="<?= e(url('admin/servicios.php')) ?>">Cancelar</a><?php endif; ?>
    </div>
  </form>
</section>

<section class="panel">
  <div class="tabla-wrap"><table>
    <thead><tr><th>Servicio</th><th>Categoría</th><th>Precio</th><th>Duración</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($servicios as $s): ?>
      <tr>
        <td><?= e($s['nombre']) ?></td>
        <td><?= e($s['categoria']) ?></td>
        <td>$<?= e(number_format((float) $s['precio'], 2)) ?></td>
        <td><?= (int) $s['duracion_min'] ?> min</td>
        <td><span class="badge <?= $s['estado'] === 'ACTIVO' ? 'badge-ok' : 'badge-off' ?>"><?= e($s['estado']) ?></span></td>
        <td>
          <a class="btn btn-sm btn-outline" href="<?= e(url('admin/servicios.php?editar=' . (int) $s['id'])) ?>">Editar</a>
          <form class="inline" method="post"><?= csrf_field() ?>
            <input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button class="btn-sm btn-outline" type="submit"><?= $s['estado'] === 'ACTIVO' ? 'Desactivar' : 'Activar' ?></button></form>
          <form class="inline" method="post"><?= csrf_field() ?>
            <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
            <button class="btn-sm btn-danger" type="submit">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
