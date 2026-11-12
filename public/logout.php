<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

// Cerrar sesión solo por POST + CSRF (evita cierres forzados desde otros sitios).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
csrf_check();
cerrar_sesion();
session_start();                       // sesión nueva solo para el mensaje
session_regenerate_id(true);
flash('exito', 'Sesión cerrada correctamente.');
redirect('login.php');
