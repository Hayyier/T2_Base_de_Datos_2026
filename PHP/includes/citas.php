<?php
/**
 * Logica comun de citas y agenda.
 *
 * Division de responsabilidades:
 *  - La REGLA DE HORARIO (bloques de 30 min, lunes a sabado 08:00-17:30,
 *    solo bloques futuros y hasta 90 dias) vive en la BD:
 *    CHECK ck_cita_bloque, trigger trg_cita_bi/bu y el procedimiento
 *    sp_bloques_disponibles. PHP NO genera bloques: siempre se los pide al
 *    procedimiento. Asi no hay dos definiciones del horario.
 *  - Las reglas de la cita (especialidad del medico, centro del medico,
 *    choques, estado inicial, transiciones) las garantizan los triggers y
 *    las restricciones UNIQUE/CHECK de la BD. PHP las comprueba ANTES para
 *    dar un mensaje claro y no mostrar opciones imposibles, pero la ultima
 *    palabra la tiene la BD (que ademas resuelve la concurrencia).
 */

// Catalogo EstadoCita: ids fijos
const ESTADO_RESERVADA  = 1;
const ESTADO_CONFIRMADA = 2;
const ESTADO_ATENDIDA   = 3;
const ESTADO_NO_ASISTIO = 4;
const ESTADO_CANCELADA  = 5;

/** Estados en que el paciente puede reprogramar o cancelar. */
const ESTADOS_MODIFICABLES = [ESTADO_RESERVADA, ESTADO_CONFIRMADA];

/** Clase de Bootstrap para la etiqueta de cada estado. */
const ESTADO_CLASE = [
    ESTADO_RESERVADA  => 'text-bg-primary',
    ESTADO_CONFIRMADA => 'text-bg-success',
    ESTADO_ATENDIDA   => 'text-bg-secondary',
    ESTADO_NO_ASISTIO => 'text-bg-warning',
    ESTADO_CANCELADA  => 'text-bg-danger',
];

/**
 * Solo para la INTERFAZ (limites del selector de fecha y texto de ayuda).
 * La regla real la aplica la BD; si se cambiara alli, aqui solo cambiaria
 * el texto mostrado, nunca se podria reservar un bloque invalido.
 */
const AGENDA_DIAS_MAX     = 90;
const AGENDA_DESCRIPCION  = 'bloques de 30 minutos, de lunes a sabado entre 08:00 y 17:30, con hasta 90 dias de anticipacion';

/**
 * Expresion SQL (sobre una fila de Cita o v_cita_detalle) que indica si el
 * paciente aun puede reprogramarla o cancelarla: Reservada/Confirmada y
 * fecha-hora futura. Se calcula con NOW() de la BD, igual que los triggers.
 */
const SQL_MODIFICABLE = '(idEstadoCita IN (1, 2) AND fechaHoraCita > NOW())';

// ---------------------------------------------------------------------
// Presentacion
// ---------------------------------------------------------------------

function badge_estado(int $idEstado, string $nombre): string
{
    $clase = ESTADO_CLASE[$idEstado] ?? 'text-bg-light';
    return '<span class="badge ' . $clase . '">' . e($nombre) . '</span>';
}

/** '2026-10-14 08:00:00' -> 'mie 14/10/2026'. */
function fecha_legible(string $fechaHora): string
{
    static $dias = ['dom', 'lun', 'mar', 'mie', 'jue', 'vie', 'sab'];
    $t = strtotime($fechaHora);
    return $dias[(int) date('w', $t)] . ' ' . date('d/m/Y', $t);
}

function hora_legible(string $fechaHora): string
{
    return date('H:i', strtotime($fechaHora));
}

// ---------------------------------------------------------------------
// Validacion de entradas
// ---------------------------------------------------------------------

/** Texto recibido por GET (recortado); nunca un arreglo. */
function get(string $clave): string
{
    $v = $_GET[$clave] ?? '';
    return is_string($v) ? trim($v) : '';
}

/** Entero positivo o 0 si el texto no lo es ("3abc", "-1", "" -> 0). */
function entero_positivo(string $v): int
{
    return preg_match('/^[1-9]\d{0,9}$/', $v) ? (int) $v : 0;
}

/** Fecha AAAA-MM-DD real ('2026-02-30' no lo es) o null. */
function fecha_valida(string $v): ?string
{
    $d = DateTime::createFromFormat('!Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

/** Fecha-hora 'AAAA-MM-DD HH:MM[:SS]' real, normalizada a 'AAAA-MM-DD HH:MM:SS', o null. */
function fecha_hora_valida(string $v): ?string
{
    foreach (['!Y-m-d H:i:s', '!Y-m-d H:i'] as $formato) {
        $d = DateTime::createFromFormat($formato, $v);
        if ($d && $d->format(substr($formato, 1)) === $v) {
            return $d->format('Y-m-d H:i:s');
        }
    }
    return null;
}

/** RUT con formato valido (sin consultar el DV) o ''. Evita enviar basura a la BD. */
function rut_parametro(string $v): string
{
    $r = normalizar_rut($v);
    return rut_formato_valido($r) ? $r : '';
}

// ---------------------------------------------------------------------
// Catalogos para el agendamiento
// ---------------------------------------------------------------------

function catalogo_especialidades(): array
{
    return q('SELECT idEspecialidad, nombreEspecialidad FROM Especialidad ORDER BY nombreEspecialidad')->fetchAll();
}

/**
 * Especialidades que tienen al menos un medico (opcionalmente, solo las de
 * un medico dado). Excluye al propio paciente como medico.
 */
function especialidades_agendables(string $rutPaciente, string $rutMedico = ''): array
{
    return q('SELECT e.idEspecialidad, e.nombreEspecialidad
                FROM Especialidad e
               WHERE EXISTS (SELECT 1 FROM Medico_Especialidad me
                              WHERE me.idEspecialidad = e.idEspecialidad
                                AND me.RUTMedico <> :pac
                                AND (:med1 = \'\' OR me.RUTMedico = :med2))
               ORDER BY e.nombreEspecialidad',
             ['pac' => $rutPaciente, 'med1' => $rutMedico, 'med2' => $rutMedico])->fetchAll();
}

/** Centros donde atiende algun medico de la especialidad (y del medico, si se indica). */
function centros_agendables(int $idEspecialidad, string $rutPaciente, string $rutMedico = ''): array
{
    return q('SELECT DISTINCT ce.idCentro, ce.nombreCentro, co.nombreComuna
                FROM Centro ce
                JOIN Comuna co ON co.idComuna = ce.idComuna
                JOIN Medico_Centro mc ON mc.idCentro = ce.idCentro
                JOIN Medico_Especialidad me ON me.RUTMedico = mc.RUTMedico
               WHERE me.idEspecialidad = :esp
                 AND mc.RUTMedico <> :pac
                 AND (:med1 = \'\' OR mc.RUTMedico = :med2)
               ORDER BY ce.nombreCentro',
             ['esp' => $idEspecialidad, 'pac' => $rutPaciente,
              'med1' => $rutMedico, 'med2' => $rutMedico])->fetchAll();
}

/** Medicos que poseen la especialidad Y atienden en el centro. */
function medicos_agendables(int $idEspecialidad, int $idCentro, string $rutPaciente): array
{
    return q('SELECT m.RUTMedico, p.nombreCompleto
                FROM Medico m
                JOIN Persona p ON p.RUT = m.RUTMedico
                JOIN Medico_Especialidad me ON me.RUTMedico = m.RUTMedico AND me.idEspecialidad = :esp
                JOIN Medico_Centro mc       ON mc.RUTMedico = m.RUTMedico AND mc.idCentro = :cen
               WHERE m.RUTMedico <> :pac
               ORDER BY p.nombreCompleto',
             ['esp' => $idEspecialidad, 'cen' => $idCentro, 'pac' => $rutPaciente])->fetchAll();
}

/**
 * Comprueba en el servidor la combinacion medico + especialidad + centro.
 * Devuelve null si es valida o el motivo exacto del rechazo.
 */
function validar_medico_especialidad_centro(string $rutMedico, int $idEspecialidad, int $idCentro, string $rutPaciente): ?string
{
    $f = q('SELECT
              EXISTS (SELECT 1 FROM Especialidad WHERE idEspecialidad = :e1)                          AS esp,
              EXISTS (SELECT 1 FROM Centro       WHERE idCentro = :c1)                                AS cen,
              EXISTS (SELECT 1 FROM Medico       WHERE RUTMedico = :m1)                               AS med,
              EXISTS (SELECT 1 FROM Medico_Especialidad WHERE RUTMedico = :m2 AND idEspecialidad = :e2) AS med_esp,
              EXISTS (SELECT 1 FROM Medico_Centro       WHERE RUTMedico = :m3 AND idCentro = :c2)       AS med_cen',
           ['e1' => $idEspecialidad, 'c1' => $idCentro, 'm1' => $rutMedico,
            'm2' => $rutMedico, 'e2' => $idEspecialidad, 'm3' => $rutMedico, 'c2' => $idCentro])->fetch();

    if (!(int) $f['esp']) { return 'La especialidad seleccionada no existe.'; }
    if (!(int) $f['cen']) { return 'El centro seleccionado no existe.'; }
    if (!(int) $f['med']) { return 'El medico seleccionado no existe.'; }
    if ($rutMedico === $rutPaciente) { return 'No puedes agendar una cita contigo mismo como medico.'; }
    if (!(int) $f['med_esp']) { return 'El medico seleccionado no posee la especialidad solicitada.'; }
    if (!(int) $f['med_cen']) { return 'El medico no atiende en el centro seleccionado.'; }
    return null;
}

// ---------------------------------------------------------------------
// Disponibilidad (procedimiento sp_bloques_disponibles)
// ---------------------------------------------------------------------

/**
 * Bloques reservables de un dia: ['2026-10-14 08:00:00' => '08:00', ...].
 *  - $rutMedico null  -> todos los bloques del horario de atencion aun reservables ese dia
 *  - $rutMedico       -> ademas, sin citas activas del medico
 *  - $rutPaciente     -> ademas, sin citas activas del paciente
 */
function bloques_disponibles(?string $rutMedico, string $fecha, ?string $rutPaciente): array
{
    $st = q('CALL sp_bloques_disponibles(?, ?, ?)', [$rutMedico, $fecha, $rutPaciente]);
    $bloques = [];
    foreach ($st->fetchAll() as $f) {
        $bloques[$f['fechaHora']] = $f['hora'];
    }
    $st->closeCursor();   // libera el result set extra que devuelve todo CALL
    return $bloques;
}

/**
 * Revalida un bloque elegido justo antes de guardar y explica el motivo real
 * si ya no se puede usar. Devuelve null si el bloque es reservable.
 * Se apoya solo en el procedimiento (misma fuente que la lista mostrada).
 */
function validar_bloque(string $rutMedico, string $rutPaciente, string $fechaHoraTexto): ?string
{
    $fechaHora = fecha_hora_valida($fechaHoraTexto);
    if ($fechaHora === null) {
        return 'La fecha u hora seleccionada no es valida.';
    }
    if (strtotime($fechaHora) <= time()) {
        return 'El horario seleccionado ya paso.';
    }
    $fecha = substr($fechaHora, 0, 10);

    $horario = bloques_disponibles(null, $fecha, null);
    if (!$horario) {
        return 'La fecha seleccionada no esta disponible (no hay atencion ese dia o esta fuera del plazo de '
             . AGENDA_DIAS_MAX . ' dias).';
    }
    if (!isset($horario[$fechaHora])) {
        return 'La cita no puede agendarse fuera del horario de atencion (' . AGENDA_DESCRIPCION . ').';
    }
    if (!isset(bloques_disponibles(null, $fecha, $rutPaciente)[$fechaHora])) {
        return 'Ya tienes una cita a esa fecha y hora.';
    }
    if (!isset(bloques_disponibles($rutMedico, $fecha, $rutPaciente)[$fechaHora])) {
        return 'El horario seleccionado ya no esta disponible.';
    }
    return null;
}

// ---------------------------------------------------------------------
// Citas del paciente
// ---------------------------------------------------------------------

/**
 * Una cita del paciente indicado, o null si no existe o NO le pertenece
 * (ambos casos se tratan igual: no se revela si la cita existe).
 * Incluye la columna calculada 'modificable'.
 */
function cita_del_paciente(int $idCita, string $rutPaciente): ?array
{
    if ($idCita <= 0) {
        return null;
    }
    $c = q('SELECT v.*, ' . SQL_MODIFICABLE . ' AS modificable
              FROM v_cita_detalle v
             WHERE v.idCita = ? AND v.RUTPaciente = ?', [$idCita, $rutPaciente])->fetch();
    return $c ?: null;
}

/** Motivo por el que una cita (de cita_del_paciente) no se puede modificar, o null. */
function motivo_no_modificable(array $cita, string $accion): ?string
{
    if ((int) $cita['modificable'] === 1) {
        return null;
    }
    if (!in_array((int) $cita['idEstadoCita'], ESTADOS_MODIFICABLES, true)) {
        return 'No se puede ' . $accion . ' una cita en estado ' . $cita['nombreEstadoCita']
             . ': solo las citas Reservadas o Confirmadas.';
    }
    return 'No se puede ' . $accion . ' una cita cuya fecha y hora ya paso.';
}

