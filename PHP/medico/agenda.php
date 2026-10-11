<?php
/**
 * Mi agenda: citas del medico de la SESION para un dia
 * (por defecto hoy), ordenadas por hora, con paciente, prevision, centro,
 * especialidad y estado. Desde aqui se cambia el estado y se registra la
 * atencion.
 *
 *  - El medico consultado es SIEMPRE rut_sesion(): la pagina solo recibe
 *    ?fecha=AAAA-MM-DD (cualquier otro parametro se ignora).
 *  - Antes de consultar se ejecuta sp_cancelar_citas_vencidas.
 *  - Las acciones mostradas salen de estados_posibles();
 *    cada POST se revalida en medico/estado.php y en trg_cita_bu.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('medico');
$rut = rut_sesion();

actualizar_citas_vencidas();

$hoy = date('Y-m-d');
$fechaTexto = get('fecha');
$fecha = $fechaTexto === '' ? $hoy : fecha_valida($fechaTexto);
$errores = [];
if ($fecha === null) {
    $errores[] = 'La fecha indicada no es valida; se muestra el dia de hoy.';
    $fecha = $hoy;
}

$anterior  = date('Y-m-d', strtotime($fecha . ' -1 day'));
$siguiente = date('Y-m-d', strtotime($fecha . ' +1 day'));

$citas = q('SELECT v.idCita, v.fechaHoraCita, v.idEstadoCita, v.nombreEstadoCita, v.RUTPaciente,
                   v.nombrePaciente, v.nombrePrevision, v.nombreCentro, v.nombreEspecialidad, v.idAtencion,
                   (v.fechaHoraCita <= NOW()) AS llego, (DATE(v.fechaHoraCita) < CURDATE()) AS vencida
              FROM v_cita_detalle v
             WHERE v.RUTMedico = ?
               AND v.fechaHoraCita >= ? AND v.fechaHoraCita < ?
             ORDER BY v.fechaHoraCita, v.idCita', [$rut, $fecha, $siguiente])->fetchAll();

encabezado('Mi agenda');
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
    <div>
        <h1 class="h3 mb-0">Mi agenda</h1>
        <p class="text-muted mb-0" id="dia-agenda"><?= e(fecha_legible($fecha)) ?><?= $fecha === $hoy ? ' (hoy)' : '' ?></p>
    </div>
    <form method="get" action="<?= e(url('medico/agenda.php')) ?>" class="d-flex flex-wrap gap-2 align-items-end">
        <a class="btn btn-outline-secondary" href="<?= e(url('medico/agenda.php?fecha=' . $anterior)) ?>" title="Dia anterior">&larr;</a>
        <div>
            <label for="fecha" class="form-label small mb-0">Fecha</label>
            <input type="date" class="form-control" id="fecha" name="fecha" value="<?= e($fecha) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Ver</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('medico/agenda.php?fecha=' . $siguiente)) ?>" title="Dia siguiente">&rarr;</a>
        <?php if ($fecha !== $hoy): ?>
            <a class="btn btn-link" href="<?= e(url('medico/agenda.php')) ?>">Hoy</a>
        <?php endif; ?>
    </form>
</div>

<?php lista_errores($errores); ?>

<div class="card shadow-sm">
    <?php if (!$citas): ?>
        <div class="card-body text-muted">No tienes citas este dia.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="agenda">
            <thead class="table-light">
                <tr><th>Hora</th><th>Paciente</th><th>Prevision</th><th>Centro</th><th>Especialidad</th><th>Estado</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($citas as $c): $posibles = estados_posibles($c); ?>
                <tr data-cita="<?= (int) $c['idCita'] ?>">
                    <td class="fw-semibold"><?= e(hora_legible($c['fechaHoraCita'])) ?></td>
                    <td>
                        <a href="<?= e(url('medico/historial.php?paciente=' . rawurlencode($c['RUTPaciente']))) ?>"
                           title="Ver historial clinico"><?= e($c['nombrePaciente']) ?></a>
                    </td>
                    <td><?= e($c['nombrePrevision']) ?></td>
                    <td><?= e($c['nombreCentro']) ?></td>
                    <td><?= e($c['nombreEspecialidad']) ?></td>
                    <td><?= badge_estado((int) $c['idEstadoCita'], $c['nombreEstadoCita']) ?></td>
                    <td class="text-end">
                        <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                        <?php foreach ($posibles as $destino): ?>
                            <form method="post" action="<?= e(url('medico/estado.php')) ?>" class="d-inline">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="id" value="<?= (int) $c['idCita'] ?>">
                                <input type="hidden" name="estado" value="<?= $destino ?>">
                                <button type="submit" class="btn btn-sm <?= $destino === ESTADO_NO_ASISTIO ? 'btn-outline-warning' : ($destino === ESTADO_ATENDIDA ? 'btn-outline-secondary' : 'btn-outline-success') ?> accion-estado"
                                        data-estado="<?= $destino ?>"><?= e(ESTADOS_MEDICO[$destino]) ?></button>
                            </form>
                        <?php endforeach; ?>
                        <?php if ((int) $c['idEstadoCita'] === ESTADO_ATENDIDA && $c['idAtencion'] === null): ?>
                            <a class="btn btn-sm btn-primary" href="<?= e(url('medico/atencion.php?cita=' . (int) $c['idCita'])) ?>">Registrar atencion</a>
                        <?php elseif ($c['idAtencion'] !== null): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('medico/ficha.php?id=' . (int) $c['idAtencion'])) ?>">Ver ficha</a>
                        <?php endif; ?>
                        <?php if (!$posibles && (int) $c['idEstadoCita'] !== ESTADO_ATENDIDA && $c['idAtencion'] === null): ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-muted">
        Confirmar: citas Reservadas de hoy o futuras. Atendida / No asistio: cuando la hora de la cita ya llego.
        Atendida y No asistio se pueden corregir entre si mientras no haya atencion registrada.
        Las citas Reservadas o Confirmadas de dias anteriores se cancelan automaticamente.
    </div>
    <?php endif; ?>
</div>
<?php pie();
