<?php
/**
 * Conexion PDO unica y reutilizable.
 * - charset utf8mb4 (tildes, ñ)
 * - errores como excepciones
 * - prepared statements reales (EMULATE_PREPARES = false)
 * - zona horaria de la sesion SQL = la de PHP
 */

/** Error de conexion: se muestra al usuario un mensaje generico. */
class ErrorConexionBD extends RuntimeException {}

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $c = config('db');
    if ($c['socket'] !== '') {
        $dsn = "mysql:unix_socket={$c['socket']};dbname={$c['name']};charset=utf8mb4";
    } else {
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
    }

    try {
        $pdo = new PDO($dsn, $c['user'], $c['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // el detalle (host, usuario, motivo) solo va al log del servidor
        error_log('SaludUSM - error de conexion: ' . $e->getMessage());
        throw new ErrorConexionBD('No fue posible conectar con la base de datos.');
    }
    // La sesion SQL usa la misma zona horaria que PHP (America/Santiago):
    // NOW() y CURDATE() de triggers, procedimientos y consultas coinciden con
    // la hora que ve el usuario aunque el servidor MySQL este en otra zona.
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    return $pdo;
}

/** Ejecuta una consulta preparada y devuelve el PDOStatement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
