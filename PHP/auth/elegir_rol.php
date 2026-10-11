<?php
/**
 * Seleccion de rol para personas con mas de un rol (p. ej. medico y paciente).
 * Solo se puede elegir entre los roles que la BD le asigna.
 */
require __DIR__ . '/../includes/bootstrap.php';

$u = usuario();
if ($u === null) {
    flash('warning', 'Debes iniciar sesion para acceder a esa pagina.');
    redirect('auth/login.php');
}
// los roles se vuelven a leer de la BD (no se confia en la sesion ni en el formulario)
$vigente = q('SELECT 1 FROM Persona WHERE RUT = ? AND passwordHash IS NOT NULL', [$u['rut']])->fetchColumn();
$roles = $vigente ? roles_de($u['rut']) : [];
if (!$roles) {
    cerrar_sesion();
    redirect('auth/login.php');
}
$_SESSION['roles'] = $roles;
if (count($roles) === 1) {
    $_SESSION['rol'] = $roles[0];
    redirect(inicio_de($roles[0]));
}

if (es_post()) {
    csrf_verificar();
    $elegido = post('rol');
    if (in_array($elegido, $roles, true)) {
        $_SESSION['rol'] = $elegido;
        flash('info', 'Ingresaste como ' . ROLES[$elegido] . '.');
        redirect(inicio_de($elegido));
    }
    flash('danger', 'El rol seleccionado no es valido.');
    redirect('auth/elegir_rol.php');
}

encabezado('Elegir rol');
?>
<div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h1 class="h4">Hola, <?= e($u['nombre']) ?></h1>
                <p class="text-muted">Tu cuenta tiene mas de un rol. ¿Con cual quieres ingresar?</p>
                <form method="post">
                    <?= csrf_campo() ?>
                    <div class="d-grid gap-2">
                        <?php foreach ($roles as $r): ?>
                            <button type="submit" name="rol" value="<?= e($r) ?>"
                                    class="btn <?= $r === $u['rol'] ? 'btn-primary' : 'btn-outline-primary' ?>">
                                <?= e(ROLES[$r]) ?><?= $r === $u['rol'] ? ' (actual)' : '' ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php pie();
