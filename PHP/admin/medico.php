<?php
/**
 * CRUD de medicos - VER + gestion de sus ESPECIALIDADES y CENTROS.
 * Solo administrador.
 *
 * Muestra cada especialidad/centro con sus citas futuras. Los botones
 * "Quitar" solo aparecen cuando la operacion es posible (mas de una
 * asignacion y sin citas futuras en ella), pero admin/asignacion.php
 * lo vuelve a comprobar y los triggers trg_me_bd / trg_mc_bd / trg_me_bi lo
 * garantizan en la BD.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('admin');

$m = medico_admin(rut_parametro(get('rut')));
if ($m === null) {
    pagina_error(404, 'Medico no encontrado', 'El medico no existe.');
}
$rut = $m['RUTMedico'];
$esps = especialidades_del_medico($rut);
$cens = centros_del_medico($rut);
$espDisponibles = array_filter(catalogo_especialidades(),
    fn($x) => !in_array((int) $x['idEspecialidad'], array_map('intval', array_column($esps, 'idEspecialidad')), true));
$cenDisponibles = array_filter(catalogo_centros(),
    fn($x) => !in_array((int) $x['idCentro'], array_map('intval', array_column($cens, 'idCentro')), true));
$roles = array_merge(['Medico'], $m['esPaciente'] ? ['Paciente'] : [], $m['esAdmin'] ? ['Administrador'] : []);

encabezado('Medico ' . $m['nombreCompleto'], 'admin/medicos.php');
?>
<a class="small" href="<?= e(url('admin/medicos.php')) ?>">&larr; Medicos</a>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2 mb-3">
    <h1 class="h3 mb-0"><?= e($m['nombreCompleto']) ?></h1>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-primary" href="<?= e(url('admin/medico_form.php?rut=' . rawurlencode($rut))) ?>">Editar datos</a>
        <a class="btn btn-outline-danger" href="<?= e(url('admin/medico_eliminar.php?rut=' . rawurlencode($rut))) ?>">Eliminar</a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Datos y cuenta</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5">RUT</dt><dd class="col-7"><?= e($rut) ?></dd>
                    <dt class="col-5">E-mail institucional</dt><dd class="col-7"><?= e($m['emailInstitucional']) ?></dd>
                    <dt class="col-5">E-mail de acceso</dt><dd class="col-7"><?= e($m['emailAcceso'] ?? 'sin cuenta') ?></dd>
                    <dt class="col-5">Roles</dt><dd class="col-7"><?= e(implode(', ', $roles)) ?></dd>
                    <dt class="col-5">Citas</dt><dd class="col-7"><?= (int) $m['citas'] ?> (<?= (int) $m['futuras'] ?> futuras)</dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Especialidades (<?= count($esps) ?> de <?= ESPECIALIDADES_MAX ?>)</div>
            <ul class="list-group list-group-flush" id="especialidades">
                <?php foreach ($esps as $x): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center" data-especialidad="<?= (int) $x['idEspecialidad'] ?>">
                        <span><?= e($x['nombreEspecialidad']) ?>
                            <?php if ((int) $x['futuras'] > 0): ?><small class="text-muted d-block"><?= (int) $x['futuras'] ?> cita(s) futura(s)</small><?php endif; ?>
                        </span>
                        <?php if (count($esps) > 1 && (int) $x['futuras'] === 0): ?>
                            <form method="post" action="<?= e(url('admin/asignacion.php')) ?>">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="rut" value="<?= e($rut) ?>">
                                <input type="hidden" name="tipo" value="especialidad">
                                <input type="hidden" name="accion" value="quitar">
                                <input type="hidden" name="id" value="<?= (int) $x['idEspecialidad'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Quitar</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-footer bg-white">
                <?php if (count($esps) < ESPECIALIDADES_MAX): ?>
                    <form method="post" action="<?= e(url('admin/asignacion.php')) ?>" class="d-flex gap-2">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="rut" value="<?= e($rut) ?>">
                        <input type="hidden" name="tipo" value="especialidad">
                        <input type="hidden" name="accion" value="agregar">
                        <select class="form-select form-select-sm" name="id" required aria-label="Especialidad">
                            <option value="">Agregar especialidad…</option>
                            <?php foreach ($espDisponibles as $x): ?>
                                <option value="<?= (int) $x['idEspecialidad'] ?>"><?= e($x['nombreEspecialidad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-primary">Agregar</button>
                    </form>
                <?php else: ?>
                    <small class="text-muted">Tiene el maximo de <?= ESPECIALIDADES_MAX ?> especialidades.</small>
                <?php endif; ?>
                <div class="form-text">Minimo 1 y maximo <?= ESPECIALIDADES_MAX ?>. No se quita una especialidad con citas futuras.</div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Centros donde atiende (<?= count($cens) ?>)</div>
            <ul class="list-group list-group-flush" id="centros">
                <?php foreach ($cens as $x): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center" data-centro="<?= (int) $x['idCentro'] ?>">
                        <span><?= e($x['codigoCentro'] . ' ' . $x['nombreCentro']) ?>
                            <?php if ((int) $x['futuras'] > 0): ?><small class="text-muted d-block"><?= (int) $x['futuras'] ?> cita(s) futura(s)</small><?php endif; ?>
                        </span>
                        <?php if (count($cens) > 1 && (int) $x['futuras'] === 0): ?>
                            <form method="post" action="<?= e(url('admin/asignacion.php')) ?>">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="rut" value="<?= e($rut) ?>">
                                <input type="hidden" name="tipo" value="centro">
                                <input type="hidden" name="accion" value="quitar">
                                <input type="hidden" name="id" value="<?= (int) $x['idCentro'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Quitar</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="card-footer bg-white">
                <form method="post" action="<?= e(url('admin/asignacion.php')) ?>" class="d-flex gap-2">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="rut" value="<?= e($rut) ?>">
                    <input type="hidden" name="tipo" value="centro">
                    <input type="hidden" name="accion" value="agregar">
                    <select class="form-select form-select-sm" name="id" required aria-label="Centro">
                        <option value="">Agregar centro…</option>
                        <?php foreach ($cenDisponibles as $x): ?>
                            <option value="<?= (int) $x['idCentro'] ?>"><?= e($x['codigoCentro'] . ' ' . $x['nombreCentro']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary">Agregar</button>
                </form>
                <div class="form-text">Al menos 1 centro. No se quita un centro con citas futuras.</div>
            </div>
        </div>
    </div>
</div>
<?php pie();
