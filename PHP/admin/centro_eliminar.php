<?php
/**
 * CRUD de centros - ELIMINAR. Solo administrador.
 *
 * Politica: un centro solo se elimina si NO tiene citas (futuras ni
 * historicas: son registro clinico) NI medicos asignados. Para retirar un
 * centro en uso, primero se quitan sus medicos desde la ficha de cada medico. 
 * No se reasignan ni cancelan citas.
 * Garantia en la BD: fk_cita_centro y fk_mc_centro con ON DELETE RESTRICT.
 * GET ?id -> confirmacion (o motivo del rechazo); POST id, csrf -> DELETE.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('admin');
if (es_post()) {
    csrf_verificar();
}

$c = centro_con_dependencias(entero_positivo(es_post() ? post('id') : get('id')));
if ($c === null) {
    pagina_error(404, 'Centro no encontrado', 'El centro no existe.');
}
$id = (int) $c['idCentro'];
$motivo = motivo_no_eliminar_centro($c);

if (es_post()) {
    if ($motivo !== null) {
        flash('danger', $motivo);
        redirect('admin/centro.php?id=' . $id);
    }
    try {
        q('DELETE FROM Centro WHERE idCentro = ?', [$id]);
        flash('success', 'Centro ' . $c['codigoCentro'] . ' eliminado.');
        redirect('admin/centros.php');
    } catch (PDOException $e) {
        // carrera: alguien asigno un medico o agendo una cita entre la revision y el DELETE (FK RESTRICT)
        flash('danger', 'El centro no puede eliminarse porque tiene informacion asociada (citas o medicos).');
        redirect('admin/centro.php?id=' . $id);
    }
}

encabezado('Eliminar centro', 'admin/centros.php');
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-danger-subtle">
            <div class="card-header bg-white fw-semibold">Eliminar centro <?= e($c['codigoCentro']) ?></div>
            <div class="card-body">
                <dl class="row small">
                    <dt class="col-sm-4">Nombre</dt><dd class="col-sm-8"><?= e($c['nombreCentro']) ?></dd>
                    <dt class="col-sm-4">Ubicacion</dt><dd class="col-sm-8"><?= e($c['nombreComuna']) ?>, <?= e($c['nombreRegion']) ?></dd>
                    <dt class="col-sm-4">Medicos asignados</dt><dd class="col-sm-8"><?= (int) $c['medicos'] ?></dd>
                    <dt class="col-sm-4">Citas</dt><dd class="col-sm-8"><?= (int) $c['citas'] ?> (<?= (int) $c['futuras'] ?> futuras)</dd>
                </dl>
                <?php if ($motivo !== null): ?>
                    <div class="alert alert-warning" id="motivo"><?= e($motivo) ?></div>
                    <a class="btn btn-outline-secondary" href="<?= e(url('admin/centro.php?id=' . $id)) ?>">Volver</a>
                <?php else: ?>
                    <p>El centro no tiene citas ni medicos asignados. Esta accion no se puede deshacer.</p>
                    <form method="post" action="<?= e(url('admin/centro_eliminar.php')) ?>" class="d-flex gap-2">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button type="submit" class="btn btn-danger">Si, eliminar el centro</button>
                        <a class="btn btn-outline-secondary" href="<?= e(url('admin/centros.php')) ?>">No, volver</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php pie();
