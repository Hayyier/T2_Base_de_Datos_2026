Tarea 2 - INF-239 Bases de Datos: PHP + MySQL (SaludUSM)

Integrantes:
    - Antonio Aguilar Lolas (202210538-0)
    - Cristopher Martinez Sanchez (202210527-5)

Repositorio GitHub: (pendiente)


=====================================================================
1. ESTRUCTURA DE LA ENTREGA
=====================================================================
    BD/salud_usm.sql          Script unico: crea la base salud_usm normalizada
                              (tablas, function, triggers, procedimientos, views)
                              y carga los datos de prueba.
    PDF/                      Normalizacion 3FN (pendiente).
    PHP/                      Aplicacion web (pendiente).
    Pruebas/pruebas_reglas.sql  Auxiliar para la defensa: verificaciones de
                              integridad e intentos de violar cada regla de
                              negocio (no modifica la base: termina en ROLLBACK).


=====================================================================
2. REQUISITOS
=====================================================================
    - MySQL 8.0.16 o superior, o MariaDB 10.4 o superior (XAMPP).
      El script se probo completo en MySQL 8.0.46 y MariaDB 10.11.14.
      Se requiere al menos esa version porque las restricciones CHECK
      solo se aplican desde MySQL 8.0.16.
    - phpMyAdmin o el cliente de consola mysql/mariadb.


=====================================================================
3. INSTRUCCIONES DE EJECUCION (BASE DE DATOS)
=====================================================================
    Opcion A - phpMyAdmin:
        Importar > seleccionar BD/salud_usm.sql > Continuar.
        (El script crea por si mismo la base salud_usm; si ya existe, la borra
        y la vuelve a crear.)

    Opcion B - consola:
        mysql -u root -p < BD/salud_usm.sql

    Pruebas de reglas (opcional, para la defensa):
        mysql -u root -p --force salud_usm < Pruebas/pruebas_reglas.sql
        Las pruebas marcadas "DEBE FALLAR" muestran el mensaje del trigger o
        de la restriccion que impide la accion.

    IMPORTANTE: las fechas de las citas de prueba son RELATIVAS al dia en que
    se ejecuta el script (ver supuesto S32). Se recomienda cargar la base el
    mismo dia de la revision para que la agenda "de hoy" tenga citas.


=====================================================================
4. USUARIOS DE PRUEBA
=====================================================================
    Contrasena de TODOS los usuarios de prueba: Salud2026
    Se puede iniciar sesion con el RUT o con el e-mail.

    Rol            RUT          E-mail                        Caso que permite probar
    -------------  -----------  ----------------------------  ---------------------------------------------
    Administrador  12345678-5   admin@saludusm.cl             Panel de gestion, CRUD de centros y medicos
    Medico         7980815-6    marcela.cordero@saludusm.cl   3 especialidades (Medicina General, Cardiologia,
                                                              Oftalmologia) y 2 centros (CM03, CM06); 7 citas
                                                              el dia de carga para la agenda
    Medico y       11433012-4   rodrigo.marti@saludusm.cl     Persona con dos roles (retroalimentacion T1):
    Paciente                                                  medico con 3 especialidades y 2 centros, y
                                                              paciente con historial y una cita proxima
    Medico         16234567-2   paula.navarro@saludusm.cl     Medico sin citas (se puede eliminar)
    Paciente       7302450-1    mario.contreras@correo.cl     Citas proximas e historicas, historial y recetas
    Paciente       25100011-5   tomas.rojas@correo.cl         Paciente menor de edad (Pediatria)
    Paciente       8735650-7    antonia.contreras@correo.cl   Paciente sin citas (eliminar cuenta, Mis citas vacio)

    Todos los pacientes y medicos de los datos de prueba tienen la misma
    contrasena. Pacientes: e-mail nombre.apellido@correo.cl (con un numero si
    se repite); medicos: su
    e-mail institucional @saludusm.cl.


=====================================================================
5. OBJETOS SQL Y DONDE SE USAN
=====================================================================
    FUNCTION  fn_validar_rut(rut)               Valida formato y digito verificador.
                                                Registro de pacientes (3.3) y trigger de Persona.
    FUNCTION  fn_porcentaje_inasistencia(id)    % de No Asistio por centro. Panel (3.4.5).
    PROCEDURE sp_cancelar_citas_vencidas(OUT n) Citas vencidas a Cancelada (3.6).
    PROCEDURE sp_bloques_disponibles(med, fecha, paciente)
                                                Bloques libres al agendar/reprogramar (3.4.4, 3.6).
    TRIGGERS  trg_cita_bi / trg_cita_bu         Reglas de crear, reprogramar y cambiar estado (3.6).
              trg_atencion_bi / trg_atencion_bu Atencion solo para cita Atendida (3.7).
              trg_ad_bd                         Atencion con al menos 1 diagnostico (3.7).
              trg_me_bi / trg_me_bd / trg_me_bu Especialidades del medico: 1 a 3, sin citas futuras (3.8).
              trg_mc_bd / trg_mc_bu             Centros del medico: al menos 1, sin citas futuras (3.8).
              trg_persona_bi / trg_persona_bu   RUT valido y no modificable (3.3).
              trg_paciente_bi / trg_paciente_bu Fecha de nacimiento no futura (3.3).
    VIEWS     v_cita_detalle                    Mis citas (3.4.2), Agenda (3.4.3), Busqueda avanzada (3.5),
                                                comprobante de reserva (Vista 1).
              v_panel_centros, v_top5_diagnosticos  Panel de gestion (3.4.5).
              v_directorio_medicos              Barra de busqueda de medicos (3.4.1).
              v_historial_clinico               Historial clinico y ficha de atencion (3.7, Vista 2).


=====================================================================
6. CAMBIOS AL MODELO DE LA TAREA 1
=====================================================================
    1. Superclase Persona (RUT, nombre, e-mail, contrasena) especializada en
       Paciente, Medico y Administrador (retroalimentacion: un medico puede
       ser paciente). Especializacion solapada y total.
    2. Normalizacion 3FN: la region dependia de la comuna (transitiva). Se
       crean Region y Comuna; Centro y Paciente referencian idComuna.
    3. Centro incorpora codigoCentro (codigo interno unico, ej. CM01).
    4. El paciente tiene e-mail (Persona.email) para el registro y el login.
       El medico conserva su e-mail institucional (Medico.emailInstitucional).
    5. Migracion a MySQL: FK declaradas a nivel de tabla, INT AUTO_INCREMENT,
       DATETIME, InnoDB y utf8mb4.
    6. Las reglas de negocio que dependen de otras tablas (especialidad y
       centro del medico, atencion solo si Atendida, 1 a 3 especialidades,
       al menos 1 centro) quedan implementadas con triggers.
    7. Los catalogos Prevision y EstadoCita tienen ids fijos y la logica
       compara por id: 1 Reservada, 2 Confirmada, 3 Atendida, 4 No Asistio,
       5 Cancelada / 1 Fonasa, 2 Isapre, 3 Particular.
    8. Datos de prueba corregidos respecto de la T1: no hay citas Atendidas
       ni No Asistio en el futuro, Pediatria solo tiene pacientes menores de
       edad, Ginecologia solo pacientes de sexo F, no hay medicamentos
       repetidos en una misma receta, y el top 5 de diagnosticos no tiene
       empate en el 5to lugar.


=====================================================================
7. SUPUESTOS
=====================================================================
Agenda y citas
    S1.  Cada bloque horario dura 30 minutos.
    S2.  Horario de atencion: lunes a sabado de 08:00 a 18:00 (el ultimo
         bloque comienza a las 17:30). Es el mismo para todos los medicos y
         centros; no se modelan turnos, vacaciones ni feriados.
    S3.  Solo se agenda o reprograma hacia bloques futuros, con un maximo de
         90 dias de anticipacion.
    S4.  Un medico no puede tener dos citas activas a la misma hora en NINGUN
         centro (la regla de la T1 decia "en el mismo centro"; se aplica una
         version mas estricta, porque un medico no puede estar en dos lugares
         a la vez).
    S5.  Una cita Cancelada libera su bloque: el mismo horario puede volver a
         reservarse.
    S6.  Una cita nueva siempre se crea en estado Reservada.
    S7.  Reprogramar solo cambia la fecha y hora (mismo medico, centro y
         especialidad) y la cita vuelve a estado Reservada. Para cambiar de
         medico, centro o especialidad se cancela y se agenda una nueva cita.
    S8.  Cancelar cambia el estado a Cancelada; la cita no se borra.
    S9.  El paciente puede reprogramar o cancelar solo sus citas Reservadas o
         Confirmadas cuya fecha y hora no hayan pasado.
    S10. Estados que puede asignar el medico a sus citas:
           - Confirmada: solo desde Reservada y si la cita no esta vencida.
           - Atendida o No Asistio: desde Reservada o Confirmada, solo cuando
             la hora de la cita ya llego.
           - Una cita Atendida o No Asistio solo puede corregirse entre esos
             dos estados, y una Atendida con atencion registrada no cambia.
           - Ninguna cita vuelve a Reservada (salvo al reprogramar) y una
             Cancelada no se reactiva. El medico no cancela citas.
    S11. Un medico no puede agendarse una cita consigo mismo como paciente.
    S12. "Proximas" son las citas con fecha y hora igual o posterior al
         momento actual; "historicas", las anteriores (en cualquier estado).

Citas vencidas
    S13. Una cita esta vencida si esta Reservada o Confirmada y su FECHA es
         anterior a hoy. Las citas de hoy no se cancelan, para que el medico
         pueda marcarlas Atendida o No Asistio durante el dia.
    S14. La actualizacion se ejecuta con el procedimiento
         sp_cancelar_citas_vencidas en cada inicio de sesion y al abrir Mis
         citas, la Agenda, la Busqueda avanzada y el Panel de gestion.

Cuentas y usuarios
    S15. Formato de RUT: 7 u 8 digitos sin puntos, guion y digito verificador
         (0-9 o K mayuscula). Se valida el digito verificador (modulo 11).
    S16. Contrasena: minimo 8 caracteres, con al menos una mayuscula, una
         minuscula y un digito. Se guarda cifrada con password_hash (bcrypt).
    S17. El login acepta RUT o e-mail. Si la persona tiene mas de un rol
         (por ejemplo medico y paciente), elige con cual entrar.
    S18. El registro desde el login rechaza un RUT ya registrado (aunque sea
         de un medico). Las personas con mas de un rol se crean desde los
         datos o por el administrador.
    S19. El RUT no se puede modificar. El paciente puede editar el resto de
         sus datos (nombre, e-mail, telefono, comuna, prevision, contrasena).
         El medico edita nombre, e-mail y contrasena; sus especialidades y
         centros solo los gestiona el administrador.
    S20. Eliminar la cuenta de paciente borra en cascada todas sus citas
         (futuras e historicas) junto con sus atenciones, diagnosticos y
         recetas. Si la persona tambien es medico o administrador, solo se
         elimina su rol de paciente y conserva sus otros roles.

Administracion
    S21. No se puede eliminar un medico que tenga citas (de cualquier estado),
         para conservar el historial clinico. Un medico sin citas se elimina
         junto con sus asignaciones de especialidades y centros.
    S22. No se puede eliminar un centro que tenga citas. Un centro sin citas se
         elimina junto con sus asignaciones de medicos.
    S23. No se puede quitar a un medico una especialidad o un centro si tiene
         citas futuras (Reservadas o Confirmadas) asociadas. Si solo tiene
         citas pasadas, se permite. Tampoco se puede quitar su ultima
         especialidad ni su ultimo centro.
    S24. Un centro o una especialidad pueden existir sin medicos asignados, y
         puede haber pacientes sin citas (se derogan los supuestos 1, 2 y 5
         de la Tarea 1).

Atenciones
    S25. Los diagnosticos se eligen del catalogo CIE-10 precargado. "Editar"
         un diagnostico de una atencion significa reemplazar el codigo
         asignado; el catalogo no se modifica desde la atencion.
    S26. Toda atencion tiene al menos un diagnostico (supuesto 10 de la T1):
         no se puede quitar el ultimo. El motivo de consulta es obligatorio y
         las observaciones son opcionales.
    S27. Una atencion puede tener cero o mas lineas de receta, y no puede
         repetir el mismo medicamento en dos lineas.
    S28. La atencion solo la puede registrar o editar el medico de la cita,
         sin limite de tiempo. Su fecha es la fecha de la cita.
    S29. El historial que ve el medico corresponde a "sus pacientes": los que
         tienen al menos una cita con el. Ve su historial completo de
         atenciones (con cualquier medico).

Panel de gestion
    S30. El porcentaje de inasistencia de un centro es citas No Asistio /
         total de citas del centro (todos los estados), igual que en la
         consulta 4 de la T1. Un centro sin citas muestra 0 %.
    S31. Si dos diagnosticos empatan en frecuencia, el top 5 los ordena por
         codigo.

Datos de prueba
    S32. Las fechas de las citas de prueba se calculan respecto del dia de
         carga del script (@hoy, o el lunes siguiente si se carga en
         domingo): siempre existen citas de hoy, proximas, historicas y
         algunas vencidas para probar el procedimiento.
