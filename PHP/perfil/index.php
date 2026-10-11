<?php
/**
 * Mi perfil (todos los roles).
 *
 * La identidad sale SIEMPRE de la sesion (rut_sesion()); el formulario no
 * envia ningun RUT ni id de usuario, por lo que no es posible editar datos
 * ajenos.
 *
 * Campos editables segun el rol activo:
 *  - todos: nombre completo, e-mail de acceso, contrasena.
 *  - paciente: ademas fecha de nacimiento, sexo, telefono, comuna y prevision.
 *  - medico: e-mail institucional, especialidades y centros SOLO lectura (los
 *    gestiona el administrador).
 *  - RUT: nunca editable (es la clave; la BD lo impide con ON UPDATE RESTRICT).
 */
require __DIR__ . '/../includes/bootstrap.php';

$u = requerir_login();
$rut = rut_sesion();
$rol = $u['rol'];
$esPaciente = $rol === 'paciente';

function cargar_perfil(string $rut): array
{
    $p = q('SELECT p.RUT, p.nombreCompleto, p.email,
                   pa.fechaNacimiento, pa.sexo, pa.telefono, pa.idComuna, pa.idPrevision,
                   co.nombreComuna, re.nombreRegion, pr.nombrePrevision,
                   m.emailInstitucional
              FROM Persona p
              LEFT JOIN Paciente  pa ON pa.RUTPaciente = p.RUT
              LEFT JOIN Comuna    co ON co.idComuna    = pa.idComuna
              LEFT JOIN Region    re ON re.idRegion    = co.idRegion
              LEFT JOIN Prevision pr ON pr.idPrevision = pa.idPrevision
              LEFT JOIN Medico    m  ON m.RUTMedico    = p.RUT
             WHERE p.RUT = ?', [$rut])->fetch();
    return $p ?: [];
}

$perfil = cargar_perfil($rut);
$errores = [];
$erroresPw = [];
$form = $perfil;   // valores mostrados en el formulario

if (es_post()) {
    csrf_verificar();
    $accion = post('accion');

    if ($accion === 'datos') {
        $form['nombreCompleto'] = normalizar_nombre(post('nombreCompleto'));
        $form['email'] = normalizar_email(post('email'));
        if ($form['nombreCompleto'] === '' || !nombre_valido($form['nombreCompleto'])) {
            $errores[] = 'El nombre es obligatorio y debe tener entre 3 y 100 caracteres validos.';
        }
        if ($form['email'] === '' || !email_valido($form['email'])) {
            $errores[] = 'El e-mail es obligatorio y debe tener un formato valido.';
        } elseif (q('SELECT 1 FROM Persona WHERE email = ? AND RUT <> ?', [$form['email'], $rut])->fetchColumn()) {
            $errores[] = 'El e-mail ingresado ya esta registrado por otra persona.';
        }

        if ($esPaciente) {
            foreach (['fechaNacimiento', 'sexo', 'idComuna', 'idPrevision'] as $k) {
                $form[$k] = post($k);
            }
            $form['telefono'] = normalizar_telefono(post('telefono'));
            if (!fecha_nacimiento_valida($form['fechaNacimiento'])) {
                $errores[] = 'La fecha de nacimiento es obligatoria y no puede ser futura.';
            }
            if (!array_key_exists($form['sexo'], SEXOS)) {
                $errores[] = 'El sexo es obligatorio.';
            }
            if ($form['telefono'] !== '' && !telefono_valido($form['telefono'])) {
                $errores[] = 'El telefono debe tener entre 8 y 11 digitos (puede empezar con +).';
            }
            if (!ctype_digit((string) $form['idComuna']) || !existe_comuna((int) $form['idComuna'])) {
                $errores[] = 'Selecciona una comuna valida.';
            }
            if (!ctype_digit((string) $form['idPrevision']) || !existe_prevision((int) $form['idPrevision'])) {
                $errores[] = 'Selecciona una prevision valida.';
            }
        }

        if (!$errores) {
            $pdo = db();
            try {
                $pdo->beginTransaction();
                q('UPDATE Persona SET nombreCompleto = ?, email = ? WHERE RUT = ?',
                  [$form['nombreCompleto'], $form['email'], $rut]);
                if ($esPaciente) {
                    q('UPDATE Paciente SET fechaNacimiento = ?, sexo = ?, telefono = ?, idComuna = ?, idPrevision = ?
                        WHERE RUTPaciente = ?',
                      [$form['fechaNacimiento'], $form['sexo'], $form['telefono'] === '' ? null : $form['telefono'],
                       (int) $form['idComuna'], (int) $form['idPrevision'], $rut]);
                }
                $pdo->commit();
                $_SESSION['nombre'] = $form['nombreCompleto'];
                flash('success', 'Tus datos fueron actualizados.');
                redirect('perfil/index.php');
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errores[] = mensaje_error_bd($e);
            }
        }
    } elseif ($accion === 'password') {
        $actual = is_string($_POST['actual'] ?? null) ? $_POST['actual'] : '';
        $nueva  = is_string($_POST['nueva'] ?? null) ? $_POST['nueva'] : '';
        $nueva2 = is_string($_POST['nueva2'] ?? null) ? $_POST['nueva2'] : '';
        $hash = q('SELECT passwordHash FROM Persona WHERE RUT = ?', [$rut])->fetchColumn();
        if (!$hash || !password_verify($actual, $hash)) {
            $erroresPw[] = 'La contrasena actual no es correcta.';
        }
        $faltan = password_errores($nueva);
        if ($faltan) {
            $erroresPw[] = 'La nueva contrasena debe tener ' . implode(', ', $faltan) . '.';
        } elseif (!hash_equals($nueva, $nueva2)) {
            $erroresPw[] = 'Las contrasenas nuevas no coinciden.';
        }
        if (!$erroresPw) {
            q('UPDATE Persona SET passwordHash = ? WHERE RUT = ?', [password_hash($nueva, PASSWORD_DEFAULT), $rut]);
            session_regenerate_id(true);
            flash('success', 'Tu contrasena fue cambiada.');
            redirect('perfil/index.php');
        }
    } else {
        pagina_error(400, 'Solicitud no valida', 'Accion desconocida.');
    }
}

// datos de solo lectura del medico
$infoMedico = null;
if ($rol === 'medico') {
    $infoMedico = q('SELECT especialidades, centros FROM v_directorio_medicos WHERE RUTMedico = ?', [$rut])->fetch();
}
$previsiones = $esPaciente ? catalogo_previsiones() : [];
$comunas = $esPaciente ? catalogo_comunas() : [];

encabezado('Mi perfil');
?>
<h1 class="h3 mb-4">Mi perfil <span class="badge bg-secondary align-middle fs-6"><?= e(ROLES[$rol]) ?></span></h1>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Datos personales</div>
            <div class="card-body">
                <?php lista_errores($errores); ?>
                <form method="post" novalidate>
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="datos">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">RUT</label>
                            <input type="text" class="form-control" value="<?= e($perfil['RUT']) ?>" disabled>
                            <div class="form-text">No modificable.</div>
                        </div>
                        <div class="col-md-8">
                            <label for="nombreCompleto" class="form-label">Nombre completo *</label>
                            <input type="text" class="form-control" id="nombreCompleto" name="nombreCompleto" maxlength="100"
                                   value="<?= e($form['nombreCompleto']) ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label for="email" class="form-label">E-mail de acceso *</label>
                            <input type="email" class="form-control" id="email" name="email" maxlength="254"
                                   value="<?= e($form['email']) ?>" required>
                        </div>

                        <?php if ($esPaciente): ?>
                        <div class="col-md-4">
                            <label for="fechaNacimiento" class="form-label">Fecha de nacimiento *</label>
                            <input type="date" class="form-control" id="fechaNacimiento" name="fechaNacimiento"
                                   value="<?= e($form['fechaNacimiento']) ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="sexo" class="form-label">Sexo *</label>
                            <select class="form-select" id="sexo" name="sexo">
                                <?php foreach (SEXOS as $v => $txt): ?>
                                    <option value="<?= e($v) ?>"<?= $form['sexo'] === $v ? ' selected' : '' ?>><?= e($txt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="telefono" class="form-label">Telefono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" maxlength="15"
                                   value="<?= e($form['telefono']) ?>">
                        </div>
                        <div class="col-md-5">
                            <label for="idPrevision" class="form-label">Prevision *</label>
                            <select class="form-select" id="idPrevision" name="idPrevision">
                                <?php foreach ($previsiones as $p): ?>
                                    <option value="<?= (int) $p['idPrevision'] ?>"<?= (string) $form['idPrevision'] === (string) $p['idPrevision'] ? ' selected' : '' ?>><?= e($p['nombrePrevision']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label for="idComuna" class="form-label">Comuna de residencia *</label>
                            <select class="form-select" id="idComuna" name="idComuna">
                                <?php foreach ($comunas as $region => $lista): ?>
                                    <optgroup label="<?= e($region) ?>">
                                        <?php foreach ($lista as $id => $nombre): ?>
                                            <option value="<?= $id ?>"<?= (string) $form['idComuna'] === (string) $id ? ' selected' : '' ?>><?= e($nombre) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($infoMedico): ?>
        <div class="card shadow-sm mt-4">
            <div class="card-header bg-white fw-semibold">Datos profesionales <span class="text-muted fw-normal small">(los modifica la administracion)</span></div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">E-mail institucional</dt><dd class="col-sm-8"><?= e($perfil['emailInstitucional']) ?></dd>
                    <dt class="col-sm-4">Especialidades</dt><dd class="col-sm-8"><?= e($infoMedico['especialidades']) ?></dd>
                    <dt class="col-sm-4">Centros</dt><dd class="col-sm-8 mb-0"><?= e($infoMedico['centros']) ?></dd>
                </dl>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-semibold">Cambiar contrasena</div>
            <div class="card-body">
                <?php lista_errores($erroresPw); ?>
                <form method="post" novalidate>
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="password">
                    <div class="mb-3">
                        <label for="actual" class="form-label">Contrasena actual</label>
                        <input type="password" class="form-control" id="actual" name="actual" autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label for="nueva" class="form-label">Nueva contrasena</label>
                        <input type="password" class="form-control" id="nueva" name="nueva" autocomplete="new-password">
                        <div class="form-text">Minimo 8 caracteres, con mayuscula, minuscula y digito.</div>
                    </div>
                    <div class="mb-3">
                        <label for="nueva2" class="form-label">Repetir nueva contrasena</label>
                        <input type="password" class="form-control" id="nueva2" name="nueva2" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100">Cambiar contrasena</button>
                </form>
            </div>
        </div>

        <?php if ($esPaciente): ?>
        <div class="card border-danger shadow-sm mt-4">
            <div class="card-body">
                <h2 class="h6 text-danger">Eliminar mi cuenta</h2>
                <p class="small text-muted mb-3">Cancela tus citas futuras y elimina tu acceso a la plataforma.</p>
                <a class="btn btn-outline-danger btn-sm" href="<?= e(url('perfil/eliminar.php')) ?>">Eliminar cuenta</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php pie();
