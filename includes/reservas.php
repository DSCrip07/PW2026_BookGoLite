<?php
declare(strict_types=1);
/**
 * Reglas de negocio de reservas. Todo lo que toca cupos va en una transacción
 * con UPDATE condicionado, para que dos clientes simultáneos no pasen del límite.
 * Cada función devuelve null si salió bien, o un mensaje de error para el usuario.
 */

/** Crea una reserva PENDIENTE y descuenta un cupo de forma atómica. */
function crear_reserva(PDO $pdo, int $clienteId, int $servicioId, int $horarioId): ?string
{
    $pdo->beginTransaction();
    try {
        // El horario debe existir, pertenecer AL SERVICIO indicado, estar activo y ser futuro.
        // FOR UPDATE bloquea la fila hasta terminar la transacción.
        $st = $pdo->prepare(
            "SELECT h.id FROM horarios h
               JOIN servicios s   ON s.id = h.servicio_id
               JOIN categorias c  ON c.id = s.categoria_id
              WHERE h.id = :h AND h.servicio_id = :s
                AND h.estado = 'ACTIVO' AND s.estado = 'ACTIVO' AND c.estado = 'ACTIVO'
                AND TIMESTAMP(h.fecha, h.hora_inicio) > NOW()
                FOR UPDATE");
        $st->execute([':h' => $horarioId, ':s' => $servicioId]);
        if (!$st->fetch()) {
            $pdo->rollBack();
            return 'El horario seleccionado no está disponible para este servicio.';
        }

        // Descuento condicionado: solo si queda al menos un cupo.
        $up = $pdo->prepare('UPDATE horarios SET cupos_disponibles = cupos_disponibles - 1
                              WHERE id = :h AND cupos_disponibles > 0');
        $up->execute([':h' => $horarioId]);
        if ($up->rowCount() !== 1) {
            $pdo->rollBack();
            return 'Ya no quedan cupos disponibles en ese horario.';
        }

        try {
            $pdo->prepare("INSERT INTO reservas (cliente_id, servicio_id, horario_id, estado)
                           VALUES (:c, :s, :h, 'PENDIENTE')")
                ->execute([':c' => $clienteId, ':s' => $servicioId, ':h' => $horarioId]);
        } catch (PDOException $ex) {
            if ($ex->getCode() === '23000') {   // UNIQUE (cliente, horario activo)
                $pdo->rollBack();               // también devuelve el cupo descontado
                return 'Ya tiene una reserva activa para ese horario.';
            }
            throw $ex;
        }
        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Cancela una reserva PENDIENTE/CONFIRMADA y devuelve EXACTAMENTE un cupo.
 * $clienteId != null => cancelación del cliente (debe ser dueño y la cita no haber comenzado).
 * $clienteId === null => cancelación del ADMIN.
 */
function cancelar_reserva(PDO $pdo, int $reservaId, ?int $clienteId): ?string
{
    $pdo->beginTransaction();
    try {
        $sql = 'SELECT r.estado, r.horario_id, (TIMESTAMP(h.fecha, h.hora_inicio) > NOW()) AS futura
                  FROM reservas r JOIN horarios h ON h.id = r.horario_id
                 WHERE r.id = :id';
        $params = [':id' => $reservaId];
        if ($clienteId !== null) {              // propiedad verificada en el servidor
            $sql .= ' AND r.cliente_id = :c';
            $params[':c'] = $clienteId;
        }
        $st = $pdo->prepare($sql . ' FOR UPDATE');
        $st->execute($params);
        $r = $st->fetch();
        if (!$r) {
            $pdo->rollBack();
            return 'Reserva no encontrada.';
        }
        if ($r['estado'] === 'REALIZADA') {
            $pdo->rollBack();
            return 'Una reserva REALIZADA no puede cancelarse.';
        }
        if ($r['estado'] === 'CANCELADA') {
            $pdo->rollBack();
            return 'La reserva ya estaba cancelada.';
        }
        if ($clienteId !== null && !(int) $r['futura']) {
            $pdo->rollBack();
            return 'No puede cancelar una cita que ya comenzó.';
        }

        // Transición condicionada: solo una cancelación "gana"; repetirla no devuelve cupos.
        $u = $pdo->prepare("UPDATE reservas SET estado = 'CANCELADA'
                             WHERE id = :id AND estado IN ('PENDIENTE','CONFIRMADA')");
        $u->execute([':id' => $reservaId]);
        if ($u->rowCount() === 1) {
            // Nunca más cupos disponibles que los totales.
            $pdo->prepare('UPDATE horarios SET cupos_disponibles = cupos_disponibles + 1
                            WHERE id = :h AND cupos_disponibles < cupos_totales')
                ->execute([':h' => $r['horario_id']]);
        }
        $pdo->commit();
        return null;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** Cambios de estado del ADMIN: PENDIENTE→CONFIRMADA y CONFIRMADA→REALIZADA. */
function avanzar_reserva(PDO $pdo, int $reservaId, string $destino): ?string
{
    $desde = ['CONFIRMADA' => 'PENDIENTE', 'REALIZADA' => 'CONFIRMADA'][$destino] ?? null;
    if ($desde === null) {
        return 'Estado destino inválido.';
    }
    $u = $pdo->prepare('UPDATE reservas SET estado = :d WHERE id = :id AND estado = :o');
    $u->execute([':d' => $destino, ':id' => $reservaId, ':o' => $desde]);
    return $u->rowCount() === 1 ? null : "Solo se puede pasar a $destino desde $desde.";
}
