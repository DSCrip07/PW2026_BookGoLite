<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/reservas.php';

$u = requiere_rol('CLIENTE');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('catalogo.php');
}
csrf_check();

$servicioId = (int) ($_POST['servicio_id'] ?? 0);
$horarioId  = (int) ($_POST['horario_id'] ?? 0);

$error = crear_reserva(db(), (int) $u['id'], $servicioId, $horarioId);
if ($error) {
    flash('error', $error);
    redirect('servicio.php?id=' . $servicioId);
}
flash('exito', 'Reserva creada en estado PENDIENTE. El administrador la confirmará.');
redirect('mis_reservas.php');
