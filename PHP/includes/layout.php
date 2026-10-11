<?php
/**
 * Layout comun: cabecera HTML, barra de navegacion segun el rol, mensajes
 * flash y pie. Las paginas llaman encabezado('Titulo') ... pie().
 */

const MENU_POR_ROL = [
    'paciente' => [
        ['Inicio',             'paciente/index.php'],
        ['Buscar medicos',     'buscar/medicos.php'],
        ['Mis citas',          'paciente/citas.php'],
        ['Agendar hora',       'paciente/agendar.php'],
        ['Mi historial',       'paciente/historial.php'],
        ['Busqueda avanzada',  'buscar/citas.php'],
    ],
    'medico' => [
        ['Inicio',             'medico/index.php'],
        ['Buscar medicos',     'buscar/medicos.php'],
        ['Mi agenda',          'medico/agenda.php'],
        ['Historial pacientes', 'medico/historial.php'],
        ['Busqueda avanzada',  'buscar/citas.php'],
    ],
    'admin' => [
        ['Inicio',             'admin/index.php'],
        ['Buscar medicos',     'buscar/medicos.php'],
        ['Panel de gestion',   'admin/panel.php'],
        ['Centros',            'admin/centros.php'],
        ['Medicos',            'admin/medicos.php'],
        ['Busqueda avanzada',  'buscar/citas.php'],
    ],
];

/**
 * $seccion: ruta del menu que se marca como activa cuando la pagina es
 * una subpagina (p. ej. reprogramar.php marca "Mis citas").
 */
function encabezado(string $titulo, ?string $seccion = null): void
{
    $u = usuario();
    $rol = $u['rol'] ?? null;
    $actual = $seccion ?? ltrim(substr(strtok($_SERVER['REQUEST_URI'] ?? '', '?'), strlen(base_url())), '/');
    $app = config('app')['nombre'];
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo) ?> | <?= e($app) ?></title>
    <link href="<?= e(url('assets/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-xl navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= e(url($u && $rol ? inicio_de($rol) : 'auth/login.php')) ?>"><?= e($app) ?></a>
        <?php if ($u && $rol): ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menu" aria-controls="menu" aria-expanded="false" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="menu">
            <ul class="navbar-nav me-auto">
                <?php foreach (MENU_POR_ROL[$rol] as [$texto, $ruta]): ?>
                    <?php if ($ruta === null): ?>
                        <li class="nav-item"><span class="nav-link disabled" title="Disponible proximamente"><?= e($texto) ?></span></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link<?= $actual === $ruta ? ' active' : '' ?>" href="<?= e(url($ruta)) ?>"><?= e($texto) ?></a></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <ul class="navbar-nav align-items-xl-center">
                <li class="nav-item"><span class="navbar-text me-2"><?= e($u['nombre']) ?> <span class="badge bg-light text-primary"><?= e(ROLES[$rol]) ?></span></span></li>
                <li class="nav-item"><a class="nav-link<?= $actual === 'perfil/index.php' ? ' active' : '' ?>" href="<?= e(url('perfil/index.php')) ?>">Mi perfil</a></li>
                <?php if (count($u['roles']) > 1): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('auth/elegir_rol.php')) ?>">Cambiar rol</a></li>
                <?php endif; ?>
                <li class="nav-item">
                    <form method="post" action="<?= e(url('auth/logout.php')) ?>" class="d-inline">
                        <?= csrf_campo() ?>
                        <button type="submit" class="btn btn-sm btn-outline-light ms-xl-2 text-nowrap">Cerrar sesion</button>
                    </form>
                </li>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</nav>
<main class="container pb-5">
    <?php foreach (flash_obtener() as $f): ?>
        <div class="alert alert-<?= e($f['tipo']) ?> alert-dismissible fade show" role="alert">
            <?= e($f['mensaje']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endforeach; ?>
    <?php
}

function pie(): void
{
    ?>
</main>
<footer class="text-center text-muted small pb-4">
    SaludUSM &middot; INF-239 Bases de Datos &middot; Tarea 2
</footer>
<script src="<?= e(url('assets/js/bootstrap.bundle.min.js')) ?>"></script>
</body>
</html>
    <?php
}

/** Lista de errores de validacion. */
function lista_errores(array $errores): void
{
    if (!$errores) {
        return;
    }
    echo '<div class="alert alert-danger" role="alert"><strong>Revisa los siguientes datos:</strong><ul class="mb-0">';
    foreach ($errores as $err) {
        echo '<li>' . e($err) . '</li>';
    }
    echo '</ul></div>';
}

/** Pagina de error completa (403, 404, 400, 500, 503) y termina la ejecucion. */
function pagina_error(int $codigo, string $titulo, string $mensaje): never
{
    if (!headers_sent()) {
        http_response_code($codigo);
    }
    $u = usuario();
    $volver = $u && $u['rol'] ? inicio_de($u['rol']) : 'auth/login.php';
    encabezado($titulo);
    ?>
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow-sm">
                <div class="card-body text-center p-5">
                    <div class="display-6 text-secondary mb-2"><?= $codigo ?></div>
                    <h1 class="h4"><?= e($titulo) ?></h1>
                    <p class="text-muted"><?= e($mensaje) ?></p>
                    <a class="btn btn-primary" href="<?= e(url($volver)) ?>">Volver al inicio</a>
                </div>
            </div>
        </div>
    </div>
    <?php
    pie();
    exit;
}
