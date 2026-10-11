<?php
/**
 * CRUD de centros - VER. Datos del centro, medicos que atienden en
 * el y resumen de sus citas por estado. Solo administrador.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('admin');

$c = centro_con_dependencias(entero_positivo(get('id')));
if ($c === null) {
    pagina_error(404, 'Centro no encontrado', 'El centro no existe.');
}
$id = (int) $c['idCentro'];

$medicos = q('SELECT d.RUTMedico, d.nombreMedico, d.especialidades
                FROM Medico_Centro mc JOIN v_directorio_medicos d ON d.RUTMedico = mc.RUTMedico
               WHERE mc.idCentro = ? ORDER BY d.nombreMedico', [$id])->fetchAll();
$porEstado = q('SELECT ec.nombreEstadoCita, COUNT(c.idCita) AS n
                  FROM EstadoCita ec LEFT JOIN Cita c ON c.idEstadoCita = ec.idEstadoCita AND c.idCentro = ?
                 GROUP BY ec.idEstadoCita, ec.nombreEstadoCita ORDER BY ec.idEstadoCita', [$id])->fetchAll();

encabezado('Centro ' . $c['codigoCentro'], 'admin/centros.php');
?>
<a class="small" href="<?= e(url('admin/centros.php')) ?>">&larr; Centros</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-3">
    <h1 class="h3 mb-0"><?= e($c['nombreCentro']) ?> <span class="text-muted fs-5">(<?= e($c['codigoCentro']) ?>)</span></h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-primary" href="<?= e(url('admin/centro_form.php?id=' . $id)) ?>">Editar</a>
        <a class="btn btn-outline-danger" href="<?= e(url('admin/centro_eliminar.php?id=' . $id)) ?>">Eliminar</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Datos</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Codigo</dt><dd class="col-sm-8"><?= e($c['codigoCentro']) ?></dd>
                    <dt class="col-sm-4">Nombre</dt><dd class="col-sm-8"><?= e($c['nombreCentro']) ?></dd>
                    <dt class="col-sm-4">Comuna</dt><dd class="col-sm-8"><?= e($c['nombreComuna']) ?></dd>
                    <dt class="col-sm-4">Region</dt><dd class="col-sm-8"><?= e($c['nombreRegion']) ?></dd>
                </dl>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Citas del centro (<?= (int) $c['citas'] ?>)</div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($porEstado as $e): ?>
                    <li class="list-group-item d-flex justify-content-between"><span><?= e($e['nombreEstadoCita']) ?></span><span><?= (int) $e['n'] ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Medicos que atienden aqui (<?= count($medicos) ?>)</div>
            <?php if (!$medicos): ?>
                <div class="card-body text-muted">Ningun medico atiende en este centro.</div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($medicos as $m): ?>
                        <a class="list-group-item list-group-item-action" href="<?= e(url('admin/medico.php?rut=' . rawurlencode($m['RUTMedico']))) ?>">
                            <strong><?= e($m['nombreMedico']) ?></strong>
                            <small class="text-muted d-block"><?= e(str_replace(' | ', ', ', (string) $m['especialidades'])) ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php pie();
