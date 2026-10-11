<?php
/**
 * Pagina principal del PACIENTE (solo rol paciente).
 * Agregar: barra de busqueda de medicos (envia a
 * buscar/medicos.php), proximas citas y accesos reales a los modulos.
 */
require __DIR__ . '/../includes/bootstrap.php';

$u = requerir_rol('paciente');
$rut = rut_sesion();

actualizar_citas_vencidas();   // las proximas citas se muestran con su estado al dia

$p = q('SELECT p.RUT, p.nombreCompleto, p.email, pa.fechaNacimiento, pa.sexo, pa.telefono,
               TIMESTAMPDIFF(YEAR, pa.fechaNacimiento, CURDATE()) AS edad,
               pr.nombrePrevision, co.nombreComuna, re.nombreRegion
          FROM Persona p
          JOIN Paciente  pa ON pa.RUTPaciente = p.RUT
          JOIN Prevision pr ON pr.idPrevision = pa.idPrevision
          JOIN Comuna    co ON co.idComuna    = pa.idComuna
          JOIN Region    re ON re.idRegion    = co.idRegion
         WHERE p.RUT = ?', [$rut])->fetch();

$proximas = q('SELECT idCita, fechaHoraCita, idEstadoCita, nombreEstadoCita, nombreMedico,
                      nombreEspecialidad, nombreCentro
                 FROM v_cita_detalle
                WHERE RUTPaciente = ? AND fechaHoraCita >= NOW() AND idEstadoCita <> ?
                ORDER BY fechaHoraCita
                LIMIT 3', [$rut, ESTADO_CANCELADA])->fetchAll();

encabezado('Inicio');
?>
<h1 class="h3 mb-1">Hola, <?= e($p['nombreCompleto']) ?></h1>
<p class="text-muted mb-4">Bienvenido/a a tu portal de paciente.</p>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(url('buscar/medicos.php')) ?>" class="row g-2 align-items-end" role="search">
            <div class="col-md-6">
                <label for="q" class="form-label fw-semibold">Buscar medicos</label>
                <input type="search" class="form-control" id="q" name="q" maxlength="100" placeholder="Nombre o especialidad (o parte)">
            </div>
            <div class="col-md-4">
                <label for="esp" class="form-label">Especialidad</label>
                <select class="form-select" id="esp" name="esp">
                    <option value="">Todas</option>
                    <?php foreach (catalogo_especialidades() as $es): ?>
                        <option value="<?= (int) $es['idEspecialidad'] ?>"><?= e($es['nombreEspecialidad']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Mis proximas citas</span>
                <a class="btn btn-sm btn-primary" href="<?= e(url('paciente/agendar.php')) ?>">Agendar hora</a>
            </div>
            <?php if ($proximas): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($proximas as $c): ?>
                        <a class="list-group-item list-group-item-action" href="<?= e(url('paciente/cita.php?id=' . (int) $c['idCita'])) ?>">
                            <div class="d-flex justify-content-between">
                                <strong><?= e(fecha_legible($c['fechaHoraCita'])) ?> · <?= e(hora_legible($c['fechaHoraCita'])) ?></strong>
                                <?= badge_estado((int) $c['idEstadoCita'], $c['nombreEstadoCita']) ?>
                            </div>
                            <small class="text-muted"><?= e($c['nombreEspecialidad']) ?> con <?= e($c['nombreMedico']) ?> — <?= e($c['nombreCentro']) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card-body text-muted">No tienes citas proximas.</div>
            <?php endif; ?>
            <div class="card-footer bg-white">
                <a href="<?= e(url('paciente/citas.php')) ?>">Ver todas mis citas</a> ·
                <a href="<?= e(url('paciente/historial.php')) ?>">Ver mi historial clinico</a>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Mis datos</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">RUT</dt><dd class="col-sm-7"><?= e($p['RUT']) ?></dd>
                    <dt class="col-sm-5">E-mail</dt><dd class="col-sm-7"><?= e($p['email']) ?></dd>
                    <dt class="col-sm-5">Edad</dt><dd class="col-sm-7"><?= (int) $p['edad'] ?> anios</dd>
                    <dt class="col-sm-5">Prevision</dt><dd class="col-sm-7"><?= e($p['nombrePrevision']) ?></dd>
                    <dt class="col-sm-5">Comuna</dt><dd class="col-sm-7"><?= e($p['nombreComuna']) ?> (<?= e($p['nombreRegion']) ?>)</dd>
                    <dt class="col-sm-5">Telefono</dt><dd class="col-sm-7"><?= e($p['telefono'] ?? '—') ?></dd>
                </dl>
                <a class="btn btn-sm btn-outline-primary mt-3" href="<?= e(url('perfil/index.php')) ?>">Editar mis datos</a>
            </div>
        </div>
    </div>
</div>
<?php pie();
