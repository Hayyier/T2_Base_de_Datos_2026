<?php
/**
 * Logica comun del modulo MEDICO: propiedad de citas y atenciones,
 * estados que puede asignar el medico, validacion de atencion, diagnosticos
 * y lineas de receta.
 *
 * Reparto de responsabilidades:
 *  - PROPIEDAD (que la cita/atencion/receta sea del medico de la sesion):
 *    solo PHP puede comprobarla, porque la BD no conoce la sesion. Cada
 *    consulta y cada UPDATE/DELETE lleva "RUTMedico = rut_sesion()" en su
 *    WHERE (o en un JOIN con Cita).
 *  - REGLAS DEL DOMINIO: las garantiza la BD y PHP traduce su error:
 *      trg_cita_bu      transiciones de estado
 *      trg_atencion_bi  atencion solo para cita Atendida
 *      uq_atencion_cita una atencion por cita
 *      trg_ad_bd        al menos un diagnostico por atencion
 *      fk_*             catalogo de diagnosticos, recetas no huerfanas
 *      uq_receta_medicamento / ck_receta_dias
 *    PHP repite algunas comprobaciones solo para mostrar las acciones
 *    posibles y dar mensajes claros antes de llegar a la BD.
 */

/** Estados que el medico puede asignar. Cancelar no es accion del medico. */
const ESTADOS_MEDICO = [
    ESTADO_CONFIRMADA => 'Confirmar',
    ESTADO_ATENDIDA   => 'Atendida',
    ESTADO_NO_ASISTIO => 'No asistio',
];

/** Limites de los campos (los de VARCHAR vienen del esquema; observaciones es TEXT). */
const MOTIVO_MAX          = 255;    // Atencion.motivo VARCHAR(255)
const OBSERVACIONES_MAX   = 4000;   // supuesto de implementacion
const MEDICAMENTO_MAX     = 100;    // Receta.medicamento VARCHAR(100)
const DOSIS_MAX           = 100;    // Receta.dosis VARCHAR(100)
const DIAS_TRATAMIENTO_MAX = 365;   // supuesto de implementacion; la BD exige > 0

// ---------------------------------------------------------------------
// Citas del medico
// ---------------------------------------------------------------------

/**
 * Una cita del medico indicado o null si no existe o es de otro medico
 * (se tratan igual). Incluye columnas calculadas con el reloj de la BD:
 *   llego   = la hora de la cita ya llego (fechaHoraCita <= NOW())
 *   vencida = es de un dia anterior a hoy
 */
function cita_del_medico(int $idCita, string $rutMedico): ?array
{
    if ($idCita <= 0) {
        return null;
    }
    $c = q('SELECT v.*, (v.fechaHoraCita <= NOW()) AS llego,
                   (DATE(v.fechaHoraCita) < CURDATE()) AS vencida
              FROM v_cita_detalle v
             WHERE v.idCita = ? AND v.RUTMedico = ?', [$idCita, $rutMedico])->fetch();
    return $c ?: null;
}

/**
 * Estados a los que el medico puede llevar la cita AHORA.
 * Solo decide que botones se muestran: la regla la vuelve a aplicar el
 * trigger trg_cita_bu en el UPDATE, y su mensaje es el que ve el medico.
 */
function estados_posibles(array $c): array
{
    $estado   = (int) $c['idEstadoCita'];
    $llego    = (int) $c['llego'] === 1;
    $vencida  = (int) $c['vencida'] === 1;
    $atencion = $c['idAtencion'] !== null;

    return match ($estado) {
        ESTADO_RESERVADA  => array_values(array_filter([
                                 !$vencida ? ESTADO_CONFIRMADA : null,
                                 $llego ? ESTADO_ATENDIDA : null,
                                 $llego ? ESTADO_NO_ASISTIO : null,
                             ])),
        ESTADO_CONFIRMADA => $llego ? [ESTADO_ATENDIDA, ESTADO_NO_ASISTIO] : [],
        ESTADO_ATENDIDA   => $atencion ? [] : [ESTADO_NO_ASISTIO],   // correccion
        ESTADO_NO_ASISTIO => [ESTADO_ATENDIDA],                      // correccion
        default           => [],                                     // Cancelada: final
    };
}

/** El paciente es "paciente del medico": tiene al menos una cita con el. */
function es_paciente_de(string $rutPaciente, string $rutMedico): bool
{
    return (bool) q('SELECT EXISTS (SELECT 1 FROM Cita WHERE RUTPaciente = ? AND RUTMedico = ?)',
                    [$rutPaciente, $rutMedico])->fetchColumn();
}

// ---------------------------------------------------------------------
// Atenciones
// ---------------------------------------------------------------------

/**
 * Ficha de una atencion (view v_historial_clinico) que el medico puede VER:
 * la registro el (es "propia") o es de uno de sus pacientes.
 * Devuelve null si no existe o no le corresponde. 'propia' = 1 si puede editarla.
 */
function atencion_visible(int $idAtencion, string $rutMedico): ?array
{
    if ($idAtencion <= 0) {
        return null;
    }
    $a = q('SELECT h.*, (h.RUTMedico = :m1) AS propia
              FROM v_historial_clinico h
             WHERE h.idAtencion = :id
               AND (h.RUTMedico = :m2
                    OR EXISTS (SELECT 1 FROM Cita c WHERE c.RUTPaciente = h.RUTPaciente AND c.RUTMedico = :m3))',
           ['m1' => $rutMedico, 'id' => $idAtencion, 'm2' => $rutMedico, 'm3' => $rutMedico])->fetch();
    return $a ?: null;
}

/**
 * Atencion que el medico puede MODIFICAR: solo las de sus propias citas.
 * Con $bloquear = true usa SELECT ... FOR UPDATE (dentro de una transaccion).
 */
function atencion_propia(int $idAtencion, string $rutMedico, bool $bloquear = false): ?array
{
    if ($idAtencion <= 0) {
        return null;
    }
    $a = q('SELECT a.idAtencion, a.idCita, a.motivo, a.observaciones
              FROM Atencion a
              JOIN Cita c ON c.idCita = a.idCita
             WHERE a.idAtencion = ? AND c.RUTMedico = ?' . ($bloquear ? ' FOR UPDATE' : ''),
           [$idAtencion, $rutMedico])->fetch();
    return $a ?: null;
}

/** Valida motivo (obligatorio) y observaciones (opcionales). Devuelve la lista de errores. */
function errores_atencion(string $motivo, string $observaciones): array
{
    $err = [];
    if ($motivo === '') {
        $err[] = 'El motivo de consulta es obligatorio.';
    } elseif (mb_strlen($motivo) > MOTIVO_MAX) {
        $err[] = 'El motivo de consulta admite hasta ' . MOTIVO_MAX . ' caracteres.';
    }
    if (mb_strlen($observaciones) > OBSERVACIONES_MAX) {
        $err[] = 'Las observaciones admiten hasta ' . OBSERVACIONES_MAX . ' caracteres.';
    }
    return $err;
}

/** Texto multilinea recibido por POST: normaliza saltos de linea y recorta. */
function post_texto(string $clave): string
{
    return trim(str_replace(["\r\n", "\r"], "\n", post($clave)));
}

// ---------------------------------------------------------------------
// Diagnosticos (catalogo CIE-10)
// ---------------------------------------------------------------------

function catalogo_diagnosticos(): array
{
    return q('SELECT codigoDiagnostico, descripcion FROM Diagnostico ORDER BY codigoDiagnostico')->fetchAll();
}

function existe_diagnostico(string $codigo): bool
{
    return $codigo !== '' && (bool) q('SELECT 1 FROM Diagnostico WHERE codigoDiagnostico = ?', [$codigo])->fetchColumn();
}

function diagnosticos_de(int $idAtencion): array
{
    return q('SELECT d.codigoDiagnostico, d.descripcion
                FROM Atencion_Diagnostico ad
                JOIN Diagnostico d ON d.codigoDiagnostico = ad.codigoDiagnostico
               WHERE ad.idAtencion = ?
               ORDER BY d.codigoDiagnostico', [$idAtencion])->fetchAll();
}

function recetas_de(int $idAtencion): array
{
    return q('SELECT idReceta, medicamento, dosis, diasTratamiento
                FROM Receta WHERE idAtencion = ? ORDER BY idReceta', [$idAtencion])->fetchAll();
}

// ---------------------------------------------------------------------
// Lineas de receta (medicamento en texto libre)
// ---------------------------------------------------------------------

/** Valida una linea de receta. Devuelve [errores, diasComoEntero]. */
function errores_receta(string $medicamento, string $dosis, string $dias): array
{
    $err = [];
    if ($medicamento === '') {
        $err[] = 'El medicamento es obligatorio.';
    } elseif (mb_strlen($medicamento) > MEDICAMENTO_MAX) {
        $err[] = 'El medicamento admite hasta ' . MEDICAMENTO_MAX . ' caracteres.';
    }
    if ($dosis === '') {
        $err[] = 'La dosis es obligatoria.';
    } elseif (mb_strlen($dosis) > DOSIS_MAX) {
        $err[] = 'La dosis admite hasta ' . DOSIS_MAX . ' caracteres.';
    }
    $n = preg_match('/^\d{1,4}$/', $dias) ? (int) $dias : -1;
    if ($n < 1 || $n > DIAS_TRATAMIENTO_MAX) {
        $err[] = 'Los dias de tratamiento deben ser un numero entero entre 1 y ' . DIAS_TRATAMIENTO_MAX . '.';
    }
    return [$err, $n];
}
