<?php
/**
 * Busqueda de medicos: por fragmento de nombre y/o especialidad.
 * El texto libre busca un trozo del nombre O de una de sus
 * especialidades ("cardio" encuentra a los cardiologos), como dice la T2;
 * el selector de especialidad del catalogo sigue disponible como filtro.
 * El boton "Agendar hora" envia ?fijo=RUT (medico preseleccionado que
 * se conserva en todos los pasos del agendamiento).
 * Comun a los tres roles (cualquier usuario autenticado). Solo el paciente ve
 * el boton "Agendar".
 *
 * Fuente: view v_directorio_medicos, una fila por medico con sus
 * especialidades y centros ya agregados (sin filas repetidas por cada
 * combinacion medico-centro-especialidad).
 * El filtro por especialidad usa el catalogo (id), no texto libre.
 */
require __DIR__ . '/../includes/bootstrap.php';

$u = requerir_login();
$esPaciente = $u['rol'] === 'paciente';

$texto = mb_substr(normalizar_nombre(get('q')), 0, 100);   // largo acotado
$idEsp = entero_positivo(get('esp'));
$especialidades = catalogo_especialidades();
$nombresEsp = array_column($especialidades, 'nombreEspecialidad', 'idEspecialidad');
$errores = [];

if (get('esp') !== '' && !isset($nombresEsp[$idEsp])) {
    $errores[] = 'La especialidad seleccionada no existe.';
    $idEsp = 0;
}

$buscado = ($texto !== '' || $idEsp > 0);
$resultados = [];
if ($buscado && !$errores) {
    // LIKE con comodines escapados: buscar "50%" o "_" busca esos caracteres literalmente
    $patron = '%' . strtr($texto, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    $resultados = q("SELECT d.RUTMedico, d.nombreMedico, d.emailInstitucional, d.especialidades, d.centros
                       FROM v_directorio_medicos d
                      WHERE (:t1 = '' OR d.nombreMedico LIKE :patron ESCAPE '!'
                             OR EXISTS (SELECT 1 FROM Medico_Especialidad me2
                                          JOIN Especialidad e2 ON e2.idEspecialidad = me2.idEspecialidad
                                         WHERE me2.RUTMedico = d.RUTMedico
                                           AND e2.nombreEspecialidad LIKE :patron2 ESCAPE '!'))
                        AND (:e1 = 0 OR EXISTS (SELECT 1 FROM Medico_Especialidad me
                                                 WHERE me.RUTMedico = d.RUTMedico
                                                   AND me.idEspecialidad = :e2))
                      ORDER BY d.nombreMedico",
                    ['t1' => $texto, 'patron' => $patron, 'patron2' => $patron, 'e1' => $idEsp, 'e2' => $idEsp])->fetchAll();
}

encabezado('Buscar medicos');
?>
<h1 class="h3 mb-3">Buscar medicos</h1>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <?php lista_errores($errores); ?>
        <form method="get" action="<?= e(url('buscar/medicos.php')) ?>" class="row g-2 align-items-end" role="search">
            <div class="col-md-6">
                <label for="q" class="form-label">Nombre o especialidad (o parte)</label>
                <input type="search" class="form-control" id="q" name="q" maxlength="100"
                       value="<?= e($texto) ?>" placeholder="Ej.: Mar, cardio">
            </div>
            <div class="col-md-4">
                <label for="esp" class="form-label">Especialidad</label>
                <select class="form-select" id="esp" name="esp">
                    <option value="">Todas</option>
                    <?php foreach ($especialidades as $es): ?>
                        <option value="<?= (int) $es['idEspecialidad'] ?>" <?= (int) $es['idEspecialidad'] === $idEsp ? 'selected' : '' ?>>
                            <?= e($es['nombreEspecialidad']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$buscado): ?>
    <p class="text-muted">Escribe parte del nombre o de la especialidad de un medico, o elige una especialidad.</p>
<?php elseif (!$errores && !$resultados): ?>
    <div class="alert alert-info">No se encontraron medicos con esos criterios.</div>
<?php elseif ($resultados): ?>
    <p class="text-muted small mb-2"><?= count($resultados) ?> medico(s) encontrado(s).</p>
    <div class="list-group shadow-sm" id="resultados">
        <?php foreach ($resultados as $m): ?>
            <div class="list-group-item py-3">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-semibold medico-nombre"><?= e($m['nombreMedico']) ?></div>
                        <div class="small text-muted"><?= e($m['emailInstitucional']) ?></div>
                    </div>
                    <?php if ($esPaciente): ?>
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?= e(url('paciente/agendar.php?' . http_build_query(array_filter(
                               ['fijo' => $m['RUTMedico'], 'esp' => $idEsp ?: null])))) ?>">Agendar hora</a>
                    <?php endif; ?>
                </div>
                <div class="mt-2 small">
                    <span class="text-muted me-1">Especialidades:</span>
                    <?php foreach (explode(' | ', (string) $m['especialidades']) as $x): ?>
                        <span class="badge text-bg-light border me-1 especialidad"><?= e($x) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="mt-1 small">
                    <span class="text-muted me-1">Atiende en:</span>
                    <?php foreach (explode(' | ', (string) $m['centros']) as $x): ?>
                        <span class="badge text-bg-light border me-1 centro"><?= e($x) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php pie();
