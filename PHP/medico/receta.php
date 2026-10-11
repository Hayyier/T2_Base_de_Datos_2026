<?php
/**
 * Lineas de receta de una atencion propia: agregar, editar y
 * eliminar. Solo POST con CSRF; vuelve a la ficha (PRG).
 *
 * Comprobaciones (en este orden):
 *   1. la atencion es de una cita del medico de la sesion (si no, 404);
 *   2. editar/eliminar: la linea pertenece A ESA atencion (si no, 404);
 *   3. medicamento y dosis obligatorios (texto libre), dias entero 1..365.
 * Las sentencias repiten la pertenencia con JOIN a Atencion y Cita.
 * La BD garantiza: receta siempre con atencion existente (fk_receta_atencion,
 * NOT NULL), dias > 0 (ck_receta_dias), sin medicamento repetido en la
 * misma atencion (uq_receta_medicamento).
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('medico');
if (!es_post()) {
    header('Allow: POST');
    pagina_error(405, 'Operacion no permitida', 'La receta se gestiona desde la ficha de atencion.');
}
csrf_verificar();
$rut = rut_sesion();

$idAtencion = entero_positivo(post('id'));
if (atencion_propia($idAtencion, $rut) === null) {
    pagina_error(404, 'Atencion no encontrada', 'La atencion no existe o no pertenece a una de tus citas.');
}
$ficha  = 'medico/ficha.php?id=' . $idAtencion;
$accion = post('accion');
$idReceta = entero_positivo(post('receta'));

if (!in_array($accion, ['agregar', 'editar', 'eliminar'], true)) {
    flash('danger', 'Operacion no valida.');
    redirect($ficha);
}
if ($accion !== 'agregar'
    && !q('SELECT 1 FROM Receta WHERE idReceta = ? AND idAtencion = ?', [$idReceta, $idAtencion])->fetchColumn()) {
    pagina_error(404, 'Linea no encontrada', 'La linea de receta no existe o no pertenece a esta atencion.');
}

$medicamento = normalizar_nombre(post('medicamento'));   // colapsa espacios
$dosis       = normalizar_nombre(post('dosis'));
$errores = [];
$dias = 0;
if ($accion !== 'eliminar') {
    [$errores, $dias] = errores_receta($medicamento, $dosis, post('dias'));
}

if (!$errores) {
    try {
        if ($accion === 'agregar') {
            q('INSERT INTO Receta (idAtencion, medicamento, dosis, diasTratamiento)
               SELECT a.idAtencion, ?, ?, ? FROM Atencion a JOIN Cita c ON c.idCita = a.idCita
                WHERE a.idAtencion = ? AND c.RUTMedico = ?', [$medicamento, $dosis, $dias, $idAtencion, $rut]);
            flash('success', 'Linea de receta agregada.');
        } elseif ($accion === 'editar') {
            q('UPDATE Receta r
                 JOIN Atencion a ON a.idAtencion = r.idAtencion
                 JOIN Cita c     ON c.idCita = a.idCita
                  SET r.medicamento = ?, r.dosis = ?, r.diasTratamiento = ?
                WHERE r.idReceta = ? AND r.idAtencion = ? AND c.RUTMedico = ?',
              [$medicamento, $dosis, $dias, $idReceta, $idAtencion, $rut]);
            flash('success', 'Linea de receta actualizada.');
        } else {
            q('DELETE r FROM Receta r
                 JOIN Atencion a ON a.idAtencion = r.idAtencion
                 JOIN Cita c     ON c.idCita = a.idCita
                WHERE r.idReceta = ? AND r.idAtencion = ? AND c.RUTMedico = ?',
              [$idReceta, $idAtencion, $rut]);
            flash('success', 'Linea de receta eliminada.');
        }
    } catch (PDOException $e) {
        $errores[] = mensaje_error_bd($e);     // uq_receta_medicamento, ck_receta_dias
    }
}
if ($errores) {
    flash('danger', implode(' ', $errores));
}
redirect($ficha);
