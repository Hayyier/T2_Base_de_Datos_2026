<?php
/**
 * Punto de entrada comun: TODA pagina empieza con
 *     require __DIR__ . '/../includes/bootstrap.php';
 * Carga configuracion, conexion, helpers, sesion, layout y manejo de errores.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$GLOBALS['__config'] = require APP_ROOT . '/config/config.php';

/** Acceso a una seccion de la configuracion ('db' o 'app'). */
function config(string $seccion): array
{
    return $GLOBALS['__config'][$seccion];
}

require APP_ROOT . '/includes/db.php';
require APP_ROOT . '/includes/helpers.php';
require APP_ROOT . '/includes/auth.php';
require APP_ROOT . '/includes/layout.php';
require APP_ROOT . '/includes/citas.php';      // logica comun de citas y agenda
require APP_ROOT . '/includes/atenciones.php'; // citas y atenciones del medico
require APP_ROOT . '/includes/historial.php';  // historial clinico (paciente y medico)
require APP_ROOT . '/includes/admin.php';      // centros, medicos y catalogos del administrador

mb_internal_encoding('UTF-8');
date_default_timezone_set('America/Santiago');

// cabeceras basicas de seguridad
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// Errores no controlados: al usuario solo un mensaje generico; el detalle va al log
ini_set('display_errors', config('app')['debug'] ? '1' : '0');
set_exception_handler(static function (Throwable $e): void {
    error_log('SaludUSM - excepcion no controlada: ' . $e);
    if ($e instanceof ErrorConexionBD) {
        pagina_error(503, 'Servicio no disponible', $e->getMessage()
            . ' Verifica que el servidor MySQL este encendido y que la base salud_usm exista.');
    }
    $detalle = config('app')['debug'] ? ' [' . get_class($e) . ': ' . $e->getMessage() . ']' : '';
    pagina_error(500, 'Error interno', 'Ocurrio un error inesperado. Intentalo nuevamente.' . $detalle);
});

iniciar_sesion();
