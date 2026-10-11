<?php
/**
 * Configuracion central de SaludUSM (UNICO lugar con datos de conexion).
 *
 * Valores por defecto: instalacion estandar de XAMPP (usuario root sin
 * contrasena, MySQL/MariaDB en 127.0.0.1:3306). Si su instalacion es distinta,
 * edite SOLO este archivo o defina las variables de entorno indicadas.
 */

$env = static function (string $nombre, ?string $defecto): ?string {
    $valor = getenv($nombre);
    return $valor === false ? $defecto : $valor;
};

return [
    'db' => [
        'host'     => $env('SALUDUSM_DB_HOST', '127.0.0.1'),
        'port'     => $env('SALUDUSM_DB_PORT', '3306'),
        'name'     => $env('SALUDUSM_DB_NAME', 'salud_usm'),
        'user'     => $env('SALUDUSM_DB_USER', 'root'),
        'password' => $env('SALUDUSM_DB_PASS', ''),
        // opcional: socket Unix (Linux/macOS). Si se define, se ignoran host y puerto.
        'socket'   => $env('SALUDUSM_DB_SOCKET', ''),
    ],
    'app' => [
        'nombre'   => 'SaludUSM',
        // URL base de la carpeta PHP ('/T2-ROL1-ROL2/PHP'). null = se calcula sola.
        'base_url' => $env('SALUDUSM_BASE_URL', null),
        // true solo en desarrollo: muestra el detalle tecnico de los errores
        'debug'    => $env('SALUDUSM_DEBUG', '0') === '1',
    ],
];
