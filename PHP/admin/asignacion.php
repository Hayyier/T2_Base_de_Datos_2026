<?php
/**
 * Especialidades y centros de un medico: agregar / quitar.
 * Solo administrador; solo POST con CSRF; PRG a la ficha del medico.
 *
 *   rut, tipo = especialidad|centro, accion = agregar|quitar, id
 *
 * Comprobaciones en PHP (mensajes claros) y garantia en la BD:
 *   - medico existente (404) y elemento existente en su catalogo     | FK
 *   - agregar: no repetido; especialidades < 3                        | PK, trg_me_bi
 *   - quitar: debe estar asignado; no puede ser el ultimo              | trg_me_bd / trg_mc_bd
 *   - quitar: sin citas futuras (Reservada/Confirmada) en esa          | trg_me_bd / trg_mc_bd
 *     especialidad o ese centro (se IMPIDE; no se
 *     reasignan ni cancelan citas automaticamente)
 * Cada operacion es UNA sentencia (atomica); los triggers repiten las reglas
 * dentro de la misma sentencia, por lo que no hace falta una transaccion.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('admin');
if (!es_post()) {
    header('Allow: POST');
    pagina_error(405, 'Operacion no permitida', 'Las asignaciones se gestionan desde la ficha del medico.');
}
csrf_verificar();

$m = medico_admin(rut_parametro(post('rut')));
if ($m === null) {
    pagina_error(404, 'Medico no encontrado', 'El medico no existe.');
}
$rut    = $m['RUTMedico'];
$volver = 'admin/medico.php?rut=' . rawurlencode($rut);
$tipo   = post('tipo');
$accion = post('accion');
$id     = entero_positivo(post('id'));

if (!in_array($tipo, ['especialidad', 'centro'], true) || !in_array($accion, ['agregar', 'quitar'], true)) {
    flash('danger', 'Operacion no valida.');
    redirect($volver);
}
$esEsp   = $tipo === 'especialidad';
$tabla   = $esEsp ? 'Medico_Especialidad' : 'Medico_Centro';      // nombres fijos del codigo
$columna = $esEsp ? 'idEspecialidad' : 'idCentro';
$nombre  = $esEsp
    ? q('SELECT nombreEspecialidad FROM Especialidad WHERE idEspecialidad = ?', [$id])->fetchColumn()
    : q("SELECT CONCAT(codigoCentro, ' ', nombreCentro) FROM Centro WHERE idCentro = ?", [$id])->fetchColumn();
$lista   = $esEsp ? especialidades_del_medico($rut) : centros_del_medico($rut);
$fila    = current(array_filter($lista, fn($x) => (int) $x[$columna] === $id)) ?: null;

$error = null;
if ($nombre === false) {
    $error = $esEsp ? 'La especialidad seleccionada no existe.' : 'El centro seleccionado no existe.';
} elseif ($accion === 'agregar') {
    if ($fila !== null) {
        $error = $esEsp ? 'El medico ya tiene esa especialidad.' : 'El medico ya atiende en ese centro.';
    } elseif ($esEsp && count($lista) >= ESPECIALIDADES_MAX) {
        $error = 'Un medico no puede tener mas de tres especialidades.';
    }
} else {
    if ($fila === null) {
        $error = $esEsp ? 'El medico no tiene esa especialidad.' : 'El medico no atiende en ese centro.';
    } elseif (count($lista) <= 1) {
        $error = $esEsp ? 'El medico debe tener al menos una especialidad.' : 'El medico debe atender en al menos un centro.';
    } elseif ((int) $fila['futuras'] > 0) {
        $error = ($esEsp ? 'No puede quitarse esta especialidad' : 'No puede quitarse este centro')
               . ' porque existen ' . (int) $fila['futuras'] . ' cita(s) futura(s) asociadas. '
               . 'El paciente debe cancelarlas o reprogramarlas, o deben pasar, antes de quitarla.';
    }
}

if ($error === null) {
    try {
        if ($accion === 'agregar') {
            q("INSERT INTO $tabla (RUTMedico, $columna) VALUES (?, ?)", [$rut, $id]);
            flash('success', ($esEsp ? 'Especialidad ' : 'Centro ') . $nombre . ' agregado(a).');
        } else {
            q("DELETE FROM $tabla WHERE RUTMedico = ? AND $columna = ?", [$rut, $id]);
            flash('success', ($esEsp ? 'Especialidad ' : 'Centro ') . $nombre . ' quitado(a).');
        }
    } catch (PDOException $e) {
        $error = ((int) ($e->errorInfo[1] ?? 0) === 1062)
            ? 'La asignacion ya existe.'
            : mensaje_error_bd($e);           // mensajes de trg_me_bi / trg_me_bd / trg_mc_bd
    }
}
if ($error !== null) {
    flash('danger', $error);
}
redirect($volver);
