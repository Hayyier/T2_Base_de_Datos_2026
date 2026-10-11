<?php
/**
 * Reprogramar una cita propia.
 *
 * Solo cambia la fecha y hora: el medico, el centro y la especialidad se
 * mantienen (para cambiarlos se cancela y se agenda otra). Condiciones:
 * la cita pertenece al paciente de la sesion, esta Reservada o Confirmada y
 * su fecha-hora no ha pasado. La cita reprogramada vuelve a Reservada
 * (lo hace el trigger trg_cita_bu).
 *
 * GET  ?id=N[&fecha=AAAA-MM-DD]  -> datos actuales + bloques disponibles
 * POST id, fechaHora, csrf       -> transaccion:
 *    1. SELECT ... FOR UPDATE de la cita filtrando por id Y RUT de la sesion
 *       (bloquea la fila: nadie la cancela ni la cambia mientras tanto);
 *    2. se revalidan estado, fecha y el bloque nuevo (procedimiento);
 *    3. UPDATE con las mismas condiciones en el WHERE;
 *    4. COMMIT y redirect al comprobante.
 * El trigger y los UNIQUE vuelven a garantizar choques y reglas.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('paciente');
$rut = rut_sesion();

if (es_post()) {
    csrf_verificar();
}
$idCita = entero_positivo(es_post() ? post('id') : get('id'));
$cita = cita_del_paciente($idCita, $rut);          // propiedad: id + RUT de la sesion
if ($cita === null) {
    pagina_error(404, 'Cita no encontrada', 'La cita no existe o no pertenece a tu cuenta.');
}
if (($motivo = motivo_no_modificable($cita, 'reprogramar')) !== null) {
    flash('warning', $motivo);
    redirect('paciente/citas.php');
}

$errores = [];
$fechaHoraElegida = '';

if (es_post()) {
    $fechaHoraElegida = post('fechaHora');
    $nueva = fecha_hora_valida($fechaHoraElegida);
    $pdo = db();
    try {
        $pdo->beginTransaction();
        // 1. bloqueo y revalidacion de la cita (propiedad + estado + fecha)
        $actual = q('SELECT idCita, fechaHoraCita, RUTMedico, ' . SQL_MODIFICABLE . ' AS modificable
                       FROM Cita WHERE idCita = ? AND RUTPaciente = ? FOR UPDATE', [$idCita, $rut])->fetch();
        if (!$actual || (int) $actual['modificable'] !== 1) {
            $error = 'La cita ya no se puede reprogramar (cambio su estado o su hora ya paso).';
        } elseif ($nueva !== null && $nueva === $actual['fechaHoraCita']) {
            $error = 'La nueva fecha y hora es igual a la actual.';
        } else {
            // 2. bloque nuevo: mismo criterio que la lista mostrada
            $error = validar_bloque($actual['RUTMedico'], $rut, $fechaHoraElegida);
        }
        if ($error === null) {
            // 3. UPDATE condicionado (defensa adicional; el trigger repite las reglas)
            $st = q('UPDATE Cita SET fechaHoraCita = ?
                      WHERE idCita = ? AND RUTPaciente = ? AND ' . SQL_MODIFICABLE,
                    [$nueva, $idCita, $rut]);
            if ($st->rowCount() !== 1) {
                $error = 'La cita ya no se puede reprogramar.';
            }
        }
        if ($error === null) {
            $pdo->commit();
            flash('success', 'Cita reprogramada. Quedo en estado Reservada para el nuevo horario.');
            redirect('paciente/cita.php?id=' . $idCita);
        }
        $pdo->rollBack();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = mensaje_error_bd($e);
    }
    $errores[] = $error;
    http_response_code(422);
    $fecha = fecha_valida(substr($fechaHoraElegida, 0, 10));
} else {
    $fechaTexto = get('fecha');
    $fecha = fecha_valida($fechaTexto);
    if ($fechaTexto !== '' && $fecha === null) {
        $errores[] = 'La fecha seleccionada no es valida.';
    }
}

$bloques = [];
$sinBloques = null;
if ($fecha !== null) {
    $bloques = bloques_disponibles($cita['RUTMedico'], $fecha, $rut);
    if (!$bloques) {
        $sinBloques = bloques_disponibles(null, $fecha, null)
            ? 'El medico no tiene bloques disponibles ese dia (o coinciden con tus otras citas). Prueba otra fecha.'
            : 'La fecha seleccionada no esta disponible: no hay atencion ese dia o esta fuera del plazo de '
              . AGENDA_DIAS_MAX . ' dias.';
    }
}
$hoy = date('Y-m-d');
$max = date('Y-m-d', strtotime('+' . AGENDA_DIAS_MAX . ' days'));

encabezado('Reprogramar cita', 'paciente/citas.php');
?>
<h1 class="h3 mb-1">Reprogramar cita #<?= (int) $cita['idCita'] ?></h1>
<p class="text-muted mb-4">Solo cambia la fecha y la hora. Para cambiar de medico, centro o especialidad,
    cancela esta cita y agenda una nueva.</p>

<?php lista_errores(array_filter($errores)); ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">Cita actual</div>
            <div class="card-body">
                <dl class="row small mb-0">
                    <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8"><?= e(fecha_legible($cita['fechaHoraCita'])) ?></dd>
                    <dt class="col-sm-4">Hora</dt><dd class="col-sm-8"><?= e(hora_legible($cita['fechaHoraCita'])) ?></dd>
                    <dt class="col-sm-4">Medico</dt><dd class="col-sm-8"><?= e($cita['nombreMedico']) ?></dd>
                    <dt class="col-sm-4">Especialidad</dt><dd class="col-sm-8"><?= e($cita['nombreEspecialidad']) ?></dd>
                    <dt class="col-sm-4">Centro</dt><dd class="col-sm-8"><?= e($cita['nombreCentro']) ?></dd>
                    <dt class="col-sm-4">Estado</dt><dd class="col-sm-8"><?= badge_estado((int) $cita['idEstadoCita'], $cita['nombreEstadoCita']) ?></dd>
                </dl>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Nueva fecha</div>
            <div class="card-body">
                <form method="get" action="<?= e(url('paciente/reprogramar.php')) ?>">
                    <input type="hidden" name="id" value="<?= (int) $cita['idCita'] ?>">
                    <input type="date" class="form-control mb-2" name="fecha" min="<?= e($hoy) ?>" max="<?= e($max) ?>"
                           value="<?= e($fecha ?? '') ?>" onchange="this.form.submit()">
                    <button type="submit" class="btn btn-outline-primary">Ver horarios</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Bloques disponibles</div>
            <div class="card-body">
                <?php if ($fecha === null): ?>
                    <p class="text-muted mb-0">Elige una fecha para ver los horarios disponibles del mismo medico.</p>
                <?php elseif ($sinBloques !== null): ?>
                    <div class="alert alert-warning mb-0" id="sin-bloques"><?= e($sinBloques) ?></div>
                <?php else: ?>
                    <form method="post" action="<?= e(url('paciente/reprogramar.php')) ?>">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="id" value="<?= (int) $cita['idCita'] ?>">
                        <p class="small text-muted"><?= e(fecha_legible($fecha)) ?></p>
                        <div class="d-flex flex-wrap gap-2 mb-3" id="bloques">
                            <?php foreach ($bloques as $valor => $hora): ?>
                                <input type="radio" class="btn-check" name="fechaHora" id="b<?= e(str_replace(':', '', $hora)) ?>"
                                       value="<?= e($valor) ?>" required>
                                <label class="btn btn-outline-primary bloque" for="b<?= e(str_replace(':', '', $hora)) ?>"><?= e($hora) ?></label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-primary">Confirmar nuevo horario</button>
                        <a class="btn btn-link" href="<?= e(url('paciente/citas.php')) ?>">Volver</a>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php pie();
