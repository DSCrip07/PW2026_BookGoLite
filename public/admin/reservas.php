<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/reservas.php';
requiere_rol('ADMIN');
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id     = (int) ($_POST['id'] ?? 0);
    $accion = (string) ($_POST['accion'] ?? '');
    $error = match ($accion) {
        'confirmar' => avanzar_reserva($pdo, $id, 'CONFIRMADA'),
        'realizar'  => avanzar_reserva($pdo, $id, 'REALIZADA'),
        'cancelar'  => cancelar_reserva($pdo, $id, null),
        default     => 'Acción inválida.',
    };
    $error ? flash('error', $error) : flash('exito', 'Reserva actualizada.');
    redirect('admin/reservas.php');
}

$reservas = $pdo->query(
    "SELECT r.id, r.estado, u.nombre, u.apellido, u.correo, s.nombre AS servicio,
            h.fecha, h.hora_inicio, h.hora_fin
       FROM reservas r
       JOIN usuarios u  ON u.id = r.cliente_id
       JOIN servicios s ON s.id = r.servicio_id
       JOIN horarios h  ON h.id = r.horario_id
      ORDER BY h.fecha DESC, h.hora_inicio DESC, r.id DESC")->fetchAll();
$clase = ['PENDIENTE' => 'badge-pend', 'CONFIRMADA' => 'badge-ok', 'REALIZADA' => 'badge-ok', 'CANCELADA' => 'badge-off'];

function boton_estado(string $accion, int $id, string $texto, string $cls = 'btn-outline'): string
{
    return '<form class="inline" method="post">' . csrf_field()
         . '<input type="hidden" name="accion" value="' . e($accion) . '">'
         . '<input type="hidden" name="id" value="' . $id . '">'
         . '<button class="btn-sm ' . $cls . '" type="submit">' . e($texto) . '</button></form> ';
}

$titulo = 'Reservas';
require __DIR__ . '/../../includes/header.php';
?>
<h1>Reservas</h1>
<section class="panel">
  <div class="tabla-wrap"><table>
    <thead><tr><th>#</th><th>Cliente</th><th>Servicio</th><th>Fecha</th><th>Horario</th><th>Estado</th><th>Acciones</th></tr></thead>
    <tbody>
    <?php foreach ($reservas as $r): ?>
      <tr>
        <td><?= (int) $r['id'] ?></td>
        <td><?= e($r['nombre'] . ' ' . $r['apellido']) ?><br><span class="muted"><?= e($r['correo']) ?></span></td>
        <td><?= e($r['servicio']) ?></td>
        <td><?= e($r['fecha']) ?></td>
        <td><?= e(substr($r['hora_inicio'], 0, 5)) ?> – <?= e(substr($r['hora_fin'], 0, 5)) ?></td>
        <td><span class="badge <?= e($clase[$r['estado']] ?? '') ?>"><?= e($r['estado']) ?></span></td>
        <td>
          <?php
          $id = (int) $r['id'];
          if ($r['estado'] === 'PENDIENTE')  echo boton_estado('confirmar', $id, 'Confirmar');
          if ($r['estado'] === 'CONFIRMADA') echo boton_estado('realizar', $id, 'Marcar realizada');
          if (in_array($r['estado'], ['PENDIENTE', 'CONFIRMADA'], true)) echo boton_estado('cancelar', $id, 'Cancelar', 'btn-danger');
          ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
