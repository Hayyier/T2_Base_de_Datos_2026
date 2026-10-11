<?php
/**
 * Cierre de sesion. Solo por POST con token CSRF (un enlace externo no puede
 * cerrar la sesion de otro). Destruye la sesion y la cookie y vuelve al login.
 */
require __DIR__ . '/../includes/bootstrap.php';

if (!es_post()) {
    redirect(usuario() ? inicio_de(rol_activo()) : 'auth/login.php');
}
csrf_verificar();

cerrar_sesion();
iniciar_sesion();               // sesion nueva y vacia, solo para el mensaje
session_regenerate_id(true);
flash('info', 'Cerraste sesion correctamente.');
redirect('auth/login.php');
