<?php
/**
 * Agendar hora.
 *
 * Flujo (GET, un paso a la vez; cada lista sale de la BD y depende de lo ya elegido):
 *   especialidad -> centro -> medico -> fecha -> bloques disponibles
 *   -> (POST) confirmar reserva -> validar -> INSERT -> redirect al comprobante.
 *
 *  - Especialidades: solo las que tienen algun medico.
 *  - Centros: donde atiende algun medico de esa especialidad.
 *  - Medicos: los que poseen la especialidad Y atienden en el centro.
 *  - Bloques: los que devuelve sp_bloques_disponibles(medico, fecha, paciente),
 *    es decir, solo los que realmente se pueden reservar.
 *  Se excluye siempre al propio paciente como medico.
 *
 * El POST vuelve a validar TODO en el servidor (no confia en las listas):
 * existencia, medico-especialidad, medico-centro y el bloque (revalidado
 * contra el procedimiento en ese momento). Luego inserta; los triggers y los
 * UNIQUE de Cita vuelven a garantizar las reglas y resuelven la concurrencia.
 * El estado inicial (Reservada) lo pone la BD (DEFAULT 1 + trigger).
 *
 * Desde la busqueda se puede llegar con ?fijo=RUT (medico preseleccionado).
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('paciente');
$rut = rut_sesion();

/** Lee un parametro del POST (al confirmar) o del GET (pasos). */
function param(string $clave): string
{
    return es_post() ? post($clave) : get($clave);
}

$errores = [];
$avisoPaso = null;           // solo en GET: por que se descarto una seleccion manipulada/obsoleta
$fechaHoraElegida = '';

$fijo   = rut_parametro(param('fijo'));
$esp    = entero_positivo(param('esp'));
$centro = entero_positivo(param('centro'));
$medico = $fijo !== '' ? $fijo : rut_parametro(param('medico'));

// ---------------------------------------------------------------------
// POST: confirmar la reserva
// ---------------------------------------------------------------------
if (es_post()) {
    csrf_verificar();
    $fechaHoraElegida = post('fechaHora');

    $error = validar_medico_especialidad_centro($medico, $esp, $centro, $rut)
          ?? validar_bloque($medico, $rut, $fechaHoraElegida);

    if ($error === null) {
        try {
            q('INSERT INTO Cita (fechaHoraCita, RUTPaciente, RUTMedico, idCentro, idEspecialidad)
               VALUES (?, ?, ?, ?, ?)',
              [fecha_hora_valida($fechaHoraElegida), $rut, $medico, $centro, $esp]);
            $idCita = (int) db()->lastInsertId();
            flash('success', 'Tu hora quedo reservada. Guarda este comprobante.');
            redirect('paciente/cita.php?id=' . $idCita);          // PRG: un refresh no vuelve a reservar
        } catch (PDOException $e) {
            // p. ej. otro paciente tomo el bloque entre la revalidacion y el INSERT (UNIQUE)
            $error = mensaje_error_bd($e);
        }
    }
    $errores[] = $error;
    http_response_code(422);
    $fecha = fecha_valida(substr($fechaHoraElegida, 0, 10));
} else {
    $fechaTexto = get('fecha');
    $fecha = fecha_valida($fechaTexto);
    if ($fechaTexto !== '' && $fecha === null) {
        $avisoPaso = 'La fecha seleccionada no es valida.';
    }
}

// ---------------------------------------------------------------------
// Pasos: cada seleccion se valida contra la lista que la BD permite
// ---------------------------------------------------------------------
$listaEsp = especialidades_agendables($rut, $medico);
if ($medico !== '' && !$listaEsp) {
    $avisoPaso ??= 'El medico seleccionado no existe o no esta disponible para agendar.';
    $medico = $fijo = '';
    $listaEsp = especialidades_agendables($rut);
}
if ($esp && !in_array($esp, array_map('intval', array_column($listaEsp, 'idEspecialidad')), true)) {
    $avisoPaso ??= $medico !== '' ? 'El medico seleccionado no posee la especialidad solicitada.'
                                  : 'La especialidad seleccionada no existe o no tiene medicos disponibles.';
    $esp = 0;
}

$listaCen = $esp ? centros_agendables($esp, $rut, $medico) : [];
if ($centro && !in_array($centro, array_map('intval', array_column($listaCen, 'idCentro')), true)) {
    $avisoPaso ??= $medico !== '' ? 'El medico no atiende en el centro seleccionado.'
                                  : 'No hay medicos de esa especialidad en el centro seleccionado.';
    $centro = 0;
}

$listaMed = ($esp && $centro) ? medicos_agendables($esp, $centro, $rut) : [];
if ($medico !== '' && $esp && $centro && !in_array($medico, array_column($listaMed, 'RUTMedico'), true)) {
    $avisoPaso ??= 'El medico seleccionado no posee la especialidad o no atiende en el centro elegido.';
    $medico = $fijo = '';
}
if ($medico === '' && count($listaMed) === 1) {
    $medico = $listaMed[0]['RUTMedico'];          // unico medico posible: se preselecciona
}

$bloques = [];
$sinBloques = null;
if ($esp && $centro && $medico !== '' && $fecha !== null) {
    $bloques = bloques_disponibles($medico, $fecha, $rut);
    if (!$bloques) {
        $sinBloques = bloques_disponibles(null, $fecha, null)
            ? 'El medico no tiene bloques disponibles ese dia (o coinciden con tus otras citas). Prueba otra fecha.'
            : 'La fecha seleccionada no esta disponible: no hay atencion ese dia o esta fuera del plazo de '
              . AGENDA_DIAS_MAX . ' dias.';
    }
}
if (!es_post() && $avisoPaso !== null) {
    $errores[] = $avisoPaso;
}

$nombreDe = static fn(array $lista, string $id, string $campo, $valor) =>
    (string) (array_column($lista, $campo, $id)[$valor] ?? '');
$nombreMedico = $nombreDe($listaMed ?: [], 'RUTMedico', 'nombreCompleto', $medico)
    ?: (string) q('SELECT nombreCompleto FROM Persona WHERE RUT = ?', [$medico])->fetchColumn();
$hoy = date('Y-m-d');
$max = date('Y-m-d', strtotime('+' . AGENDA_DIAS_MAX . ' days'));

encabezado('Agendar hora');
?>
<h1 class="h3 mb-1">Agendar hora</h1>
<p class="text-muted mb-4">Elige especialidad, centro, medico y fecha; luego uno de los bloques disponibles
    (<?= e(AGENDA_DESCRIPCION) ?>).</p>

<?php lista_errores(array_filter($errores)); ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">1. Que necesitas</div>
            <div class="card-body">
                <form method="get" action="<?= e(url('paciente/agendar.php')) ?>" id="form-pasos">
                    <?php if ($fijo !== ''): ?>
                        <input type="hidden" name="fijo" value="<?= e($fijo) ?>">
                        <div class="alert alert-light border py-2 small">
                            Medico elegido: <strong><?= e($nombreMedico) ?></strong>
                            · <a href="<?= e(url('paciente/agendar.php')) ?>">elegir otro</a>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="esp" class="form-label">Especialidad</label>
                        <select class="form-select" id="esp" name="esp" data-nivel="1">
                            <option value="">Selecciona…</option>
                            <?php foreach ($listaEsp as $x): ?>
                                <option value="<?= (int) $x['idEspecialidad'] ?>" <?= (int) $x['idEspecialidad'] === $esp ? 'selected' : '' ?>><?= e($x['nombreEspecialidad']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($esp): ?>
                    <div class="mb-3">
                        <label for="centro" class="form-label">Centro</label>
                        <select class="form-select" id="centro" name="centro" data-nivel="2">
                            <option value="">Selecciona…</option>
                            <?php foreach ($listaCen as $x): ?>
                                <option value="<?= (int) $x['idCentro'] ?>" <?= (int) $x['idCentro'] === $centro ? 'selected' : '' ?>><?= e($x['nombreCentro']) ?> (<?= e($x['nombreComuna']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if ($esp && $centro && $fijo === ''): ?>
                    <div class="mb-3">
                        <label for="medico" class="form-label">Medico</label>
                        <select class="form-select" id="medico" name="medico" data-nivel="3">
                            <option value="">Selecciona…</option>
                            <?php foreach ($listaMed as $x): ?>
                                <option value="<?= e($x['RUTMedico']) ?>" <?= $x['RUTMedico'] === $medico ? 'selected' : '' ?>><?= e($x['nombreCompleto']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <?php if ($esp && $centro && $medico !== ''): ?>
                    <div class="mb-3">
                        <label for="fecha" class="form-label">Fecha</label>
                        <input type="date" class="form-control" id="fecha" name="fecha" data-nivel="4"
                               min="<?= e($hoy) ?>" max="<?= e($max) ?>" value="<?= e($fecha ?? '') ?>">
                        <div class="form-text">Lunes a sabado, hasta el <?= e(date('d/m/Y', strtotime($max))) ?>.</div>
                    </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-outline-primary">Continuar</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">2. Elige un bloque y confirma</div>
            <div class="card-body">
                <?php if (!($esp && $centro && $medico !== '' && $fecha !== null)): ?>
                    <p class="text-muted mb-0">Completa los datos de la izquierda para ver los horarios disponibles.</p>
                <?php elseif ($sinBloques !== null): ?>
                    <div class="alert alert-warning mb-0" id="sin-bloques"><?= e($sinBloques) ?></div>
                <?php else: ?>
                    <dl class="row small mb-3">
                        <dt class="col-sm-4">Especialidad</dt><dd class="col-sm-8"><?= e($nombreDe($listaEsp, 'idEspecialidad', 'nombreEspecialidad', $esp)) ?></dd>
                        <dt class="col-sm-4">Centro</dt><dd class="col-sm-8"><?= e($nombreDe($listaCen, 'idCentro', 'nombreCentro', $centro)) ?></dd>
                        <dt class="col-sm-4">Medico</dt><dd class="col-sm-8"><?= e($nombreMedico) ?></dd>
                        <dt class="col-sm-4">Fecha</dt><dd class="col-sm-8"><?= e(fecha_legible($fecha)) ?></dd>
                    </dl>
                    <form method="post" action="<?= e(url('paciente/agendar.php')) ?>">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="esp" value="<?= (int) $esp ?>">
                        <input type="hidden" name="centro" value="<?= (int) $centro ?>">
                        <input type="hidden" name="medico" value="<?= e($medico) ?>">
                        <?php if ($fijo !== ''): ?><input type="hidden" name="fijo" value="<?= e($fijo) ?>"><?php endif; ?>
                        <div class="d-flex flex-wrap gap-2 mb-3" id="bloques">
                            <?php foreach ($bloques as $valor => $hora): ?>
                                <input type="radio" class="btn-check" name="fechaHora" id="b<?= e(str_replace(':', '', $hora)) ?>"
                                       value="<?= e($valor) ?>" <?= $valor === fecha_hora_valida($fechaHoraElegida) ? 'checked' : '' ?> required>
                                <label class="btn btn-outline-primary bloque" for="b<?= e(str_replace(':', '', $hora)) ?>"><?= e($hora) ?></label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn btn-primary">Confirmar reserva</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Al cambiar un paso se descartan los siguientes y se recarga la pagina (sin JS: boton "Continuar").
document.querySelectorAll('#form-pasos [data-nivel]').forEach(function (campo) {
    campo.addEventListener('change', function () {
        var nivel = Number(campo.dataset.nivel);
        document.querySelectorAll('#form-pasos [data-nivel]').forEach(function (otro) {
            if (Number(otro.dataset.nivel) > nivel) { otro.disabled = true; }
        });
        campo.form.submit();
    });
});
</script>
<?php pie();
