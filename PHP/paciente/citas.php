<?php
/**
 * Mis citas: solo las citas del paciente de la sesion, separadas
 * en proximas (fecha-hora >= ahora) e historicas (anteriores).
 *
 * Antes de listar se ejecuta sp_cancelar_citas_vencidas: las
 * Reservadas/Confirmadas de dias anteriores pasan a Cancelada. Clasificar una
 * cita como historica NO cambia su estado.
 *
 * Las acciones (reprogramar/cancelar) solo se muestran si la cita es
 * modificable (SQL_MODIFICABLE); aun asi, cada operacion se revalida en su POST.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('paciente');
$rut = rut_sesion();

actualizar_citas_vencidas();

$citas = q('SELECT v.idCita, v.fechaHoraCita, v.idEstadoCita, v.nombreEstadoCita,
                   v.nombreMedico, v.nombreEspecialidad, v.nombreCentro, v.nombreComuna,
                   (v.fechaHoraCita >= NOW()) AS proxima,
                   ' . SQL_MODIFICABLE . ' AS modificable
              FROM v_cita_detalle v
             WHERE v.RUTPaciente = ?
             ORDER BY v.fechaHoraCita', [$rut])->fetchAll();

$proximas   = array_values(array_filter($citas, fn($c) => (int) $c['proxima'] === 1));
$historicas = array_reverse(array_values(array_filter($citas, fn($c) => (int) $c['proxima'] === 0)));

/** Tabla de citas (misma estructura para ambas secciones). */
function tabla_citas(array $citas, string $id, bool $acciones): void
{
    ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="<?= e($id) ?>">
            <thead class="table-light">
                <tr>
                    <th>N.º</th><th>Fecha</th><th>Hora</th><th>Medico</th>
                    <th>Especialidad</th><th>Centro</th><th>Estado</th>
                    <?php if ($acciones): ?><th class="text-end">Acciones</th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($citas as $c): ?>
                <tr data-cita="<?= (int) $c['idCita'] ?>">
                    <td><a href="<?= e(url('paciente/cita.php?id=' . (int) $c['idCita'])) ?>">#<?= (int) $c['idCita'] ?></a></td>
                    <td class="text-nowrap"><?= e(fecha_legible($c['fechaHoraCita'])) ?></td>
                    <td><?= e(hora_legible($c['fechaHoraCita'])) ?></td>
                    <td><?= e($c['nombreMedico']) ?></td>
                    <td><?= e($c['nombreEspecialidad']) ?></td>
                    <td><?= e($c['nombreCentro']) ?> <small class="text-muted">(<?= e($c['nombreComuna']) ?>)</small></td>
                    <td><?= badge_estado((int) $c['idEstadoCita'], $c['nombreEstadoCita']) ?></td>
                    <?php if ($acciones): ?>
                        <td class="text-end text-nowrap">
                            <?php if ((int) $c['modificable'] === 1): ?>
                                <a class="btn btn-sm btn-outline-primary"
                                   href="<?= e(url('paciente/reprogramar.php?id=' . (int) $c['idCita'])) ?>">Reprogramar</a>
                                <a class="btn btn-sm btn-outline-danger"
                                   href="<?= e(url('paciente/cancelar.php?id=' . (int) $c['idCita'])) ?>">Cancelar</a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

encabezado('Mis citas');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <h1 class="h3 mb-0">Mis citas</h1>
    <a class="btn btn-primary" href="<?= e(url('paciente/agendar.php')) ?>">Agendar hora</a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Proximas <span class="badge text-bg-light border"><?= count($proximas) ?></span></div>
    <?php if ($proximas): ?>
        <?php tabla_citas($proximas, 'proximas', true); ?>
    <?php else: ?>
        <div class="card-body text-muted">No tienes citas proximas.</div>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Historicas <span class="badge text-bg-light border"><?= count($historicas) ?></span></div>
    <?php if ($historicas): ?>
        <?php tabla_citas($historicas, 'historicas', false); ?>
        <div class="card-footer bg-white small text-muted">
            Las citas Reservadas o Confirmadas de dias anteriores se cancelan automaticamente.
            Las de hoy cuya hora ya paso quedan pendientes de que el medico registre si fue atendida.
        </div>
    <?php else: ?>
        <div class="card-body text-muted">No tienes citas anteriores.</div>
    <?php endif; ?>
</div>
<?php pie();
