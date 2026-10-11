<?php
/**
 * Autenticacion y autorizacion.
 *
 * Modelo: Persona(RUT, nombreCompleto, email, passwordHash) con los
 * subtipos Paciente, Medico y Administrador. El ROL de una persona se deduce
 * de los subtipos en que aparece su RUT; una persona puede tener varios roles
 * (p. ej. medico y paciente) y elige con cual entrar.
 *
 * En $_SESSION se guarda solo lo minimo: rut, nombre, roles y rol activo.
 * Nunca la contrasena ni su hash.
 */

const ROLES = [
    'paciente' => 'Paciente',
    'medico'   => 'Medico',
    'admin'    => 'Administrador',
];

const INICIO_POR_ROL = [
    'paciente' => 'paciente/index.php',
    'medico'   => 'medico/index.php',
    'admin'    => 'admin/index.php',
];

/** Inicia la sesion con parametros seguros. */
function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');   // rechaza IDs de sesion inventados
    ini_set('session.use_only_cookies', '1');  // nunca el ID en la URL
    session_name('SALUDUSM_SID');
    session_set_cookie_params([
        'lifetime' => 0,                        // hasta cerrar el navegador
        'path'     => (base_url() === '' ? '/' : base_url() . '/'),
        'httponly' => true,                     // no accesible desde JavaScript
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/** Roles que tiene una persona segun los subtipos (orden: paciente, medico, admin). */
function roles_de(string $rut): array
{
    $fila = q('SELECT
                 EXISTS (SELECT 1 FROM Paciente      WHERE RUTPaciente      = :r1) AS paciente,
                 EXISTS (SELECT 1 FROM Medico        WHERE RUTMedico        = :r2) AS medico,
                 EXISTS (SELECT 1 FROM Administrador WHERE RUTAdministrador = :r3) AS admin',
              ['r1' => $rut, 'r2' => $rut, 'r3' => $rut])->fetch();
    return array_keys(array_filter($fila, static fn($v) => (int) $v === 1));
}

/**
 * Verifica RUT o e-mail + contrasena. Devuelve la fila de Persona o null.
 * El mismo mensaje se usa para "no existe" y "contrasena incorrecta" (no se
 * revela que usuarios existen).
 */
function autenticar(string $identificador, string $password): ?array
{
    if (str_contains($identificador, '@')) {
        $persona = q('SELECT RUT, nombreCompleto, passwordHash FROM Persona
                       WHERE email = ? AND passwordHash IS NOT NULL',
                     [normalizar_email($identificador)])->fetch();
    } else {
        // solo se consulta si tiene formato de RUT: la columna es ascii_bin y un
        // texto con tildes ("josé") producia un error de collation (HTTP 500)
        $rut = normalizar_rut($identificador);
        $persona = rut_formato_valido($rut)
            ? q('SELECT RUT, nombreCompleto, passwordHash FROM Persona
                  WHERE RUT = ? AND passwordHash IS NOT NULL', [$rut])->fetch()
            : false;
    }

    if (!$persona) {
        // se verifica igual contra un hash ficticio para no delatar por tiempo de respuesta
        password_verify($password, '$2y$10$VfVQfYYoFaIuW.PrmHhiPO0xh6k/ql9yPKoUy59uOaJWJhOdR1Iyy');
        return null;
    }
    if (!password_verify($password, $persona['passwordHash'])) {
        return null;
    }
    // si PHP cambia su algoritmo por defecto, el hash se actualiza en este login
    if (password_needs_rehash($persona['passwordHash'], PASSWORD_DEFAULT)) {
        q('UPDATE Persona SET passwordHash = ? WHERE RUT = ?',
          [password_hash($password, PASSWORD_DEFAULT), $persona['RUT']]);
    }
    unset($persona['passwordHash']);
    return $persona;
}

/** Crea la sesion autenticada. Regenera el ID para evitar fijacion de sesion. */
function abrir_sesion_usuario(array $persona, array $roles): void
{
    session_regenerate_id(true);
    $flash = $_SESSION['flash'] ?? [];
    $_SESSION = [
        'rut'    => $persona['RUT'],
        'nombre' => $persona['nombreCompleto'],
        'roles'  => $roles,
        'rol'    => count($roles) === 1 ? $roles[0] : null,  // con varios roles, se elige despues
        'csrf'   => bin2hex(random_bytes(32)),
        'flash'  => $flash,
    ];
    actualizar_citas_vencidas();
}

/**
 * Las citas vencidas pasan a Cancelada en cada inicio de sesion
 * (y al abrir las secciones de citas, en los prompts siguientes).
 */
function actualizar_citas_vencidas(): void
{
    try {
        q('CALL sp_cancelar_citas_vencidas(@n)');
    } catch (PDOException $e) {
        error_log('SaludUSM - sp_cancelar_citas_vencidas: ' . $e->getMessage());
    }
}

function cerrar_sesion(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $p['path'],
            'httponly' => true, 'samesite' => 'Lax', 'secure' => $p['secure'],
        ]);
    }
    session_destroy();
}

/** Usuario de la sesion o null. */
function usuario(): ?array
{
    if (empty($_SESSION['rut'])) {
        return null;
    }
    return [
        'rut'    => $_SESSION['rut'],
        'nombre' => $_SESSION['nombre'],
        'roles'  => $_SESSION['roles'],
        'rol'    => $_SESSION['rol'],
    ];
}

/** RUT del usuario autenticado: la UNICA fuente de identidad para "mis datos". */
function rut_sesion(): string
{
    return (string) ($_SESSION['rut'] ?? '');
}

function rol_activo(): ?string
{
    return $_SESSION['rol'] ?? null;
}

function inicio_de(?string $rol): string
{
    return INICIO_POR_ROL[$rol] ?? 'auth/login.php';
}

/**
 * Exige un usuario autenticado con un rol activo.
 * Ademas comprueba en la BD que la cuenta siga existiendo y conserve el rol
 * (una sesion de una cuenta eliminada deja de servir de inmediato).
 */
function requerir_login(): array
{
    $u = usuario();
    if ($u === null) {
        flash('warning', 'Debes iniciar sesion para acceder a esa pagina.');
        redirect('auth/login.php');
    }

    $vigente = q('SELECT 1 FROM Persona WHERE RUT = ? AND passwordHash IS NOT NULL', [$u['rut']])->fetchColumn();
    $roles = $vigente ? roles_de($u['rut']) : [];
    if (!$vigente || ($u['rol'] !== null && !in_array($u['rol'], $roles, true))) {
        cerrar_sesion();
        iniciar_sesion();
        flash('warning', 'Tu sesion ya no es valida. Inicia sesion nuevamente.');
        redirect('auth/login.php');
    }
    $_SESSION['roles'] = $roles;

    if ($u['rol'] === null) {
        redirect('auth/elegir_rol.php');
    }
    return usuario();
}

/** Exige usuario autenticado con uno de los roles indicados; si no, 403. */
function requerir_rol(string ...$permitidos): array
{
    $u = requerir_login();
    if (!in_array($u['rol'], $permitidos, true)) {
        pagina_error(403, 'Acceso denegado',
            'Tu rol actual (' . ROLES[$u['rol']] . ') no tiene permiso para ver esta pagina.');
    }
    return $u;
}
