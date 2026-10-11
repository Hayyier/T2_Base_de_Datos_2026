<?php
/**
 * Login de los tres roles (publico).
 * Identificador: RUT (XXXXXXXX-X) o e-mail de acceso.
 */
require __DIR__ . '/../includes/bootstrap.php';

if (usuario() !== null && rol_activo() !== null) {
    redirect(inicio_de(rol_activo()));
}

$error = null;
$identificador = '';

if (es_post()) {
    csrf_verificar();
    $identificador = post('identificador');
    $password = $_POST['password'] ?? '';
    $password = is_string($password) ? $password : '';

    if ($identificador === '' || $password === '') {
        $error = 'Ingresa tu RUT o e-mail y tu contrasena.';
    } else {
        $persona = autenticar($identificador, $password);
        $roles = $persona ? roles_de($persona['RUT']) : [];
        if (!$persona || !$roles) {
            $error = 'RUT/e-mail o contrasena incorrectos.';
        } else {
            abrir_sesion_usuario($persona, $roles);
            if (rol_activo() === null) {
                redirect('auth/elegir_rol.php');
            }
            flash('success', 'Bienvenido/a, ' . $persona['nombreCompleto'] . '.');
            redirect(inicio_de(rol_activo()));
        }
    }
}

encabezado('Iniciar sesion');
?>
<div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4 mb-1">Iniciar sesion</h1>
                <p class="text-muted small mb-4">Pacientes, medicos y administradores.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><?= e($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrf_campo() ?>
                    <div class="mb-3">
                        <label for="identificador" class="form-label">RUT o e-mail</label>
                        <input type="text" class="form-control" id="identificador" name="identificador"
                               value="<?= e($identificador) ?>" placeholder="12345678-5 o nombre@correo.cl"
                               autocomplete="username" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Contrasena</label>
                        <input type="password" class="form-control" id="password" name="password"
                               autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Ingresar</button>
                </form>

                <hr class="my-4">
                <p class="text-center mb-0 small">
                    ¿Eres paciente y no tienes cuenta?
                    <a href="<?= e(url('auth/registro.php')) ?>">Registrate aqui</a>
                </p>
            </div>
        </div>
        <p class="text-center text-muted small mt-3">
            Los medicos y administradores son creados por la administracion de SaludUSM.
        </p>
    </div>
</div>
<?php pie();
