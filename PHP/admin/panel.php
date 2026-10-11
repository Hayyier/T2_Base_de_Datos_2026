<?php
/**
 * Panel de gestion. Solo administrador.
 *
 *  - Por centro: total de citas y % de inasistencia (No Asistio / total),
 *    desde la view v_panel_centros, que calcula el porcentaje con la function
 *    fn_porcentaje_inasistencia (centro sin citas -> 0.0, sin division por 0).
 *  - Top 5 de diagnosticos de la red: view v_top5_diagnosticos
 *    (frecuencia = n.º de atenciones en que aparece; empate -> por codigo).
 *    Asimismo, se informa si hay diagnosticos empatados con el 5.º lugar que
 *    quedan fuera por el desempate, y cuantos diagnosticos distintos existen
 *    (la seleccion es real: hay mas de 5).
 * Antes de calcular, se cancelan las citas vencidas, para que los
 * totales por estado esten al dia.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('admin');
actualizar_citas_vencidas();

$centros = q('SELECT idCentro, codigoCentro, nombreCentro, nombreComuna, nombreRegion,
                     totalCitas, citasNoAsistio, porcentajeInasistencia
                FROM v_panel_centros
               ORDER BY codigoCentro')->fetchAll();
$totalCitas = array_sum(array_column($centros, 'totalCitas'));
$totalNoAsistio = array_sum(array_column($centros, 'citasNoAsistio'));

$top5 = q('SELECT codigoDiagnostico, descripcion, vecesRegistrado FROM v_top5_diagnosticos')->fetchAll();
$quinto = $top5 ? (int) end($top5)['vecesRegistrado'] : 0;
$empatados = $top5 ? q('SELECT d.codigoDiagnostico, d.descripcion, COUNT(*) AS n
                          FROM Atencion_Diagnostico ad
                          JOIN Diagnostico d ON d.codigoDiagnostico = ad.codigoDiagnostico
                         GROUP BY d.codigoDiagnostico, d.descripcion
                        HAVING COUNT(*) = ?
                         ORDER BY d.codigoDiagnostico', [$quinto])->fetchAll() : [];
$empatadosFuera = array_values(array_filter($empatados,
    fn($d) => !in_array($d['codigoDiagnostico'], array_column($top5, 'codigoDiagnostico'), true)));
$resumenDiag = q('SELECT (SELECT COUNT(*) FROM Diagnostico) AS catalogo,
                         (SELECT COUNT(DISTINCT codigoDiagnostico) FROM Atencion_Diagnostico) AS usados,
                         (SELECT COUNT(*) FROM Atencion) AS atenciones')->fetch();

encabezado('Panel de gestion');
?>
<h1 class="h3 mb-1">Panel de gestion</h1>
<p class="text-muted mb-4">Datos calculados en este momento desde la base de datos.</p>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Citas e inasistencia por centro</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0" id="panel-centros">
                    <thead class="table-light">
                        <tr><th>Centro</th><th>Region</th><th class="text-end">Total citas</th><th class="text-end">No asistio</th><th style="min-width:9rem">% inasistencia</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($centros as $c): $pct = (float) $c['porcentajeInasistencia']; ?>
                        <tr data-centro="<?= (int) $c['idCentro'] ?>">
                            <td><strong><?= e($c['codigoCentro']) ?></strong> <?= e($c['nombreCentro']) ?></td>
                            <td class="small"><?= e($c['nombreRegion']) ?></td>
                            <td class="text-end total"><?= (int) $c['totalCitas'] ?></td>
                            <td class="text-end"><?= (int) $c['citasNoAsistio'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="pct" style="min-width:3.2rem"><?= e(number_format($pct, 1, ',', '.')) ?> %</span>
                                    <div class="progress flex-grow-1" style="height:.5rem" role="progressbar" aria-label="Inasistencia" aria-valuenow="<?= e((string) $pct) ?>" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar bg-warning" style="width: <?= e((string) min(100, $pct)) ?>%"></div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr><th colspan="2">Red SaludUSM</th><th class="text-end"><?= (int) $totalCitas ?></th><th class="text-end"><?= (int) $totalNoAsistio ?></th>
                            <th><?= e(number_format($totalCitas ? round(100 * $totalNoAsistio / $totalCitas, 1) : 0, 1, ',', '.')) ?> %</th></tr>
                    </tfoot>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">
                % de inasistencia = citas No Asistio / total de citas del centro (todos los estados) &times; 100, con 1 decimal.
                Un centro sin citas muestra 0 %.
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">5 diagnosticos mas frecuentes de la red</div>
            <ol class="list-group list-group-numbered list-group-flush" id="top5">
                <?php foreach ($top5 as $d): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-start" data-codigo="<?= e($d['codigoDiagnostico']) ?>">
                        <div class="ms-2 me-auto">
                            <span class="badge text-bg-light border me-1"><?= e($d['codigoDiagnostico']) ?></span><?= e($d['descripcion']) ?>
                        </div>
                        <span class="badge text-bg-primary rounded-pill frecuencia"><?= (int) $d['vecesRegistrado'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <div class="card-footer bg-white small text-muted">
                Frecuencia = numero de atenciones en que aparece el diagnostico. Se consideran <?= (int) $resumenDiag['usados'] ?> diagnosticos
                distintos usados en <?= (int) $resumenDiag['atenciones'] ?> atenciones (catalogo: <?= (int) $resumenDiag['catalogo'] ?> codigos CIE-10).
                En caso de empate se ordena por codigo.
                <?php if ($empatadosFuera): ?>
                    <div class="mt-1 text-body" id="empates">Empatan con el 5.º lugar (<?= $quinto ?> veces) y quedan fuera por el desempate:
                        <?= e(implode(', ', array_map(fn($d) => $d['codigoDiagnostico'] . ' ' . $d['descripcion'], $empatadosFuera))) ?>.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php pie();
