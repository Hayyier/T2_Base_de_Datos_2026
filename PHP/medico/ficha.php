<?php
/**
 * Ficha de Atencion (Vista 2 del Anexo) + gestion de diagnosticos
 * y receta.
 *
 * Acceso:
 *  - VER: atenciones registradas por el medico de la sesion o de pacientes
 *    que tienen al menos una cita con el (atencion_visible()).
 *  - MODIFICAR: solo si la atencion es de una cita propia: solo
 *    entonces se muestran los formularios; diagnostico.php y receta.php
 *    lo vuelven a comprobar en cada POST.
 * Datos: view v_historial_clinico (cabecera, una fila) + diagnosticos y
 * recetas como filas (las mismas funciones que el historial).
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('medico');
$rut = rut_sesion();

$a = atencion_visible(entero_positivo(get('id')), $rut);
if ($a === null) {
    pagina_error(404, 'Atencion no encontrada', 'La atencion no existe o no corresponde a tus pacientes.');
}
$id = (int) $a['idAtencion'];
$propia = (int) $a['propia'] === 1;
$diagnosticos = diagnosticos_de($id);
$recetas = recetas_de($id);
$catalogo = $propia ? catalogo_diagnosticos() : [];
$asignados = array_column($diagnosticos, 'codigoDiagnostico');

/** <select> de diagnosticos del catalogo; excluye los ya asignados salvo $actual. */
function select_diagnostico(array $catalogo, array $asignados, string $actual, string $nombre): void
{
    echo '<select class="form-select form-select-sm" name="' . e($nombre) . '" required>';
    if ($actual === '') {
        echo '<option value="">Selecciona…</option>';
    }
    foreach ($catalogo as $d) {
        if ($d['codigoDiagnostico'] !== $actual && in_array($d['codigoDiagnostico'], $asignados, true)) {
            continue;
        }
        echo '<option value="' . e($d['codigoDiagnostico']) . '"' . ($d['codigoDiagnostico'] === $actual ? ' selected' : '') . '>'
           . e($d['codigoDiagnostico'] . ' — ' . $d['descripcion']) . '</option>';
    }
    echo '</select>';
}

encabezado('Ficha de atencion #' . $id, $propia ? 'medico/agenda.php' : 'medico/historial.php');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Ficha de atencion</h1>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('medico/historial.php?paciente=' . rawurlencode($a['RUTPaciente']))) ?>">Historial del paciente</a>
        <?php if ($propia): ?>
            <a class="btn btn-outline-secondary btn-sm" href="<?= e(url('medico/agenda.php?fecha=' . substr($a['fechaAtencion'], 0, 10))) ?>">Agenda del dia</a>
            <a class="btn btn-outline-primary btn-sm" href="<?= e(url('medico/atencion.php?id=' . $id)) ?>">Editar motivo / observaciones</a>
            <a class="btn btn-outline-danger btn-sm" href="<?= e(url('medico/atencion_eliminar.php?id=' . $id)) ?>">Eliminar atencion</a>
        <?php endif; ?>
    </div>
</div>
<?php if (!$propia): ?>
    <div class="alert alert-light border small">Atencion registrada por otro medico: solo lectura.</div>
<?php endif; ?>

<div class="card shadow-sm mb-4" id="ficha">
    <div class="card-header bg-white fw-semibold">SaludUSM – Ficha de Atencion</div>
    <div class="card-body">
        <div class="row">
            <dl class="row col-md-6 mb-0 small">
                <dt class="col-5">N.º de atencion</dt><dd class="col-7" id="n-atencion"><?= $id ?></dd>
                <dt class="col-5">Fecha</dt><dd class="col-7"><?= e(date('d-m-Y H:i', strtotime($a['fechaAtencion']))) ?></dd>
                <dt class="col-5">RUT paciente</dt><dd class="col-7"><?= e($a['RUTPaciente']) ?></dd>
                <dt class="col-5">Fecha nacimiento</dt><dd class="col-7"><?= e(date('d-m-Y', strtotime($a['fechaNacimiento']))) ?></dd>
                <dt class="col-5">RUT medico</dt><dd class="col-7"><?= e($a['RUTMedico']) ?></dd>
                <dt class="col-5">Especialidad</dt><dd class="col-7"><?= e($a['nombreEspecialidad']) ?></dd>
            </dl>
            <dl class="row col-md-6 mb-0 small">
                <dt class="col-5">N.º de cita</dt><dd class="col-7"><?= (int) $a['idCita'] ?></dd>
                <dt class="col-5">Centro</dt><dd class="col-7"><?= e($a['nombreCentro']) ?></dd>
                <dt class="col-5">Nombre paciente</dt><dd class="col-7"><?= e($a['nombrePaciente']) ?></dd>
                <dt class="col-5">Sexo</dt><dd class="col-7"><?= e(SEXOS[$a['sexo']] ?? $a['sexo']) ?></dd>
                <dt class="col-5">Nombre medico</dt><dd class="col-7"><?= e($a['nombreMedico']) ?></dd>
                <dt class="col-5">E-mail medico</dt><dd class="col-7"><?= e($a['emailMedico']) ?></dd>
            </dl>
        </div>
        <hr>
        <h2 class="h6">Motivo de consulta</h2>
        <p class="mb-3" id="motivo"><?= e($a['motivo']) ?></p>
        <h2 class="h6">Observaciones</h2>
        <p class="mb-0 texto-clinico" id="observaciones"><?= e($a['observaciones'] ?? '—') ?></p>
    </div>
</div>

<div class="row g-4">
    <!-- Diagnosticos -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Diagnosticos</div>
            <ul class="list-group list-group-flush" id="diagnosticos">
                <?php foreach ($diagnosticos as $d): ?>
                    <li class="list-group-item" data-codigo="<?= e($d['codigoDiagnostico']) ?>">
                        <?php if (!$propia): ?>
                            <span class="badge text-bg-light border me-1"><?= e($d['codigoDiagnostico']) ?></span><?= e($d['descripcion']) ?>
                        <?php else: ?>
                            <form method="post" action="<?= e(url('medico/diagnostico.php')) ?>" class="d-flex gap-2 align-items-center">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="codigo" value="<?= e($d['codigoDiagnostico']) ?>">
                                <?php select_diagnostico($catalogo, $asignados, $d['codigoDiagnostico'], 'nuevo'); ?>
                                <button type="submit" name="accion" value="editar" class="btn btn-sm btn-outline-primary">Cambiar</button>
                                <?php if (count($diagnosticos) > 1): ?>
                                    <button type="submit" name="accion" value="quitar" class="btn btn-sm btn-outline-danger">Quitar</button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($propia): ?>
            <div class="card-footer bg-white">
                <form method="post" action="<?= e(url('medico/diagnostico.php')) ?>" class="d-flex gap-2">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="accion" value="agregar">
                    <?php select_diagnostico($catalogo, $asignados, '', 'nuevo'); ?>
                    <button type="submit" class="btn btn-sm btn-primary text-nowrap">Agregar</button>
                </form>
                <div class="form-text">Toda atencion conserva al menos un diagnostico.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Receta -->
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Receta</div>
            <div class="card-body">
                <?php if (!$propia): ?>
                    <?php mostrar_receta($recetas); ?>
                <?php else: ?>
                    <?php if (!$recetas): ?><p class="small text-muted">Sin medicamentos recetados.</p><?php endif; ?>
                    <div id="recetas">
                    <?php foreach ($recetas as $r): ?>
                        <form method="post" action="<?= e(url('medico/receta.php')) ?>" class="row g-1 align-items-center mb-2 linea-receta" data-receta="<?= (int) $r['idReceta'] ?>">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="receta" value="<?= (int) $r['idReceta'] ?>">
                            <div class="col-5"><input class="form-control form-control-sm" name="medicamento" maxlength="<?= MEDICAMENTO_MAX ?>" value="<?= e($r['medicamento']) ?>" required aria-label="Medicamento"></div>
                            <div class="col-4"><input class="form-control form-control-sm" name="dosis" maxlength="<?= DOSIS_MAX ?>" value="<?= e($r['dosis']) ?>" required aria-label="Dosis"></div>
                            <div class="col-3"><input class="form-control form-control-sm" name="dias" type="number" min="1" max="<?= DIAS_TRATAMIENTO_MAX ?>" value="<?= (int) $r['diasTratamiento'] ?>" required aria-label="Dias"></div>
                            <div class="col-12 d-flex gap-1 justify-content-end">
                                <button type="submit" name="accion" value="editar" class="btn btn-sm btn-outline-primary">Guardar</button>
                                <button type="submit" name="accion" value="eliminar" class="btn btn-sm btn-outline-danger" formnovalidate>Eliminar</button>
                            </div>
                        </form>
                    <?php endforeach; ?>
                    </div>
                    <hr>
                    <form method="post" action="<?= e(url('medico/receta.php')) ?>" class="row g-1 align-items-end">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="accion" value="agregar">
                        <div class="col-5"><label class="form-label small mb-0" for="n-med">Medicamento</label><input class="form-control form-control-sm" id="n-med" name="medicamento" maxlength="<?= MEDICAMENTO_MAX ?>" required></div>
                        <div class="col-4"><label class="form-label small mb-0" for="n-dosis">Dosis</label><input class="form-control form-control-sm" id="n-dosis" name="dosis" maxlength="<?= DOSIS_MAX ?>" required></div>
                        <div class="col-3"><label class="form-label small mb-0" for="n-dias">Dias</label><input class="form-control form-control-sm" id="n-dias" name="dias" type="number" min="1" max="<?= DIAS_TRATAMIENTO_MAX ?>" required></div>
                        <div class="col-12 text-end mt-2"><button type="submit" class="btn btn-sm btn-primary">Agregar linea</button></div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php pie();
