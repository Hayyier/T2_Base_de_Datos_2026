<?php
/**
 * Detalle / comprobante de una cita del paciente (Vista 1 de la T1,
 * "Comprobante de Reserva de Hora"). Se muestra tras agendar o reprogramar.
 * Se agrega el telefono del paciente, que la Vista 1 incluye.
 *
 * Todos los datos salen de la BD (v_cita_detalle) filtrando por el id recibido
 * Y por el RUT de la sesion: con el id de una cita ajena la respuesta es la
 * misma que con un id inexistente (404), sin revelar nada.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('paciente');
$cita = cita_del_paciente(entero_positivo(get('id')), rut_sesion());
if ($cita === null) {
    pagina_error(404, 'Cita no encontrada', 'La cita no existe o no pertenece a tu cuenta.');
}

encabezado('Cita #' . $cita['idCita'], 'paciente/citas.php');
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm" id="comprobante">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Comprobante de reserva de hora</span>
                <?= badge_estado((int) $cita['idEstadoCita'], $cita['nombreEstadoCita']) ?>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">N.º de cita</dt><dd class="col-sm-8" id="numero-cita"><?= (int) $cita['idCita'] ?></dd>
                    <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8"><?= e(fecha_legible($cita['fechaHoraCita'])) ?></dd>
                    <dt class="col-sm-4">Hora</dt><dd class="col-sm-8"><?= e(hora_legible($cita['fechaHoraCita'])) ?></dd>
                    <dt class="col-sm-4">Paciente</dt><dd class="col-sm-8"><?= e($cita['nombrePaciente']) ?> (<?= e($cita['RUTPaciente']) ?>)</dd>
                    <dt class="col-sm-4">Prevision</dt><dd class="col-sm-8"><?= e($cita['nombrePrevision']) ?></dd>
                    <dt class="col-sm-4">Telefono</dt><dd class="col-sm-8"><?= e($cita['telefonoPaciente'] ?? '—') ?></dd>
                    <dt class="col-sm-4">Medico</dt><dd class="col-sm-8"><?= e($cita['nombreMedico']) ?></dd>
                    <dt class="col-sm-4">Especialidad</dt><dd class="col-sm-8"><?= e($cita['nombreEspecialidad']) ?></dd>
                    <dt class="col-sm-4">Centro</dt>
                    <dd class="col-sm-8"><?= e($cita['nombreCentro']) ?> (<?= e($cita['codigoCentro']) ?>),
                        <?= e($cita['nombreComuna']) ?>, <?= e($cita['nombreRegion']) ?></dd>
                    <dt class="col-sm-4">Estado</dt><dd class="col-sm-8"><?= e($cita['nombreEstadoCita']) ?></dd>
                </dl>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="<?= e(url('paciente/citas.php')) ?>">Volver a Mis citas</a>
                <?php if ((int) $cita['modificable'] === 1): ?>
                    <a class="btn btn-outline-primary" href="<?= e(url('paciente/reprogramar.php?id=' . (int) $cita['idCita'])) ?>">Reprogramar</a>
                    <a class="btn btn-outline-danger" href="<?= e(url('paciente/cancelar.php?id=' . (int) $cita['idCita'])) ?>">Cancelar cita</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php pie();
