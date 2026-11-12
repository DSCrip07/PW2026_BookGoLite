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
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $desc   = trim((string) ($_POST['descripcion'] ?? ''));
            if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60) {
                flash('error', 'El nombre debe tener entre 2 y 60 caracteres.');
            } elseif (mb_strlen($desc) > 255) {
                flash('error', 'La descripción no puede superar 255 caracteres.');
            } elseif ($accion === 'crear') {
                $pdo->prepare('INSERT INTO categorias (nombre, descripcion) VALUES (:n, :d)')
                    ->execute([':n' => $nombre, ':d' => $desc]);
                flash('exito', 'Categoría creada.');
            } else {
                $pdo->prepare('UPDATE categorias SET nombre = :n, descripcion = :d WHERE id = :id')
                    ->execute([':n' => $nombre, ':d' => $desc, ':id' => $id]);
                flash('exito', 'Categoría actualizada.');
            }
        } elseif ($accion === 'estado') {
            $pdo->prepare("UPDATE categorias SET estado = IF(estado='ACTIVO','INACTIVO','ACTIVO') WHERE id = :id")
                ->execute([':id' => $id]);
            flash('exito', 'Estado de la categoría actualizado.');
        } elseif ($accion === 'eliminar') {
            $c = $pdo->prepare('SELECT COUNT(*) FROM servicios WHERE categoria_id = :id');
            $c->execute([':id' => $id]);
            if ((int) $c->fetchColumn() > 0) {
                flash('error', 'La categoría tiene servicios asociados: no se puede eliminar. Desactívela en su lugar.');
            } else {
                $pdo->prepare('DELETE FROM categorias WHERE id = :id')->execute([':id' => $id]);
                flash('exito', 'Categoría eliminada.');
            }
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            flash('error', 'Ya existe una categoría con ese nombre o el registro tiene dependencias.');
        } else {
            throw $ex;
        }
    }
    redirect('admin/categorias.php');
}

$editar = null;
if (isset($_GET['editar'])) {
    $st = $pdo->prepare('SELECT * FROM categorias WHERE id = :id');
    $st->execute([':id' => (int) $_GET['editar']]);
    $editar = $st->fetch() ?: null;
}
$cats = $pdo->query(
    'SELECT c.*, (SELECT COUNT(*) FROM servicios s WHERE s.categoria_id = c.id) AS n_servicios
       FROM categorias c ORDER BY c.nombre')->fetchAll();

$titulo = 'Categorías';
require __DIR__ . '/../../includes/header.php';
?>
<h1>Categorías</h1>

<section class="panel">
  <h2><?= $editar ? 'Editar categoría' : 'Nueva categoría' ?></h2>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" value="<?= $editar ? 'editar' : 'crear' ?>">
    <?php if ($editar): ?><input type="hidden" name="id" value="<?= (int) $editar['id'] ?>"><?php endif; ?>
    <div class="row">
      <div><label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" required maxlength="60" value="<?= e($editar['nombre'] ?? '') ?>"></div>
      <div><label for="descripcion">Descripción</label>
        <input id="descripcion" name="descripcion" maxlength="255" value="<?= e($editar['descripcion'] ?? '') ?>"></div>
    </div>
    <div class="actions">
      <button type="submit"><?= $editar ? 'Guardar cambios' : 'Crear categoría' ?></button>
      <?php if ($editar): ?><a href="<?= e(url('admin/categorias.php')) ?>">Cancelar</a><?php endif; ?>
    </div>
  </form>
</section>

<section class="panel">
  <div class="tabla-wrap"><table>
    <thead><tr><th>Nombre</th><th>Descripción</th><th>Servicios</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($cats as $c): ?>
      <tr>
        <td><?= e($c['nombre']) ?></td>
        <td class="muted"><?= e($c['descripcion']) ?></td>
        <td><?= (int) $c['n_servicios'] ?></td>
        <td><span class="badge <?= $c['estado'] === 'ACTIVO' ? 'badge-ok' : 'badge-off' ?>"><?= e($c['estado']) ?></span></td>
        <td>
          <a class="btn btn-sm btn-outline" href="<?= e(url('admin/categorias.php?editar=' . (int) $c['id'])) ?>">Editar</a>
          <form class="inline" method="post"><?= csrf_field() ?>
            <input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn-sm btn-outline" type="submit"><?= $c['estado'] === 'ACTIVO' ? 'Desactivar' : 'Activar' ?></button></form>
          <form class="inline" method="post"><?= csrf_field() ?>
            <input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
            <button class="btn-sm btn-danger" type="submit">Eliminar</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
