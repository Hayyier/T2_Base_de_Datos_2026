<?php
/**
 * Cambiar el estado de una cita PROPIA (el medico puede pasar sus
 * citas a Confirmada, Atendida o No Asistio). Solo POST con CSRF.
 *
 *  1. rol medico + token CSRF;
 *  2. el estado pedido debe ser uno de los que el medico puede asignar
 *     (Confirmada, Atendida, No Asistio). Reservada y Cancelada se rechazan
 *     aqui: el medico no cancela ni "des-reserva";
 *  3. la cita debe ser del medico de la sesion (si no, 404);
 *  4. UPDATE con "AND RUTMedico = ?" en el WHERE: la pertenencia es parte
 *     de la misma sentencia que modifica;
 *  5. la transicion (desde que estado, si la hora ya llego, si hay atencion)
 *     la valida trg_cita_bu; su mensaje se muestra tal cual.
 *  6. PRG: redirect a la agenda del dia de la cita (o a registrar la
 *     atencion, si la cita quedo Atendida y aun no la tiene).
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('medico');
if (!es_post()) {
    header('Allow: POST');
    pagina_error(405, 'Operacion no permitida', 'El estado de una cita se cambia desde la agenda.');
}
csrf_verificar();
$rut = rut_sesion();

$cita = cita_del_medico(entero_positivo(post('id')), $rut);
if ($cita === null) {
    pagina_error(404, 'Cita no encontrada', 'La cita no existe o no pertenece a tu agenda.');
}
$volver = 'medico/agenda.php?fecha=' . substr($cita['fechaHoraCita'], 0, 10);

$estado = entero_positivo(post('estado'));
if (!isset(ESTADOS_MEDICO[$estado])) {
    flash('danger', 'Estado no permitido: el medico solo puede marcar sus citas como Confirmada, Atendida o No Asistio.');
    redirect($volver);
}
if ((int) $cita['idEstadoCita'] === $estado) {
    flash('info', 'La cita #' . (int) $cita['idCita'] . ' ya estaba en ese estado.');
    redirect($volver);
}

try {
    $st = q('UPDATE Cita SET idEstadoCita = ? WHERE idCita = ? AND RUTMedico = ?',
            [$estado, (int) $cita['idCita'], $rut]);
    if ($st->rowCount() !== 1) {
        flash('warning', 'No se pudo actualizar la cita.');
        redirect($volver);
    }
} catch (PDOException $e) {
    flash('danger', mensaje_error_bd($e));        // p. ej. mensaje de trg_cita_bu
    redirect($volver);
}

$nombre = q('SELECT nombreEstadoCita FROM EstadoCita WHERE idEstadoCita = ?', [$estado])->fetchColumn();
flash('success', 'Cita #' . (int) $cita['idCita'] . ' marcada como ' . $nombre . '.');
if ($estado === ESTADO_ATENDIDA && $cita['idAtencion'] === null) {
    redirect('medico/atencion.php?cita=' . (int) $cita['idCita']);   // siguiente paso natural
}
redirect($volver);
