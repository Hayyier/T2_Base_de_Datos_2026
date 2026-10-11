<?php
/**
 * CRUD de centros medicos - LISTAR. Solo administrador.
 * Una fila por centro con codigo, nombre, comuna, region (deducida de la
 * comuna, 3FN) y conteo de medicos y citas, para saber de antemano si se
 * puede eliminar.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('admin');

$centros = q('SELECT ce.idCentro, ce.codigoCentro, ce.nombreCentro, co.nombreComuna, re.nombreRegion,
                     (SELECT COUNT(*) FROM Medico_Centro mc WHERE mc.idCentro = ce.idCentro) AS medicos,
                     (SELECT COUNT(*) FROM Cita c WHERE c.idCentro = ce.idCentro) AS citas,
                     (SELECT COUNT(*) FROM Cita c WHERE c.idCentro = ce.idCentro AND ' . SQL_CITA_FUTURA . ') AS futuras
                FROM Centro ce
                JOIN Comuna co ON co.idComuna = ce.idComuna
                JOIN Region re ON re.idRegion = co.idRegion
               ORDER BY ce.codigoCentro')->fetchAll();

encabezado('Centros medicos');
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Centros medicos</h1>
    <a class="btn btn-primary" href="<?= e(url('admin/centro_form.php')) ?>">Nuevo centro</a>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="centros">
            <thead class="table-light">
                <tr><th>Codigo</th><th>Nombre</th><th>Comuna</th><th>Region</th><th class="text-end">Medicos</th><th class="text-end">Citas</th><th class="text-end">Futuras</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            <?php foreach ($centros as $c): ?>
                <tr data-centro="<?= (int) $c['idCentro'] ?>">
                    <td class="fw-semibold"><?= e($c['codigoCentro']) ?></td>
                    <td><a href="<?= e(url('admin/centro.php?id=' . (int) $c['idCentro'])) ?>"><?= e($c['nombreCentro']) ?></a></td>
                    <td><?= e($c['nombreComuna']) ?></td>
                    <td><?= e($c['nombreRegion']) ?></td>
                    <td class="text-end"><?= (int) $c['medicos'] ?></td>
                    <td class="text-end"><?= (int) $c['citas'] ?></td>
                    <td class="text-end"><?= (int) $c['futuras'] ?></td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('admin/centro_form.php?id=' . (int) $c['idCentro'])) ?>">Editar</a>
                        <a class="btn btn-sm btn-outline-danger" href="<?= e(url('admin/centro_eliminar.php?id=' . (int) $c['idCentro'])) ?>">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white small text-muted">
        Un centro solo se puede eliminar si no tiene citas (ni futuras ni historicas) ni medicos asignados.
    </div>
</div>
<?php pie();
