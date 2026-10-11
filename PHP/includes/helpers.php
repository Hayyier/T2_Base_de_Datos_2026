<?php
/**
 * Funciones comunes: escape HTML, URLs, mensajes flash, CSRF, validaciones
 * (RUT, e-mail, contrasena, nombre, telefono, fecha) y traduccion de errores
 * de la base de datos a mensajes comprensibles.
 */

// ---------------------------------------------------------------------
// Salida y navegacion
// ---------------------------------------------------------------------

/** Escapa texto para imprimirlo en HTML. */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL absoluta dentro de la aplicacion: url('auth/login.php'). */
function url(string $ruta = ''): string
{
    return base_url() . '/' . ltrim($ruta, '/');
}

function base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $conf = config('app')['base_url'];
    if ($conf !== null) {
        return $base = rtrim($conf, '/');
    }
    // se calcula comparando la carpeta PHP con el DocumentRoot del servidor
    $raiz = str_replace('\\', '/', (string) realpath(APP_ROOT));
    $doc  = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    if ($doc !== '' && stripos($raiz, $doc) === 0) {
        $base = rtrim(substr($raiz, strlen($doc)), '/');
    } else {
        $base = '';
    }
    $base = implode('/', array_map('rawurlencode', explode('/', $base)));
    return $base;
}

function redirect(string $ruta): never
{
    header('Location: ' . url($ruta));
    exit;
}

// ---------------------------------------------------------------------
// Mensajes flash (se muestran una vez en la siguiente pagina)
// ---------------------------------------------------------------------

function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flash_obtener(): array
{
    $m = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $m;
}

// ---------------------------------------------------------------------
// CSRF: todo formulario POST lleva un token de la sesion
// ---------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Detiene la peticion si el token no coincide. */
function csrf_verificar(): void
{
    $enviado = $_POST['csrf'] ?? '';
    if (!is_string($enviado) || !hash_equals(csrf_token(), $enviado)) {
        pagina_error(400, 'Solicitud no valida',
            'El formulario expiro o no es valido. Vuelve a cargar la pagina e intentalo de nuevo.');
    }
}

function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Valor de texto recibido por POST (recortado). */
function post(string $clave): string
{
    $v = $_POST[$clave] ?? '';
    return is_string($v) ? trim($v) : '';
}

// ---------------------------------------------------------------------
// Validaciones
// ---------------------------------------------------------------------

/** Quita puntos y espacios y deja la K en mayuscula: '12.345.678-k' -> '12345678-K'. */
function normalizar_rut(string $rut): string
{
    return strtoupper(str_replace(['.', ' '], '', trim($rut)));
}

/** Formato XXXXXXXX-X: 7 u 8 digitos, guion, digito verificador 0-9 o K. */
function rut_formato_valido(string $rut): bool
{
    return (bool) preg_match('/^\d{7,8}-[\dK]$/', $rut);
}

/**
 * RUT valido = formato + digito verificador. El digito verificador lo
 * calcula la function SQL fn_validar_rut, para no duplicar el algoritmo.
 */
function rut_valido(string $rut): bool
{
    if (!rut_formato_valido($rut)) {
        return false;
    }
    return (int) q('SELECT fn_validar_rut(?)', [$rut])->fetchColumn() === 1;
}

function normalizar_email(string $email): string
{
    return mb_strtolower(trim($email), 'UTF-8');
}

function email_valido(string $email): bool
{
    return strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Politica de contrasenas: minimo 8 caracteres, al menos una mayuscula,
 * una minuscula y un digito. Maximo 72 bytes (limite de bcrypt).
 * Devuelve la lista de requisitos que NO se cumplen.
 */
function password_errores(string $pw): array
{
    $err = [];
    if (mb_strlen($pw, 'UTF-8') < 8)          { $err[] = 'al menos 8 caracteres'; }
    if (!preg_match('/[A-ZÁÉÍÓÚÑ]/u', $pw))   { $err[] = 'una letra mayuscula'; }
    if (!preg_match('/[a-záéíóúñ]/u', $pw))   { $err[] = 'una letra minuscula'; }
    if (!preg_match('/\d/', $pw))             { $err[] = 'un digito'; }
    if (strlen($pw) > 72)                     { $err[] = 'como maximo 72 caracteres'; }
    return $err;
}

/** Nombre completo: 3 a 100 caracteres, letras, espacios, apostrofo, punto o guion. */
function normalizar_nombre(string $nombre): string
{
    return preg_replace('/\s+/u', ' ', trim($nombre));
}

function nombre_valido(string $nombre): bool
{
    return (bool) preg_match("/^[\p{L}][\p{L} '.-]{2,99}$/u", $nombre);
}

/** Telefono opcional: 8 a 11 digitos, con '+' inicial opcional (cabe en VARCHAR(12)). */
function normalizar_telefono(string $tel): string
{
    return str_replace([' ', '-', '(', ')'], '', trim($tel));
}

function telefono_valido(string $tel): bool
{
    return (bool) preg_match('/^\+?\d{8,11}$/', $tel);
}

/** Fecha AAAA-MM-DD real, no futura y posterior a 1900-01-01. */
function fecha_nacimiento_valida(string $fecha): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$d || $d->format('Y-m-d') !== $fecha) {
        return false;
    }
    return $fecha >= '1900-01-01' && $d <= new DateTime('today');
}

// ---------------------------------------------------------------------
// Errores de la base de datos -> mensajes para el usuario
// ---------------------------------------------------------------------

function mensaje_error_bd(PDOException $e): string
{
    $sqlstate = $e->errorInfo[0] ?? (string) $e->getCode();
    $codigo   = (int) ($e->errorInfo[1] ?? 0);
    $detalle  = (string) ($e->errorInfo[2] ?? '');

    // SIGNAL de los triggers/procedimientos: mensaje redactado por nosotros
    if ($sqlstate === '45000') {
        return $detalle;
    }
    // UNIQUE / PRIMARY KEY duplicada
    if ($codigo === 1062) {
        $mapa = [
            'uq_persona_email'      => 'El e-mail ingresado ya esta registrado.',
            'uq_medico_email'       => 'El e-mail institucional ya esta registrado.',
            'uq_centro_codigo'      => 'El codigo de centro ya esta registrado.',
            // choques detectados por los UNIQUE de Cita (p. ej. dos reservas simultaneas)
            'uq_cita_medico_hora'   => 'El horario seleccionado ya no esta disponible.',
            'uq_cita_paciente_hora' => 'Ya tienes una cita a esa fecha y hora.',
            // atenciones, diagnosticos y recetas
            'uq_atencion_cita'      => 'La cita ya tiene una atencion registrada.',
            'uq_receta_medicamento' => 'Ese medicamento ya esta en la receta de esta atencion.',
            'Atencion_Diagnostico'  => 'Ese diagnostico ya esta registrado en esta atencion.',
            'Persona.PRIMARY'       => 'El RUT ingresado ya esta registrado.',
            "'PRIMARY'"             => 'El registro ya existe.',
        ];
        foreach ($mapa as $clave => $msg) {
            if (str_contains($detalle, $clave)) {
                return $msg;
            }
        }
        return 'El dato ingresado ya existe en el sistema.';
    }
    // CHECK
    if ($codigo === 3819 || $codigo === 4025) {
        $mapa = [
            'ck_persona_rut'   => 'El RUT no tiene el formato XXXXXXXX-X.',
            'ck_persona_email' => 'El e-mail no es valido.',
            'ck_paciente_sexo' => 'El sexo debe ser M, F u Otro.',
            // [P6]
            'ck_cita_bloque'          => 'La cita no puede agendarse fuera del horario de atencion.',
            'ck_cita_paciente_medico' => 'No puedes agendar una cita contigo mismo como medico.',
            // [P7]
            'ck_receta_dias'          => 'Los dias de tratamiento deben ser mayores que 0.',
            'ck_medico_email'         => 'El e-mail institucional no es valido.',
        ];
        foreach ($mapa as $clave => $msg) {
            if (str_contains($detalle, $clave)) {
                return $msg;
            }
        }
        return 'Alguno de los datos no cumple las restricciones del sistema.';
    }
    if ($codigo === 1452) {
        return 'Alguno de los valores seleccionados no existe.';
    }
    if ($codigo === 1451) {
        return 'No se puede eliminar o modificar: hay informacion asociada.';
    }
    error_log('SaludUSM - error de BD: ' . $e->getMessage());
    return 'Ocurrio un error al guardar los datos. Intentalo nuevamente.';
}

// ---------------------------------------------------------------------
// Catalogos para formularios
// ---------------------------------------------------------------------

function catalogo_previsiones(): array
{
    return q('SELECT idPrevision, nombrePrevision FROM Prevision ORDER BY idPrevision')->fetchAll();
}

/** Comunas agrupadas por region: ['Region' => [idComuna => nombre, ...], ...] */
function catalogo_comunas(): array
{
    $filas = q('SELECT c.idComuna, c.nombreComuna, r.nombreRegion
                  FROM Comuna c JOIN Region r ON r.idRegion = c.idRegion
                 ORDER BY r.nombreRegion, c.nombreComuna')->fetchAll();
    $grupos = [];
    foreach ($filas as $f) {
        $grupos[$f['nombreRegion']][(int) $f['idComuna']] = $f['nombreComuna'];
    }
    return $grupos;
}

function existe_prevision(int $id): bool
{
    return (bool) q('SELECT 1 FROM Prevision WHERE idPrevision = ?', [$id])->fetchColumn();
}

function existe_comuna(int $id): bool
{
    return (bool) q('SELECT 1 FROM Comuna WHERE idComuna = ?', [$id])->fetchColumn();
}

const SEXOS = ['M' => 'Masculino', 'F' => 'Femenino', 'Otro' => 'Otro'];
