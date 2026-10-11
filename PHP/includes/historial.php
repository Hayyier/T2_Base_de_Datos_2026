<?php
/**
 * Historial clinico de UN paciente, comun a:
 *   - paciente/historial.php (el paciente ve el suyo; solo lectura)
 *   - medico/historial.php   (el medico ve el de sus pacientes)
 *
 * Quien llama decide QUE paciente puede ver (control de acceso); estas
 * funciones solo leen. Fuentes: view v_historial_clinico (una fila por
 * atencion) + Atencion_Diagnostico/Diagnostico y Receta como filas.
 */

/** Devuelve [atenciones, diagnosticos por idAtencion, recetas por idAtencion]. */
function historial_de_paciente(string $rutPaciente): array
{
    $atenciones = q('SELECT idAtencion, idCita, fechaAtencion, RUTMedico, nombreMedico, nombreEspecialidad,
                            nombreCentro, motivo, observaciones
                       FROM v_historial_clinico
                      WHERE RUTPaciente = ?
                      ORDER BY fechaAtencion DESC', [$rutPaciente])->fetchAll();

    $diagnosticos = [];
    foreach (q('SELECT ad.idAtencion, d.codigoDiagnostico, d.descripcion
                  FROM Atencion_Diagnostico ad
                  JOIN Diagnostico d ON d.codigoDiagnostico = ad.codigoDiagnostico
                  JOIN Atencion a    ON a.idAtencion = ad.idAtencion
                  JOIN Cita c        ON c.idCita = a.idCita
                 WHERE c.RUTPaciente = ?
                 ORDER BY d.codigoDiagnostico', [$rutPaciente]) as $d) {
        $diagnosticos[(int) $d['idAtencion']][] = $d;
    }

    $recetas = [];
    foreach (q('SELECT r.idAtencion, r.medicamento, r.dosis, r.diasTratamiento
                  FROM Receta r
                  JOIN Atencion a ON a.idAtencion = r.idAtencion
                  JOIN Cita c     ON c.idCita = a.idCita
                 WHERE c.RUTPaciente = ?
                 ORDER BY r.idReceta', [$rutPaciente]) as $r) {
        $recetas[(int) $r['idAtencion']][] = $r;
    }
    return [$atenciones, $diagnosticos, $recetas];
}

/** Lista de diagnosticos (codigo + descripcion). */
function mostrar_diagnosticos(array $lista): void
{
    echo '<ul class="list-unstyled small mb-0 diagnosticos">';
    foreach ($lista as $d) {
        echo '<li><span class="badge text-bg-light border me-1">' . e($d['codigoDiagnostico']) . '</span>'
           . e($d['descripcion']) . '</li>';
    }
    echo '</ul>';
}

/** Tabla de receta (medicamento, dosis, dias) o aviso si no hay lineas. */
function mostrar_receta(array $lista): void
{
    if (!$lista) {
        echo '<p class="small text-muted mb-0">Sin medicamentos recetados.</p>';
        return;
    }
    echo '<table class="table table-sm small mb-0 recetas"><thead><tr><th>Medicamento</th><th>Dosis</th><th>Dias</th></tr></thead><tbody>';
    foreach ($lista as $r) {
        echo '<tr><td>' . e($r['medicamento']) . '</td><td>' . e($r['dosis']) . '</td><td>'
           . (int) $r['diasTratamiento'] . '</td></tr>';
    }
    echo '</tbody></table>';
}

/**
 * Tarjetas del historial. $enlaceFicha (opcional) recibe la fila y devuelve
 * la URL de la ficha o null (el medico ve "Ver ficha"; el paciente no).
 */
function mostrar_historial(array $atenciones, array $diagnosticos, array $recetas, ?callable $enlaceFicha = null): void
{
    if (!$atenciones) {
        echo '<div class="alert alert-info">No hay atenciones registradas.</div>';
        return;
    }
    foreach ($atenciones as $a) {
        $id = (int) $a['idAtencion'];
        $ficha = $enlaceFicha ? $enlaceFicha($a) : null;
        ?>
        <div class="card shadow-sm mb-3 atencion" data-atencion="<?= $id ?>">
            <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                <span class="fw-semibold"><?= e(fecha_legible($a['fechaAtencion'])) ?> · <?= e(hora_legible($a['fechaAtencion'])) ?></span>
                <span class="text-muted small">
                    Atencion #<?= $id ?> · Cita #<?= (int) $a['idCita'] ?>
                    <?php if ($ficha): ?> · <a href="<?= e(url($ficha)) ?>">Ver ficha</a><?php endif; ?>
                </span>
            </div>
            <div class="card-body">
                <dl class="row small mb-3">
                    <dt class="col-sm-3">Medico</dt><dd class="col-sm-9"><?= e($a['nombreMedico']) ?></dd>
                    <dt class="col-sm-3">Especialidad</dt><dd class="col-sm-9"><?= e($a['nombreEspecialidad']) ?></dd>
                    <dt class="col-sm-3">Centro</dt><dd class="col-sm-9"><?= e($a['nombreCentro']) ?></dd>
                    <dt class="col-sm-3">Motivo</dt><dd class="col-sm-9"><?= e($a['motivo']) ?></dd>
                    <dt class="col-sm-3">Observaciones</dt><dd class="col-sm-9 texto-clinico"><?= e($a['observaciones'] ?? '—') ?></dd>
                </dl>
                <div class="row g-3">
                    <div class="col-md-6">
                        <h2 class="h6">Diagnosticos</h2>
                        <?php mostrar_diagnosticos($diagnosticos[$id] ?? []); ?>
                    </div>
                    <div class="col-md-6">
                        <h2 class="h6">Recetas</h2>
                        <?php mostrar_receta($recetas[$id] ?? []); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
