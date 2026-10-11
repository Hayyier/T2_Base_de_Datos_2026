<?php
/**
 * Eliminar una ATENCION propia (la D del CRUD).
 *
 * GET ?id=A -> confirmacion; POST id, csrf -> DELETE.
 * Se borran en cascada (FK ON DELETE CASCADE) sus diagnosticos y recetas:
 * no quedan recetas huerfanas. La cita sigue Atendida y el medico puede
 * registrar una nueva atencion para ella. El DELETE lleva la pertenencia
 * en la misma sentencia (JOIN Cita ... AND c.RUTMedico = ?).
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('medico');
$rut = rut_sesion();
if (es_post()) {
    csrf_verificar();
}

$idAtencion = entero_positivo(es_post() ? post('id') : get('id'));
$atencion = atencion_visible($idAtencion, $rut);
if ($atencion === null || (int) $atencion['propia'] !== 1) {
    pagina_error(404, 'Atencion no encontrada', 'La atencion no existe o no pertenece a una de tus citas.');
}

if (es_post()) {
    try {
        $st = q('DELETE a FROM Atencion a JOIN Cita c ON c.idCita = a.idCita
                  WHERE a.idAtencion = ? AND c.RUTMedico = ?', [$idAtencion, $rut]);
        if ($st->rowCount() === 1) {
            flash('success', 'Atencion #' . $idAtencion . ' eliminada junto con sus diagnosticos y recetas. '
                  . 'La cita #' . (int) $atencion['idCita'] . ' sigue Atendida y puedes registrar una nueva atencion.');
            redirect('medico/agenda.php?fecha=' . substr($atencion['fechaAtencion'], 0, 10));
        }
        flash('warning', 'No se pudo eliminar la atencion.');
    } catch (PDOException $e) {
        flash('danger', mensaje_error_bd($e));
    }
    redirect('medico/ficha.php?id=' . $idAtencion);
}

encabezado('Eliminar atencion', 'medico/agenda.php');
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm border-danger-subtle">
            <div class="card-header bg-white fw-semibold">Eliminar atencion #<?= $idAtencion ?></div>
            <div class="card-body">
                <dl class="row small">
                    <dt class="col-sm-4">Paciente</dt><dd class="col-sm-8"><?= e($atencion['nombrePaciente']) ?></dd>
                    <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8"><?= e(fecha_legible($atencion['fechaAtencion'])) ?> · <?= e(hora_legible($atencion['fechaAtencion'])) ?></dd>
                    <dt class="col-sm-4">Motivo</dt><dd class="col-sm-8"><?= e($atencion['motivo']) ?></dd>
                </dl>
                <p>Se eliminaran tambien <strong>todos sus diagnosticos y lineas de receta</strong>. Esta accion no se puede deshacer.
                    La cita seguira en estado Atendida.</p>
                <form method="post" action="<?= e(url('medico/atencion_eliminar.php')) ?>" class="d-flex gap-2">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= $idAtencion ?>">
                    <button type="submit" class="btn btn-danger">Si, eliminar la atencion</button>
                    <a class="btn btn-outline-secondary" href="<?= e(url('medico/ficha.php?id=' . $idAtencion)) ?>">No, volver a la ficha</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php pie();
