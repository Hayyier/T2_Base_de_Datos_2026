<?php
/**
 * Diagnosticos de una atencion propia: agregar, editar (reemplazar
 * el codigo) y quitar. Solo POST con CSRF; vuelve a la ficha (PRG).
 *
 * Identificacion: Atencion_Diagnostico no tiene id propio; su clave es
 * (idAtencion, codigoDiagnostico). Por eso cada operacion recibe la
 * atencion + el codigo, y se comprueba:
 *   1. la atencion es de una cita del medico de la sesion (si no, 404);
 *   2. editar/quitar: el codigo esta asignado A ESA atencion;
 *   3. agregar/editar: el codigo nuevo existe en el catalogo y no esta repetido.
 * Las sentencias repiten la pertenencia (JOIN Atencion/Cita ... RUTMedico = ?).
 * La BD garantiza: catalogo (fk_ad_diagnostico), sin repetidos (PK) y al
 * menos un diagnostico por atencion (trg_ad_bd).
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('medico');
if (!es_post()) {
    header('Allow: POST');
    pagina_error(405, 'Operacion no permitida', 'Los diagnosticos se gestionan desde la ficha de atencion.');
}
csrf_verificar();
$rut = rut_sesion();

$idAtencion = entero_positivo(post('id'));
if (atencion_propia($idAtencion, $rut) === null) {
    pagina_error(404, 'Atencion no encontrada', 'La atencion no existe o no pertenece a una de tus citas.');
}
$ficha  = 'medico/ficha.php?id=' . $idAtencion;
$accion = post('accion');
$codigo = post('codigo');
$nuevo  = post('nuevo');

$asignado = static fn(string $c): bool => $c !== '' && (bool) q(
    'SELECT 1 FROM Atencion_Diagnostico WHERE idAtencion = ? AND codigoDiagnostico = ?', [$idAtencion, $c])->fetchColumn();

$error = null;
if (!in_array($accion, ['agregar', 'editar', 'quitar'], true)) {
    $error = 'Operacion no valida.';
} elseif ($accion !== 'agregar' && !$asignado($codigo)) {
    $error = 'El diagnostico indicado no pertenece a esta atencion.';
} elseif ($accion !== 'quitar' && !existe_diagnostico($nuevo)) {
    $error = 'El diagnostico seleccionado no existe en el catalogo CIE-10.';
} elseif ($accion !== 'quitar' && $nuevo !== $codigo && $asignado($nuevo)) {
    $error = 'Ese diagnostico ya esta registrado en esta atencion.';
} elseif ($accion === 'editar' && $nuevo === $codigo) {
    flash('info', 'No hubo cambios en el diagnostico.');
    redirect($ficha);
}

if ($error === null) {
    try {
        if ($accion === 'agregar') {
            q('INSERT INTO Atencion_Diagnostico (idAtencion, codigoDiagnostico)
               SELECT a.idAtencion, ? FROM Atencion a JOIN Cita c ON c.idCita = a.idCita
                WHERE a.idAtencion = ? AND c.RUTMedico = ?', [$nuevo, $idAtencion, $rut]);
            flash('success', 'Diagnostico ' . $nuevo . ' agregado.');
        } elseif ($accion === 'editar') {
            q('UPDATE Atencion_Diagnostico ad
                 JOIN Atencion a ON a.idAtencion = ad.idAtencion
                 JOIN Cita c     ON c.idCita = a.idCita
                  SET ad.codigoDiagnostico = ?
                WHERE ad.idAtencion = ? AND ad.codigoDiagnostico = ? AND c.RUTMedico = ?',
              [$nuevo, $idAtencion, $codigo, $rut]);
            flash('success', 'Diagnostico ' . $codigo . ' reemplazado por ' . $nuevo . '.');
        } else {
            q('DELETE ad FROM Atencion_Diagnostico ad
                 JOIN Atencion a ON a.idAtencion = ad.idAtencion
                 JOIN Cita c     ON c.idCita = a.idCita
                WHERE ad.idAtencion = ? AND ad.codigoDiagnostico = ? AND c.RUTMedico = ?',
              [$idAtencion, $codigo, $rut]);
            flash('success', 'Diagnostico ' . $codigo . ' quitado.');
        }
    } catch (PDOException $e) {
        // trg_ad_bd ("al menos un diagnostico"), PK duplicada, FK de catalogo
        $error = ((int) ($e->errorInfo[1] ?? 0) === 1062)
            ? 'Ese diagnostico ya esta registrado en esta atencion.'
            : mensaje_error_bd($e);
    }
}
if ($error !== null) {
    flash('danger', $error);
}
redirect($ficha);
