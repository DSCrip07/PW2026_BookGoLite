<?php
// Variable esperada: $titulo (string). Requiere bootstrap.php ya cargado.
$u = usuario_actual();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo ?? 'Inicio') ?> · <?= e(APP_NAME) ?></title>
  <link rel="stylesheet" href="<?= e(url('css/style.css')) ?>">
</head>
<body>
<header class="topbar">
  <div class="wrap nav">
    <a class="logo" href="<?= e(url('index.php')) ?>">✂ <?= e(APP_NAME) ?></a>
    <nav>
      <?php if (!$u): ?>
        <a href="<?= e(url('catalogo.php')) ?>">Servicios</a>
        <a href="<?= e(url('login.php')) ?>">Iniciar sesión</a>
        <a class="btn btn-sm" href="<?= e(url('registro.php')) ?>">Registrarse</a>
      <?php elseif ($u['rol'] === 'ADMIN'): ?>
        <a href="<?= e(url('admin/index.php')) ?>">Panel</a>
        <a href="<?= e(url('admin/categorias.php')) ?>">Categorías</a>
        <a href="<?= e(url('admin/servicios.php')) ?>">Servicios</a>
        <a href="<?= e(url('admin/horarios.php')) ?>">Horarios</a>
        <a href="<?= e(url('admin/reservas.php')) ?>">Reservas</a>
        <a href="<?= e(url('catalogo.php')) ?>">Catálogo</a>
      <?php else: ?>
        <a href="<?= e(url('catalogo.php')) ?>">Catálogo</a>
        <a href="<?= e(url('mis_reservas.php')) ?>">Mis reservas</a>
      <?php endif; ?>
      <?php if ($u): ?>
        <form class="inline" method="post" action="<?= e(url('logout.php')) ?>">
          <?= csrf_field() ?>
          <span class="quien"><?= e($u['nombre']) ?> (<?= e($u['rol']) ?>)</span>
          <button class="btn btn-sm btn-outline" type="submit">Salir</button>
        </form>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="wrap">
<?php foreach (flash_todos() as $f): ?>
  <div class="alert alert-<?= e($f['tipo']) ?>" role="alert"><?= e($f['mensaje']) ?></div>
<?php endforeach; ?>
