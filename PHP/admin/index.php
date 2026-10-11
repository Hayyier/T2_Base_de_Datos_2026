<?php
/**
 * Pagina principal del ADMINISTRADOR (solo rol admin).
 * Resumen de la red y accesos reales a: Centros, Medicos (y sus
 * especialidades y centros), Panel de gestion, Busqueda avanzada y Perfil.
 */
require __DIR__ . '/../includes/bootstrap.php';

$u = requerir_rol('admin');

$a = q('SELECT RUT, nombreCompleto, email FROM Persona WHERE RUT = ?', [rut_sesion()])->fetch();
$r = q('SELECT (SELECT COUNT(*) FROM Centro)   AS centros,
               (SELECT COUNT(*) FROM Medico)   AS medicos,
               (SELECT COUNT(*) FROM Paciente) AS pacientes,
               (SELECT COUNT(*) FROM Cita WHERE DATE(fechaHoraCita) = CURDATE()) AS citasHoy,
               (SELECT COUNT(*) FROM Cita WHERE idEstadoCita IN (1, 2) AND fechaHoraCita >= NOW()) AS futuras')->fetch();

$accesos = [
    ['Centros medicos',  'admin/centros.php',  'Crear, ver, editar y eliminar centros (codigo, nombre, comuna y region).'],
    ['Medicos',          'admin/medicos.php',  'Crear medicos con su cuenta, editar sus datos y gestionar sus especialidades y centros.'],
    ['Panel de gestion', 'admin/panel.php',    'Citas e inasistencia por centro y los 5 diagnosticos mas frecuentes.'],
    ['Busqueda avanzada', 'buscar/citas.php',  'Filtrar todas las citas de la red con filtros combinables.'],
    ['Buscar medicos',   'buscar/medicos.php', 'Por nombre o especialidad.'],
    ['Mi perfil',        'perfil/index.php',   'Ver y editar mis datos.'],
];

encabezado('Inicio');
?>
<h1 class="h3 mb-1">Hola, <?= e($a['nombreCompleto']) ?></h1>
<p class="text-muted mb-4">Administracion de la red SaludUSM (<?= e($a['email']) ?>).</p>

<div class="row g-3 mb-4" id="resumen">
    <?php foreach ([['Centros', $r['centros']], ['Medicos', $r['medicos']], ['Pacientes', $r['pacientes']],
                    ['Citas hoy', $r['citasHoy']], ['Citas futuras activas', $r['futuras']]] as [$t, $n]): ?>
        <div class="col-6 col-md">
            <div class="card shadow-sm text-center h-100"><div class="card-body py-3">
                <div class="fs-3 fw-semibold"><?= (int) $n ?></div><div class="small text-muted"><?= e($t) ?></div>
            </div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <?php foreach ($accesos as [$titulo, $ruta, $desc]): ?>
        <div class="col-md-6 col-xl-4">
            <a class="card shadow-sm h-100 text-decoration-none" href="<?= e(url($ruta)) ?>">
                <div class="card-body">
                    <div class="fw-semibold text-primary"><?= e($titulo) ?></div>
                    <small class="text-muted"><?= e($desc) ?></small>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php pie();
