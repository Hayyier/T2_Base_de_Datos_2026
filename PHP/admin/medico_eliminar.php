<?php
/**
 * CRUD de medicos - ELIMINAR. Solo administrador.
 *
 * Politica: un medico con citas (en cualquier estado) NO se elimina:
 * sus citas, atenciones, diagnosticos y recetas son registro clinico de sus
 * pacientes (garantia en la BD: fk_cita_medico ON DELETE RESTRICT).
 * Un medico sin citas se elimina junto con sus asignaciones de
 * especialidades y centros (fk_me_medico / fk_mc_medico CASCADE; los borrados
 * en cascada no disparan trg_me_bd/trg_mc_bd, asi que no chocan con el
 * minimo de 1). Asimismo:
 *   - si la persona tiene otro rol (paciente o administrador), solo se borra
 *     el rol de medico y la persona conserva su cuenta;
 *   - si solo era medico, se borra la Persona completa (cascada a Medico).
 * Todo en una transaccion, con la fila del medico bloqueada (FOR UPDATE).
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('admin');
if (es_post()) {
    csrf_verificar();
}

$m = medico_admin(rut_parametro(es_post() ? post('rut') : get('rut')));
if ($m === null) {
    pagina_error(404, 'Medico no encontrado', 'El medico no existe.');
}
$rut = $m['RUTMedico'];
$otrosRoles = (int) $m['esPaciente'] || (int) $m['esAdmin'];
$motivo = (int) $m['citas'] > 0
    ? 'El medico no puede eliminarse porque tiene ' . (int) $m['citas'] . ' cita(s) registrada(s) ('
      . (int) $m['futuras'] . ' futura(s)); sus citas y atenciones son parte del historial clinico de sus pacientes.'
    : null;

if (es_post()) {
    if ($motivo !== null) {
        flash('danger', $motivo);
        redirect('admin/medico.php?rut=' . rawurlencode($rut));
    }
    $pdo = db();
    try {
        $pdo->beginTransaction();
        q('SELECT RUTMedico FROM Medico WHERE RUTMedico = ? FOR UPDATE', [$rut]);
        if ((int) q('SELECT COUNT(*) FROM Cita WHERE RUTMedico = ?', [$rut])->fetchColumn() > 0) {
            throw new RuntimeException('El medico tiene citas registradas y no puede eliminarse.');
        }
        if ($otrosRoles) {
            q('DELETE FROM Medico WHERE RUTMedico = ?', [$rut]);       // cascada: especialidades y centros
        } else {
            q('DELETE FROM Persona WHERE RUT = ?', [$rut]);            // cascada: Medico y asignaciones
        }
        $pdo->commit();
        flash('success', 'Medico ' . $m['nombreCompleto'] . ' eliminado'
              . ($otrosRoles ? ' (la persona conserva sus otros roles y su cuenta).' : '.'));
        redirect('admin/medicos.php');
    } catch (PDOException $e) {
        $pdo->rollBack();
        flash('danger', mensaje_error_bd($e));
    } catch (RuntimeException $e) {
        $pdo->rollBack();
        flash('danger', $e->getMessage());
    }
    redirect('admin/medico.php?rut=' . rawurlencode($rut));
}

encabezado('Eliminar medico', 'admin/medicos.php');
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-danger-subtle">
            <div class="card-header bg-white fw-semibold">Eliminar medico</div>
            <div class="card-body">
                <dl class="row small">
                    <dt class="col-sm-4">Nombre</dt><dd class="col-sm-8"><?= e($m['nombreCompleto']) ?></dd>
                    <dt class="col-sm-4">RUT</dt><dd class="col-sm-8"><?= e($rut) ?></dd>
                    <dt class="col-sm-4">Citas</dt><dd class="col-sm-8"><?= (int) $m['citas'] ?> (<?= (int) $m['futuras'] ?> futuras)</dd>
                </dl>
                <?php if ($motivo !== null): ?>
                    <div class="alert alert-warning" id="motivo"><?= e($motivo) ?></div>
                    <a class="btn btn-outline-secondary" href="<?= e(url('admin/medico.php?rut=' . rawurlencode($rut))) ?>">Volver</a>
                <?php else: ?>
                    <p>El medico no tiene citas. Se eliminaran sus asignaciones de especialidades y centros
                        <?= $otrosRoles ? 'y su rol de medico; la persona conserva sus otros roles y su cuenta.' : 'y su cuenta de acceso.' ?></p>
                    <form method="post" action="<?= e(url('admin/medico_eliminar.php')) ?>" class="d-flex gap-2">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="rut" value="<?= e($rut) ?>">
                        <button type="submit" class="btn btn-danger">Si, eliminar el medico</button>
                        <a class="btn btn-outline-secondary" href="<?= e(url('admin/medico.php?rut=' . rawurlencode($rut))) ?>">No, volver</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php pie();
