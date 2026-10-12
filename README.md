# SaludUSM: Tarea 2, INF-239 Bases de Datos

Sistema web de gestión de citas médicas, en PHP y MySQL, para la red de centros SaludUSM. Hay tres roles: paciente, médico y administrador.

## Identificación

| Integrante | Rol USM |
|---|---|
| Antonio Aguilar Lolas | 202210538-0 |
| Cristopher Martínez Sánchez | 202210527-5 |

Repositorio: https://github.com/Hayyier/T2_Base_de_Datos_2026

## Requisitos

| Software | Versión |
|---|---|
| PHP | 8.1 o superior | 
| Motor de BD | MySQL 8.0.16+ o MariaDB 10.4+ |
| Servidor web | Apache (XAMPP) |
| Navegador | Cualquiera actual |

La forma recomendada es usar **XAMPP 8.1 o superior**, que incluye Apache, PHP y MariaDB.

## Estructura de la entrega

```text
T2-202210538-0-202210527-5/
├── PDF/
│   └── Normalizacion_3FN.pdf     Normalización: DF, 1FN, 2FN, 3FN y modelo final
├── BD/
│   └── salud_usm.sql             Script único: crea la BD, los objetos SQL y los datos de prueba
├── PHP/                          Aplicación web (raíz del sitio)
│   ├── index.php                 Entrada: lleva al login o al inicio del rol activo
│   ├── config/config.php         ÚNICO archivo con los datos de conexión
│   ├── includes/                 Código común (no se abre desde el navegador)
│   │   ├── bootstrap.php         Carga la configuración, la conexión, la sesión y el manejo de errores
│   │   ├── db.php                PDO (prepares reales, utf8mb4, zona horaria de la sesión SQL)
│   │   ├── helpers.php           Escape HTML, CSRF, validaciones (RUT, e-mail, contraseña, fechas)
│   │   ├── auth.php              Login, roles, requerir_rol(), actualización de citas vencidas
│   │   ├── layout.php            Cabecera, menú por rol y pie de página
│   │   ├── citas.php             Reglas de agenda y reserva (bloques, estados)
│   │   ├── atenciones.php        Atención, diagnósticos y recetas
│   │   ├── historial.php         Historial clínico
│   │   └── admin.php             Validaciones de centros y médicos
│   ├── auth/                     login, registro de paciente, elegir rol, logout
│   ├── perfil/                   Ver y modificar datos propios; eliminar la cuenta de paciente
│   ├── buscar/                   Búsqueda de médicos (3 roles) y búsqueda avanzada de citas
│   ├── paciente/                 Inicio, Mis citas, agendar, reprogramar, cancelar, comprobante, historial
│   ├── medico/                   Inicio, agenda, estados, atención, diagnósticos, recetas, historial
│   ├── admin/                    Centros, médicos, asignaciones, panel de gestión
│   └── assets/                   css/ (Bootstrap y estilos propios), js/ (Bootstrap)
└── README.md
```

Las carpetas `PHP/includes/` y `PHP/config/` tienen un `.htaccess` con `Require all denied`, así que Apache no las sirve directamente.

## Instalación (XAMPP)

1. **Iniciar los servicios.** Abrir el XAMPP Control Panel y pulsar *Start* en **Apache** y en **MySQL**.
2. **Crear la BD con todos sus objetos y datos.** El archivo `BD/salud_usm.sql` hace todo en un solo paso, en este orden:

   | Paso | Contenido |
   |---|---|
   | Crear BD | `DROP DATABASE IF EXISTS` + `CREATE DATABASE salud_usm` |
   | DDL | 17 tablas con PK, FK (CASCADE/RESTRICT), UNIQUE, CHECK, índices y catálogos fijos |
   | Objetos SQL | 2 functions |
   | | 14 triggers |
   | | 3 procedures |
   | | 5 views |
   | DML | Datos de prueba |

   Se puede importar de dos formas:
   - **phpMyAdmin.** Abrir `http://localhost/phpmyadmin`, ir a **Importar**, elegir `BD/salud_usm.sql` y pulsar **Importar** (o *Continuar*). No hace falta crear la base antes.
   - **Consola.** En `C:\xampp\mysql\bin`, ejecutar `mysql -u root -p < ruta\a\BD\salud_usm.sql` (con XAMPP por defecto, dejar la contraseña vacía).

3. **Copiar la aplicación.** Copiar la carpeta completa `T2-202210538-0-202210527-5` dentro de `C:\xampp\htdocs\`.
4. **Abrir la aplicación** en `http://localhost/T2-202210538-0-202210527-5/PHP/`.

## Usuarios de prueba

Todos los usuarios usan la contraseña **`Salud2026`**. Se puede iniciar sesión con el RUT (con o sin puntos) o con el e-mail.

| Rol | RUT | E-mail | Para qué sirve |
|---|---|---|---|
| Administrador | 12345678-5 | admin@saludusm.cl | Panel de gestión, CRUD de centros y médicos, asignaciones |
| Médico | 7980815-6 | marcela.cordero@saludusm.cl | 3 especialidades y 2 centros; agenda con citas de hoy y próximas; atenciones y recetas |
| Médico y paciente | 11433012-4 | rodrigo.marti@saludusm.cl | Elegir y cambiar de rol; como paciente tiene historial y una cita próxima; no puede eliminar su cuenta desde el rol paciente (S46) |
| Médico | 16234567-2 | paula.navarro@saludusm.cl | 1 especialidad y 1 centro, sin citas: se puede eliminar desde el administrador |
| Paciente | 7302450-1 | mario.contreras@correo.cl | Citas en todos los estados, historial clínico con recetas; eliminación de cuenta con historial (S20) |
| Paciente | 25100011-5 | tomas.rojas@correo.cl | Paciente menor de edad (Pediatría) |
| Paciente | 8735650-7 | antonia.contreras@correo.cl | Sin citas: eliminación completa de la cuenta; agendar la primera cita |

Asimismo, hay 89 pacientes, 32 médicos, 10 centros, 10 especialidades, 15 diagnósticos CIE-10 y unas 250 citas. Todos los pacientes usan e-mails del tipo `nombre.apellido@correo.cl` y los médicos su e-mail institucional `@saludusm.cl`, siempre con la misma contraseña. También se puede registrar un paciente nuevo desde el enlace **Registrate aqui** del login.

**Fechas de los datos de prueba.** Las fechas son relativas al día en que se importa el script (S32). Si se importa un domingo, la agenda "de hoy" se arma para el lunes siguiente, porque el domingo no hay atención (S2).

## Política de contraseñas

La política se aplica al registrarse, al cambiar la contraseña en el perfil y cuando el administrador crea un médico o restablece su contraseña (S16):

- entre 8 y 72 caracteres (72 es el límite de bcrypt);
- al menos una letra mayúscula, una minúscula y un dígito;
- se guarda solo el hash: `password_hash()` (bcrypt) al guardar y `password_verify()` al iniciar sesión;
- para cambiar la contraseña desde el perfil se pide la contraseña actual.

## Citas vencidas

- **Qué es una cita vencida.** Una cita en estado **Reservada** o **Confirmada** cuya **fecha** es anterior a hoy (S13). Las citas de hoy no se tocan, aunque su hora ya haya pasado, para que el médico pueda marcarlas Atendida o No Asistió.
- **En qué se transforma.** Pasa a estado **Cancelada**. La cita no se borra y su bloque queda libre.
- **Quién la transforma.** El procedimiento almacenado `sp_cancelar_citas_vencidas(OUT p_cantidad)`. PHP lo llama mediante `actualizar_citas_vencidas()` en `PHP/includes/auth.php`.
- **Cuándo se ejecuta** (S14), justo antes de leer las citas en cada una de estas operaciones:

  | Operación o página | Archivo |
  |---|---|
  | Inicio de sesión (cualquier rol) | `includes/auth.php` (login) |
  | Inicio del paciente | `paciente/index.php` |
  | Mis citas | `paciente/citas.php` |
  | Inicio del médico | `medico/index.php` |
  | Mi agenda | `medico/agenda.php` |
  | Búsqueda avanzada de citas (los tres roles) | `buscar/citas.php` |
  | Panel de gestión | `admin/panel.php` |

## Resumen de las decisiones principales

| Tema | Decisión |
|---|---|
| Bloques | 30 minutos, en las horas :00 y :30 |
| Horario | Lunes a sábado, de 08:00 a 18:00 (último bloque a las 17:30); se agenda hasta 90 días hacia adelante |
| Choque de horarios | Un médico no tiene dos citas activas a la misma hora (en ningún centro); un paciente tampoco |
| Reprogramar | Solo cambia la fecha y hora; la cita vuelve a Reservada |
| Cancelar | La cita pasa a Cancelada (no se borra) y libera el bloque |
| Citas vencidas | Pasan a Cancelada al día siguiente (ver la sección anterior) |
| Eliminar paciente | Sin citas: se borra todo. Con citas: se cancelan las futuras, se eliminan las credenciales y se conserva el historial clínico |
| Eliminar médico | Solo si no tiene citas |
| Eliminar centro | Solo si no tiene citas ni médicos asignados |
| Quitar especialidad o centro a un médico | No se permite con citas futuras asociadas ni si es la última; máximo 3 especialidades |
| Eliminar especialidad | No se permite con médicos o citas (catálogo mantenido desde la BD) |
| Atención | Al menos un diagnóstico; recetas opcionales y sin medicamentos repetidos |
| % de inasistencia | Citas No Asistió / total de citas del centro |

## Supuestos

Esta es la lista completa de supuestos y decisiones del proyecto. La columna *Dónde se garantiza* indica la capa que hace cumplir cada regla: restricción de la BD, trigger, procedure, function, view o PHP.

### Agenda y citas

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S1 | Cada bloque horario dura 30 minutos. | BD: `ck_cita_bloque` (minutos 00 o 30, segundos 0) |
| S2 | Horario de atención: lunes a sábado, 08:00–18:00 (último bloque 17:30). Igual para todos los médicos y centros; no se modelan turnos, vacaciones ni feriados. | BD: `ck_cita_bloque` (hora 8–17, no domingo) |
| S3 | Solo se agenda o reprograma hacia bloques futuros, con un máximo de 90 días de anticipación. | Trig / PHP (depende de `NOW()`, no puede ser CHECK) |
| S4 | Un médico no puede tener dos citas activas a la misma hora en ningún centro (versión más estricta que la regla 1 de T1, que decía "en el mismo centro"). | BD: `uq_cita_medico_hora` |
| S5 | Una cita Cancelada libera su bloque: ese horario puede volver a reservarse. | BD: columna generada `slotActivo` |
| S6 | Una cita nueva se crea en estado Reservada. | BD: `DEFAULT 1` en `Cita.idEstadoCita` + Trig |
| S7 | Reprogramar solo cambia la fecha y hora (mismo médico, centro y especialidad) y la cita vuelve a Reservada. Para cambiar de médico, centro o especialidad se cancela y se agenda otra. | Trig / PHP |
| S8 | Cancelar cambia el estado a Cancelada; la cita no se borra. | PHP |
| S9 | El paciente solo reprograma o cancela sus citas Reservadas o Confirmadas cuya fecha y hora no hayan pasado. | Trig / PHP |
| S10 | Estados que asigna el médico: Confirmada (desde Reservada, no vencida); Atendida o No Asistió (desde Reservada o Confirmada, solo si la hora ya llegó); Atendida y No Asistió solo se corrigen entre sí; una Atendida con atención no cambia; nada vuelve a Reservada salvo al reprogramar; una Cancelada no se reactiva; el médico no cancela. | Trig / PHP |
| S11 | Un médico no puede ser paciente de su propia cita. | BD: `ck_cita_paciente_medico` |
| S12 | "Próximas" = citas con fecha y hora igual o posterior al momento actual; "históricas" = las anteriores (en cualquier estado). | PHP |

### Citas vencidas

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S13 | Una cita está vencida si está Reservada o Confirmada y su **fecha** es anterior a hoy. Las de hoy no se cancelan, para que el médico pueda marcarlas Atendida o No Asistió. | Proc |
| S14 | La cancelación de vencidas (`sp_cancelar_citas_vencidas`) se ejecuta en cada inicio de sesión, al abrir el Inicio del paciente y Mis citas, al abrir el Inicio del médico y Mi agenda, y al abrir la Búsqueda avanzada y el Panel de gestión. | Proc + PHP (`actualizar_citas_vencidas()`) |

### Cuentas y usuarios

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S15 | Formato de RUT: 7 u 8 dígitos sin puntos, guion y DV (0–9 o **K mayúscula**). Se valida el dígito verificador (módulo 11). | BD: `ck_persona_rut` + columna `ascii_bin` (formato y mayúscula). Func/Trig: dígito verificador |
| S16 | Contraseña: mínimo 8 caracteres, con al menos una mayúscula, una minúscula y un dígito; máximo 72 (límite de bcrypt). Se guarda con `password_hash` (bcrypt). | PHP (`password_errores()`); BD: `passwordHash VARCHAR(255)` |
| S17 | El login acepta RUT (con o sin puntos, k minúscula o mayúscula: se normaliza) o e-mail (sin distinguir mayúsculas). Si la persona tiene más de un rol, elige con cuál entrar y puede cambiarlo desde el menú. | PHP |
| S18 | El registro desde el login rechaza un RUT ya registrado (aunque sea de un médico), salvo el caso de reactivación (S47). Las personas con más de un rol las crea el administrador (`sp_crear_medico` reutiliza la persona existente). | PHP + BD (PK) |
| S19 | El RUT no se modifica. Todos los roles editan nombre, e-mail de acceso y contraseña (pidiendo la actual). El paciente además edita fecha de nacimiento, sexo, teléfono, comuna y previsión. El e-mail institucional, las especialidades y los centros del médico solo los modifica el administrador. | BD: FK con `ON UPDATE RESTRICT` + PHP (`perfil/index.php`) |
| S20 | Eliminar la cuenta de paciente: si no tiene citas, se elimina por completo. Si tiene citas, sus citas futuras Reservadas o Confirmadas pasan a Cancelada, se eliminan sus credenciales (`email` y `passwordHash` = NULL; ya no puede iniciar sesión) y se **conservan** sus citas pasadas, atenciones, diagnósticos y recetas como registro clínico. No se borra en cascada, porque eso destruiría información clínica de los médicos y de la red. | BD: `fk_cita_paciente RESTRICT` + `ck_persona_credenciales`; PHP (`perfil/eliminar.php`, transacción) |
| S33 | Los e-mails son únicos sin distinguir mayúsculas de minúsculas (`Ana@x.cl` = `ana@x.cl`) y admiten hasta 254 caracteres. | BD: `VARCHAR(254)` + collation `utf8mb4_unicode_ci` |

### Administración

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S21 | No se puede eliminar un médico con citas (de cualquier estado). Un médico sin citas se elimina junto con sus asignaciones de especialidades y centros. | BD: `fk_cita_medico RESTRICT`; `fk_me_medico` y `fk_mc_medico CASCADE` |
| S22 | Solo se puede eliminar un centro que no tenga citas **ni médicos asignados**. Antes hay que quitar sus asignaciones; cada quite respeta S23. No se borra en cascada, porque un borrado en cascada no dispara triggers y podría dejar a un médico sin ningún centro (viola "al menos 1 centro"). | BD: `fk_cita_centro` y `fk_mc_centro` RESTRICT |
| S23 | No se puede quitar a un médico una especialidad o un centro si tiene citas futuras (Reservadas o Confirmadas) asociadas. Con solo citas pasadas, sí. Tampoco se puede quitar su última especialidad ni su último centro. | Trig + PHP |
| S24 | Un centro o una especialidad pueden existir sin médicos, y puede haber pacientes sin citas. | BD + PHP |
| S34 | Una especialidad no se puede eliminar mientras tenga médicos o citas asociadas. La T2 no pide CRUD de especialidades: el catálogo se mantiene desde la BD. | BD: `fk_me_especialidad` y `fk_cita_especialidad` RESTRICT |

### Atenciones

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S25 | Los diagnósticos se eligen del catálogo CIE-10 precargado. "Editar" un diagnóstico de una atención = reemplazar el código asignado; el catálogo no se modifica desde la atención. | PHP |
| S26 | Toda atención tiene al menos un diagnóstico (no se puede quitar el último). El motivo es obligatorio y las observaciones opcionales. | BD: `motivo NOT NULL`, `observaciones NULL`. Trig / PHP: mínimo 1 diagnóstico |
| S27 | Una atención puede tener cero o más líneas de receta, y no repite un medicamento en dos líneas. | BD: `uq_receta_medicamento` |
| S28 | Solo el médico de la cita registra o edita la atención, sin límite de tiempo. La fecha de la atención es la de la cita (no se almacena aparte). | PHP; modelo sin `fechaAtencion` |
| S29 | El historial que ve un médico es el de "sus pacientes" (los que tienen al menos una cita con él), con todas sus atenciones, con cualquier médico. | PHP |

### Panel de gestión

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S30 | % de inasistencia de un centro = citas No Asistió / total de citas del centro (todos los estados), como en la consulta 4 de T1. Un centro sin citas muestra 0 %. | Func / View |
| S31 | Si dos diagnósticos empatan en frecuencia, el top 5 los ordena por código. | View |

### Modelo de datos y normalización

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S32 | El nombre de cada comuna es único en el país; por eso la comuna determina la región. | BD: `uq_comuna_nombre (nombreComuna)` |
| S33 | La fecha de una atención es la fecha de la cita que la origina. | Modelo (sin columna de fecha en Atencion) |
| S34 | En una misma atención no se repite un medicamento (igual que S27). | BD: `uq_receta_medicamento` |
| S35 | Los catálogos Prevision (1 Fonasa, 2 Isapre, 3 Particular) y EstadoCita (1 Reservada, 2 Confirmada, 3 Atendida, 4 No Asistió, 5 Cancelada) tienen ids fijos y se cargan junto con el DDL, porque la lógica compara por id. Especialidad, Diagnostico, Region y Comuna se cargan con los datos. | BD |
| S36 | `slotActivo` en Cita es una columna generada de implementación (no es un atributo del modelo lógico). Depende de que Cancelada tenga id 5. | BD |
| S37 | Persona tiene dos correos distintos: `Persona.email` (acceso, único) y `Medico.emailInstitucional` (único, el que muestra la ficha). Pueden coincidir. | BD |

### Entorno

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S38 | El `.sql` final de la entrega (`BD/salud_usm.sql`, un solo archivo) empieza con `DROP DATABASE IF EXISTS salud_usm;` y luego crea todo desde cero, así que se puede ejecutar varias veces con el mismo resultado. | BD (`BD/salud_usm.sql`) |
| S39 | Motor: MySQL 8.0.16+ o MariaDB 10.4+ (XAMPP), requerido para que se apliquen los CHECK. Probado en MySQL 8.0.46 y MariaDB 10.11.14. | Doc |

### Capa SQL y datos de prueba

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S40 | Eliminar una atención (CRUD 3.7) borra en cascada sus diagnósticos y recetas. La cita sigue en estado Atendida y el médico puede registrar una nueva atención para ella. Se mantiene "atención -> cita Atendida"; "cita Atendida -> atención" se acepta como estado transitorio. | BD: `fk_ad_atencion` y `fk_receta_atencion` CASCADE; Trig: `trg_atencion_bi` |
| S41 | El script de datos de prueba fija la variable de sesión `@carga_datos = 1` para poder insertar citas históricas (pasadas y en estados finales). Solo desactiva las reglas que dependen del momento actual; las estructurales se validan siempre. La aplicación nunca fija esta variable. | Trig |
| S42 | Los médicos se crean con el procedimiento `sp_crear_medico` (una transacción: Persona + Medico + 1 a 3 especialidades + >=1 centro). Si el RUT ya existe como persona (por ejemplo, un paciente), se reutiliza esa persona y conserva su e-mail y contraseña: así un paciente pasa a ser también médico. | Proc + PHP |
| S43 | Todos los usuarios de prueba tienen la contraseña `Salud2026`, guardada como hash bcrypt generado con `password_hash()` de PHP. | Datos + README |
| S44 | E-mail de acceso de los usuarios de prueba: pacientes `nombre.apellido@correo.cl` (con un número si se repite); médicos, su e-mail institucional `@saludusm.cl`; administrador `admin@saludusm.cl`. | Datos + README |
| S45 | El ranking de medicamentos por especialidad y la edad promedio por diagnóstico (consultas 8 y 9 de la T1) no son funcionalidades de la T2. Los datos permiten calcularlas, pero no se crean objetos SQL ni secciones para ellas (Aclaración 6 de T2). | Doc |

### Aplicación PHP: infraestructura

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S46 | Una persona que además es médico o administrador no puede eliminar su cuenta desde el rol paciente (perdería sus otros accesos); debe gestionarlo la administración. | PHP |
| S47 | Si alguien se registra con el RUT de una cuenta de paciente eliminada (persona sin credenciales y solo con rol paciente), la cuenta se **reactiva**: se actualizan sus datos y credenciales y conserva su historial. | PHP (`auth/registro.php`) |
| S48 | Los e-mails se guardan en minúsculas. El RUT se guarda sin puntos, con guion y K mayúscula. | PHP + BD (`ascii_bin`, CHECK) |
| S49 | Teléfono opcional de 8 a 11 dígitos (con `+` inicial opcional); nombre completo de 3 a 100 caracteres (letras, espacios, `'`, `.`, `-`); fecha de nacimiento real, no futura y desde 1900-01-01. | PHP (+ trigger de fecha no futura) |
| S50 | La sesión termina con el logout, al cerrar el navegador o tras unos 24 minutos sin actividad (recolección de sesiones de PHP, `session.gc_maxlifetime` = 1440 s por defecto; la aplicación no cambia ese valor). No hay límite de intentos de login (fuera del alcance de la tarea). El logout se hace solo por POST con token CSRF. | PHP (configuración por defecto de PHP) |
| S51 | Configuración de la conexión solo en `PHP/config/config.php`. Por defecto usa XAMPP: `127.0.0.1:3306`, usuario `root` sin contraseña, base `salud_usm`; se puede cambiar con las variables de entorno `SALUDUSM_DB_*`. Requiere PHP 8.1 o superior. | Doc + config |
| S52 | Política de errores: el usuario ve mensajes comprensibles (los `SIGNAL` de los triggers se muestran tal cual; UNIQUE, CHECK y FK se traducen por el nombre de la restricción); SQL, trazas y credenciales solo van al log del servidor. Una BD caída muestra HTTP 503. | PHP (`mensaje_error_bd`, `set_exception_handler`) |
| S53 | Bootstrap 5.3.3 se incluye localmente en `PHP/assets/` (licencia MIT): la interfaz funciona sin conexión a internet. | Archivos |
| S54 | El script SQL fija `SET NAMES utf8mb4` al inicio, para que las tildes no se corrompan aunque el cliente use otra codificación por defecto. | Script SQL |
| S55 | En cada petición a una página protegida se revalida en la BD que la cuenta siga activa (con credenciales) y conserve el rol activo; si no, la sesión se cierra. | PHP (`requerir_login`) |

### Módulo Paciente

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S56 | La sesión SQL usa la misma zona horaria que PHP (`America/Santiago`): al conectar se ejecuta `SET time_zone = '<desfase actual>'`. Así `NOW()` y `CURDATE()` de triggers, procedimientos y consultas coinciden con la hora del usuario aunque el servidor MySQL esté en otra zona (p. ej. UTC). | PHP (`includes/db.php`) |
| S57 | La búsqueda de médicos es común a los tres roles (T2 3.4.1); solo el paciente ve el botón "Agendar". Busca por fragmento del nombre (sin distinguir mayúsculas ni tildes, por la collation) y/o por una especialidad del catálogo. `%` y `_` se buscan como texto, no como comodines. | PHP (`buscar/medicos.php`) + View `v_directorio_medicos` |
| S58 | Al agendar solo se ofrecen especialidades con al menos un médico, centros donde atiende algún médico de esa especialidad y médicos que tienen la especialidad **y** atienden en el centro. Si queda un solo médico posible, se preselecciona. El propio paciente nunca aparece como médico (S11). | PHP (`includes/citas.php`) + Trig |
| S59 | Un paciente puede tener varias citas futuras (incluso con el mismo médico o especialidad, o el mismo día), siempre que no coincidan en fecha y hora. | BD: `uq_cita_paciente_hora` |
| S60 | Una cita de hoy cuya hora ya pasó no se puede cancelar ni reprogramar (ya no es "futura"), y tampoco la cancela el procedimiento (S13): queda para que el médico la marque Atendida o No Asistió. Al día siguiente, si sigue Reservada o Confirmada, pasa a Cancelada. | PHP (`SQL_MODIFICABLE`) + Trig + Proc |
| S61 | Cancelar exige una página de confirmación (GET) y un POST con token CSRF. No se registra el motivo ni quién canceló (el modelo no tiene esos atributos). | PHP |
| S62 | El paciente puede ver en cualquier momento el comprobante de cada una de sus citas (Vista 1 de la T1), con datos leídos de la BD. | PHP (`paciente/cita.php`) + View `v_cita_detalle` |
| S63 | El historial del paciente muestra solo citas con atención registrada, de la más reciente a la más antigua. Una cita Atendida cuya atención fue eliminada (S40) no aparece hasta que el médico registre una nueva. | PHP (`paciente/historial.php`) + View `v_historial_clinico` |
| S64 | Cuando un bloque no se puede reservar, el motivo informado se elige en este orden: fecha u hora no válida -> hora ya pasada -> día sin atención o fuera del plazo de 90 días -> hora fuera de la grilla de bloques -> el paciente ya tiene una cita a esa hora -> el médico ya tiene una cita a esa hora. | PHP (`validar_bloque()`) |

### Módulo Médico

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S65 | Al registrar una atención, se exige un primer diagnóstico del catálogo (se guardan juntos en una transacción), porque toda atención tiene al menos uno (S26). Los demás diagnósticos y la receta se agregan después desde la ficha. | PHP (`medico/atencion.php`, transacción) + Trig `trg_ad_bd` |
| S66 | El CRUD de atención incluye editar el motivo y las observaciones, y eliminar la atención completa (sus diagnósticos y recetas se borran en cascada, S40). Las observaciones vacías se guardan como NULL. | PHP + BD (FK CASCADE) |
| S67 | Límites de los campos clínicos: motivo hasta 255 caracteres (el del esquema), observaciones hasta 4.000, medicamento y dosis hasta 100 (texto libre, sin catálogo de medicamentos, como en la T1), días de tratamiento entero entre 1 y 365 (la BD exige > 0). | PHP (`errores_atencion()`, `errores_receta()`) + BD (`ck_receta_dias`) |
| S68 | La ficha de una atención la puede **ver** su autor y cualquier médico que tenga al paciente entre sus pacientes (S29); solo el autor la **modifica** (S28). Para cualquier otro médico, la ficha responde 404. | PHP (`atencion_visible()`, `atencion_propia()`) |
| S69 | Al marcar una cita como Atendida, la aplicación lleva directo al formulario de atención. No es obligatorio registrarla en ese momento: las citas Atendidas sin atención aparecen como pendientes en el Inicio del médico. | PHP |
| S70 | La agenda muestra todas las citas del día seleccionado, también las Canceladas y No Asistió, ordenadas por hora (en caso de empate, por número de cita). Solo se pueden consultar las citas del médico de la sesión. | PHP (`medico/agenda.php`) |
| S71 | La barra de búsqueda (T2 3.4.1) busca el texto en el nombre del médico **o** en el nombre de sus especialidades ("cardio" encuentra a los cardiólogos). El selector de especialidad del catálogo se mantiene como filtro adicional. | PHP (`buscar/medicos.php`) |

### Módulo Administrador y búsqueda avanzada

| ID | Supuesto | Dónde se garantiza |
|---|---|---|
| S72 | Código de centro: de 2 a 10 caracteres (letras, dígitos o guion), único y guardado en mayúsculas. Nombre del centro: de 3 a 100 caracteres. La región no se elige por separado: se deduce de la comuna (3FN), y el selector agrupa las comunas por región. | PHP (`errores_centro()`) + BD (`uq_centro_codigo`, FK a Comuna) |
| S73 | Al crear un médico, el administrador define su e-mail institucional y una contraseña inicial (política S16). Si la persona aún no tiene cuenta, ese e-mail institucional es también su e-mail de acceso (S44). Si el RUT ya existe como persona con cuenta (por ejemplo, un paciente), se reutiliza y conserva su nombre, su e-mail de acceso y su contraseña (S42). Al editar un médico, el administrador puede restablecer su contraseña. | PHP (`admin/medico_form.php`) + Proc `sp_crear_medico` |
| S74 | Solo se elimina un médico **sin citas** (S21). Si la persona tiene además otro rol (paciente o administrador), solo se le quita el rol de médico y conserva su cuenta. Si solo era médico, se elimina la persona completa. En ambos casos sus asignaciones se borran en cascada. | PHP (transacción) + BD (`fk_cita_medico` RESTRICT, cascadas) |
| S75 | El RUT de un médico no se modifica. Sus especialidades y centros se agregan o quitan uno a uno desde su ficha, y cada cambio se valida por separado (máximo 3 y mínimo 1 especialidad, al menos 1 centro, no se quita una asignación con citas futuras, S23). No hay edición masiva. | PHP (`admin/asignacion.php`) + Trig `trg_me_bi`, `trg_me_bd`, `trg_mc_bd` |
| S76 | Búsqueda avanzada: las fechas son inclusivas por día (desde las 00:00 del día "desde" hasta las 23:59 del día "hasta") y deben estar entre 2000 y 2099. Si un filtro no es válido, se muestra el error y no se ejecuta la búsqueda. Los resultados van de la cita más reciente a la más antigua, en páginas de 50. El filtro de previsión usa la previsión **actual** del paciente, porque el modelo no guarda su historial. Para el médico, el filtro de médico queda fijo en él mismo; el paciente puede filtrar por cualquier médico, pero siempre dentro de sus propias citas. | PHP (`buscar/citas.php`) + View `v_cita_detalle` |
| S77 | Panel de gestión: el total de citas de cada centro cuenta todos los estados. También se muestra el total de la red. Si hay diagnósticos empatados con el 5.° lugar que quedan fuera por el desempate por código (S31), el panel los nombra. | View `v_panel_centros` (+ Func), View `v_top5_diagnosticos` |

## Objetos SQL avanzados

| Tipo | Objeto | Uso en la aplicación |
|---|---|---|
| Function | `fn_validar_rut(rut)` | Valida el dígito verificador (módulo 11); la usan los triggers de Persona. |
| Function | `fn_porcentaje_inasistencia(idCentro)` | % de citas No Asistió de un centro; la usa la view `v_panel_centros`. |
| Trigger | `trg_persona_bi/bu`, `trg_paciente_bi/bu` | RUT válido; fecha de nacimiento no futura. |
| Trigger | `trg_cita_bi/bu` | Al reservar o reprogramar: el bloque es futuro y está dentro de 90 días, el médico tiene la especialidad y atiende en el centro, y las transiciones de estado son válidas. |
| Trigger | `trg_atencion_bi/bu`, `trg_ad_bd` | Solo se registra atención en citas Atendidas; no se quita el último diagnóstico. |
| Trigger | `trg_me_bi/bu/bd`, `trg_mc_bu/bd` | Máximo 3 especialidades; no se quita la última especialidad o el último centro, ni una asignación con citas futuras. |
| Procedure | `sp_cancelar_citas_vencidas` | Citas vencidas (ver la sección correspondiente). |
| Procedure | `sp_bloques_disponibles` | Bloques libres de un médico en un día, para agendar y reprogramar. |
| Procedure | `sp_crear_medico` | Alta de un médico en una transacción (persona, especialidades, centros). |
| View | `v_cita_detalle` | Mis citas, agenda, comprobante, búsqueda avanzada. |
| View | `v_directorio_medicos` | Búsqueda de médicos. |
| View | `v_historial_clinico` | Historial del paciente y de los pacientes del médico. |
| View | `v_panel_centros`, `v_top5_diagnosticos` | Panel de gestión. |
