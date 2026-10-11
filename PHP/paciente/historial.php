<?php
/**
 * Mi historial clinico: atenciones del paciente de la sesion
 * con sus diagnosticos y recetas. SOLO LECTURA: esta pagina no tiene
 * formularios y rechaza cualquier metodo distinto de GET (405).
 *
 * Las consultas y la presentacion se movieron a includes/historial.php
 * (las comparte el historial del medico). El control de acceso sigue aqui:
 * el unico paciente consultado es el de la SESION; la pagina no recibe ids.
 * Una atencion solo existe para una cita Atendida (trigger trg_atencion_bi),
 * por lo que no se corrige nada desde PHP.
 */
require __DIR__ . '/../includes/bootstrap.php';

requerir_rol('paciente');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    pagina_error(405, 'Operacion no permitida', 'El historial clinico es de solo lectura.');
}

[$atenciones, $diagnosticos, $recetas] = historial_de_paciente(rut_sesion());

encabezado('Mi historial clinico');
?>
<h1 class="h3 mb-1">Mi historial clinico</h1>
<p class="text-muted mb-4">Atenciones registradas por tus medicos. Esta informacion es de solo lectura.</p>
<?php mostrar_historial($atenciones, $diagnosticos, $recetas); ?>
<?php pie();
