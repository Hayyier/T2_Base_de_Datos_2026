<?php
/**
 * Pagina principal del MEDICO (solo rol medico).
 * Resumen del dia (citas de hoy por estado y proximas), atenciones
 * pendientes de registrar y accesos a Mi agenda, Historial de pacientes,
 * Buscar medicos y Mi perfil. Todo filtrado por el medico de la sesion.
 */
require __DIR__ . '/../includes/bootstrap.php';

$u = requerir_rol('medico');
$rut = rut_sesion();

actualizar_citas_vencidas();   // estados al dia antes de resumir

$m = q('SELECT d.RUTMedico, d.nombreMedico, d.emailInstitucional, d.especialidades, d.centros
          FROM v_directorio_medicos d
         WHERE d.RUTMedico = ?', [$rut])->fetch();

$hoy = q('SELECT v.idCita, v.fechaHoraCita, v.idEstadoCita, v.nombreEstadoCita, v.nombrePaciente,
                 v.nombreCentro, v.idAtencion
            FROM v_cita_detalle v
           WHERE v.RUTMedico = ? AND DATE(v.fechaHoraCita) = CURDATE()
           ORDER BY v.fechaHoraCita', [$rut])->fetchAll();
$porEstado = array_count_values(array_column($hoy, 'nombreEstadoCita'));

// citas Atendidas sin atencion registrada (pendientes del medico)
$pendientes = q('SELECT v.idCita, v.fechaHoraCita, v.nombrePaciente
                   FROM v_cita_detalle v
                  WHERE v.RUTMedico = ? AND v.idEstadoCita = ? AND v.idAtencion IS NULL
                  ORDER BY v.fechaHoraCita DESC LIMIT 5', [$rut, ESTADO_ATENDIDA])->fetchAll();

encabezado('Inicio');
?>
<h1 class="h3 mb-1">Hola, Dr(a). <?= e($m['nombreMedico']) ?></h1>
<p class="text-muted mb-4">Bienvenido/a a tu portal medico. Hoy es <?= e(fecha_legible(date('Y-m-d'))) ?>.</p>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Agenda de hoy</span>
                <a class="btn btn-sm btn-primary" href="<?= e(url('medico/agenda.php')) ?>">Abrir mi agenda</a>
            </div>
            <div class="card-body">
                <?php if (!$hoy): ?>
                    <p class="text-muted mb-0">No tienes citas hoy.</p>
                <?php else: ?>
                    <p class="mb-2"><strong><?= count($hoy) ?></strong> cita(s):
                        <?php foreach ($porEstado as $estado => $n): ?>
                            <span class="badge text-bg-light border me-1"><?= e($estado) ?>: <?= (int) $n ?></span>
                        <?php endforeach; ?>
                    </p>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach (array_slice($hoy, 0, 6) as $c): ?>
                            <li class="py-1 border-bottom">
                                <strong><?= e(hora_legible($c['fechaHoraCita'])) ?></strong>
                                <?= e($c['nombrePaciente']) ?> — <?= e($c['nombreCentro']) ?>
                                <?= badge_estado((int) $c['idEstadoCita'], $c['nombreEstadoCita']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Atenciones por registrar</div>
            <?php if (!$pendientes): ?>
                <div class="card-body text-muted small">No tienes citas Atendidas sin atencion registrada.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($pendientes as $c): ?>
                        <a class="list-group-item list-group-item-action small" href="<?= e(url('medico/atencion.php?cita=' . (int) $c['idCita'])) ?>">
                            <?= e(fecha_legible($c['fechaHoraCita'])) ?> <?= e(hora_legible($c['fechaHoraCita'])) ?> — <?= e($c['nombrePaciente']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Mis datos</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-sm-5">RUT</dt><dd class="col-sm-7"><?= e($m['RUTMedico']) ?></dd>
                    <dt class="col-sm-5">E-mail institucional</dt><dd class="col-sm-7"><?= e($m['emailInstitucional']) ?></dd>
                    <dt class="col-sm-5">Especialidades</dt><dd class="col-sm-7"><?= e(str_replace(' | ', ', ', $m['especialidades'])) ?></dd>
                    <dt class="col-sm-5">Centros</dt><dd class="col-sm-7"><?= e(str_replace(' | ', ', ', $m['centros'])) ?></dd>
                </dl>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('medico/historial.php')) ?>">Historial de pacientes</a>
                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('buscar/medicos.php')) ?>">Buscar medicos</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('perfil/index.php')) ?>">Mi perfil</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php pie();
