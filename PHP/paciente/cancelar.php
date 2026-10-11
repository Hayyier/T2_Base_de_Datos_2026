<?php
/**
 * Cancelar una cita propia.
 *
 * La cita NO se borra: pasa a estado Cancelada (id 5) y libera el bloque
 * (columna generada slotActivo). Solo si pertenece al paciente de la
 * sesion, esta Reservada o Confirmada y su fecha-hora no ha pasado.
 *
 * GET  ?id=N         -> pagina de confirmacion
 * POST id, csrf      -> UPDATE condicionado (id + RUT de la sesion + estado
 *                       + fecha en el mismo WHERE: es atomico, no depende
 *                       de una lectura previa) -> redirect a Mis citas.
 * El trigger trg_cita_bu vuelve a validar la transicion de estado.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('paciente');
$rut = rut_sesion();

if (es_post()) {
    csrf_verificar();
}
$idCita = entero_positivo(es_post() ? post('id') : get('id'));
$cita = cita_del_paciente($idCita, $rut);
if ($cita === null) {
    pagina_error(404, 'Cita no encontrada', 'La cita no existe o no pertenece a tu cuenta.');
}
if (($motivo = motivo_no_modificable($cita, 'cancelar')) !== null) {
    flash('warning', $motivo);
    redirect('paciente/citas.php');
}

$error = null;
if (es_post()) {
    try {
        $st = q('UPDATE Cita SET idEstadoCita = ' . ESTADO_CANCELADA . '
                  WHERE idCita = ? AND RUTPaciente = ? AND ' . SQL_MODIFICABLE, [$idCita, $rut]);
        if ($st->rowCount() === 1) {
            flash('success', 'La cita #' . $idCita . ' fue cancelada. El horario quedo libre.');
            redirect('paciente/citas.php');
        }
        $error = 'La cita ya no se puede cancelar (cambio su estado o su hora ya paso).';
    } catch (PDOException $e) {
        $error = mensaje_error_bd($e);
    }
    http_response_code(422);
}

encabezado('Cancelar cita', 'paciente/citas.php');
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <?php lista_errores($error ? [$error] : []); ?>
        <div class="card shadow-sm border-danger-subtle">
            <div class="card-header bg-white fw-semibold">Cancelar cita #<?= (int) $cita['idCita'] ?></div>
            <div class="card-body">
                <dl class="row small">
                    <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8"><?= e(fecha_legible($cita['fechaHoraCita'])) ?></dd>
                    <dt class="col-sm-4">Hora</dt><dd class="col-sm-8"><?= e(hora_legible($cita['fechaHoraCita'])) ?></dd>
                    <dt class="col-sm-4">Medico</dt><dd class="col-sm-8"><?= e($cita['nombreMedico']) ?></dd>
                    <dt class="col-sm-4">Especialidad</dt><dd class="col-sm-8"><?= e($cita['nombreEspecialidad']) ?></dd>
                    <dt class="col-sm-4">Centro</dt><dd class="col-sm-8"><?= e($cita['nombreCentro']) ?></dd>
                    <dt class="col-sm-4">Estado</dt><dd class="col-sm-8"><?= badge_estado((int) $cita['idEstadoCita'], $cita['nombreEstadoCita']) ?></dd>
                </dl>
                <p class="mb-3">La cita quedara en estado <strong>Cancelada</strong> y no podra reactivarse.
                    Si necesitas otra hora, tendras que agendarla de nuevo.</p>
                <form method="post" action="<?= e(url('paciente/cancelar.php')) ?>" class="d-flex gap-2">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= (int) $cita['idCita'] ?>">
                    <button type="submit" class="btn btn-danger">Si, cancelar la cita</button>
                    <a class="btn btn-outline-secondary" href="<?= e(url('paciente/citas.php')) ?>">No, volver</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php pie();
