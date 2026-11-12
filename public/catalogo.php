<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

$q   = trim((string) ($_GET['q'] ?? ''));
$cat = (int) ($_GET['categoria'] ?? 0);

$categorias = db()->query("SELECT id, nombre FROM categorias WHERE estado = 'ACTIVO' ORDER BY nombre")->fetchAll();

// Filtros siempre como parámetros preparados (nunca concatenados).
$sql = "SELECT s.id, s.nombre, s.descripcion, s.precio, s.duracion_min, c.nombre AS categoria
          FROM servicios s JOIN categorias c ON c.id = s.categoria_id
         WHERE s.estado = 'ACTIVO' AND c.estado = 'ACTIVO'";
$params = [];
if ($q !== '') {
    $sql .= ' AND s.nombre LIKE :q';
    $params[':q'] = '%' . addcslashes($q, '%_\\') . '%';
}
if ($cat > 0) {
    $sql .= ' AND s.categoria_id = :cat';
    $params[':cat'] = $cat;
}
$sql .= ' ORDER BY c.nombre, s.nombre';
$st = db()->prepare($sql);
$st->execute($params);
$servicios = $st->fetchAll();

$titulo = 'Catálogo de servicios';
require __DIR__ . '/../includes/header.php';
?>
<h1>Catálogo de servicios</h1>
<form class="panel filtros" method="get">
  <div><label for="q">Buscar por nombre</label>
    <input id="q" name="q" maxlength="100" value="<?= e($q) ?>" placeholder="Ej. corte, barba…"></div>
  <div><label for="categoria">Categoría</label>
    <select id="categoria" name="categoria">
      <option value="0">Todas</option>
      <?php foreach ($categorias as $c): ?>
        <option value="<?= (int) $c['id'] ?>" <?= $cat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <button type="submit">Filtrar</button>
</form>

<?php if (!$servicios): ?>
  <p class="muted">No se encontraron servicios con esos criterios.</p>
<?php else: ?>
<div class="grid">
  <?php foreach ($servicios as $s): ?>
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
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
