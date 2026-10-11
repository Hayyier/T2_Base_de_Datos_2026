<?php
/**
 * Busqueda avanzada de citas. Comun a los tres roles.
 *
 * Filtros combinables (todos opcionales, se aplican a la vez con AND):
 *   rango de fechas (desde/hasta, inclusivos por dia), centro, region,
 *   especialidad, medico, estado y prevision del paciente. Todos salvo las
 *   fechas se eligen de los catalogos de la BD y se validan contra ellos.
 *
 * Restriccion por rol: se agrega SIEMPRE desde la sesion, nunca desde la URL:
 *   paciente -> v.RUTPaciente = rut_sesion()
 *   medico   -> v.RUTMedico   = rut_sesion()   (el filtro "medico" queda fijo)
 *   admin    -> todas
 * La pagina no lee ningun parametro de RUT de paciente; uno enviado a mano
 * simplemente se ignora.
 *
 * Consulta: una sola sentencia sobre la view v_cita_detalle con condiciones
 * opcionales armadas como lista de fragmentos FIJOS + parametros con nombre
 * (nunca se concatena un valor del usuario). Antes se cancelan las citas
 * vencidas. Resultados paginados de 50 en 50, mas recientes primero.
 */
require __DIR__ . '/../includes/bootstrap.php';

$u = requerir_login();
$rol = $u['rol'];
$rut = rut_sesion();

actualizar_citas_vencidas();

const POR_PAGINA = 50;

$catCentros  = catalogo_centros();
$catRegiones = catalogo_regiones();
$catEsp      = catalogo_especialidades();
$catMedicos  = $rol === 'medico' ? [] : catalogo_medicos();
$catEstados  = catalogo_estados();
$catPrev     = catalogo_previsiones();

$f = [
    'desde'        => get('desde'),
    'hasta'        => get('hasta'),
    'centro'       => get('centro'),
    'region'       => get('region'),
    'especialidad' => get('especialidad'),
    'medico'       => $rol === 'medico' ? '' : get('medico'),
    'estado'       => get('estado'),
    'prevision'    => get('prevision'),
];
$pagina  = max(1, entero_positivo(get('pagina')));
$errores = [];

/** Valida un id contra un catalogo; devuelve el valor o null (y registra el error). */
$validarId = function (string $valor, array $catalogo, string $columna, string $nombre) use (&$errores) {
    if ($valor === '') {
        return null;
    }
    $ids = array_map('strval', array_column($catalogo, $columna));
    if (!in_array($valor, $ids, true)) {
        $errores[] = 'El filtro "' . $nombre . '" no es valido.';
        return null;
    }
    return $valor;
};

/** Fecha de filtro valida (AAAA-MM-DD entre 2000 y 2099) o null (y registra el error). */
$validarFecha = function (string $valor, string $nombre) use (&$errores): ?string {
    if ($valor === '') {
        return null;
    }
    $d = fecha_valida($valor);
    if ($d === null || $d < '2000-01-01' || $d > '2099-12-31') {
        $errores[] = $nombre . ' no es una fecha valida (AAAA-MM-DD, entre 2000 y 2099).';
        return null;
    }
    return $d;
};
$desde = $validarFecha($f['desde'], 'Fecha desde');
$hasta = $validarFecha($f['hasta'], 'Fecha hasta');
if ($desde !== null && $hasta !== null && $desde > $hasta) {
    $errores[] = 'Rango de fechas invalido: la fecha desde es posterior a la fecha hasta.';
}
$centro       = $validarId($f['centro'], $catCentros, 'idCentro', 'Centro');
$region       = $validarId($f['region'], $catRegiones, 'idRegion', 'Region');
$especialidad = $validarId($f['especialidad'], $catEsp, 'idEspecialidad', 'Especialidad');
$medico       = $f['medico'] === '' ? null : $validarId(rut_parametro($f['medico']) ?: '-', $catMedicos, 'RUTMedico', 'Medico');
$estado       = $validarId($f['estado'], $catEstados, 'idEstadoCita', 'Estado');
$prevision    = $validarId($f['prevision'], $catPrev, 'idPrevision', 'Prevision');

// ---- condiciones: fragmentos fijos + parametros con nombre ----
$cond = [];
$par  = [];
if ($rol === 'paciente') {                      // restriccion por rol (desde la sesion)
    $cond[] = 'v.RUTPaciente = :rutSesion';
    $par['rutSesion'] = $rut;
} elseif ($rol === 'medico') {
    $cond[] = 'v.RUTMedico = :rutSesion';
    $par['rutSesion'] = $rut;
}
if ($desde !== null)        { $cond[] = 'v.fechaHoraCita >= :desde';      $par['desde'] = $desde . ' 00:00:00'; }
if ($hasta !== null)        { $cond[] = 'v.fechaHoraCita < :hasta';       $par['hasta'] = date('Y-m-d', strtotime($hasta . ' +1 day')) . ' 00:00:00'; }
if ($centro !== null)       { $cond[] = 'v.idCentro = :centro';           $par['centro'] = (int) $centro; }
if ($region !== null)       { $cond[] = 'v.idRegion = :region';           $par['region'] = (int) $region; }
if ($especialidad !== null) { $cond[] = 'v.idEspecialidad = :esp';        $par['esp'] = (int) $especialidad; }
if ($medico !== null)       { $cond[] = 'v.RUTMedico = :medico';          $par['medico'] = $medico; }
if ($estado !== null)       { $cond[] = 'v.idEstadoCita = :estado';       $par['estado'] = (int) $estado; }
if ($prevision !== null)    { $cond[] = 'v.idPrevision = :prevision';     $par['prevision'] = (int) $prevision; }
$where = $cond ? 'WHERE ' . implode(' AND ', $cond) : '';

$total = 0;
$citas = [];
if (!$errores) {
    $total = (int) q("SELECT COUNT(*) FROM v_cita_detalle v $where", $par)->fetchColumn();
    $paginas = max(1, (int) ceil($total / POR_PAGINA));
    $pagina = min($pagina, $paginas);
    $citas = q("SELECT v.idCita, v.fechaHoraCita, v.nombrePaciente, v.RUTPaciente, v.nombreMedico, v.nombreEspecialidad,
                       v.codigoCentro, v.nombreCentro, v.nombreRegion, v.nombrePrevision, v.idEstadoCita, v.nombreEstadoCita, v.idAtencion
                  FROM v_cita_detalle v $where
                 ORDER BY v.fechaHoraCita DESC, v.idCita DESC
                 LIMIT " . POR_PAGINA . ' OFFSET ' . (($pagina - 1) * POR_PAGINA), $par)->fetchAll();
}
$paginas = $paginas ?? 1;
$filtrosUrl = array_filter($f, fn($v) => $v !== '');

/** <select> de un catalogo con opcion "Todos". */
function select_filtro(string $nombre, string $etiqueta, array $catalogo, string $colId, callable $texto, string $actual): void
{
    echo '<label for="f-' . e($nombre) . '" class="form-label small mb-0">' . e($etiqueta) . '</label>';
    echo '<select class="form-select form-select-sm" id="f-' . e($nombre) . '" name="' . e($nombre) . '"><option value="">Todos</option>';
    foreach ($catalogo as $x) {
        $v = (string) $x[$colId];
        echo '<option value="' . e($v) . '"' . ($v === $actual ? ' selected' : '') . '>' . e($texto($x)) . '</option>';
    }
    echo '</select>';
}

encabezado('Busqueda avanzada');
?>
<h1 class="h3 mb-1">Busqueda avanzada de citas</h1>
<p class="text-muted mb-3">
    <?php if ($rol === 'paciente'): ?>Se buscan solo <strong>tus</strong> citas.
    <?php elseif ($rol === 'medico'): ?>Se buscan solo las citas de <strong>tu agenda</strong>.
    <?php else: ?>Se buscan <strong>todas</strong> las citas de la red.<?php endif; ?>
    Los filtros se combinan entre si.
</p>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="get" action="<?= e(url('buscar/citas.php')) ?>" class="row g-2 align-items-end" id="filtros">
            <div class="col-6 col-md-3 col-xl">
                <label for="f-desde" class="form-label small mb-0">Desde</label>
                <input type="date" class="form-control form-control-sm" id="f-desde" name="desde" value="<?= e($f['desde']) ?>">
            </div>
            <div class="col-6 col-md-3 col-xl">
                <label for="f-hasta" class="form-label small mb-0">Hasta</label>
                <input type="date" class="form-control form-control-sm" id="f-hasta" name="hasta" value="<?= e($f['hasta']) ?>">
            </div>
            <div class="col-6 col-md-3 col-xl"><?php select_filtro('centro', 'Centro', $catCentros, 'idCentro', fn($x) => $x['codigoCentro'] . ' ' . $x['nombreCentro'], $f['centro']); ?></div>
            <div class="col-6 col-md-3 col-xl"><?php select_filtro('region', 'Region', $catRegiones, 'idRegion', fn($x) => $x['nombreRegion'], $f['region']); ?></div>
            <div class="col-6 col-md-3 col-xl"><?php select_filtro('especialidad', 'Especialidad', $catEsp, 'idEspecialidad', fn($x) => $x['nombreEspecialidad'], $f['especialidad']); ?></div>
            <div class="col-6 col-md-3 col-xl">
                <?php if ($rol === 'medico'): ?>
                    <label class="form-label small mb-0" for="f-medico">Medico</label>
                    <input class="form-control form-control-sm" id="f-medico" value="<?= e($u['nombre']) ?>" disabled>
                <?php else: ?>
                    <?php select_filtro('medico', 'Medico', $catMedicos, 'RUTMedico', fn($x) => $x['nombreCompleto'], $f['medico']); ?>
                <?php endif; ?>
            </div>
            <div class="col-6 col-md-3 col-xl"><?php select_filtro('estado', 'Estado', $catEstados, 'idEstadoCita', fn($x) => $x['nombreEstadoCita'], $f['estado']); ?></div>
            <div class="col-6 col-md-3 col-xl"><?php select_filtro('prevision', 'Prevision', $catPrev, 'idPrevision', fn($x) => $x['nombrePrevision'], $f['prevision']); ?></div>
            <div class="col-12 d-flex gap-2 justify-content-end">
                <a class="btn btn-sm btn-link" href="<?= e(url('buscar/citas.php')) ?>">Limpiar</a>
                <button type="submit" class="btn btn-sm btn-primary">Buscar</button>
            </div>
        </form>
    </div>
</div>

<?php lista_errores($errores); ?>

<?php if (!$errores): ?>
<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold" id="total" data-total="<?= $total ?>"><?= $total ?> cita(s)</span>
        <?php if ($paginas > 1): ?><span class="small text-muted">Pagina <?= $pagina ?> de <?= $paginas ?></span><?php endif; ?>
    </div>
    <?php if (!$citas): ?>
        <div class="card-body text-muted">No hay citas que cumplan todos los filtros.</div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small" id="resultados">
            <thead class="table-light">
                <tr><th>Fecha</th><th>Hora</th><th>Paciente</th><th>Medico</th><th>Especialidad</th><th>Centro</th><th>Prevision</th><th>Estado</th></tr>
            </thead>
            <tbody>
            <?php foreach ($citas as $c): ?>
                <tr data-cita="<?= (int) $c['idCita'] ?>">
                    <td class="text-nowrap"><?= e(fecha_legible($c['fechaHoraCita'])) ?></td>
                    <td><?= e(hora_legible($c['fechaHoraCita'])) ?></td>
                    <td><?= e($c['nombrePaciente']) ?></td>
                    <td><?= e($c['nombreMedico']) ?></td>
                    <td><?= e($c['nombreEspecialidad']) ?></td>
                    <td><?= e($c['codigoCentro'] . ' ' . $c['nombreCentro']) ?> <span class="text-muted">(<?= e($c['nombreRegion']) ?>)</span></td>
                    <td><?= e($c['nombrePrevision']) ?></td>
                    <td class="text-nowrap">
                        <?= badge_estado((int) $c['idEstadoCita'], $c['nombreEstadoCita']) ?>
                        <?php if ($rol === 'paciente'): ?>
                            <a class="ms-1" href="<?= e(url('paciente/cita.php?id=' . (int) $c['idCita'])) ?>">ver</a>
                        <?php elseif ($rol === 'medico' && $c['idAtencion'] !== null): ?>
                            <a class="ms-1" href="<?= e(url('medico/ficha.php?id=' . (int) $c['idAtencion'])) ?>">ficha</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($paginas > 1): ?>
    <div class="card-footer bg-white">
        <nav aria-label="Paginas"><ul class="pagination pagination-sm mb-0 flex-wrap">
            <?php for ($i = 1; $i <= $paginas; $i++): ?>
                <li class="page-item<?= $i === $pagina ? ' active' : '' ?>">
                    <a class="page-link" href="<?= e(url('buscar/citas.php?' . http_build_query($filtrosUrl + ['pagina' => $i]))) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul></nav>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php pie();
