<?php
/** Entrada de la aplicacion: lleva al inicio del rol o al login. */
require __DIR__ . '/includes/bootstrap.php';

$u = usuario();
if ($u === null) {
    redirect('auth/login.php');
}
if ($u['rol'] === null) {
    redirect('auth/elegir_rol.php');
}
redirect(inicio_de($u['rol']));
