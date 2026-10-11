<?php
/**
 * Historial clinico de MIS PACIENTES (el medico puede consultar las
 * atenciones anteriores de sus pacientes).
 *
 * "sus pacientes" = pacientes con al menos una cita (en cualquier estado)
 * con el medico de la sesion. Para ellos se muestran TODAS sus atenciones,
 * con cualquier medico (fecha, especialidad, medico, diagnosticos, recetas).
 * No hay tabla de permisos: el acceso sale de la relacion real
 * Medico - Cita - Paciente.
 *
 *  GET                 -> lista de mis pacientes (filtro por nombre o RUT)
 *  GET ?paciente=RUT   -> historial de ese paciente (404 si no es paciente mio)
 * Solo lectura: las atenciones propias enlazan a su ficha, donde se editan.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('medico');
$rut = rut_sesion();

$rutPaciente = get('paciente') === '' ? '' : rut_parametro(get('paciente'));

if (get('paciente') !== '') {
    if ($rutPaciente === '' || !es_paciente_de($rutPaciente, $rut)) {
        pagina_error(404, 'Paciente no encontrado', 'El paciente no existe o no tiene citas contigo.');
    }
    $p = q('SELECT p.RUT, p.nombreCompleto, pa.fechaNacimiento, pa.sexo, pa.telefono, pr.nombrePrevision,
                   TIMESTAMPDIFF(YEAR, pa.fechaNacimiento, CURDATE()) AS edad
              FROM Persona p
              JOIN Paciente pa  ON pa.RUTPaciente = p.RUT
              JOIN Prevision pr ON pr.idPrevision = pa.idPrevision
             WHERE p.RUT = ?', [$rutPaciente])->fetch();
    [$atenciones, $diagnosticos, $recetas] = historial_de_paciente($rutPaciente);

    encabezado('Historial de ' . $p['nombreCompleto'], 'medico/historial.php');
    ?>
    <a class="small" href="<?= e(url('medico/historial.php')) ?>">&larr; Mis pacientes</a>
    <h1 class="h3 mt-2 mb-1">Historial clinico</h1>
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-sm-2">Paciente</dt><dd class="col-sm-4" id="paciente-nombre"><?= e($p['nombreCompleto']) ?></dd>
                <dt class="col-sm-2">RUT</dt><dd class="col-sm-4"><?= e($p['RUT']) ?></dd>
                <dt class="col-sm-2">Nacimiento</dt><dd class="col-sm-4"><?= e(date('d-m-Y', strtotime($p['fechaNacimiento']))) ?> (<?= (int) $p['edad'] ?> anios)</dd>
                <dt class="col-sm-2">Sexo</dt><dd class="col-sm-4"><?= e(SEXOS[$p['sexo']] ?? $p['sexo']) ?></dd>
                <dt class="col-sm-2">Prevision</dt><dd class="col-sm-4"><?= e($p['nombrePrevision']) ?></dd>
                <dt class="col-sm-2">Telefono</dt><dd class="col-sm-4"><?= e($p['telefono'] ?? '—') ?></dd>
            </dl>
        </div>
    </div>
    <?php
    mostrar_historial($atenciones, $diagnosticos, $recetas,
        static fn(array $a): string => 'medico/ficha.php?id=' . (int) $a['idAtencion']);
    pie();
    exit;
}

// ---- lista de mis pacientes ----
$texto = mb_substr(normalizar_nombre(get('q')), 0, 100);
$patron = '%' . strtr($texto, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
$pacientes = q("SELECT p.RUT, p.nombreCompleto,
                       MAX(c.fechaHoraCita) AS ultimaCita,
                       COUNT(DISTINCT c.idCita) AS citasConmigo,
                       (SELECT COUNT(*) FROM Atencion a2 JOIN Cita c2 ON c2.idCita = a2.idCita
                         WHERE c2.RUTPaciente = p.RUT) AS atenciones
                  FROM Cita c
                  JOIN Persona p ON p.RUT = c.RUTPaciente
                 WHERE c.RUTMedico = :m
                   AND (:t1 = '' OR p.nombreCompleto LIKE :p1 ESCAPE '!' OR CONVERT(p.RUT USING utf8mb4) LIKE :p2 ESCAPE '!')   -- RUT es ascii_bin: se convierte para comparar con texto UTF-8
                 GROUP BY p.RUT, p.nombreCompleto
                 ORDER BY p.nombreCompleto",
                ['m' => $rut, 't1' => $texto, 'p1' => $patron, 'p2' => $patron])->fetchAll();

encabezado('Historial de pacientes');
?>
<h1 class="h3 mb-1">Historial de pacientes</h1>
<p class="text-muted">Pacientes que tienen o tuvieron citas contigo. Elige uno para ver su historial clinico completo.</p>

<form method="get" action="<?= e(url('medico/historial.php')) ?>" class="d-flex gap-2 mb-3" role="search">
    <input type="search" class="form-control" name="q" maxlength="100" value="<?= e($texto) ?>" placeholder="Nombre o RUT del paciente">
    <button type="submit" class="btn btn-primary">Filtrar</button>
</form>

<div class="card shadow-sm">
    <?php if (!$pacientes): ?>
        <div class="card-body text-muted">No hay pacientes que coincidan.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="pacientes">
            <thead class="table-light"><tr><th>Paciente</th><th>RUT</th><th>Ultima cita conmigo</th><th>Citas conmigo</th><th>Atenciones (total)</th></tr></thead>
            <tbody>
            <?php foreach ($pacientes as $p): ?>
                <tr data-paciente="<?= e($p['RUT']) ?>">
                    <td><a href="<?= e(url('medico/historial.php?paciente=' . rawurlencode($p['RUT']))) ?>"><?= e($p['nombreCompleto']) ?></a></td>
                    <td><?= e($p['RUT']) ?></td>
                    <td><?= e(fecha_legible($p['ultimaCita'])) ?></td>
                    <td><?= (int) $p['citasConmigo'] ?></td>
                    <td><?= (int) $p['atenciones'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php pie();
