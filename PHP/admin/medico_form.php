<?php
/**
 * CRUD de medicos - CREAR (sin ?rut) y EDITAR (?rut=...). Solo administrador.
 *
 * CREAR:
 *   RUT, nombre, e-mail institucional (tambien es el e-mail de acceso),
 *   contrasena inicial, 1 a 3 especialidades y al menos 1 centro.
 *   Validacion en PHP (formato y DV del RUT con fn_validar_rut, unicidad,
 *   politica de contrasena, catalogos, limites) y luego UNA llamada a
 *   sp_crear_medico, que en una transaccion crea Persona + Medico +
 *   asignaciones y deshace todo si algo falla (los triggers vuelven a
 *   validar el maximo de 3 y las FK los catalogos).
 *   Si el RUT ya existe como persona (p. ej. un paciente), se reutiliza: la
 *   persona conserva su nombre, e-mail y contrasena de acceso.
 *
 * EDITAR: nombre, e-mail institucional y, opcionalmente, nueva contrasena.
 *   El RUT no se modifica (FK ON UPDATE RESTRICT). Las especialidades y
 *   centros se gestionan en la ficha (admin/medico.php). Transaccion porque
 *   toca Persona y Medico.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('admin');
if (es_post()) {
    csrf_verificar();
}

$rutEditar = rut_parametro(es_post() ? post('rut_editar') : get('rut'));
$editar = (es_post() ? post('rut_editar') : get('rut')) !== '';
$m = $editar ? medico_admin($rutEditar) : null;
if ($editar && $m === null) {
    pagina_error(404, 'Medico no encontrado', 'El medico no existe.');
}

$rut    = $m['RUTMedico'] ?? '';
$nombre = $m['nombreCompleto'] ?? '';
$email  = $m['emailInstitucional'] ?? '';
$esps   = [];
$cens   = [];
$errores = [];

if (es_post()) {
    $nombre = normalizar_nombre(post('nombre'));
    $email  = normalizar_email(post('email'));
    $pw     = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $pw2    = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';

    if (!nombre_valido($nombre)) {
        $errores[] = 'El nombre es obligatorio (3 a 100 caracteres: letras, espacios, apostrofo, punto o guion).';
    }
    if (!email_valido($email)) {
        $errores[] = 'El e-mail institucional no tiene un formato valido.';
    } elseif (q('SELECT 1 FROM Medico WHERE emailInstitucional = ? AND RUTMedico <> ?', [$email, $rut])->fetchColumn()) {
        $errores[] = 'El e-mail ya esta registrado como e-mail institucional de otro medico.';
    }

    if ($editar) {
        // ---------------- editar ----------------
        if ($pw !== '' || $pw2 !== '') {
            if (!(int) $m['tieneCuenta']) {
                $errores[] = 'La persona no tiene cuenta de acceso; no se puede cambiar su contrasena.';
            } elseif ($faltan = password_errores($pw)) {
                $errores[] = 'La nueva contrasena debe tener ' . implode(', ', $faltan) . '.';
            } elseif ($pw !== $pw2) {
                $errores[] = 'Las contrasenas no coinciden.';
            }
        }
        if (!$errores) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                q('UPDATE Persona SET nombreCompleto = ? WHERE RUT = ?', [$nombre, $rut]);
                q('UPDATE Medico SET emailInstitucional = ? WHERE RUTMedico = ?', [$email, $rut]);
                if ($pw !== '') {
                    q('UPDATE Persona SET passwordHash = ? WHERE RUT = ? AND passwordHash IS NOT NULL',
                      [password_hash($pw, PASSWORD_DEFAULT), $rut]);
                }
                $pdo->commit();
                flash('success', 'Datos del medico actualizados' . ($pw !== '' ? ' (contrasena restablecida).' : '.'));
                redirect('admin/medico.php?rut=' . rawurlencode($rut));
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errores[] = mensaje_error_bd($e);
            }
        }
    } else {
        // ---------------- crear ----------------
        $rut  = normalizar_rut(post('rut'));
        $esps = post_ids('especialidades');
        $cens = post_ids('centros');

        $persona = rut_formato_valido($rut)
            ? q('SELECT RUT, nombreCompleto, email, (passwordHash IS NOT NULL) AS tieneCuenta FROM Persona WHERE RUT = ?', [$rut])->fetch()
            : false;
        if (!rut_valido($rut)) {
            $errores[] = 'El RUT no es valido: use el formato XXXXXXXX-X con digito verificador correcto.';
        } elseif (q('SELECT 1 FROM Medico WHERE RUTMedico = ?', [$rut])->fetchColumn()) {
            $errores[] = 'El RUT ya esta registrado como medico.';
        }
        $necesitaCuenta = !$persona || !(int) $persona['tieneCuenta'];
        if ($necesitaCuenta) {
            if (email_valido($email) && q('SELECT 1 FROM Persona WHERE email = ?', [$email])->fetchColumn()) {
                $errores[] = 'El e-mail ya esta registrado como e-mail de acceso de otra persona.';
            }
            if ($faltan = password_errores($pw)) {
                $errores[] = 'La contrasena inicial debe tener ' . implode(', ', $faltan) . '.';
            } elseif ($pw !== $pw2) {
                $errores[] = 'Las contrasenas no coinciden.';
            }
        }
        if (count($esps) < 1) {
            $errores[] = 'El medico debe tener al menos una especialidad.';
        } elseif (count($esps) > ESPECIALIDADES_MAX) {
            $errores[] = 'Un medico no puede tener mas de tres especialidades.';
        } elseif (contar_existentes('Especialidad', 'idEspecialidad', $esps) !== count($esps)) {
            $errores[] = 'Alguna de las especialidades seleccionadas no existe.';
        }
        if (count($cens) < 1) {
            $errores[] = 'El medico debe atender en al menos un centro.';
        } elseif (contar_existentes('Centro', 'idCentro', $cens) !== count($cens)) {
            $errores[] = 'Alguno de los centros seleccionados no existe.';
        }

        if (!$errores) {
            try {
                $st = q('CALL sp_crear_medico(?, ?, ?, ?, ?, ?, ?)', [
                    $rut, $nombre,
                    $necesitaCuenta ? $email : null,
                    $necesitaCuenta ? password_hash($pw, PASSWORD_DEFAULT) : null,
                    $email, implode(',', $esps), implode(',', $cens),
                ]);
                $st->closeCursor();
                flash('success', $persona
                    ? 'Medico creado a partir de la persona ya registrada (' . $persona['nombreCompleto'] . ').'
                      . ($necesitaCuenta ? ' Se le asigno la cuenta de acceso indicada.' : ' Conserva su e-mail y contrasena de acceso actuales.')
                    : 'Medico creado. Puede iniciar sesion con ' . $email . ' y la contrasena indicada.');
                redirect('admin/medico.php?rut=' . rawurlencode($rut));
            } catch (PDOException $e) {
                $errores[] = mensaje_error_bd($e);         // SIGNAL del procedimiento/triggers o UNIQUE
            }
        }
    }
    http_response_code(422);
}

$catEsp = catalogo_especialidades();
$catCen = catalogo_centros();
encabezado($editar ? 'Editar medico' : 'Nuevo medico', 'admin/medicos.php');
?>
<a class="small" href="<?= e(url($editar ? 'admin/medico.php?rut=' . rawurlencode($rut) : 'admin/medicos.php')) ?>">&larr; Volver</a>
<h1 class="h3 mt-2 mb-3"><?= $editar ? 'Editar medico' : 'Nuevo medico' ?></h1>
<?php lista_errores($errores); ?>

<form method="post" action="<?= e(url('admin/medico_form.php')) ?>" novalidate>
    <?= csrf_campo() ?>
    <?php if ($editar): ?><input type="hidden" name="rut_editar" value="<?= e($rut) ?>"><?php endif; ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-semibold">Datos y cuenta</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="rut" class="form-label">RUT <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="rut" name="rut" maxlength="12" value="<?= e($rut) ?>"
                               <?= $editar ? 'disabled' : 'required' ?> placeholder="12345678-5">
                        <div class="form-text"><?= $editar ? 'El RUT no se modifica.' : 'Sin puntos, con guion y digito verificador. Si la persona ya existe (p. ej. es paciente), se reutiliza.' ?></div>
                    </div>
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre completo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" value="<?= e($nombre) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail institucional <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="254" value="<?= e($email) ?>" required>
                        <div class="form-text"><?= $editar ? 'E-mail que muestra la ficha de atencion.' : 'Tambien sera su e-mail de acceso (si la persona aun no tiene cuenta).' ?></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-sm-6">
                            <label for="password" class="form-label"><?= $editar ? 'Nueva contrasena (opcional)' : 'Contrasena inicial' ?></label>
                            <input type="password" class="form-control" id="password" name="password" autocomplete="new-password">
                        </div>
                        <div class="col-sm-6">
                            <label for="password2" class="form-label">Repetir contrasena</label>
                            <input type="password" class="form-control" id="password2" name="password2" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="form-text">Minimo 8 caracteres, con mayuscula, minuscula y digito.</div>
                </div>
            </div>
        </div>
        <?php if (!$editar): ?>
        <div class="col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Especialidades (1 a <?= ESPECIALIDADES_MAX ?>)</div>
                <div class="card-body">
                    <?php foreach ($catEsp as $x): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="especialidades[]" id="esp<?= (int) $x['idEspecialidad'] ?>"
                                   value="<?= (int) $x['idEspecialidad'] ?>" <?= in_array((int) $x['idEspecialidad'], $esps, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="esp<?= (int) $x['idEspecialidad'] ?>"><?= e($x['nombreEspecialidad']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-white fw-semibold">Centros (al menos 1)</div>
                <div class="card-body">
                    <?php foreach ($catCen as $x): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="centros[]" id="cen<?= (int) $x['idCentro'] ?>"
                                   value="<?= (int) $x['idCentro'] ?>" <?= in_array((int) $x['idCentro'], $cens, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="cen<?= (int) $x['idCentro'] ?>"><?= e($x['codigoCentro'] . ' ' . $x['nombreCentro']) ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary"><?= $editar ? 'Guardar cambios' : 'Crear medico' ?></button>
        <a class="btn btn-link" href="<?= e(url($editar ? 'admin/medico.php?rut=' . rawurlencode($rut) : 'admin/medicos.php')) ?>">Cancelar</a>
    </div>
</form>
<?php pie();
