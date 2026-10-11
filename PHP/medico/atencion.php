<?php
/**
 * Registrar (crear) o editar una ATENCION.
 *
 *   GET/POST ?cita=N  -> registrar la atencion de la cita N
 *   GET/POST ?id=A    -> editar motivo y observaciones de la atencion A
 *
 * Crear (regla: "solo puede existir una atencion si la cita
 * esta Atendida y pertenece al medico que la registra"):
 *   1. rol medico; 2. cita del medico de la sesion (si no, 404);
 *   3. cita en estado Atendida; 4. sin atencion previa;
 *   5. motivo obligatorio, observaciones opcionales, 1 diagnostico inicial
 *      del catalogo (toda atencion tiene al menos uno);
 *   6. TRANSACCION: SELECT ... FOR UPDATE de la cita (nadie le cambia el
 *      estado mientras tanto) + INSERT Atencion + INSERT Atencion_Diagnostico;
 *   7. redirect a la ficha, donde se agregan mas diagnosticos y la receta.
 * La BD vuelve a garantizar 3 y 4 (trg_atencion_bi, uq_atencion_cita) y el
 * catalogo (fk_ad_diagnostico).
 *
 * Editar: solo atenciones de citas propias, sin limite de tiempo.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('medico');
$rut = rut_sesion();
if (es_post()) {
    csrf_verificar();
}

$idCita     = entero_positivo(es_post() ? post('cita') : get('cita'));
$idAtencion = entero_positivo(es_post() ? post('id') : get('id'));
$editar     = $idAtencion > 0;
$errores    = [];

if ($editar) {
    $atencion = atencion_propia($idAtencion, $rut);
    if ($atencion === null) {
        pagina_error(404, 'Atencion no encontrada', 'La atencion no existe o no pertenece a una de tus citas.');
    }
    $cita = cita_del_medico((int) $atencion['idCita'], $rut);
    $motivo = $atencion['motivo'];
    $observaciones = (string) $atencion['observaciones'];
} else {
    $cita = cita_del_medico($idCita, $rut);
    if ($cita === null) {
        pagina_error(404, 'Cita no encontrada', 'La cita no existe o no pertenece a tu agenda.');
    }
    if ($cita['idAtencion'] !== null) {
        flash('info', 'La cita #' . (int) $cita['idCita'] . ' ya tiene una atencion registrada.');
        redirect('medico/ficha.php?id=' . (int) $cita['idAtencion']);
    }
    if ((int) $cita['idEstadoCita'] !== ESTADO_ATENDIDA) {
        flash('warning', 'Solo se puede registrar una atencion para una cita en estado Atendida (estado actual: '
              . $cita['nombreEstadoCita'] . ').');
        redirect('medico/agenda.php?fecha=' . substr($cita['fechaHoraCita'], 0, 10));
    }
    $motivo = $observaciones = '';
}
$codigo = '';

if (es_post()) {
    $motivo        = post_texto('motivo');
    $observaciones = post_texto('observaciones');
    $errores = errores_atencion($motivo, $observaciones);

    if ($editar) {
        if (!$errores) {
            try {
                q('UPDATE Atencion a JOIN Cita c ON c.idCita = a.idCita
                      SET a.motivo = ?, a.observaciones = ?
                    WHERE a.idAtencion = ? AND c.RUTMedico = ?',
                  [$motivo, $observaciones === '' ? null : $observaciones, $idAtencion, $rut]);
                flash('success', 'Atencion actualizada.');
                redirect('medico/ficha.php?id=' . $idAtencion);
            } catch (PDOException $e) {
                $errores[] = mensaje_error_bd($e);
            }
        }
    } else {
        $codigo = post('diagnostico');
        if (!existe_diagnostico($codigo)) {
            $errores[] = 'Debes seleccionar un diagnostico valido del catalogo CIE-10.';
        }
        if (!$errores) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                $bloqueada = q('SELECT idEstadoCita FROM Cita WHERE idCita = ? AND RUTMedico = ? FOR UPDATE',
                               [$idCita, $rut])->fetchColumn();
                if ((int) $bloqueada !== ESTADO_ATENDIDA) {
                    throw new RuntimeException('La cita ya no esta en estado Atendida.');
                }
                q('INSERT INTO Atencion (idCita, motivo, observaciones) VALUES (?, ?, ?)',
                  [$idCita, $motivo, $observaciones === '' ? null : $observaciones]);
                $nueva = (int) $pdo->lastInsertId();
                q('INSERT INTO Atencion_Diagnostico (idAtencion, codigoDiagnostico) VALUES (?, ?)', [$nueva, $codigo]);
                $pdo->commit();
                flash('success', 'Atencion #' . $nueva . ' registrada. Agrega mas diagnosticos o la receta si corresponde.');
                redirect('medico/ficha.php?id=' . $nueva);       // PRG
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errores[] = mensaje_error_bd($e);              // p. ej. uq_atencion_cita (atencion duplicada)
            } catch (RuntimeException $e) {
                $pdo->rollBack();
                $errores[] = $e->getMessage();
            }
        }
    }
    http_response_code(422);
}

encabezado($editar ? 'Editar atencion' : 'Registrar atencion', 'medico/agenda.php');
?>
<h1 class="h3 mb-3"><?= $editar ? 'Editar atencion #' . $idAtencion : 'Registrar atencion' ?></h1>

<?php lista_errores($errores); ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Cita #<?= (int) $cita['idCita'] ?></div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-5">Paciente</dt><dd class="col-7"><?= e($cita['nombrePaciente']) ?></dd>
                    <dt class="col-5">RUT</dt><dd class="col-7"><?= e($cita['RUTPaciente']) ?></dd>
                    <dt class="col-5">Fecha</dt><dd class="col-7"><?= e(fecha_legible($cita['fechaHoraCita'])) ?></dd>
                    <dt class="col-5">Hora</dt><dd class="col-7"><?= e(hora_legible($cita['fechaHoraCita'])) ?></dd>
                    <dt class="col-5">Especialidad</dt><dd class="col-7"><?= e($cita['nombreEspecialidad']) ?></dd>
                    <dt class="col-5">Centro</dt><dd class="col-7"><?= e($cita['nombreCentro']) ?></dd>
                    <dt class="col-5">Estado</dt><dd class="col-7"><?= badge_estado((int) $cita['idEstadoCita'], $cita['nombreEstadoCita']) ?></dd>
                </dl>
                <a class="small" href="<?= e(url('medico/historial.php?paciente=' . rawurlencode($cita['RUTPaciente']))) ?>">Ver historial del paciente</a>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post" action="<?= e(url('medico/atencion.php')) ?>" novalidate>
                    <?= csrf_campo() ?>
                    <?php if ($editar): ?>
                        <input type="hidden" name="id" value="<?= $idAtencion ?>">
                    <?php else: ?>
                        <input type="hidden" name="cita" value="<?= (int) $cita['idCita'] ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo de consulta <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="motivo" name="motivo" maxlength="<?= MOTIVO_MAX ?>"
                               value="<?= e($motivo) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones clinicas</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="5"
                                  maxlength="<?= OBSERVACIONES_MAX ?>"><?= e($observaciones) ?></textarea>
                        <div class="form-text">Opcional. Hasta <?= OBSERVACIONES_MAX ?> caracteres.</div>
                    </div>
                    <?php if (!$editar): ?>
                    <div class="mb-3">
                        <label for="diagnostico" class="form-label">Diagnostico (CIE-10) <span class="text-danger">*</span></label>
                        <select class="form-select" id="diagnostico" name="diagnostico" required>
                            <option value="">Selecciona…</option>
                            <?php foreach (catalogo_diagnosticos() as $d): ?>
                                <option value="<?= e($d['codigoDiagnostico']) ?>" <?= $d['codigoDiagnostico'] === $codigo ? 'selected' : '' ?>>
                                    <?= e($d['codigoDiagnostico'] . ' — ' . $d['descripcion']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Toda atencion tiene al menos un diagnostico. Los demas diagnosticos y la receta se agregan en la ficha.</div>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary"><?= $editar ? 'Guardar cambios' : 'Registrar atencion' ?></button>
                    <a class="btn btn-link" href="<?= e(url($editar ? 'medico/ficha.php?id=' . $idAtencion
                        : 'medico/agenda.php?fecha=' . substr($cita['fechaHoraCita'], 0, 10))) ?>">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php pie();
