<?php
/**
 * Logica comun del modulo ADMINISTRADOR: validacion de centros y
 * medicos, lecturas con conteo de dependencias y catalogos.
 *
 * Reparto de responsabilidades:
 *  - PHP valida formato, existencia y unicidad para dar mensajes claros
 *    ANTES de llegar a la BD, y muestra solo las acciones posibles.
 *  - La BD garantiza las reglas aunque el cambio venga de otro lado:
 *      uq_centro_codigo, uq_persona_email, uq_medico_email, PK de Persona
 *      trg_me_bi   maximo 3 especialidades
 *      trg_me_bd   minimo 1 especialidad y no quitar con citas futuras
 *      trg_mc_bd   minimo 1 centro y no quitar con citas futuras
 *      fk_cita_centro / fk_mc_centro RESTRICT   centro con citas o medicos
 *      fk_cita_medico RESTRICT                  medico con citas
 *      sp_crear_medico   alta atomica de Persona + Medico + asignaciones
 */

const ESPECIALIDADES_MAX = 3;      // para trg_me_bi y sp_crear_medico

/** Citas "futuras": Reservadas o Confirmadas desde ahora (igual que los triggers). */
const SQL_CITA_FUTURA = 'c.idEstadoCita IN (1, 2) AND c.fechaHoraCita >= NOW()';

// ---------------------------------------------------------------------
// Catalogos
// ---------------------------------------------------------------------

function catalogo_centros(): array
{
    return q('SELECT ce.idCentro, ce.codigoCentro, ce.nombreCentro, co.nombreComuna, re.idRegion, re.nombreRegion
                FROM Centro ce
                JOIN Comuna co ON co.idComuna = ce.idComuna
                JOIN Region re ON re.idRegion = co.idRegion
               ORDER BY ce.codigoCentro')->fetchAll();
}

function catalogo_regiones(): array
{
    return q('SELECT idRegion, nombreRegion FROM Region ORDER BY idRegion')->fetchAll();
}

function catalogo_estados(): array
{
    return q('SELECT idEstadoCita, nombreEstadoCita FROM EstadoCita ORDER BY idEstadoCita')->fetchAll();
}

function catalogo_medicos(): array
{
    return q('SELECT m.RUTMedico, p.nombreCompleto
                FROM Medico m JOIN Persona p ON p.RUT = m.RUTMedico
               ORDER BY p.nombreCompleto')->fetchAll();
}

// ---------------------------------------------------------------------
// Centros
// ---------------------------------------------------------------------

/** Codigo de centro: se guarda en mayusculas, sin espacios. */
function normalizar_codigo_centro(string $codigo): string
{
    return strtoupper(preg_replace('/\s+/', '', $codigo));
}

/**
 * Valida los datos de un centro. $idActual = centro que se edita (0 al crear),
 * para que la unicidad del codigo no choque consigo mismo.
 */
function errores_centro(string $codigo, string $nombre, int $idComuna, int $idActual = 0): array
{
    $err = [];
    if ($codigo === '') {
        $err[] = 'El codigo del centro es obligatorio.';
    } elseif (!preg_match('/^[A-Z0-9-]{2,10}$/', $codigo)) {
        $err[] = 'El codigo debe tener entre 2 y 10 caracteres: letras, digitos o guion (ej.: CM10).';
    } elseif (q('SELECT 1 FROM Centro WHERE codigoCentro = ? AND idCentro <> ?', [$codigo, $idActual])->fetchColumn()) {
        $err[] = 'El codigo de centro ' . $codigo . ' ya esta registrado.';
    }
    if ($nombre === '') {
        $err[] = 'El nombre del centro es obligatorio.';
    } elseif (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) {
        $err[] = 'El nombre del centro debe tener entre 3 y 100 caracteres.';
    }
    if (!$idComuna || !existe_comuna($idComuna)) {
        $err[] = 'Debes seleccionar una comuna valida (la region se deduce de la comuna).';
    }
    return $err;
}

/** Centro con su ubicacion y el conteo de sus dependencias, o null. */
function centro_con_dependencias(int $idCentro): ?array
{
    if ($idCentro <= 0) {
        return null;
    }
    $c = q('SELECT ce.idCentro, ce.codigoCentro, ce.nombreCentro, ce.idComuna, co.nombreComuna, re.nombreRegion,
                   (SELECT COUNT(*) FROM Medico_Centro mc WHERE mc.idCentro = ce.idCentro) AS medicos,
                   (SELECT COUNT(*) FROM Cita c WHERE c.idCentro = ce.idCentro) AS citas,
                   (SELECT COUNT(*) FROM Cita c WHERE c.idCentro = ce.idCentro AND ' . SQL_CITA_FUTURA . ') AS futuras
              FROM Centro ce
              JOIN Comuna co ON co.idComuna = ce.idComuna
              JOIN Region re ON re.idRegion = co.idRegion
             WHERE ce.idCentro = ?', [$idCentro])->fetch();
    return $c ?: null;
}

/** Motivo por el que un centro no se puede eliminar, o null si se puede. */
function motivo_no_eliminar_centro(array $c): ?string
{
    $partes = [];
    if ((int) $c['futuras'] > 0) {
        $partes[] = (int) $c['futuras'] . ' cita(s) futura(s)';
    }
    if ((int) $c['citas'] > (int) $c['futuras']) {
        $partes[] = ((int) $c['citas'] - (int) $c['futuras']) . ' cita(s) en su historial';
    }
    if ((int) $c['medicos'] > 0) {
        $partes[] = (int) $c['medicos'] . ' medico(s) asignado(s)';
    }
    if (!$partes) {
        return null;
    }
    return 'El centro no puede eliminarse porque tiene ' . implode(', ', $partes) . '. '
         . 'Las citas se conservan como registro clinico; los medicos deben quitarse antes desde su ficha.';
}

// ---------------------------------------------------------------------
// Medicos
// ---------------------------------------------------------------------

/** Ficha administrativa de un medico (datos, cuenta, roles y conteos), o null. */
function medico_admin(string $rut): ?array
{
    if ($rut === '') {
        return null;
    }
    $m = q('SELECT m.RUTMedico, p.nombreCompleto, p.email AS emailAcceso, (p.passwordHash IS NOT NULL) AS tieneCuenta,
                   m.emailInstitucional,
                   EXISTS (SELECT 1 FROM Paciente WHERE RUTPaciente = m.RUTMedico)            AS esPaciente,
                   EXISTS (SELECT 1 FROM Administrador WHERE RUTAdministrador = m.RUTMedico) AS esAdmin,
                   (SELECT COUNT(*) FROM Cita c WHERE c.RUTMedico = m.RUTMedico)              AS citas,
                   (SELECT COUNT(*) FROM Cita c WHERE c.RUTMedico = m.RUTMedico AND ' . SQL_CITA_FUTURA . ') AS futuras
              FROM Medico m JOIN Persona p ON p.RUT = m.RUTMedico
             WHERE m.RUTMedico = ?', [$rut])->fetch();
    return $m ?: null;
}

/** Especialidades del medico con sus citas futuras en cada una. */
function especialidades_del_medico(string $rut): array
{
    return q('SELECT e.idEspecialidad, e.nombreEspecialidad,
                     (SELECT COUNT(*) FROM Cita c WHERE c.RUTMedico = me.RUTMedico
                         AND c.idEspecialidad = me.idEspecialidad AND ' . SQL_CITA_FUTURA . ') AS futuras
                FROM Medico_Especialidad me
                JOIN Especialidad e ON e.idEspecialidad = me.idEspecialidad
               WHERE me.RUTMedico = ?
               ORDER BY e.nombreEspecialidad', [$rut])->fetchAll();
}

/** Centros del medico con sus citas futuras en cada uno. */
function centros_del_medico(string $rut): array
{
    return q('SELECT ce.idCentro, ce.codigoCentro, ce.nombreCentro, co.nombreComuna,
                     (SELECT COUNT(*) FROM Cita c WHERE c.RUTMedico = mc.RUTMedico
                         AND c.idCentro = mc.idCentro AND ' . SQL_CITA_FUTURA . ') AS futuras
                FROM Medico_Centro mc
                JOIN Centro ce ON ce.idCentro = mc.idCentro
                JOIN Comuna co ON co.idComuna = ce.idComuna
               WHERE mc.RUTMedico = ?
               ORDER BY ce.codigoCentro', [$rut])->fetchAll();
}

/** Lista de enteros positivos distintos recibida como arreglo por POST (checkboxes). */
function post_ids(string $clave): array
{
    $v = $_POST[$clave] ?? [];
    if (!is_array($v)) {
        return [];
    }
    $ids = [];
    foreach ($v as $x) {
        $n = is_string($x) ? entero_positivo($x) : 0;
        if ($n > 0) {
            $ids[$n] = $n;
        }
    }
    return array_values($ids);
}

/** Cuantos de los ids existen en la tabla/columna indicada (nombres fijos del codigo, no del usuario). */
function contar_existentes(string $tabla, string $columna, array $ids): int
{
    if (!$ids) {
        return 0;
    }
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    return (int) q("SELECT COUNT(*) FROM $tabla WHERE $columna IN ($marcas)", $ids)->fetchColumn();
}
