<?php
/**
 * Eliminar mi cuenta (SOLO paciente). Politica: NO se borra informacion clinica.
 *
 *  1. Si el paciente no tiene ninguna cita: se elimina por completo
 *     (Persona -> Paciente en cascada). No hay historial que conservar.
 *  2. Si tiene citas:
 *     a. sus citas FUTURAS Reservadas/Confirmadas pasan a Cancelada (liberan
 *        el bloque del medico);
 *     b. se eliminan sus credenciales (email y passwordHash = NULL): ya no
 *        puede iniciar sesion;
 *     c. se CONSERVAN sus citas pasadas, atenciones, diagnosticos y recetas
 *        (historial clinico de la red y de los medicos que lo atendieron).
 *  Todo en una transaccion. Si la persona tambien es medico o administrador,
 *  la cuenta no se elimina desde aqui (perderia sus otros accesos): debe
 *  gestionarlo la administracion.
 *  Se pide la contrasena para confirmar.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('paciente');
$rut = rut_sesion();

$resumen = q('SELECT
                SUM(fechaHoraCita > NOW() AND idEstadoCita IN (1, 2)) AS futuras,
                COUNT(*)                                               AS total,
                (SELECT COUNT(*) FROM Atencion a JOIN Cita c2 ON c2.idCita = a.idCita
                  WHERE c2.RUTPaciente = :r2)                          AS atenciones
              FROM Cita WHERE RUTPaciente = :r1', ['r1' => $rut, 'r2' => $rut])->fetch();
$otrosRoles = array_values(array_diff(roles_de($rut), ['paciente']));
$error = null;

if (es_post()) {
    csrf_verificar();
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $hash = q('SELECT passwordHash FROM Persona WHERE RUT = ?', [$rut])->fetchColumn();

    if ($otrosRoles) {
        $error = 'Tu cuenta tambien tiene rol de ' . implode(' y ', array_map(fn($r) => ROLES[$r], $otrosRoles))
               . '. Para eliminarla contacta a la administracion de SaludUSM.';
    } elseif (post('confirmo') !== 'si') {
        $error = 'Debes confirmar que entiendes las consecuencias.';
    } elseif (!$hash || !password_verify($password, $hash)) {
        $error = 'La contrasena no es correcta.';
    } else {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $tieneCitas = (int) q('SELECT COUNT(*) FROM Cita WHERE RUTPaciente = ?', [$rut])->fetchColumn() > 0;
            if (!$tieneCitas) {
                q('DELETE FROM Persona WHERE RUT = ?', [$rut]);              // cascada a Paciente
            } else {
                q('UPDATE Cita SET idEstadoCita = 5
                    WHERE RUTPaciente = ? AND idEstadoCita IN (1, 2) AND fechaHoraCita > NOW()', [$rut]);
                q('UPDATE Persona SET email = NULL, passwordHash = NULL WHERE RUT = ?', [$rut]);
            }
            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = mensaje_error_bd($e);
        }
        if ($error === null) {
            cerrar_sesion();
            iniciar_sesion();
            session_regenerate_id(true);
            flash('info', 'Tu cuenta fue eliminada. Tus citas futuras fueron canceladas.');
            redirect('auth/login.php');
        }
    }
}

encabezado('Eliminar cuenta');
?>
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card border-danger shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 text-danger">Eliminar mi cuenta</h1>
                <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

                <p>Al eliminar tu cuenta:</p>
                <ul>
                    <li>Tus <strong><?= (int) $resumen['futuras'] ?> citas futuras</strong> (Reservadas o Confirmadas) se cancelaran y esas horas quedaran disponibles.</li>
                    <li>Ya no podras iniciar sesion con tu RUT ni con tu e-mail.</li>
                    <li>Tu historial clinico (<?= (int) $resumen['atenciones'] ?> atenciones, con sus diagnosticos y recetas) y tus citas pasadas
                        <strong>se conservan</strong> en la red SaludUSM, porque forman parte del registro clinico.</li>
                    <li>Si mas adelante te registras de nuevo con el mismo RUT, tu cuenta se reactivara con tu historial.</li>
                </ul>

                <?php if ($otrosRoles): ?>
                    <div class="alert alert-warning mb-0">
                        Tu cuenta tambien tiene rol de <?= e(implode(' y ', array_map(fn($r) => ROLES[$r], $otrosRoles))) ?>.
                        Para eliminarla contacta a la administracion de SaludUSM.
                    </div>
                <?php else: ?>
                    <form method="post" class="mt-4">
                        <?= csrf_campo() ?>
                        <div class="mb-3">
                            <label for="password" class="form-label">Confirma con tu contrasena</label>
                            <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="si" id="confirmo" name="confirmo">
                            <label class="form-check-label" for="confirmo">Entiendo que mis citas futuras se cancelaran y que perdere el acceso.</label>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a class="btn btn-outline-secondary" href="<?= e(url('perfil/index.php')) ?>">Volver</a>
                            <button type="submit" class="btn btn-danger">Eliminar mi cuenta</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php pie();
