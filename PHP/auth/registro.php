<?php
/**
 * Registro de PACIENTES (unico registro publico).
 * Valida en el servidor: RUT (formato + digito verificador + unicidad),
 * e-mail (formato + unicidad), campos obligatorios, contrasena,
 * prevision y comuna desde los catalogos de la BD.
 * Inserta Persona + Paciente en una transaccion.
 *
 * Caso especial: si el RUT pertenece a un paciente que elimino su cuenta
 * (persona sin credenciales), el registro REACTIVA esa cuenta: actualiza sus
 * datos y le asigna las nuevas credenciales, conservando su historial.
 */
require __DIR__ . '/../includes/bootstrap.php';

if (usuario() !== null && rol_activo() !== null) {
    redirect(inicio_de(rol_activo()));
}

$d = [
    'rut' => '', 'nombre' => '', 'email' => '', 'fechaNacimiento' => '', 'sexo' => '',
    'telefono' => '', 'idComuna' => '', 'idPrevision' => '',
];
$errores = [];

if (es_post()) {
    csrf_verificar();
    foreach (array_keys($d) as $k) {
        $d[$k] = post($k);
    }
    $d['rut']      = normalizar_rut($d['rut']);
    $d['nombre']   = normalizar_nombre($d['nombre']);
    $d['email']    = normalizar_email($d['email']);
    $d['telefono'] = normalizar_telefono($d['telefono']);
    $password  = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $password2 = is_string($_POST['password2'] ?? null) ? $_POST['password2'] : '';

    // --- campos obligatorios ---
    $obligatorios = [
        'rut' => 'RUT', 'nombre' => 'Nombre completo', 'email' => 'E-mail',
        'fechaNacimiento' => 'Fecha de nacimiento', 'sexo' => 'Sexo',
        'idComuna' => 'Comuna de residencia', 'idPrevision' => 'Prevision',
    ];
    foreach ($obligatorios as $k => $etiqueta) {
        if ($d[$k] === '') {
            $errores[] = "El campo \"$etiqueta\" es obligatorio.";
        }
    }
    if ($password === '') {
        $errores[] = 'El campo "Contrasena" es obligatorio.';
    }

    // --- formato de cada dato ---
    if ($d['rut'] !== '' && !rut_formato_valido($d['rut'])) {
        $errores[] = 'El RUT debe tener el formato XXXXXXXX-X (sin puntos, con guion).';
    } elseif ($d['rut'] !== '' && !rut_valido($d['rut'])) {
        $errores[] = 'El RUT no es valido: el digito verificador no corresponde.';
    }
    if ($d['nombre'] !== '' && !nombre_valido($d['nombre'])) {
        $errores[] = 'El nombre debe tener entre 3 y 100 caracteres y solo letras, espacios, apostrofo, punto o guion.';
    }
    if ($d['email'] !== '' && !email_valido($d['email'])) {
        $errores[] = 'El e-mail no tiene un formato valido.';
    }
    if ($d['fechaNacimiento'] !== '' && !fecha_nacimiento_valida($d['fechaNacimiento'])) {
        $errores[] = 'La fecha de nacimiento no es valida (no puede ser futura).';
    }
    if ($d['sexo'] !== '' && !array_key_exists($d['sexo'], SEXOS)) {
        $errores[] = 'El sexo seleccionado no es valido.';
    }
    if ($d['telefono'] !== '' && !telefono_valido($d['telefono'])) {
        $errores[] = 'El telefono debe tener entre 8 y 11 digitos (puede empezar con +).';
    }
    if ($d['idComuna'] !== '' && (!ctype_digit($d['idComuna']) || !existe_comuna((int) $d['idComuna']))) {
        $errores[] = 'La comuna seleccionada no existe.';
    }
    if ($d['idPrevision'] !== '' && (!ctype_digit($d['idPrevision']) || !existe_prevision((int) $d['idPrevision']))) {
        $errores[] = 'La prevision seleccionada no existe.';
    }
    if ($password !== '') {
        $faltan = password_errores($password);
        if ($faltan) {
            $errores[] = 'La contrasena debe tener ' . implode(', ', $faltan) . '.';
        } elseif (!hash_equals($password, $password2)) {
            $errores[] = 'Las contrasenas no coinciden.';
        }
    }

    // --- unicidad de RUT y e-mail ---
    $reactivar = false;
    if (!$errores) {
        $existente = q('SELECT RUT, passwordHash FROM Persona WHERE RUT = ?', [$d['rut']])->fetch();
        if ($existente) {
            $roles = roles_de($d['rut']);
            if ($existente['passwordHash'] === null && $roles === ['paciente']) {
                $reactivar = true;    // paciente que elimino su cuenta (S20)
            } else {
                $errores[] = 'El RUT ingresado ya esta registrado. Si es tuyo, inicia sesion.';
            }
        }
        $emailUsado = q('SELECT 1 FROM Persona WHERE email = ? AND RUT <> ?', [$d['email'], $d['rut']])->fetchColumn();
        if ($emailUsado) {
            $errores[] = 'El e-mail ingresado ya esta registrado.';
        }
    }

    // --- insercion (transaccion: Persona + Paciente o ninguna) ---
    if (!$errores) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $tel  = $d['telefono'] === '' ? null : $d['telefono'];
        $pdo  = db();
        try {
            $pdo->beginTransaction();
            if ($reactivar) {
                q('UPDATE Persona SET nombreCompleto = ?, email = ?, passwordHash = ? WHERE RUT = ?',
                  [$d['nombre'], $d['email'], $hash, $d['rut']]);
                q('UPDATE Paciente SET fechaNacimiento = ?, sexo = ?, telefono = ?, idComuna = ?, idPrevision = ?
                    WHERE RUTPaciente = ?',
                  [$d['fechaNacimiento'], $d['sexo'], $tel, (int) $d['idComuna'], (int) $d['idPrevision'], $d['rut']]);
            } else {
                q('INSERT INTO Persona (RUT, nombreCompleto, email, passwordHash) VALUES (?, ?, ?, ?)',
                  [$d['rut'], $d['nombre'], $d['email'], $hash]);
                q('INSERT INTO Paciente (RUTPaciente, fechaNacimiento, sexo, telefono, idComuna, idPrevision)
                   VALUES (?, ?, ?, ?, ?, ?)',
                  [$d['rut'], $d['fechaNacimiento'], $d['sexo'], $tel, (int) $d['idComuna'], (int) $d['idPrevision']]);
            }
            $pdo->commit();
            flash('success', $reactivar
                ? 'Tu cuenta fue reactivada. Ya puedes iniciar sesion; tu historial se conservo.'
                : 'Registro exitoso. Ya puedes iniciar sesion con tu RUT o e-mail.');
            redirect('auth/login.php');
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errores[] = mensaje_error_bd($e);   // p. ej. RUT/e-mail duplicado por registro simultaneo
        }
    }
}

$previsiones = catalogo_previsiones();
$comunas = catalogo_comunas();

encabezado('Registro de paciente');
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Registro de paciente</h1>
                <p class="text-muted small">Los campos marcados con * son obligatorios. El registro esta disponible solo para pacientes.</p>

                <?php lista_errores($errores); ?>

                <form method="post" novalidate>
                    <?= csrf_campo() ?>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="rut" class="form-label">RUT *</label>
                            <input type="text" class="form-control" id="rut" name="rut" maxlength="12"
                                   value="<?= e($d['rut']) ?>" placeholder="12345678-5" required>
                            <div class="form-text">Sin puntos, con guion y digito verificador.</div>
                        </div>
                        <div class="col-md-7">
                            <label for="nombre" class="form-label">Nombre completo *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100"
                                   value="<?= e($d['nombre']) ?>" required>
                        </div>
                        <div class="col-md-7">
                            <label for="email" class="form-label">E-mail *</label>
                            <input type="email" class="form-control" id="email" name="email" maxlength="254"
                                   value="<?= e($d['email']) ?>" autocomplete="email" required>
                        </div>
                        <div class="col-md-5">
                            <label for="telefono" class="form-label">Telefono</label>
                            <input type="tel" class="form-control" id="telefono" name="telefono" maxlength="15"
                                   value="<?= e($d['telefono']) ?>" placeholder="+56912345678">
                        </div>
                        <div class="col-md-4">
                            <label for="fechaNacimiento" class="form-label">Fecha de nacimiento *</label>
                            <input type="date" class="form-control" id="fechaNacimiento" name="fechaNacimiento"
                                   value="<?= e($d['fechaNacimiento']) ?>" max="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label for="sexo" class="form-label">Sexo *</label>
                            <select class="form-select" id="sexo" name="sexo" required>
                                <option value="">Seleccione</option>
                                <?php foreach (SEXOS as $v => $txt): ?>
                                    <option value="<?= e($v) ?>"<?= $d['sexo'] === $v ? ' selected' : '' ?>><?= e($txt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label for="idPrevision" class="form-label">Prevision *</label>
                            <select class="form-select" id="idPrevision" name="idPrevision" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($previsiones as $p): ?>
                                    <option value="<?= (int) $p['idPrevision'] ?>"<?= $d['idPrevision'] === (string) $p['idPrevision'] ? ' selected' : '' ?>><?= e($p['nombrePrevision']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label for="idComuna" class="form-label">Comuna de residencia *</label>
                            <select class="form-select" id="idComuna" name="idComuna" required>
                                <option value="">Seleccione</option>
                                <?php foreach ($comunas as $region => $lista): ?>
                                    <optgroup label="<?= e($region) ?>">
                                        <?php foreach ($lista as $id => $nombre): ?>
                                            <option value="<?= $id ?>"<?= $d['idComuna'] === (string) $id ? ' selected' : '' ?>><?= e($nombre) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Contrasena *</label>
                            <input type="password" class="form-control" id="password" name="password"
                                   autocomplete="new-password" required>
                            <div class="form-text">Minimo 8 caracteres, con mayuscula, minuscula y digito.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="password2" class="form-label">Repetir contrasena *</label>
                            <input type="password" class="form-control" id="password2" name="password2"
                                   autocomplete="new-password" required>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <a href="<?= e(url('auth/login.php')) ?>">Volver al inicio de sesion</a>
                        <button type="submit" class="btn btn-primary">Crear cuenta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php pie();
