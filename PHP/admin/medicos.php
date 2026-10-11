<?php
/**
 * CRUD de medicos - LISTAR. Solo administrador.
 * Una fila por medico (view v_directorio_medicos: especialidades y centros
 * agregados) + citas futuras. Filtro por nombre o RUT.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('admin');

$texto = mb_substr(normalizar_nombre(get('q')), 0, 100);
$patron = '%' . strtr($texto, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
$medicos = q("SELECT d.RUTMedico, d.nombreMedico, d.emailInstitucional, d.especialidades, d.centros,
                     (SELECT COUNT(*) FROM Cita c WHERE c.RUTMedico = d.RUTMedico AND " . SQL_CITA_FUTURA . ") AS futuras
                FROM v_directorio_medicos d
               WHERE (:t = '' OR d.nombreMedico LIKE :p1 ESCAPE '!'
                      OR CONVERT(d.RUTMedico USING utf8mb4) LIKE :p2 ESCAPE '!')
               ORDER BY d.nombreMedico",
             ['t' => $texto, 'p1' => $patron, 'p2' => $patron])->fetchAll();

encabezado('Medicos');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Medicos <span class="badge text-bg-light border fs-6"><?= count($medicos) ?></span></h1>
    <a class="btn btn-primary" href="<?= e(url('admin/medico_form.php')) ?>">Nuevo medico</a>
</div>

<form method="get" action="<?= e(url('admin/medicos.php')) ?>" class="d-flex gap-2 mb-3" role="search">
    <input type="search" class="form-control" name="q" maxlength="100" value="<?= e($texto) ?>" placeholder="Nombre o RUT">
    <button type="submit" class="btn btn-outline-primary">Filtrar</button>
</form>

<div class="card shadow-sm">
    <?php if (!$medicos): ?>
        <div class="card-body text-muted">No hay medicos que coincidan.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="medicos">
            <thead class="table-light">
                <tr><th>Nombre</th><th>RUT</th><th>E-mail institucional</th><th>Especialidades</th><th>Centros</th><th class="text-end">Citas futuras</th></tr>
            </thead>
            <tbody>
            <?php foreach ($medicos as $m): ?>
                <tr data-medico="<?= e($m['RUTMedico']) ?>">
                    <td><a href="<?= e(url('admin/medico.php?rut=' . rawurlencode($m['RUTMedico']))) ?>"><?= e($m['nombreMedico']) ?></a></td>
                    <td class="text-nowrap"><?= e($m['RUTMedico']) ?></td>
                    <td><?= e($m['emailInstitucional']) ?></td>
                    <td class="small"><?= e(str_replace(' | ', ', ', (string) $m['especialidades'])) ?></td>
                    <td class="small"><?= e(str_replace(' | ', ', ', (string) $m['centros'])) ?></td>
                    <td class="text-end"><?= (int) $m['futuras'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php pie();
