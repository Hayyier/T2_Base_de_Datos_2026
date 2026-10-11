SET NAMES utf8mb4;   -- el script contiene tildes
CREATE DATABASE IF NOT EXISTS salud_usm
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE salud_usm;

-- 1. Ubicacion (normalizacion 3FN: comuna -> region)
CREATE TABLE Region (
    idRegion     INT         NOT NULL AUTO_INCREMENT,
    nombreRegion VARCHAR(60) NOT NULL,
    CONSTRAINT pk_region        PRIMARY KEY (idRegion),
    CONSTRAINT uq_region_nombre UNIQUE (nombreRegion)
) ENGINE=InnoDB;

CREATE TABLE Comuna (
    idComuna     INT         NOT NULL AUTO_INCREMENT,
    nombreComuna VARCHAR(60) NOT NULL,
    idRegion     INT         NOT NULL,
    CONSTRAINT pk_comuna        PRIMARY KEY (idComuna),
    CONSTRAINT uq_comuna_nombre UNIQUE (nombreComuna),
    CONSTRAINT fk_comuna_region FOREIGN KEY (idRegion) REFERENCES Region (idRegion)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 2. Catalogos (dominios fijos).
CREATE TABLE Prevision (
    idPrevision     INT         NOT NULL,
    nombrePrevision VARCHAR(20) NOT NULL,
    CONSTRAINT pk_prevision        PRIMARY KEY (idPrevision),
    CONSTRAINT uq_prevision_nombre UNIQUE (nombrePrevision)
) ENGINE=InnoDB;

CREATE TABLE EstadoCita (
    idEstadoCita     INT         NOT NULL,
    nombreEstadoCita VARCHAR(20) NOT NULL,
    CONSTRAINT pk_estadocita        PRIMARY KEY (idEstadoCita),
    CONSTRAINT uq_estadocita_nombre UNIQUE (nombreEstadoCita)
) ENGINE=InnoDB;

CREATE TABLE Especialidad (
    idEspecialidad     INT         NOT NULL AUTO_INCREMENT,
    nombreEspecialidad VARCHAR(50) NOT NULL,
    CONSTRAINT pk_especialidad        PRIMARY KEY (idEspecialidad),
    CONSTRAINT uq_especialidad_nombre UNIQUE (nombreEspecialidad)
) ENGINE=InnoDB;

CREATE TABLE Diagnostico (
    codigoDiagnostico VARCHAR(10)  NOT NULL,   -- codigo CIE-10 (ej. J06, I10)
    descripcion       VARCHAR(150) NOT NULL,
    CONSTRAINT pk_diagnostico PRIMARY KEY (codigoDiagnostico)
) ENGINE=InnoDB;

-- Valores fijos de los dominios
INSERT INTO Prevision (idPrevision, nombrePrevision) VALUES
    (1, 'Fonasa'),
    (2, 'Isapre'),
    (3, 'Particular');

INSERT INTO EstadoCita (idEstadoCita, nombreEstadoCita) VALUES
    (1, 'Reservada'),
    (2, 'Confirmada'),
    (3, 'Atendida'),
    (4, 'No Asistió'),
    (5, 'Cancelada');

-- 3. Personas (retroalimentacion T1)
-- Paciente, Medico y Administrador
CREATE TABLE Persona (
    RUT            VARCHAR(10)  CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    nombreCompleto VARCHAR(100) NOT NULL,
    email          VARCHAR(254) NULL,       -- correo de acceso (login); NULL = sin cuenta
    passwordHash   VARCHAR(255) NULL,       -- resultado de password_hash() de PHP; NULL = sin cuenta
    CONSTRAINT pk_persona        PRIMARY KEY (RUT),
    CONSTRAINT uq_persona_email  UNIQUE (email),
    -- formato XXXXXXXX-X (7 u 8 digitos, guion, DV 0-9 o K); el DV se valida despues
    CONSTRAINT ck_persona_rut    CHECK (RUT REGEXP '^[0-9]{7,8}-[0-9K]$'),
    CONSTRAINT ck_persona_email  CHECK (email LIKE '%_@_%._%'),
    -- ambas credenciales existen o ninguna (cuenta activa / sin cuenta)
    CONSTRAINT ck_persona_credenciales CHECK ((email IS NULL) = (passwordHash IS NULL))
) ENGINE=InnoDB;

CREATE TABLE Paciente (
    RUTPaciente     VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    fechaNacimiento DATE        NOT NULL,
    sexo            VARCHAR(4)  NOT NULL,
    telefono        VARCHAR(12) NULL,
    idComuna        INT         NOT NULL,   -- comuna de residencia
    idPrevision     INT         NOT NULL,
    CONSTRAINT pk_paciente           PRIMARY KEY (RUTPaciente),
    CONSTRAINT ck_paciente_sexo      CHECK (sexo IN ('M', 'F', 'Otro')),
    CONSTRAINT fk_paciente_persona   FOREIGN KEY (RUTPaciente) REFERENCES Persona (RUT)
        ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_paciente_comuna    FOREIGN KEY (idComuna) REFERENCES Comuna (idComuna)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_paciente_prevision FOREIGN KEY (idPrevision) REFERENCES Prevision (idPrevision)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Medico (
    RUTMedico          VARCHAR(10)  CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    emailInstitucional VARCHAR(254) NOT NULL,
    CONSTRAINT pk_medico         PRIMARY KEY (RUTMedico),
    CONSTRAINT uq_medico_email   UNIQUE (emailInstitucional),
    CONSTRAINT ck_medico_email   CHECK (emailInstitucional LIKE '%_@_%._%'),
    CONSTRAINT fk_medico_persona FOREIGN KEY (RUTMedico) REFERENCES Persona (RUT)
        ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE Administrador (
    RUTAdministrador VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    CONSTRAINT pk_administrador         PRIMARY KEY (RUTAdministrador),
    CONSTRAINT fk_administrador_persona FOREIGN KEY (RUTAdministrador) REFERENCES Persona (RUT)
        ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- 4. Centros
CREATE TABLE Centro (
    idCentro     INT          NOT NULL AUTO_INCREMENT,
    codigoCentro VARCHAR(10)  NOT NULL,
    nombreCentro VARCHAR(100) NOT NULL,
    idComuna     INT          NOT NULL,
    CONSTRAINT pk_centro        PRIMARY KEY (idCentro),
    CONSTRAINT uq_centro_codigo UNIQUE (codigoCentro),
    CONSTRAINT fk_centro_comuna FOREIGN KEY (idComuna) REFERENCES Comuna (idComuna)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 5. Relaciones N:M del medico
CREATE TABLE Medico_Especialidad (
    RUTMedico      VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    idEspecialidad INT         NOT NULL,
    CONSTRAINT pk_medico_especialidad PRIMARY KEY (RUTMedico, idEspecialidad),
    CONSTRAINT fk_me_medico       FOREIGN KEY (RUTMedico) REFERENCES Medico (RUTMedico)
        ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_me_especialidad FOREIGN KEY (idEspecialidad) REFERENCES Especialidad (idEspecialidad)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Medico_Centro (
    RUTMedico VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    idCentro  INT         NOT NULL,
    CONSTRAINT pk_medico_centro PRIMARY KEY (RUTMedico, idCentro),
    CONSTRAINT fk_mc_medico FOREIGN KEY (RUTMedico) REFERENCES Medico (RUTMedico)
        ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT fk_mc_centro FOREIGN KEY (idCentro) REFERENCES Centro (idCentro)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 6. Citas
-- slotActivo vale NULL cuando la cita esta Cancelada (id 5) y 1 en otro
-- caso. Como los NULL no chocan en un UNIQUE, una cita cancelada libera
-- su bloque horario y las activas no pueden repetirse.
CREATE TABLE Cita (
    idCita         INT         NOT NULL AUTO_INCREMENT,
    fechaHoraCita  DATETIME    NOT NULL,
    RUTPaciente    VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    RUTMedico      VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    idCentro       INT         NOT NULL,
    idEspecialidad INT         NOT NULL,
    idEstadoCita   INT         NOT NULL DEFAULT 1,   -- toda cita nace Reservada
    slotActivo     TINYINT GENERATED ALWAYS AS (CASE WHEN idEstadoCita = 5 THEN NULL ELSE 1 END) STORED,
    CONSTRAINT pk_cita PRIMARY KEY (idCita),
    -- Un medico no tiene dos citas activas a la misma hora (en ningun centro)
    CONSTRAINT uq_cita_medico_hora   UNIQUE (RUTMedico, fechaHoraCita, slotActivo),
    -- Un paciente no tiene dos citas activas a la misma hora
    CONSTRAINT uq_cita_paciente_hora UNIQUE (RUTPaciente, fechaHoraCita, slotActivo),
    -- Un medico no puede ser paciente de su propia cita
    CONSTRAINT ck_cita_paciente_medico CHECK (RUTPaciente <> RUTMedico),
    -- Bloques de 30 min, lunes a sabado, 08:00-17:30
    CONSTRAINT ck_cita_bloque CHECK (
        MINUTE(fechaHoraCita) IN (0, 30) AND SECOND(fechaHoraCita) = 0
        AND HOUR(fechaHoraCita) BETWEEN 8 AND 17
        AND DAYOFWEEK(fechaHoraCita) <> 1
    ),
    -- Un paciente con citas no se borra fisicamente,
    -- su historial clinico se conserva
    CONSTRAINT fk_cita_paciente FOREIGN KEY (RUTPaciente) REFERENCES Paciente (RUTPaciente)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_cita_medico FOREIGN KEY (RUTMedico) REFERENCES Medico (RUTMedico)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_cita_centro FOREIGN KEY (idCentro) REFERENCES Centro (idCentro)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cita_especialidad FOREIGN KEY (idEspecialidad) REFERENCES Especialidad (idEspecialidad)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cita_estado FOREIGN KEY (idEstadoCita) REFERENCES EstadoCita (idEstadoCita)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB;

-- Agenda por rango de fechas / busqueda avanzada por fecha
CREATE INDEX ix_cita_fecha ON Cita (fechaHoraCita);
-- Citas vencidas (estado Reservada/Confirmada con fecha pasada) y filtros por estado
CREATE INDEX ix_cita_estado_fecha ON Cita (idEstadoCita, fechaHoraCita);

-- 7. Atenciones, diagnosticos y recetas
CREATE TABLE Atencion (
    idAtencion    INT          NOT NULL AUTO_INCREMENT,
    idCita        INT          NOT NULL,
    motivo        VARCHAR(255) NOT NULL,
    observaciones TEXT         NULL,
    CONSTRAINT pk_atencion      PRIMARY KEY (idAtencion),
    CONSTRAINT uq_atencion_cita UNIQUE (idCita),   -- a lo mas una atencion por cita
    CONSTRAINT fk_atencion_cita FOREIGN KEY (idCita) REFERENCES Cita (idCita)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Atencion_Diagnostico (
    idAtencion        INT         NOT NULL,
    codigoDiagnostico VARCHAR(10) NOT NULL,
    CONSTRAINT pk_atencion_diagnostico PRIMARY KEY (idAtencion, codigoDiagnostico),
    CONSTRAINT fk_ad_atencion    FOREIGN KEY (idAtencion) REFERENCES Atencion (idAtencion)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ad_diagnostico FOREIGN KEY (codigoDiagnostico) REFERENCES Diagnostico (codigoDiagnostico)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Receta (
    idReceta        INT          NOT NULL AUTO_INCREMENT,
    idAtencion      INT          NOT NULL,
    medicamento     VARCHAR(100) NOT NULL,
    dosis           VARCHAR(100) NOT NULL,
    diasTratamiento INT          NOT NULL,
    CONSTRAINT pk_receta             PRIMARY KEY (idReceta),
    -- supuesto S-C: un medicamento no se repite en la misma atencion
    CONSTRAINT uq_receta_medicamento UNIQUE (idAtencion, medicamento),
    CONSTRAINT ck_receta_dias        CHECK (diasTratamiento > 0),
    CONSTRAINT fk_receta_atencion    FOREIGN KEY (idAtencion) REFERENCES Atencion (idAtencion)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 8. Funciones
DROP FUNCTION IF EXISTS fn_validar_rut;
DROP FUNCTION IF EXISTS fn_porcentaje_inasistencia;

DELIMITER $$

-- ---------------------------------------------------------------------
-- fn_validar_rut(rut): 1 si el RUT tiene formato XXXXXXXX-X (7 u 8 digitos,
-- guion, DV 0-9 o K mayuscula) Y su digito verificador es correcto
-- (modulo 11); 0 en cualquier otro caso (incluido NULL).
-- Uso: registro de pacientes y alta de medicos, desde PHP con SELECT 
-- fn_validar_rut y desde trg_persona_bi.
-- El CHECK ck_persona_rut solo valida el formato; el DV requiere este calculo.
-- ---------------------------------------------------------------------
CREATE FUNCTION fn_validar_rut(p_rut VARCHAR(12))
RETURNS TINYINT
DETERMINISTIC
NO SQL
BEGIN
    DECLARE v_len      INT;
    DECLARE v_cuerpo   VARCHAR(8);
    DECLARE v_dv       CHAR(1);
    DECLARE v_suma     INT DEFAULT 0;
    DECLARE v_factor   INT DEFAULT 2;
    DECLARE v_i        INT;
    DECLARE v_c        CHAR(1);
    DECLARE v_resto    INT;
    DECLARE v_esperado CHAR(1);

    IF p_rut IS NULL THEN
        RETURN 0;
    END IF;
    SET v_len = CHAR_LENGTH(p_rut);
    IF v_len NOT IN (9, 10) OR SUBSTRING(p_rut, v_len - 1, 1) <> '-' THEN
        RETURN 0;
    END IF;

    SET v_cuerpo = SUBSTRING(p_rut, 1, v_len - 2);
    SET v_dv     = SUBSTRING(p_rut, v_len, 1);

    -- DV: digito o 'K' mayuscula (se compara por codigo ASCII para no
    -- depender de la collation de la conexion)
    IF NOT (ASCII(v_dv) BETWEEN 48 AND 57 OR ASCII(v_dv) = 75) THEN
        RETURN 0;
    END IF;

    -- cuerpo de derecha a izquierda con factores 2,3,4,5,6,7,2,3...
    SET v_i = CHAR_LENGTH(v_cuerpo);
    WHILE v_i >= 1 DO
        SET v_c = SUBSTRING(v_cuerpo, v_i, 1);
        IF ASCII(v_c) NOT BETWEEN 48 AND 57 THEN
            RETURN 0;
        END IF;
        SET v_suma   = v_suma + (ASCII(v_c) - 48) * v_factor;
        SET v_factor = IF(v_factor = 7, 2, v_factor + 1);
        SET v_i      = v_i - 1;
    END WHILE;

    SET v_resto    = 11 - (v_suma MOD 11);
    SET v_esperado = CASE v_resto WHEN 11 THEN '0' WHEN 10 THEN 'K' ELSE CHAR(48 + v_resto) END;

    RETURN IF(ASCII(v_dv) = ASCII(v_esperado), 1, 0);
END$$

-- ---------------------------------------------------------------------
-- fn_porcentaje_inasistencia(idCentro): porcentaje (1 decimal) de citas en
-- estado No Asistio (id 4) sobre el total de citas del centro, en todos los
-- estados
-- Casos limite: centro sin citas -> 0.0; centro inexistente -> 0.0.
-- Uso: panel de gestion del administrador, via v_panel_centros.
-- ---------------------------------------------------------------------
CREATE FUNCTION fn_porcentaje_inasistencia(p_idCentro INT)
RETURNS DECIMAL(5,1)
READS SQL DATA
BEGIN
    DECLARE v_total INT DEFAULT 0;
    DECLARE v_no    INT DEFAULT 0;

    SELECT COUNT(*), COALESCE(SUM(idEstadoCita = 4), 0)
      INTO v_total, v_no
      FROM Cita
     WHERE idCentro = p_idCentro;

    IF v_total = 0 THEN
        RETURN 0.0;
    END IF;
    RETURN ROUND(100 * v_no / v_total, 1);
END$$

DELIMITER ;

-- 9. Triggers
-- Variable de sesion @carga_datos: el script de datos de prueba la fija en 1
-- para poder insertar citas historicas (pasadas y en estados finales). Solo
-- desactiva las reglas que dependen del momento actual (no agendar en el
-- pasado, estado inicial, transiciones). Las reglas estructurales
-- (especialidad, centro, choques, maximos) se validan SIEMPRE. La aplicacion
-- PHP nunca fija esta variable.

DROP TRIGGER IF EXISTS trg_persona_bi;
DROP TRIGGER IF EXISTS trg_persona_bu;
DROP TRIGGER IF EXISTS trg_paciente_bi;
DROP TRIGGER IF EXISTS trg_paciente_bu;
DROP TRIGGER IF EXISTS trg_cita_bi;
DROP TRIGGER IF EXISTS trg_cita_bu;
DROP TRIGGER IF EXISTS trg_atencion_bi;
DROP TRIGGER IF EXISTS trg_atencion_bu;
DROP TRIGGER IF EXISTS trg_ad_bd;
DROP TRIGGER IF EXISTS trg_me_bi;
DROP TRIGGER IF EXISTS trg_me_bu;
DROP TRIGGER IF EXISTS trg_me_bd;
DROP TRIGGER IF EXISTS trg_mc_bu;
DROP TRIGGER IF EXISTS trg_mc_bd;

DELIMITER $$

-- Persona: digito verificador del RUT. El formato lo valida el CHECK.
CREATE TRIGGER trg_persona_bi BEFORE INSERT ON Persona
FOR EACH ROW
BEGIN
    IF fn_validar_rut(NEW.RUT) = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'RUT invalido: use el formato XXXXXXXX-X (sin puntos) con digito verificador correcto.';
    END IF;
END$$

CREATE TRIGGER trg_persona_bu BEFORE UPDATE ON Persona
FOR EACH ROW
BEGIN
    IF NEW.RUT <> OLD.RUT AND fn_validar_rut(NEW.RUT) = 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'RUT invalido: use el formato XXXXXXXX-X (sin puntos) con digito verificador correcto.';
    END IF;
END$$

-- Paciente: la fecha de nacimiento no puede ser futura
CREATE TRIGGER trg_paciente_bi BEFORE INSERT ON Paciente
FOR EACH ROW
BEGIN
    IF NEW.fechaNacimiento > CURDATE() THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La fecha de nacimiento no puede ser futura.';
    END IF;
END$$

CREATE TRIGGER trg_paciente_bu BEFORE UPDATE ON Paciente
FOR EACH ROW
BEGIN
    IF NEW.fechaNacimiento > CURDATE() THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La fecha de nacimiento no puede ser futura.';
    END IF;
END$$

-- Cita - crear
CREATE TRIGGER trg_cita_bi BEFORE INSERT ON Cita
FOR EACH ROW
BEGIN
    -- El medico debe poseer la especialidad solicitada
    IF NOT EXISTS (SELECT 1 FROM Medico_Especialidad
                    WHERE RUTMedico = NEW.RUTMedico AND idEspecialidad = NEW.idEspecialidad) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El medico seleccionado no posee la especialidad solicitada.';
    END IF;

    -- El medico debe atender en el centro seleccionado
    IF NOT EXISTS (SELECT 1 FROM Medico_Centro
                    WHERE RUTMedico = NEW.RUTMedico AND idCentro = NEW.idCentro) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El medico seleccionado no atiende en el centro elegido.';
    END IF;

    IF NEW.idEstadoCita <> 5 THEN
        IF EXISTS (SELECT 1 FROM Cita
                    WHERE RUTMedico = NEW.RUTMedico AND fechaHoraCita = NEW.fechaHoraCita
                      AND idEstadoCita <> 5) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El medico ya tiene una cita en esa fecha y hora (sobre-agendamiento).';
        END IF;
        IF EXISTS (SELECT 1 FROM Cita
                    WHERE RUTPaciente = NEW.RUTPaciente AND fechaHoraCita = NEW.fechaHoraCita
                      AND idEstadoCita <> 5) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El paciente ya tiene una cita en esa fecha y hora.';
        END IF;
    END IF;

    -- reglas que dependen del momento actual
    IF @carga_datos IS NULL THEN
        IF NEW.idEstadoCita <> 1 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Una cita nueva debe crearse en estado Reservada.';
        END IF;
        IF NEW.fechaHoraCita <= NOW() THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'No se puede agendar una cita en una fecha u hora pasada.';
        END IF;
        IF NEW.fechaHoraCita > NOW() + INTERVAL 90 DAY THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Solo se puede agendar con hasta 90 dias de anticipacion.';
        END IF;
    END IF;
END$$

-- Cita - modificar: reprogramacion (solo fecha/hora) y cambios de estado
CREATE TRIGGER trg_cita_bu BEFORE UPDATE ON Cita
FOR EACH ROW
BEGIN
    DECLARE v_cambia_fecha  TINYINT DEFAULT 0;
    DECLARE v_cambia_estado TINYINT DEFAULT 0;

    SET v_cambia_fecha  = (NEW.fechaHoraCita <> OLD.fechaHoraCita);
    SET v_cambia_estado = (NEW.idEstadoCita <> OLD.idEstadoCita);

    IF @carga_datos IS NULL AND (NEW.RUTPaciente <> OLD.RUTPaciente OR NEW.RUTMedico <> OLD.RUTMedico
        OR NEW.idCentro <> OLD.idCentro OR NEW.idEspecialidad <> OLD.idEspecialidad) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Solo se puede reprogramar la fecha y hora; para cambiar medico, centro o especialidad cancele y agende una nueva cita.';
    END IF;

    IF v_cambia_fecha THEN
        -- ---- reprogramacion ----
        IF @carga_datos IS NULL THEN
            IF OLD.idEstadoCita NOT IN (1, 2) OR OLD.fechaHoraCita <= NOW() THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Solo se pueden reprogramar citas Reservadas o Confirmadas cuya fecha no haya pasado.';
            END IF;
            IF NEW.fechaHoraCita <= NOW() THEN
                SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La nueva fecha y hora debe ser futura.';
            END IF;
            IF NEW.fechaHoraCita > NOW() + INTERVAL 90 DAY THEN
                SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Solo se puede agendar con hasta 90 dias de anticipacion.';
            END IF;
            -- una cita reprogramada vuelve a quedar Reservada
            SET NEW.idEstadoCita = 1;
        END IF;
    ELSEIF v_cambia_estado AND @carga_datos IS NULL THEN
        -- ---- cambio de estado ----
        IF OLD.idEstadoCita = 5 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una cita Cancelada no puede cambiar de estado.';
        END IF;
        IF OLD.idEstadoCita = 3 AND EXISTS (SELECT 1 FROM Atencion WHERE idCita = OLD.idCita) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'La cita tiene una atencion registrada y debe permanecer en estado Atendida.';
        END IF;
        IF OLD.idEstadoCita IN (3, 4) AND NEW.idEstadoCita NOT IN (3, 4) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Una cita Atendida o No Asistio solo puede corregirse entre esos dos estados.';
        END IF;
        IF NEW.idEstadoCita = 1 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una cita no puede volver a estado Reservada.';
        END IF;
        IF NEW.idEstadoCita = 2 AND (OLD.idEstadoCita <> 1 OR DATE(OLD.fechaHoraCita) < CURDATE()) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Solo se pueden confirmar citas Reservadas que no esten vencidas.';
        END IF;
        IF NEW.idEstadoCita IN (3, 4) AND OLD.fechaHoraCita > NOW() THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Solo se puede marcar Atendida o No Asistio una cita cuya hora ya llego.';
        END IF;
        -- cancelar: Reservada/Confirmada futura (paciente) o vencida de un dia anterior (procedimiento)
        IF NEW.idEstadoCita = 5 AND (OLD.idEstadoCita NOT IN (1, 2)
            OR (OLD.fechaHoraCita <= NOW() AND DATE(OLD.fechaHoraCita) >= CURDATE())) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Solo se pueden cancelar citas Reservadas o Confirmadas cuya fecha no haya pasado.';
        END IF;
    END IF;

    -- choques para la fila resultante (al reprogramar)
    IF NEW.idEstadoCita <> 5 AND (v_cambia_fecha OR OLD.idEstadoCita = 5) THEN
        IF EXISTS (SELECT 1 FROM Cita
                    WHERE RUTMedico = NEW.RUTMedico AND fechaHoraCita = NEW.fechaHoraCita
                      AND idEstadoCita <> 5 AND idCita <> NEW.idCita) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El medico ya tiene una cita en esa fecha y hora (sobre-agendamiento).';
        END IF;
        IF EXISTS (SELECT 1 FROM Cita
                    WHERE RUTPaciente = NEW.RUTPaciente AND fechaHoraCita = NEW.fechaHoraCita
                      AND idEstadoCita <> 5 AND idCita <> NEW.idCita) THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'El paciente ya tiene una cita en esa fecha y hora.';
        END IF;
    END IF;
END$$

-- Atencion: solo para citas en estado Atendida
CREATE TRIGGER trg_atencion_bi BEFORE INSERT ON Atencion
FOR EACH ROW
BEGIN
    IF NOT EXISTS (SELECT 1 FROM Cita WHERE idCita = NEW.idCita AND idEstadoCita = 3) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Solo se puede registrar una atencion para una cita en estado Atendida.';
    END IF;
END$$

CREATE TRIGGER trg_atencion_bu BEFORE UPDATE ON Atencion
FOR EACH ROW
BEGIN
    IF NEW.idCita <> OLD.idCita THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una atencion no puede cambiarse a otra cita.';
    END IF;
END$$

-- Toda atencion conserva al menos un diagnostico. Los borrados en
-- cascada al eliminar la atencion no disparan triggers, por lo que no se bloquean.
CREATE TRIGGER trg_ad_bd BEFORE DELETE ON Atencion_Diagnostico
FOR EACH ROW
BEGIN
    IF (SELECT COUNT(*) FROM Atencion_Diagnostico WHERE idAtencion = OLD.idAtencion) <= 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La atencion debe conservar al menos un diagnostico.';
    END IF;
END$$

-- Especialidades del medico: maximo 3, minimo 1, no quitar con citas futuras.
CREATE TRIGGER trg_me_bi BEFORE INSERT ON Medico_Especialidad
FOR EACH ROW
BEGIN
    IF (SELECT COUNT(*) FROM Medico_Especialidad WHERE RUTMedico = NEW.RUTMedico) >= 3 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Un medico puede tener como maximo 3 especialidades.';
    END IF;
END$$

CREATE TRIGGER trg_me_bu BEFORE UPDATE ON Medico_Especialidad
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Para cambiar una especialidad, quitela y agregue la nueva.';
END$$

CREATE TRIGGER trg_me_bd BEFORE DELETE ON Medico_Especialidad
FOR EACH ROW
BEGIN
    IF (SELECT COUNT(*) FROM Medico_Especialidad WHERE RUTMedico = OLD.RUTMedico) <= 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Un medico debe tener al menos 1 especialidad.';
    END IF;
    IF EXISTS (SELECT 1 FROM Cita
                WHERE RUTMedico = OLD.RUTMedico AND idEspecialidad = OLD.idEspecialidad
                  AND idEstadoCita IN (1, 2) AND fechaHoraCita >= NOW()) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede quitar la especialidad: el medico tiene citas futuras en ella.';
    END IF;
END$$

-- Centros del medico: minimo 1, no quitar con citas futuras
CREATE TRIGGER trg_mc_bu BEFORE UPDATE ON Medico_Centro
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Para cambiar un centro, quitelo y agregue el nuevo.';
END$$

CREATE TRIGGER trg_mc_bd BEFORE DELETE ON Medico_Centro
FOR EACH ROW
BEGIN
    IF (SELECT COUNT(*) FROM Medico_Centro WHERE RUTMedico = OLD.RUTMedico) <= 1 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Un medico debe atender en al menos 1 centro.';
    END IF;
    IF EXISTS (SELECT 1 FROM Cita
                WHERE RUTMedico = OLD.RUTMedico AND idCentro = OLD.idCentro
                  AND idEstadoCita IN (1, 2) AND fechaHoraCita >= NOW()) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede quitar el centro: el medico tiene citas futuras en el.';
    END IF;
END$$

DELIMITER ;


-- 10. Procedimientos almacenados

DROP PROCEDURE IF EXISTS sp_cancelar_citas_vencidas;
DROP PROCEDURE IF EXISTS sp_bloques_disponibles;
DROP PROCEDURE IF EXISTS sp_crear_medico;

DELIMITER $$

-- sp_cancelar_citas_vencidas(OUT p_cantidad)
-- Citas vencidas (consulta 10 de la T1): pasa a Cancelada toda cita
-- Reservada o Confirmada cuya FECHA es anterior a hoy. Las citas de hoy no
-- se cancelan, para que el medico pueda marcarlas Atendida o No Asistio.
-- Se invoca desde PHP al iniciar sesion y al abrir Mis citas, Agenda,
-- Busqueda avanzada y Panel. Devuelve cuantas citas cancelo.
CREATE PROCEDURE sp_cancelar_citas_vencidas(OUT p_cantidad INT)
BEGIN
    UPDATE Cita
       SET idEstadoCita = 5
     WHERE idEstadoCita IN (1, 2)
       AND fechaHoraCita < CURDATE();     -- equivale a DATE(fechaHoraCita) < CURDATE() y usa ix_cita_estado_fecha
    SET p_cantidad = ROW_COUNT();
END$$

-- sp_bloques_disponibles(p_rutMedico, p_fecha, p_rutPaciente)
-- Bloques de 30 minutos disponibles de un medico en una fecha (08:00-17:30,
-- lunes a sabado), excluyendo los ocupados por el medico y, si se indica,
-- los ocupados por el paciente. Uso: seccion Agendar hora / reprogramar.
CREATE PROCEDURE sp_bloques_disponibles(
    IN p_rutMedico   VARCHAR(10),
    IN p_fecha       DATE,
    IN p_rutPaciente VARCHAR(10)
)
BEGIN
    WITH RECURSIVE bloques (inicio) AS (
        SELECT TIMESTAMP(p_fecha, '08:00:00')
        UNION ALL
        SELECT inicio + INTERVAL 30 MINUTE FROM bloques
         WHERE inicio < TIMESTAMP(p_fecha, '17:30:00')
    )
    SELECT b.inicio AS fechaHora,
           TIME_FORMAT(b.inicio, '%H:%i') AS hora
      FROM bloques b
     WHERE DAYOFWEEK(p_fecha) <> 1
       AND b.inicio > NOW()
       AND b.inicio <= NOW() + INTERVAL 90 DAY
       AND NOT EXISTS (SELECT 1 FROM Cita c
                        WHERE c.RUTMedico = p_rutMedico
                          AND c.fechaHoraCita = b.inicio AND c.idEstadoCita <> 5)
       AND (p_rutPaciente IS NULL OR NOT EXISTS (
                       SELECT 1 FROM Cita c
                        WHERE c.RUTPaciente = p_rutPaciente
                          AND c.fechaHoraCita = b.inicio AND c.idEstadoCita <> 5))
     ORDER BY b.inicio;
END$$

-- sp_crear_medico(...)
-- CRUD de medicos
-- Problema: el minimo de 1 especialidad y 1 centro no se puede declarar ni
-- validar fila a fila (al insertar el Medico todavia no tiene asignaciones).
-- Este procedimiento crea en UNA transaccion la Persona (si no existe), el
-- Medico y todas sus asignaciones; si algo falla, deshace todo.
-- Parametros:
--   p_rut, p_nombre, p_email, p_passwordHash : datos de Persona (si la persona
--       ya existe, p.ej. un paciente, se reutiliza; si no tenia cuenta, se le
--       asignan p_email y p_passwordHash)
--   p_emailInstitucional                      : e-mail institucional del medico
--   p_especialidades : ids separados por coma, ej. '1,2'  (1 a 3, distintos)
--   p_centros        : ids separados por coma, ej. '3,6'  (al menos 1, distintos)
-- Nota: inicia su propia transaccion; PHP debe llamarlo fuera de otra
-- transaccion (START TRANSACTION confirma implicitamente la anterior).
CREATE PROCEDURE sp_crear_medico(
    IN p_rut                VARCHAR(10),
    IN p_nombre             VARCHAR(100),
    IN p_email              VARCHAR(254),
    IN p_passwordHash       VARCHAR(255),
    IN p_emailInstitucional VARCHAR(254),
    IN p_especialidades     VARCHAR(100),
    IN p_centros            VARCHAR(500)
)
BEGIN
    DECLARE v_lista VARCHAR(500);
    DECLARE v_item  VARCHAR(20);
    DECLARE v_n     INT;
    DECLARE v_msg   VARCHAR(255);

    -- ante cualquier error: deshacer todo y propagar el mensaje original
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    -- validaciones previas (sin tocar datos)
    IF p_especialidades IS NULL OR TRIM(p_especialidades) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Debe asignar al menos 1 especialidad al medico.';
    END IF;
    IF p_centros IS NULL OR TRIM(p_centros) = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Debe asignar al menos 1 centro al medico.';
    END IF;
    SET v_n = CHAR_LENGTH(p_especialidades) - CHAR_LENGTH(REPLACE(p_especialidades, ',', '')) + 1;
    IF v_n > 3 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un medico puede tener como maximo 3 especialidades.';
    END IF;
    IF EXISTS (SELECT 1 FROM Medico WHERE RUTMedico = p_rut) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ya existe un medico con ese RUT.';
    END IF;

    START TRANSACTION;

    -- Persona: se crea solo si no existe (un paciente puede pasar a ser tambien medico)
    IF NOT EXISTS (SELECT 1 FROM Persona WHERE RUT = p_rut) THEN
        INSERT INTO Persona (RUT, nombreCompleto, email, passwordHash)
        VALUES (p_rut, p_nombre, p_email, p_passwordHash);
    ELSE
        -- Persona existente SIN cuenta (paciente que elimino su
        -- cuenta): se le asignan las credenciales recibidas
        UPDATE Persona SET email = p_email, passwordHash = p_passwordHash
         WHERE RUT = p_rut AND passwordHash IS NULL;
    END IF;

    INSERT INTO Medico (RUTMedico, emailInstitucional) VALUES (p_rut, p_emailInstitucional);

    -- especialidades
    SET v_lista = REPLACE(p_especialidades, ' ', '');
    WHILE v_lista <> '' DO
        SET v_item  = SUBSTRING_INDEX(v_lista, ',', 1);
        SET v_lista = IF(LOCATE(',', v_lista) > 0, SUBSTRING(v_lista, LOCATE(',', v_lista) + 1), '');
        IF v_item NOT REGEXP '^[0-9]+$' OR NOT EXISTS (SELECT 1 FROM Especialidad WHERE idEspecialidad = v_item) THEN
            SET v_msg = CONCAT('La especialidad ', v_item, ' no existe.');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
        IF EXISTS (SELECT 1 FROM Medico_Especialidad WHERE RUTMedico = p_rut AND idEspecialidad = v_item) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Hay especialidades repetidas en la lista.';
        END IF;
        INSERT INTO Medico_Especialidad (RUTMedico, idEspecialidad) VALUES (p_rut, v_item);
    END WHILE;

    -- centros
    SET v_lista = REPLACE(p_centros, ' ', '');
    WHILE v_lista <> '' DO
        SET v_item  = SUBSTRING_INDEX(v_lista, ',', 1);
        SET v_lista = IF(LOCATE(',', v_lista) > 0, SUBSTRING(v_lista, LOCATE(',', v_lista) + 1), '');
        IF v_item NOT REGEXP '^[0-9]+$' OR NOT EXISTS (SELECT 1 FROM Centro WHERE idCentro = v_item) THEN
            SET v_msg = CONCAT('El centro ', v_item, ' no existe.');
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
        END IF;
        IF EXISTS (SELECT 1 FROM Medico_Centro WHERE RUTMedico = p_rut AND idCentro = v_item) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Hay centros repetidos en la lista.';
        END IF;
        INSERT INTO Medico_Centro (RUTMedico, idCentro) VALUES (p_rut, v_item);
    END WHILE;

    COMMIT;
END$$

DELIMITER ;

-- 11. Vistas
DROP VIEW IF EXISTS v_cita_detalle;
DROP VIEW IF EXISTS v_panel_centros;
DROP VIEW IF EXISTS v_top5_diagnosticos;
DROP VIEW IF EXISTS v_directorio_medicos;
DROP VIEW IF EXISTS v_historial_clinico;

-- Detalle completo de cada cita (Vista 1 / comprobante). Base de Mis citas,
-- Agenda del medico y Busqueda avanzada: expone los ids para filtrar.
CREATE VIEW v_cita_detalle AS
SELECT c.idCita,
       c.fechaHoraCita,
       DATE(c.fechaHoraCita)                 AS fecha,
       TIME_FORMAT(c.fechaHoraCita, '%H:%i') AS hora,
       c.idEstadoCita,
       ec.nombreEstadoCita,
       c.RUTPaciente,
       pp.nombreCompleto                     AS nombrePaciente,
       pa.telefono                           AS telefonoPaciente,
       pa.idPrevision,
       pr.nombrePrevision,
       c.RUTMedico,
       pm.nombreCompleto                     AS nombreMedico,
       m.emailInstitucional                  AS emailMedico,
       c.idEspecialidad,
       es.nombreEspecialidad,
       c.idCentro,
       ce.codigoCentro,
       ce.nombreCentro,
       co.idComuna,
       co.nombreComuna,
       re.idRegion,
       re.nombreRegion,
       a.idAtencion
  FROM Cita c
  JOIN EstadoCita   ec ON ec.idEstadoCita   = c.idEstadoCita
  JOIN Paciente     pa ON pa.RUTPaciente    = c.RUTPaciente
  JOIN Persona      pp ON pp.RUT            = pa.RUTPaciente
  JOIN Prevision    pr ON pr.idPrevision    = pa.idPrevision
  JOIN Medico       m  ON m.RUTMedico       = c.RUTMedico
  JOIN Persona      pm ON pm.RUT            = m.RUTMedico
  JOIN Especialidad es ON es.idEspecialidad = c.idEspecialidad
  JOIN Centro       ce ON ce.idCentro       = c.idCentro
  JOIN Comuna       co ON co.idComuna       = ce.idComuna
  JOIN Region       re ON re.idRegion       = co.idRegion
  LEFT JOIN Atencion a ON a.idCita          = c.idCita;

-- Panel de gestion: total de citas y % de inasistencia por centro
-- (incluye centros sin citas).
CREATE VIEW v_panel_centros AS
SELECT ce.idCentro,
       ce.codigoCentro,
       ce.nombreCentro,
       co.nombreComuna,
       re.nombreRegion,
       COUNT(c.idCita)                         AS totalCitas,
       COALESCE(SUM(c.idEstadoCita = 4), 0)    AS citasNoAsistio,
       fn_porcentaje_inasistencia(ce.idCentro) AS porcentajeInasistencia
  FROM Centro ce
  JOIN Comuna co ON co.idComuna = ce.idComuna
  JOIN Region re ON re.idRegion = co.idRegion
  LEFT JOIN Cita c ON c.idCentro = ce.idCentro
 GROUP BY ce.idCentro, ce.codigoCentro, ce.nombreCentro, co.nombreComuna, re.nombreRegion;

-- Top 5 de diagnosticos mas frecuentes de la red (desempate por codigo).
CREATE VIEW v_top5_diagnosticos AS
SELECT d.codigoDiagnostico,
       d.descripcion,
       COUNT(*) AS vecesRegistrado
  FROM Atencion_Diagnostico ad
  JOIN Diagnostico d ON d.codigoDiagnostico = ad.codigoDiagnostico
 GROUP BY d.codigoDiagnostico, d.descripcion
 ORDER BY vecesRegistrado DESC, d.codigoDiagnostico ASC
 LIMIT 5;

-- Directorio de medicos para la barra de busqueda: especialidades y centros
-- concatenados (busqueda por trozo de nombre o de especialidad).
CREATE VIEW v_directorio_medicos AS
SELECT m.RUTMedico,
       p.nombreCompleto AS nombreMedico,
       m.emailInstitucional,
       (SELECT GROUP_CONCAT(e.nombreEspecialidad ORDER BY e.nombreEspecialidad SEPARATOR ' | ')
          FROM Medico_Especialidad me
          JOIN Especialidad e ON e.idEspecialidad = me.idEspecialidad
         WHERE me.RUTMedico = m.RUTMedico) AS especialidades,
       (SELECT GROUP_CONCAT(ce.nombreCentro ORDER BY ce.nombreCentro SEPARATOR ' | ')
          FROM Medico_Centro mc
          JOIN Centro ce ON ce.idCentro = mc.idCentro
         WHERE mc.RUTMedico = m.RUTMedico) AS centros
  FROM Medico m
  JOIN Persona p ON p.RUT = m.RUTMedico;

-- Historial clinico: una fila por atencion con sus diagnosticos y recetas
-- (Vista 2 / ficha de atencion e historial del paciente).
CREATE VIEW v_historial_clinico AS
SELECT a.idAtencion,
       a.idCita,
       c.fechaHoraCita       AS fechaAtencion,
       c.RUTPaciente,
       pp.nombreCompleto     AS nombrePaciente,
       pa.fechaNacimiento,
       pa.sexo,
       c.RUTMedico,
       pm.nombreCompleto     AS nombreMedico,
       m.emailInstitucional  AS emailMedico,
       c.idEspecialidad,
       es.nombreEspecialidad,
       c.idCentro,
       ce.nombreCentro,
       a.motivo,
       a.observaciones,
       (SELECT GROUP_CONCAT(CONCAT(d.codigoDiagnostico, ' - ', d.descripcion)
                            ORDER BY d.codigoDiagnostico SEPARATOR ' | ')
          FROM Atencion_Diagnostico ad
          JOIN Diagnostico d ON d.codigoDiagnostico = ad.codigoDiagnostico
         WHERE ad.idAtencion = a.idAtencion) AS diagnosticos,
       (SELECT GROUP_CONCAT(CONCAT(r.medicamento, ' (', r.dosis, ', ', r.diasTratamiento, ' dias)')
                            ORDER BY r.idReceta SEPARATOR ' | ')
          FROM Receta r
         WHERE r.idAtencion = a.idAtencion) AS recetas
  FROM Atencion a
  JOIN Cita         c  ON c.idCita          = a.idCita
  JOIN Paciente     pa ON pa.RUTPaciente    = c.RUTPaciente
  JOIN Persona      pp ON pp.RUT            = pa.RUTPaciente
  JOIN Medico       m  ON m.RUTMedico       = c.RUTMedico
  JOIN Persona      pm ON pm.RUT            = m.RUTMedico
  JOIN Especialidad es ON es.idEspecialidad = c.idEspecialidad
  JOIN Centro       ce ON ce.idCentro       = c.idCentro;

-- 12. Datos de prueba
-- Basados en los datos de la Tarea 1, con fechas RELATIVAS al dia de carga:
-- @hoy es el dia de carga (o el lunes siguiente si se carga en domingo) y
-- @lunes el lunes de esa semana. Asi siempre existen citas de hoy, proximas,
-- historicas y vencidas, sin importar cuando se revise la tarea.
-- Contrasena de TODOS los usuarios de prueba: Salud2026
-- @carga_datos = 1 permite insertar citas historicas.
SET @carga_datos = 1;
SET @hoy   = IF(DAYOFWEEK(CURDATE()) = 1, CURDATE() + INTERVAL 1 DAY, CURDATE());
SET @lunes = @hoy - INTERVAL WEEKDAY(@hoy) DAY;
SET @pass  = '$2y$10$/4PsGIkbE6RGXsQ4VrLYoezrXuAl6vN/oInfnMlR/E.HaFPpgBvai';

-- Ubicacion
INSERT INTO Region (idRegion, nombreRegion) VALUES
    (1, 'Metropolitana'),
    (2, 'Valparaíso'),
    (3, 'Biobío'),
    (4, 'Los Lagos'),
    (5, 'La Araucanía'),
    (6, 'Coquimbo'),
    (7, 'O''Higgins'),
    (8, 'Maule'),
    (9, 'Antofagasta');

INSERT INTO Comuna (idComuna, nombreComuna, idRegion) VALUES
    (1, 'Providencia', 1),
    (2, 'Santiago', 1),
    (3, 'Las Condes', 1),
    (4, 'Ñuñoa', 1),
    (5, 'Maipú', 1),
    (6, 'Valparaíso', 2),
    (7, 'Viña del Mar', 2),
    (8, 'Quilpué', 2),
    (9, 'Concepción', 3),
    (10, 'Talcahuano', 3),
    (11, 'Chiguayante', 3),
    (12, 'Puerto Montt', 4),
    (13, 'Osorno', 4),
    (14, 'Puerto Varas', 4),
    (15, 'Temuco', 5),
    (16, 'Coquimbo', 6),
    (17, 'La Serena', 6),
    (18, 'Rancagua', 7),
    (19, 'Talca', 8),
    (20, 'Antofagasta', 9);

-- Catalogos de datos (Prevision y EstadoCita ya los carga el DDL de P3)
INSERT INTO Especialidad (idEspecialidad, nombreEspecialidad) VALUES
    (1, 'Medicina General'),
    (2, 'Cardiología'),
    (3, 'Pediatría'),
    (4, 'Traumatología'),
    (5, 'Dermatología'),
    (6, 'Ginecología'),
    (7, 'Oftalmología'),
    (8, 'Otorrinolaringología'),
    (9, 'Psiquiatría'),
    (10, 'Endocrinología');

INSERT INTO Diagnostico (codigoDiagnostico, descripcion) VALUES
    ('E11', 'Diabetes mellitus tipo 2'),
    ('E66', 'Obesidad'),
    ('F41', 'Trastornos de ansiedad'),
    ('H10', 'Conjuntivitis'),
    ('I10', 'Hipertension esencial (primaria)'),
    ('J00', 'Rinofaringitis aguda (resfriado comun)'),
    ('J02', 'Faringitis aguda'),
    ('J06', 'Infeccion aguda de las vias respiratorias superiores'),
    ('J45', 'Asma'),
    ('K21', 'Enfermedad del reflujo gastroesofagico'),
    ('L20', 'Dermatitis atopica'),
    ('M25', 'Otros trastornos articulares'),
    ('M54', 'Dorsalgia'),
    ('N39', 'Otros trastornos del sistema urinario'),
    ('R51', 'Cefalea');

-- Personas (superclase) y subtipos
INSERT INTO Persona (RUT, nombreCompleto, email, passwordHash) VALUES
    ('10194119-1', 'Felipe Bravo Aguilar', 'felipe.bravo@correo.cl', @pass),
    ('10282565-9', 'Diego Miranda Sepulveda', 'diego.miranda@correo.cl', @pass),
    ('10742460-1', 'Diego Vega Diaz', 'diego.vega@correo.cl', @pass),
    ('10940891-3', 'Diego Aguilar Munoz', 'diego.aguilar@correo.cl', @pass),
    ('12172169-4', 'Diego Diaz Aldunate', 'diego.diaz@correo.cl', @pass),
    ('12313676-4', 'Daniela Soto Sepulveda', 'daniela.soto@correo.cl', @pass),
    ('12832403-8', 'Amanda Guzman Fuentes', 'amanda.guzman@correo.cl', @pass),
    ('13327906-7', 'Rocio Sepulveda Sepulveda', 'rocio.sepulveda@correo.cl', @pass),
    ('13697025-9', 'Trinidad Espinoza Pizarro', 'trinidad.espinoza@correo.cl', @pass),
    ('13764927-6', 'Andrea Gomez Sepulveda', 'andrea.gomez@correo.cl', @pass),
    ('13837869-1', 'Joaquin Gomez Pizarro', 'joaquin.gomez@correo.cl', @pass),
    ('13960920-4', 'Macarena Lagos Espinoza', 'macarena.lagos@correo.cl', @pass),
    ('14174617-0', 'Isidora Contreras Vega', 'isidora.contreras@correo.cl', @pass),
    ('14269324-0', 'Francisca Vera Silva', 'francisca.vera@correo.cl', @pass),
    ('14334531-9', 'Diego Lagos Castro', 'diego.lagos@correo.cl', @pass),
    ('14336742-8', 'Josefa Miranda Espinoza', 'josefa.miranda@correo.cl', @pass),
    ('14495860-8', 'Nicolas Contreras Reyes', 'nicolas.contreras@correo.cl', @pass),
    ('14627226-6', 'Macarena Vega Vega', 'macarena.vega@correo.cl', @pass),
    ('14920542-K', 'Alvaro Guzman Diaz', 'alvaro.guzman@correo.cl', @pass),
    ('15246860-1', 'Emilio Aguilar Gomez', 'emilio.aguilar@correo.cl', @pass),
    ('15646752-9', 'Rocio Aguilar Vera', 'rocio.aguilar@correo.cl', @pass),
    ('15727672-7', 'Rodrigo Rojas Contreras', 'rodrigo.rojas@correo.cl', @pass),
    ('16287597-3', 'Javiera Espinoza Fuentes', 'javiera.espinoza@correo.cl', @pass),
    ('16505153-K', 'Trinidad Espinoza Silva', 'trinidad.espinoza2@correo.cl', @pass),
    ('17006085-7', 'Cristian Reyes Medina', 'cristian.reyes@correo.cl', @pass),
    ('17593211-9', 'Andres Bravo Lagos', 'andres.bravo@correo.cl', @pass),
    ('17835970-3', 'Antonia Fuentes Vega', 'antonia.fuentes@correo.cl', @pass),
    ('17925013-6', 'Ignacio Bustos Lagos', 'ignacio.bustos@correo.cl', @pass),
    ('18085780-K', 'Sofia Aguilar Aguilar', 'sofia.aguilar@correo.cl', @pass),
    ('18370299-8', 'Ignacio Reyes Pizarro', 'ignacio.reyes@correo.cl', @pass),
    ('18566616-6', 'Fernanda Sepulveda Carrasco', 'fernanda.sepulveda@correo.cl', @pass),
    ('18812517-4', 'Carolina Medina Carrasco', 'carolina.medina@correo.cl', @pass),
    ('19198153-7', 'Constanza Lagos Torres', 'constanza.lagos@correo.cl', @pass),
    ('19265539-0', 'Andrea Carrasco Contreras', 'andrea.carrasco@correo.cl', @pass),
    ('19277538-8', 'Francisca Reyes Lagos', 'francisca.reyes@correo.cl', @pass),
    ('19471939-6', 'Rocio Munoz Guzman', 'rocio.munoz@correo.cl', @pass),
    ('20038242-0', 'Nicolas Contreras Diaz', 'nicolas.contreras2@correo.cl', @pass),
    ('20860207-1', 'Paula Fuentes Gomez', 'paula.fuentes@correo.cl', @pass),
    ('21029931-9', 'Gonzalo Espinoza Miranda', 'gonzalo.espinoza@correo.cl', @pass),
    ('21223484-2', 'Francisca Contreras Miranda', 'francisca.contreras@correo.cl', @pass),
    ('21793059-6', 'Camila Sepulveda Bustos', 'camila.sepulveda@correo.cl', @pass),
    ('22096865-0', 'Cristian Rojas Pizarro', 'cristian.rojas@correo.cl', @pass),
    ('22748304-0', 'Valentina Rojas Silva', 'valentina.rojas@correo.cl', @pass),
    ('22754131-8', 'Cristian Guzman Carrasco', 'cristian.guzman@correo.cl', @pass),
    ('22795716-6', 'Francisca Espinoza Aldunate', 'francisca.espinoza@correo.cl', @pass),
    ('23040017-2', 'Fernanda Reyes Miranda', 'fernanda.reyes@correo.cl', @pass),
    ('23207008-0', 'Sebastian Castro Diaz', 'sebastian.castro@correo.cl', @pass),
    ('23306972-8', 'Amanda Carrasco Rojas', 'amanda.carrasco@correo.cl', @pass),
    ('23483090-2', 'Camila Contreras Gomez', 'camila.contreras@correo.cl', @pass),
    ('23521786-4', 'Alvaro Vega Lagos', 'alvaro.vega@correo.cl', @pass),
    ('23567645-1', 'Vicente Gomez Aldunate', 'vicente.gomez@correo.cl', @pass),
    ('24186345-K', 'Andrea Munoz Aguilar', 'andrea.munoz@correo.cl', @pass),
    ('24425300-8', 'Alvaro Guzman Bravo', 'alvaro.guzman2@correo.cl', @pass),
    ('24640403-8', 'Camila Espinoza Reyes', 'camila.espinoza@correo.cl', @pass),
    ('24700576-5', 'Josefa Bravo Bustos', 'josefa.bravo@correo.cl', @pass),
    ('5038623-6', 'Paula Bustos Fuentes', 'paula.bustos@correo.cl', @pass),
    ('5340614-9', 'Andres Contreras Aguilar', 'andres.contreras@correo.cl', @pass),
    ('5999828-5', 'Joaquin Sepulveda Aldunate', 'joaquin.sepulveda@correo.cl', @pass),
    ('6077105-7', 'Josefa Miranda Fuentes', 'josefa.miranda2@correo.cl', @pass),
    ('7033823-8', 'Valentina Contreras Fuentes', 'valentina.contreras@correo.cl', @pass);
INSERT INTO Persona (RUT, nombreCompleto, email, passwordHash) VALUES
    ('7261579-4', 'Camila Vera Contreras', 'camila.vera@correo.cl', @pass),
    ('7302450-1', 'Mario Contreras Fuentes', 'mario.contreras@correo.cl', @pass),
    ('7396116-5', 'Rocio Aguilar Bravo', 'rocio.aguilar2@correo.cl', @pass),
    ('7443709-5', 'Gonzalo Espinoza Diaz', 'gonzalo.espinoza2@correo.cl', @pass),
    ('7529497-2', 'Felipe Pizarro Carrasco', 'felipe.pizarro@correo.cl', @pass),
    ('7642648-1', 'Camila Medina Carrasco', 'camila.medina@correo.cl', @pass),
    ('7644095-6', 'Amanda Fuentes Lagos', 'amanda.fuentes@correo.cl', @pass),
    ('7845266-8', 'Camila Medina Contreras', 'camila.medina2@correo.cl', @pass),
    ('8112034-K', 'Daniela Sepulveda Silva', 'daniela.sepulveda@correo.cl', @pass),
    ('8389045-2', 'Daniela Soto Reyes', 'daniela.soto2@correo.cl', @pass),
    ('8541296-5', 'Macarena Lagos Rojas', 'macarena.lagos2@correo.cl', @pass),
    ('8735650-7', 'Antonia Contreras Rojas', 'antonia.contreras@correo.cl', @pass),
    ('9011591-K', 'Sebastian Fuentes Aguilar', 'sebastian.fuentes@correo.cl', @pass),
    ('9060227-6', 'Francisco Aldunate Bravo', 'francisco.aldunate@correo.cl', @pass),
    ('9061933-0', 'Pablo Carrasco Castro', 'pablo.carrasco@correo.cl', @pass),
    ('9389579-7', 'Javiera Aguilar Vega', 'javiera.aguilar@correo.cl', @pass),
    ('9391546-1', 'Macarena Munoz Medina', 'macarena.munoz@correo.cl', @pass),
    ('9553319-1', 'Felipe Silva Diaz', 'felipe.silva@correo.cl', @pass),
    ('9793975-6', 'Benjamin Carrasco Silva', 'benjamin.carrasco@correo.cl', @pass),
    ('9986034-0', 'Daniela Munoz Carrasco', 'daniela.munoz@correo.cl', @pass),
    ('10232182-0', 'Alejandra Toledo Sandoval', 'alejandra.toledo@saludusm.cl', @pass),
    ('10730012-0', 'Luis Molina Campos', 'luis.molina@saludusm.cl', @pass),
    ('11433012-4', 'Rodrigo Marti Alarcon', 'rodrigo.marti@saludusm.cl', @pass),
    ('14044229-1', 'Angelica Alarcon Fernandez', 'angelica.alarcon@saludusm.cl', @pass),
    ('14117398-7', 'Gloria Herrera Fernandez', 'gloria.herrera@saludusm.cl', @pass),
    ('14920785-6', 'Angelica Cordero Araya', 'angelica.cordero@saludusm.cl', @pass),
    ('15509051-0', 'Sergio Fernandez Toledo', 'sergio.fernandez@saludusm.cl', @pass),
    ('16677723-2', 'Manuel Ibanez Poblete', 'manuel.ibanez@saludusm.cl', @pass),
    ('17142625-1', 'Ricardo Riquelme Godoy', 'ricardo.riquelme@saludusm.cl', @pass),
    ('17584561-5', 'Cristian Campos Escobar', 'cristian.campos@saludusm.cl', @pass),
    ('17665120-2', 'Alejandra Sandoval Godoy', 'alejandra.sandoval@saludusm.cl', @pass),
    ('17680243-K', 'Monica Toledo Bahamondes', 'monica.toledo@saludusm.cl', @pass),
    ('17727180-2', 'Jose Godoy Riquelme', 'jose.godoy@saludusm.cl', @pass),
    ('19530857-8', 'Manuel Barrera Sandoval', 'manuel.barrera@saludusm.cl', @pass),
    ('20402710-2', 'Patricio Molina Herrera', 'patricio.molina@saludusm.cl', @pass),
    ('20661724-1', 'Ricardo Zuniga Ibanez', 'ricardo.zuniga@saludusm.cl', @pass),
    ('20820721-0', 'Gustavo Molina Campos', 'gustavo.molina@saludusm.cl', @pass),
    ('21781464-2', 'Ximena Bahamondes Barrera', 'ximena.bahamondes@saludusm.cl', @pass),
    ('21895568-1', 'Hector Godoy Marti', 'hector.godoy@saludusm.cl', @pass),
    ('6358976-4', 'Ivan Fernandez Leiva', 'ivan.fernandez@saludusm.cl', @pass),
    ('6387481-7', 'Sergio Toledo Herrera', 'sergio.toledo@saludusm.cl', @pass),
    ('6468706-9', 'Pilar Zuniga Ibanez', 'pilar.zuniga@saludusm.cl', @pass),
    ('7044345-7', 'Jose Ibanez Araya', 'jose.ibanez@saludusm.cl', @pass),
    ('7351205-0', 'Jose Poblete Sandoval', 'jose.poblete@saludusm.cl', @pass),
    ('7441955-0', 'Loreto Alarcon Poblete', 'loreto.alarcon@saludusm.cl', @pass),
    ('7455421-0', 'Pilar Escobar Alarcon', 'pilar.escobar@saludusm.cl', @pass),
    ('7728987-9', 'Alejandro Fernandez Campos', 'alejandro.fernandez@saludusm.cl', @pass),
    ('7980815-6', 'Marcela Cordero Herrera', 'marcela.cordero@saludusm.cl', @pass),
    ('8169968-2', 'Rodrigo Escobar Sandoval', 'rodrigo.escobar@saludusm.cl', @pass),
    ('8532032-7', 'Luis Herrera Poblete', 'luis.herrera@saludusm.cl', @pass),
    ('25100011-5', 'Tomas Rojas Fuentes', 'tomas.rojas@correo.cl', @pass),
    ('25100148-0', 'Isidora Vera Munoz', 'isidora.vera@correo.cl', @pass),
    ('25100285-1', 'Martin Soto Carrasco', 'martin.soto@correo.cl', @pass),
    ('25100422-6', 'Florencia Lagos Diaz', 'florencia.lagos@correo.cl', @pass),
    ('25100559-1', 'Agustin Pizarro Vega', 'agustin.pizarro@correo.cl', @pass),
    ('25100696-2', 'Emilia Torres Gomez', 'emilia.torres@correo.cl', @pass),
    ('25100833-7', 'Benjamin Castro Reyes', 'benjamin.castro@correo.cl', @pass),
    ('25100970-8', 'Josefa Bravo Silva', 'josefa.bravo2@correo.cl', @pass),
    ('16234567-2', 'Paula Navarro Henriquez', 'paula.navarro@saludusm.cl', @pass),
    ('12345678-5', 'Carolina Perez Soto', 'admin@saludusm.cl', @pass);

INSERT INTO Paciente (RUTPaciente, fechaNacimiento, sexo, telefono, idComuna, idPrevision) VALUES
    ('10194119-1', '1952-12-11', 'M', '+56977491435', 18, 1),
    ('10282565-9', '1953-11-28', 'M', '+56986460539', 8, 1),
    ('10742460-1', '1979-11-23', 'M', '+56917507864', 8, 2),
    ('10940891-3', '1997-11-28', 'M', '+56931367172', 3, 2),
    ('12172169-4', '1994-05-26', 'M', '+56984593961', 16, 2),
    ('12313676-4', '1959-03-10', 'F', '+56951870192', 15, 1),
    ('12832403-8', '1999-02-18', 'Otro', '+56945652586', 5, 1),
    ('13327906-7', '1973-09-13', 'F', '+56988990506', 14, 1),
    ('13697025-9', '1975-05-15', 'F', '+56946468984', 11, 1),
    ('13764927-6', '2000-06-24', 'F', '+56978642041', 3, 2),
    ('13837869-1', '1983-11-17', 'M', '+56931682744', 19, 2),
    ('13960920-4', '1960-01-21', 'F', '+56931257157', 10, 2),
    ('14174617-0', '1963-07-07', 'F', '+56928321839', 4, 3),
    ('14269324-0', '1945-05-24', 'F', '+56998575748', 16, 1),
    ('14334531-9', '1988-05-05', 'M', NULL, 3, 1),
    ('14336742-8', '1947-12-18', 'F', NULL, 8, 1),
    ('14495860-8', '1967-05-01', 'M', '+56916109013', 2, 2),
    ('14627226-6', '1972-09-16', 'F', NULL, 6, 2),
    ('14920542-K', '1967-10-19', 'M', '+56910054484', 10, 1),
    ('15246860-1', '1971-11-07', 'M', '+56942194159', 6, 1),
    ('15646752-9', '1999-09-15', 'F', '+56973709724', 17, 2),
    ('15727672-7', '1994-12-05', 'M', '+56964707061', 11, 2),
    ('16287597-3', '1990-05-11', 'F', '+56947080140', 19, 1),
    ('16505153-K', '1951-02-21', 'F', '+56910475894', 11, 1),
    ('17006085-7', '1997-10-24', 'M', NULL, 6, 1),
    ('17593211-9', '1976-02-23', 'M', NULL, 20, 2),
    ('17835970-3', '1989-05-27', 'F', '+56913176186', 7, 2),
    ('17925013-6', '2000-01-17', 'M', '+56936445607', 12, 3),
    ('18085780-K', '1973-08-08', 'F', '+56962191530', 11, 1),
    ('18370299-8', '1996-11-27', 'M', NULL, 3, 1),
    ('18566616-6', '1948-07-19', 'F', '+56996268397', 14, 2),
    ('18812517-4', '1949-08-03', 'F', '+56964247457', 18, 1),
    ('19198153-7', '1996-11-21', 'Otro', NULL, 17, 1),
    ('19265539-0', '1960-08-05', 'F', '+56985017682', 20, 2),
    ('19277538-8', '2003-05-10', 'Otro', '+56935848226', 11, 1),
    ('19471939-6', '1960-12-10', 'F', '+56953779528', 13, 1),
    ('20038242-0', '1976-05-22', 'M', '+56981259135', 12, 2),
    ('20860207-1', '1957-11-14', 'F', '+56917270733', 3, 1),
    ('21029931-9', '1951-03-05', 'M', NULL, 4, 2),
    ('21223484-2', '1980-08-08', 'F', '+56999682738', 13, 2),
    ('21793059-6', '2000-12-11', 'F', '+56940725714', 15, 2),
    ('22096865-0', '1965-11-14', 'M', '+56989978790', 17, 2),
    ('22748304-0', '1959-02-24', 'F', '+56991178885', 7, 2),
    ('22754131-8', '1979-11-11', 'M', '+56970897765', 1, 1),
    ('22795716-6', '1959-06-27', 'Otro', '+56942329237', 15, 2),
    ('23040017-2', '1976-06-10', 'F', NULL, 10, 1),
    ('23207008-0', '1947-10-13', 'M', NULL, 10, 1),
    ('23306972-8', '2000-03-02', 'F', NULL, 2, 1),
    ('23483090-2', '2004-02-21', 'F', '+56949172995', 18, 2),
    ('23521786-4', '2005-05-02', 'M', '+56970939053', 4, 1),
    ('23567645-1', '1981-08-16', 'M', '+56916895666', 9, 1),
    ('24186345-K', '2005-12-26', 'F', NULL, 5, 1),
    ('24425300-8', '1985-05-07', 'M', '+56945650176', 13, 2),
    ('24640403-8', '1997-06-11', 'F', NULL, 11, 2),
    ('24700576-5', '1967-08-17', 'Otro', NULL, 6, 2),
    ('5038623-6', '1991-10-25', 'F', '+56916927215', 7, 1),
    ('5340614-9', '1997-08-18', 'M', '+56996691619', 16, 2),
    ('5999828-5', '1970-12-21', 'M', '+56970291817', 15, 2),
    ('6077105-7', '1985-04-21', 'F', NULL, 17, 1),
    ('7033823-8', '1969-09-15', 'F', '+56972092888', 8, 1);
INSERT INTO Paciente (RUTPaciente, fechaNacimiento, sexo, telefono, idComuna, idPrevision) VALUES
    ('7261579-4', '1980-11-16', 'F', '+56986644106', 15, 1),
    ('7302450-1', '1947-10-05', 'M', '+56925353091', 15, 1),
    ('7396116-5', '1981-08-04', 'F', '+56964023778', 9, 1),
    ('7443709-5', '1976-03-21', 'M', NULL, 3, 2),
    ('7529497-2', '1946-02-25', 'M', '+56975181648', 16, 2),
    ('7642648-1', '1961-03-22', 'F', '+56945575298', 18, 1),
    ('7644095-6', '1953-01-22', 'F', '+56920709497', 8, 1),
    ('7845266-8', '1984-04-26', 'F', '+56942862209', 16, 1),
    ('8112034-K', '1950-12-15', 'F', NULL, 13, 1),
    ('8389045-2', '1990-04-22', 'F', NULL, 4, 1),
    ('8541296-5', '2000-03-24', 'F', '+56992666356', 19, 1),
    ('8735650-7', '1958-11-24', 'F', '+56966629388', 2, 2),
    ('9011591-K', '2006-05-24', 'M', NULL, 20, 2),
    ('9060227-6', '1974-10-08', 'M', '+56917901903', 8, 1),
    ('9061933-0', '1992-02-17', 'M', '+56966379329', 16, 2),
    ('9389579-7', '1999-09-01', 'F', NULL, 5, 1),
    ('9391546-1', '1954-01-15', 'F', '+56923419256', 4, 2),
    ('9553319-1', '1986-04-15', 'M', NULL, 13, 1),
    ('9793975-6', '1999-10-13', 'M', NULL, 5, 1),
    ('9986034-0', '1993-08-11', 'F', NULL, 8, 2),
    ('25100011-5', '2014-03-12', 'M', '+56987100000', 2, 1),
    ('25100148-0', '2016-07-25', 'F', '+56987100731', 6, 2),
    ('25100285-1', '2012-11-02', 'M', '+56987101462', 9, 1),
    ('25100422-6', '2018-01-19', 'F', '+56987102193', 10, 1),
    ('25100559-1', '2015-09-08', 'M', '+56987102924', 12, 3),
    ('25100696-2', '2019-05-30', 'F', '+56987103655', 13, 2),
    ('25100833-7', '2013-12-14', 'M', '+56987104386', 1, 2),
    ('25100970-8', '2017-04-03', 'F', '+56987105117', 7, 1),
    ('11433012-4', '1978-06-21', 'M', '+56991234567', 3, 2);

INSERT INTO Medico (RUTMedico, emailInstitucional) VALUES
    ('10232182-0', 'alejandra.toledo@saludusm.cl'),
    ('10730012-0', 'luis.molina@saludusm.cl'),
    ('11433012-4', 'rodrigo.marti@saludusm.cl'),
    ('14044229-1', 'angelica.alarcon@saludusm.cl'),
    ('14117398-7', 'gloria.herrera@saludusm.cl'),
    ('14920785-6', 'angelica.cordero@saludusm.cl'),
    ('15509051-0', 'sergio.fernandez@saludusm.cl'),
    ('16677723-2', 'manuel.ibanez@saludusm.cl'),
    ('17142625-1', 'ricardo.riquelme@saludusm.cl'),
    ('17584561-5', 'cristian.campos@saludusm.cl'),
    ('17665120-2', 'alejandra.sandoval@saludusm.cl'),
    ('17680243-K', 'monica.toledo@saludusm.cl'),
    ('17727180-2', 'jose.godoy@saludusm.cl'),
    ('19530857-8', 'manuel.barrera@saludusm.cl'),
    ('20402710-2', 'patricio.molina@saludusm.cl'),
    ('20661724-1', 'ricardo.zuniga@saludusm.cl'),
    ('20820721-0', 'gustavo.molina@saludusm.cl'),
    ('21781464-2', 'ximena.bahamondes@saludusm.cl'),
    ('21895568-1', 'hector.godoy@saludusm.cl'),
    ('6358976-4', 'ivan.fernandez@saludusm.cl'),
    ('6387481-7', 'sergio.toledo@saludusm.cl'),
    ('6468706-9', 'pilar.zuniga@saludusm.cl'),
    ('7044345-7', 'jose.ibanez@saludusm.cl'),
    ('7351205-0', 'jose.poblete@saludusm.cl'),
    ('7441955-0', 'loreto.alarcon@saludusm.cl'),
    ('7455421-0', 'pilar.escobar@saludusm.cl'),
    ('7728987-9', 'alejandro.fernandez@saludusm.cl'),
    ('7980815-6', 'marcela.cordero@saludusm.cl'),
    ('8169968-2', 'rodrigo.escobar@saludusm.cl'),
    ('8532032-7', 'luis.herrera@saludusm.cl'),
    ('16234567-2', 'paula.navarro@saludusm.cl');

INSERT INTO Administrador (RUTAdministrador) VALUES
    ('12345678-5');

-- Centros y asignaciones de medicos
INSERT INTO Centro (idCentro, codigoCentro, nombreCentro, idComuna) VALUES
    (1, 'CM01', 'Centro Salud Providencia', 1),
    (2, 'CM02', 'Centro Salud Santiago', 2),
    (3, 'CM03', 'Centro Salud Valparaíso', 6),
    (4, 'CM04', 'Centro Salud Viña del Mar', 7),
    (5, 'CM05', 'Centro Salud Concepción', 9),
    (6, 'CM06', 'Centro Salud Talcahuano', 10),
    (7, 'CM07', 'Centro Salud Puerto Montt', 12),
    (8, 'CM08', 'Centro Salud Osorno', 13),
    (9, 'CM09', 'Centro Salud Temuco', 15);

INSERT INTO Medico_Especialidad (RUTMedico, idEspecialidad) VALUES
    ('10232182-0', 8),
    ('10730012-0', 10),
    ('11433012-4', 1),
    ('11433012-4', 2),
    ('11433012-4', 6),
    ('14044229-1', 3),
    ('14044229-1', 4),
    ('14044229-1', 8),
    ('14117398-7', 5),
    ('14117398-7', 8),
    ('14920785-6', 6),
    ('14920785-6', 8),
    ('15509051-0', 2),
    ('15509051-0', 4),
    ('16677723-2', 4),
    ('16677723-2', 8),
    ('16677723-2', 10),
    ('17142625-1', 5),
    ('17142625-1', 7),
    ('17142625-1', 8),
    ('17584561-5', 7),
    ('17665120-2', 2),
    ('17665120-2', 10),
    ('17680243-K', 4),
    ('17727180-2', 6),
    ('17727180-2', 8),
    ('19530857-8', 3),
    ('19530857-8', 6),
    ('20402710-2', 5),
    ('20402710-2', 10),
    ('20661724-1', 4),
    ('20820721-0', 7),
    ('21781464-2', 1),
    ('21895568-1', 1),
    ('21895568-1', 7),
    ('6358976-4', 3),
    ('6358976-4', 7),
    ('6387481-7', 1),
    ('6387481-7', 9),
    ('6468706-9', 6),
    ('6468706-9', 8),
    ('7044345-7', 6),
    ('7351205-0', 4),
    ('7441955-0', 7),
    ('7455421-0', 3),
    ('7455421-0', 10),
    ('7728987-9', 1),
    ('7728987-9', 10),
    ('7980815-6', 1),
    ('7980815-6', 2),
    ('7980815-6', 7),
    ('8169968-2', 8),
    ('8532032-7', 1),
    ('8532032-7', 8),
    ('16234567-2', 1);

INSERT INTO Medico_Centro (RUTMedico, idCentro) VALUES
    ('10232182-0', 4),
    ('10232182-0', 8),
    ('10730012-0', 3),
    ('11433012-4', 1),
    ('11433012-4', 4),
    ('14044229-1', 6),
    ('14117398-7', 3),
    ('14117398-7', 5),
    ('14920785-6', 3),
    ('15509051-0', 3),
    ('15509051-0', 6),
    ('16677723-2', 1),
    ('17142625-1', 1),
    ('17142625-1', 6),
    ('17584561-5', 7),
    ('17665120-2', 6),
    ('17665120-2', 8),
    ('17680243-K', 6),
    ('17727180-2', 2),
    ('19530857-8', 3),
    ('20402710-2', 3),
    ('20661724-1', 3),
    ('20661724-1', 6),
    ('20820721-0', 2),
    ('20820721-0', 7),
    ('21781464-2', 5),
    ('21895568-1', 1),
    ('21895568-1', 5),
    ('6358976-4', 4),
    ('6358976-4', 7),
    ('6387481-7', 2),
    ('6468706-9', 3),
    ('6468706-9', 6),
    ('7044345-7', 1),
    ('7044345-7', 2),
    ('7351205-0', 1),
    ('7441955-0', 2),
    ('7455421-0', 8),
    ('7728987-9', 6),
    ('7728987-9', 8),
    ('7980815-6', 3),
    ('7980815-6', 6),
    ('8169968-2', 5),
    ('8532032-7', 3),
    ('8532032-7', 8),
    ('16234567-2', 9);

-- Citas (estados: 1 Reservada, 2 Confirmada, 3 Atendida, 4 No Asistio, 5 Cancelada)
INSERT INTO Cita (idCita, fechaHoraCita, RUTPaciente, RUTMedico, idCentro, idEspecialidad, idEstadoCita) VALUES
    (1, TIMESTAMP(@lunes + INTERVAL 117 DAY, '11:00:00'), '15246860-1', '10730012-0', 3, 10, 2),
    (2, TIMESTAMP(@lunes + INTERVAL -27 DAY, '12:30:00'), '9389579-7', '7044345-7', 1, 6, 5),
    (3, TIMESTAMP(@lunes + INTERVAL -469 DAY, '16:00:00'), '5340614-9', '15509051-0', 3, 4, 5),
    (4, TIMESTAMP(@lunes + INTERVAL -454 DAY, '15:00:00'), '14174617-0', '7441955-0', 2, 7, 3),
    (5, TIMESTAMP(@lunes + INTERVAL -480 DAY, '09:00:00'), '14920542-K', '10232182-0', 8, 8, 3),
    (6, TIMESTAMP(@lunes + INTERVAL -249 DAY, '16:00:00'), '17835970-3', '17665120-2', 6, 2, 5),
    (7, TIMESTAMP(@lunes + INTERVAL -187 DAY, '14:00:00'), '7642648-1', '21895568-1', 1, 1, 5),
    (8, TIMESTAMP(@lunes + INTERVAL -431 DAY, '15:30:00'), '22748304-0', '11433012-4', 4, 2, 3),
    (9, TIMESTAMP(@lunes + INTERVAL -94 DAY, '11:00:00'), '22096865-0', '16677723-2', 1, 10, 4),
    (10, TIMESTAMP(@lunes + INTERVAL -144 DAY, '08:00:00'), '7845266-8', '17665120-2', 6, 2, 5),
    (11, TIMESTAMP(@lunes + INTERVAL -381 DAY, '08:00:00'), '10940891-3', '7351205-0', 1, 4, 1),
    (12, TIMESTAMP(@lunes + INTERVAL -292 DAY, '15:00:00'), '24640403-8', '14044229-1', 6, 4, 3),
    (13, TIMESTAMP(@lunes + INTERVAL -80 DAY, '14:30:00'), '14336742-8', '6468706-9', 6, 8, 5),
    (14, TIMESTAMP(@lunes + INTERVAL -464 DAY, '11:00:00'), '14627226-6', '11433012-4', 1, 1, 5),
    (15, TIMESTAMP(@lunes + INTERVAL -266 DAY, '15:00:00'), '7033823-8', '19530857-8', 3, 6, 5),
    (16, TIMESTAMP(@lunes + INTERVAL -409 DAY, '09:00:00'), '14336742-8', '7351205-0', 1, 4, 5),
    (17, TIMESTAMP(@lunes + INTERVAL -107 DAY, '13:30:00'), '14174617-0', '10232182-0', 8, 8, 3),
    (18, TIMESTAMP(@lunes + INTERVAL -297 DAY, '10:00:00'), '14336742-8', '7728987-9', 6, 10, 3),
    (19, TIMESTAMP(@lunes + INTERVAL -322 DAY, '16:30:00'), '9391546-1', '7980815-6', 6, 2, 2),
    (20, TIMESTAMP(@lunes + INTERVAL -436 DAY, '09:30:00'), '9061933-0', '20820721-0', 2, 7, 5),
    (21, TIMESTAMP(@lunes + INTERVAL -221 DAY, '14:30:00'), '18812517-4', '7044345-7', 1, 6, 4),
    (22, TIMESTAMP(@lunes + INTERVAL -305 DAY, '09:00:00'), '23521786-4', '14117398-7', 5, 8, 5),
    (23, TIMESTAMP(@lunes + INTERVAL -276 DAY, '08:00:00'), '16505153-K', '17584561-5', 7, 7, 5),
    (24, TIMESTAMP(@lunes + INTERVAL -437 DAY, '10:30:00'), '6077105-7', '8532032-7', 8, 1, 5),
    (25, TIMESTAMP(@lunes + INTERVAL -343 DAY, '15:00:00'), '18085780-K', '7044345-7', 2, 6, 5),
    (26, TIMESTAMP(@lunes + INTERVAL -5 DAY, '13:30:00'), '14334531-9', '17680243-K', 6, 4, 4),
    (27, TIMESTAMP(@lunes + INTERVAL -416 DAY, '12:30:00'), '14495860-8', '7441955-0', 2, 7, 3),
    (28, TIMESTAMP(@lunes + INTERVAL -108 DAY, '08:30:00'), '21793059-6', '21895568-1', 1, 1, 5),
    (29, TIMESTAMP(@lunes + INTERVAL -398 DAY, '15:30:00'), '23207008-0', '6468706-9', 6, 8, 5),
    (30, TIMESTAMP(@lunes + INTERVAL -101 DAY, '08:00:00'), '14174617-0', '19530857-8', 3, 6, 3),
    (31, TIMESTAMP(@lunes + INTERVAL -411 DAY, '09:00:00'), '14269324-0', '20820721-0', 2, 7, 5),
    (32, TIMESTAMP(@lunes + INTERVAL -87 DAY, '17:00:00'), '15646752-9', '20402710-2', 3, 5, 3),
    (33, TIMESTAMP(@lunes + INTERVAL -395 DAY, '15:30:00'), '7845266-8', '10730012-0', 3, 10, 5),
    (34, TIMESTAMP(@lunes + INTERVAL -223 DAY, '08:00:00'), '9986034-0', '16677723-2', 1, 10, 3),
    (35, TIMESTAMP(@lunes + INTERVAL -32 DAY, '15:30:00'), '13697025-9', '7728987-9', 8, 10, 5),
    (36, TIMESTAMP(@lunes + INTERVAL -279 DAY, '11:00:00'), '23040017-2', '21781464-2', 5, 1, 3),
    (37, TIMESTAMP(@lunes + INTERVAL -347 DAY, '15:30:00'), '20860207-1', '10232182-0', 4, 8, 3),
    (38, TIMESTAMP(@lunes + INTERVAL -311 DAY, '09:30:00'), '25100696-2', '6358976-4', 4, 3, 3),
    (39, TIMESTAMP(@lunes + INTERVAL -28 DAY, '13:00:00'), '9553319-1', '17665120-2', 8, 10, 4),
    (40, TIMESTAMP(@lunes + INTERVAL -452 DAY, '12:00:00'), '22096865-0', '7455421-0', 8, 10, 5),
    (41, TIMESTAMP(@lunes + INTERVAL -96 DAY, '08:00:00'), '7845266-8', '7441955-0', 2, 7, 3),
    (42, TIMESTAMP(@lunes + INTERVAL -212 DAY, '11:30:00'), '8112034-K', '20820721-0', 2, 7, 4),
    (43, TIMESTAMP(@lunes + INTERVAL -27 DAY, '09:30:00'), '25100696-2', '14044229-1', 6, 3, 3),
    (44, TIMESTAMP(@lunes + INTERVAL -280 DAY, '15:30:00'), '23040017-2', '6387481-7', 2, 1, 5),
    (45, TIMESTAMP(@lunes + INTERVAL -447 DAY, '10:30:00'), '7396116-5', '16677723-2', 1, 10, 5),
    (46, TIMESTAMP(@lunes + INTERVAL -255 DAY, '09:00:00'), '7302450-1', '7351205-0', 1, 4, 3),
    (47, TIMESTAMP(@lunes + INTERVAL -20 DAY, '11:00:00'), '25100285-1', '6358976-4', 7, 3, 3),
    (48, TIMESTAMP(@lunes + INTERVAL -105 DAY, '16:00:00'), '24700576-5', '20661724-1', 6, 4, 4),
    (49, TIMESTAMP(@lunes + INTERVAL -383 DAY, '09:30:00'), '22748304-0', '21781464-2', 5, 1, 5),
    (50, TIMESTAMP(@lunes + INTERVAL 65 DAY, '13:00:00'), '25100833-7', '6358976-4', 7, 3, 2),
    (51, TIMESTAMP(@lunes + INTERVAL -446 DAY, '15:00:00'), '13327906-7', '16677723-2', 1, 10, 3),
    (52, TIMESTAMP(@lunes + INTERVAL -261 DAY, '09:00:00'), '8112034-K', '8532032-7', 8, 8, 3),
    (53, TIMESTAMP(@lunes + INTERVAL -3 DAY, '10:00:00'), '20860207-1', '7441955-0', 2, 7, 5),
    (54, TIMESTAMP(@lunes + INTERVAL -396 DAY, '11:30:00'), '13960920-4', '6358976-4', 7, 7, 3),
    (55, TIMESTAMP(@lunes + INTERVAL 42 DAY, '16:30:00'), '23521786-4', '15509051-0', 6, 2, 2),
    (56, TIMESTAMP(@lunes + INTERVAL -340 DAY, '16:30:00'), '14627226-6', '17680243-K', 6, 4, 3),
    (57, TIMESTAMP(@lunes + INTERVAL -69 DAY, '09:30:00'), '17593211-9', '10232182-0', 4, 8, 3),
    (58, TIMESTAMP(@lunes + INTERVAL -439 DAY, '16:00:00'), '7396116-5', '14117398-7', 5, 5, 5),
    (59, TIMESTAMP(@lunes + INTERVAL -314 DAY, '09:00:00'), '13837869-1', '15509051-0', 3, 4, 5),
    (60, TIMESTAMP(@lunes + INTERVAL 0 DAY, '15:30:00'), '13764927-6', '10730012-0', 3, 10, 1);
INSERT INTO Cita (idCita, fechaHoraCita, RUTPaciente, RUTMedico, idCentro, idEspecialidad, idEstadoCita) VALUES
    (61, TIMESTAMP(@lunes + INTERVAL -55 DAY, '17:30:00'), '7642648-1', '8532032-7', 8, 8, 5),
    (62, TIMESTAMP(@lunes + INTERVAL -286 DAY, '10:00:00'), '7644095-6', '17665120-2', 8, 2, 2),
    (63, TIMESTAMP(@lunes + INTERVAL -234 DAY, '17:30:00'), '23207008-0', '17680243-K', 6, 4, 3),
    (64, TIMESTAMP(@lunes + INTERVAL 59 DAY, '14:30:00'), '23040017-2', '17584561-5', 7, 7, 1),
    (65, TIMESTAMP(@lunes + INTERVAL -275 DAY, '16:30:00'), '7396116-5', '6387481-7', 2, 1, 3),
    (66, TIMESTAMP(@lunes + INTERVAL -129 DAY, '10:30:00'), '23306972-8', '7044345-7', 2, 6, 3),
    (67, TIMESTAMP(@lunes + INTERVAL -385 DAY, '10:00:00'), '9391546-1', '21781464-2', 5, 1, 5),
    (68, TIMESTAMP(@lunes + INTERVAL -385 DAY, '14:30:00'), '14336742-8', '7728987-9', 8, 1, 4),
    (69, TIMESTAMP(@lunes + INTERVAL -466 DAY, '09:30:00'), '12313676-4', '8532032-7', 3, 1, 2),
    (70, TIMESTAMP(@lunes + INTERVAL -30 DAY, '13:00:00'), '19198153-7', '17680243-K', 6, 4, 3),
    (71, TIMESTAMP(@lunes + INTERVAL -376 DAY, '08:00:00'), '14495860-8', '17680243-K', 6, 4, 5),
    (72, TIMESTAMP(@lunes + INTERVAL -296 DAY, '09:30:00'), '9391546-1', '15509051-0', 6, 2, 5),
    (73, TIMESTAMP(@lunes + INTERVAL 109 DAY, '08:30:00'), '22795716-6', '7455421-0', 8, 10, 1),
    (74, TIMESTAMP(@lunes + INTERVAL -150 DAY, '14:00:00'), '22795716-6', '17680243-K', 6, 4, 3),
    (75, TIMESTAMP(@lunes + INTERVAL -102 DAY, '08:00:00'), '22748304-0', '7044345-7', 1, 6, 3),
    (76, TIMESTAMP(@lunes + INTERVAL -20 DAY, '13:30:00'), '17593211-9', '7441955-0', 2, 7, 4),
    (77, TIMESTAMP(@lunes + INTERVAL -37 DAY, '08:00:00'), '22795716-6', '20820721-0', 7, 7, 3),
    (78, TIMESTAMP(@lunes + INTERVAL -117 DAY, '13:00:00'), '13327906-7', '21895568-1', 5, 7, 4),
    (79, TIMESTAMP(@lunes + INTERVAL -69 DAY, '10:00:00'), '12172169-4', '17665120-2', 6, 2, 5),
    (80, TIMESTAMP(@lunes + INTERVAL -69 DAY, '11:00:00'), '24425300-8', '6358976-4', 7, 7, 4),
    (81, TIMESTAMP(@lunes + INTERVAL -381 DAY, '08:00:00'), '9011591-K', '21895568-1', 5, 1, 5),
    (82, TIMESTAMP(@lunes + INTERVAL -178 DAY, '13:00:00'), '13764927-6', '6387481-7', 2, 1, 5),
    (83, TIMESTAMP(@lunes + INTERVAL -48 DAY, '15:30:00'), '14336742-8', '20820721-0', 7, 7, 3),
    (84, TIMESTAMP(@lunes + INTERVAL -461 DAY, '09:30:00'), '5038623-6', '16677723-2', 1, 10, 3),
    (85, TIMESTAMP(@lunes + INTERVAL -73 DAY, '16:00:00'), '16287597-3', '6468706-9', 6, 8, 5),
    (86, TIMESTAMP(@lunes + INTERVAL -220 DAY, '12:00:00'), '13764927-6', '17727180-2', 2, 6, 2),
    (87, TIMESTAMP(@lunes + INTERVAL -406 DAY, '16:30:00'), '16287597-3', '21781464-2', 5, 1, 3),
    (88, TIMESTAMP(@lunes + INTERVAL -89 DAY, '15:30:00'), '12172169-4', '7351205-0', 1, 4, 5),
    (89, TIMESTAMP(@lunes + INTERVAL 22 DAY, '14:30:00'), '8541296-5', '19530857-8', 3, 6, 2),
    (90, TIMESTAMP(@lunes + INTERVAL -192 DAY, '15:30:00'), '12832403-8', '20820721-0', 2, 7, 3),
    (91, TIMESTAMP(@lunes + INTERVAL -234 DAY, '17:00:00'), '8112034-K', '15509051-0', 6, 4, 5),
    (92, TIMESTAMP(@lunes + INTERVAL -77 DAY, '08:30:00'), '22748304-0', '14920785-6', 3, 6, 3),
    (93, TIMESTAMP(@lunes + INTERVAL -45 DAY, '15:30:00'), '9389579-7', '6468706-9', 3, 6, 5),
    (94, TIMESTAMP(@lunes + INTERVAL -384 DAY, '15:00:00'), '23521786-4', '7351205-0', 1, 4, 5),
    (95, TIMESTAMP(@lunes + INTERVAL -18 DAY, '09:30:00'), '25100833-7', '7455421-0', 8, 3, 2),
    (96, TIMESTAMP(@lunes + INTERVAL -112 DAY, '15:00:00'), '9060227-6', '7441955-0', 2, 7, 5),
    (97, TIMESTAMP(@lunes + INTERVAL -55 DAY, '13:30:00'), '13960920-4', '8532032-7', 8, 8, 4),
    (98, TIMESTAMP(@lunes + INTERVAL -240 DAY, '15:30:00'), '7261579-4', '7044345-7', 2, 6, 1),
    (99, TIMESTAMP(@lunes + INTERVAL -206 DAY, '17:30:00'), '17925013-6', '6358976-4', 7, 7, 3),
    (100, TIMESTAMP(@lunes + INTERVAL -290 DAY, '11:30:00'), '17006085-7', '10232182-0', 8, 8, 3),
    (101, TIMESTAMP(@lunes + INTERVAL -475 DAY, '12:00:00'), '6077105-7', '6387481-7', 2, 1, 5),
    (102, TIMESTAMP(@lunes + INTERVAL 70 DAY, '12:00:00'), '9389579-7', '11433012-4', 4, 2, 1),
    (103, TIMESTAMP(@lunes + INTERVAL -10 DAY, '15:30:00'), '18566616-6', '21895568-1', 1, 7, 3),
    (104, TIMESTAMP(@lunes + INTERVAL -408 DAY, '09:30:00'), '20860207-1', '21781464-2', 5, 1, 3),
    (105, TIMESTAMP(@lunes + INTERVAL -220 DAY, '14:00:00'), '8541296-5', '7455421-0', 8, 10, 4),
    (106, TIMESTAMP(@lunes + INTERVAL -84 DAY, '10:30:00'), '14920542-K', '8169968-2', 5, 8, 3),
    (107, TIMESTAMP(@lunes + INTERVAL -404 DAY, '16:30:00'), '19265539-0', '8169968-2', 5, 8, 5),
    (108, TIMESTAMP(@lunes + INTERVAL -475 DAY, '09:00:00'), '14627226-6', '10232182-0', 4, 8, 3),
    (109, TIMESTAMP(@lunes + INTERVAL -149 DAY, '12:30:00'), '21029931-9', '20661724-1', 6, 4, 5),
    (110, TIMESTAMP(@lunes + INTERVAL -109 DAY, '09:30:00'), '19265539-0', '7980815-6', 3, 2, 3),
    (111, TIMESTAMP(@lunes + INTERVAL -172 DAY, '15:00:00'), '12832403-8', '10730012-0', 3, 10, 3),
    (112, TIMESTAMP(@lunes + INTERVAL -110 DAY, '08:00:00'), '18085780-K', '20820721-0', 7, 7, 3),
    (113, TIMESTAMP(@lunes + INTERVAL -107 DAY, '14:00:00'), '18812517-4', '20820721-0', 2, 7, 3),
    (114, TIMESTAMP(@lunes + INTERVAL -19 DAY, '12:30:00'), '25100422-6', '7455421-0', 8, 3, 4),
    (115, TIMESTAMP(@lunes + INTERVAL -182 DAY, '08:00:00'), '12172169-4', '7980815-6', 6, 2, 3),
    (116, TIMESTAMP(@lunes + INTERVAL -72 DAY, '12:30:00'), '13960920-4', '14044229-1', 6, 4, 4),
    (117, TIMESTAMP(@lunes + INTERVAL -377 DAY, '13:00:00'), '17925013-6', '21895568-1', 5, 7, 5),
    (118, TIMESTAMP(@lunes + INTERVAL -233 DAY, '11:30:00'), '8541296-5', '6387481-7', 2, 1, 3),
    (119, TIMESTAMP(@lunes + INTERVAL -126 DAY, '13:00:00'), '7302450-1', '20661724-1', 3, 4, 4),
    (120, TIMESTAMP(@lunes + INTERVAL -67 DAY, '09:00:00'), '23567645-1', '21781464-2', 5, 1, 4);
INSERT INTO Cita (idCita, fechaHoraCita, RUTPaciente, RUTMedico, idCentro, idEspecialidad, idEstadoCita) VALUES
    (121, TIMESTAMP(@lunes + INTERVAL -481 DAY, '14:00:00'), '15727672-7', '20661724-1', 6, 4, 4),
    (122, TIMESTAMP(@lunes + INTERVAL -114 DAY, '08:00:00'), '25100011-5', '6358976-4', 7, 3, 4),
    (123, TIMESTAMP(@lunes + INTERVAL -318 DAY, '14:00:00'), '10940891-3', '8532032-7', 8, 1, 3),
    (124, TIMESTAMP(@lunes + INTERVAL 57 DAY, '12:30:00'), '8541296-5', '21781464-2', 5, 1, 2),
    (125, TIMESTAMP(@lunes + INTERVAL -367 DAY, '12:30:00'), '7845266-8', '6358976-4', 4, 7, 3),
    (126, TIMESTAMP(@lunes + INTERVAL -467 DAY, '17:00:00'), '20038242-0', '7351205-0', 1, 4, 3),
    (127, TIMESTAMP(@lunes + INTERVAL -282 DAY, '10:30:00'), '7642648-1', '14044229-1', 6, 8, 3),
    (128, TIMESTAMP(@lunes + INTERVAL -41 DAY, '11:30:00'), '14174617-0', '10232182-0', 8, 8, 3),
    (129, TIMESTAMP(@lunes + INTERVAL -165 DAY, '16:30:00'), '10282565-9', '6358976-4', 7, 7, 5),
    (130, TIMESTAMP(@lunes + INTERVAL -72 DAY, '13:00:00'), '23567645-1', '15509051-0', 6, 4, 3),
    (131, TIMESTAMP(@lunes + INTERVAL -75 DAY, '08:30:00'), '9389579-7', '7351205-0', 1, 4, 5),
    (132, TIMESTAMP(@lunes + INTERVAL -241 DAY, '08:00:00'), '6077105-7', '7728987-9', 6, 1, 5),
    (133, TIMESTAMP(@lunes + INTERVAL -53 DAY, '17:30:00'), '10940891-3', '6358976-4', 7, 7, 5),
    (134, TIMESTAMP(@lunes + INTERVAL -219 DAY, '16:00:00'), '7033823-8', '8532032-7', 3, 8, 5),
    (135, TIMESTAMP(@lunes + INTERVAL 101 DAY, '10:30:00'), '7033823-8', '6468706-9', 3, 6, 1),
    (136, TIMESTAMP(@lunes + INTERVAL -124 DAY, '12:00:00'), '25100833-7', '7455421-0', 8, 3, 3),
    (137, TIMESTAMP(@lunes + INTERVAL 116 DAY, '16:30:00'), '16505153-K', '7044345-7', 2, 6, 5),
    (138, TIMESTAMP(@lunes + INTERVAL -287 DAY, '09:00:00'), '9061933-0', '17142625-1', 6, 8, 4),
    (139, TIMESTAMP(@lunes + INTERVAL -61 DAY, '16:00:00'), '21793059-6', '10730012-0', 3, 10, 3),
    (140, TIMESTAMP(@lunes + INTERVAL -69 DAY, '09:00:00'), '7845266-8', '7455421-0', 8, 10, 3),
    (141, TIMESTAMP(@lunes + INTERVAL -341 DAY, '09:30:00'), '22096865-0', '10730012-0', 3, 10, 3),
    (142, TIMESTAMP(@lunes + INTERVAL -472 DAY, '14:00:00'), '16287597-3', '17680243-K', 6, 4, 4),
    (143, TIMESTAMP(@lunes + INTERVAL -368 DAY, '15:00:00'), '18812517-4', '17584561-5', 7, 7, 4),
    (144, TIMESTAMP(@lunes + INTERVAL 68 DAY, '12:30:00'), '20860207-1', '19530857-8', 3, 6, 5),
    (145, TIMESTAMP(@lunes + INTERVAL -291 DAY, '11:00:00'), '17835970-3', '7351205-0', 1, 4, 3),
    (146, TIMESTAMP(@lunes + INTERVAL -97 DAY, '15:30:00'), '17925013-6', '20402710-2', 3, 5, 4),
    (147, TIMESTAMP(@lunes + INTERVAL -380 DAY, '10:00:00'), '13837869-1', '21781464-2', 5, 1, 3),
    (148, TIMESTAMP(@lunes + INTERVAL -73 DAY, '08:30:00'), '9391546-1', '7728987-9', 8, 10, 4),
    (149, TIMESTAMP(@lunes + INTERVAL -451 DAY, '10:30:00'), '23306972-8', '10232182-0', 8, 8, 5),
    (150, TIMESTAMP(@lunes + INTERVAL -47 DAY, '16:00:00'), '13697025-9', '8532032-7', 8, 1, 1),
    (151, TIMESTAMP(@lunes + INTERVAL -145 DAY, '14:30:00'), '14495860-8', '17727180-2', 2, 8, 3),
    (152, TIMESTAMP(@lunes + INTERVAL 58 DAY, '08:00:00'), '10940891-3', '6468706-9', 3, 8, 2),
    (153, TIMESTAMP(@lunes + INTERVAL -376 DAY, '16:30:00'), '16287597-3', '17727180-2', 2, 8, 3),
    (154, TIMESTAMP(@lunes + INTERVAL -322 DAY, '12:00:00'), '7642648-1', '7044345-7', 1, 6, 4),
    (155, TIMESTAMP(@lunes + INTERVAL 28 DAY, '16:00:00'), '25100970-8', '6358976-4', 7, 3, 1),
    (156, TIMESTAMP(@lunes + INTERVAL -93 DAY, '08:00:00'), '23040017-2', '16677723-2', 1, 8, 3),
    (157, TIMESTAMP(@lunes + INTERVAL -2 DAY, '17:00:00'), '24186345-K', '17727180-2', 2, 6, 3),
    (158, TIMESTAMP(@lunes + INTERVAL -212 DAY, '15:00:00'), '18085780-K', '21781464-2', 5, 1, 5),
    (159, TIMESTAMP(@lunes + INTERVAL -75 DAY, '16:30:00'), '18812517-4', '10730012-0', 3, 10, 3),
    (160, TIMESTAMP(@lunes + INTERVAL -335 DAY, '09:30:00'), '15646752-9', '15509051-0', 6, 2, 5),
    (161, TIMESTAMP(@lunes + INTERVAL -238 DAY, '17:00:00'), '18085780-K', '11433012-4', 1, 1, 3),
    (162, TIMESTAMP(@lunes + INTERVAL -452 DAY, '14:00:00'), '20038242-0', '14920785-6', 3, 8, 5),
    (163, TIMESTAMP(@lunes + INTERVAL 122 DAY, '08:00:00'), '9553319-1', '20661724-1', 6, 4, 2),
    (164, TIMESTAMP(@lunes + INTERVAL 56 DAY, '14:00:00'), '7033823-8', '8532032-7', 8, 1, 1),
    (165, TIMESTAMP(@lunes + INTERVAL -350 DAY, '12:00:00'), '23521786-4', '20820721-0', 7, 7, 5),
    (166, TIMESTAMP(@lunes + INTERVAL -336 DAY, '14:00:00'), '7644095-6', '20661724-1', 3, 4, 5),
    (167, TIMESTAMP(@lunes + INTERVAL -63 DAY, '17:30:00'), '15727672-7', '14117398-7', 3, 8, 5),
    (168, TIMESTAMP(@lunes + INTERVAL -10 DAY, '14:30:00'), '23567645-1', '10232182-0', 4, 8, 4),
    (169, TIMESTAMP(@lunes + INTERVAL -56 DAY, '14:30:00'), '12832403-8', '20402710-2', 3, 5, 3),
    (170, TIMESTAMP(@lunes + INTERVAL -41 DAY, '14:00:00'), '14920542-K', '11433012-4', 1, 2, 3),
    (171, TIMESTAMP(@lunes + INTERVAL -357 DAY, '15:00:00'), '10940891-3', '7351205-0', 1, 4, 5),
    (172, TIMESTAMP(@lunes + INTERVAL -377 DAY, '16:00:00'), '19471939-6', '19530857-8', 3, 6, 5),
    (173, TIMESTAMP(@lunes + INTERVAL -107 DAY, '16:30:00'), '17006085-7', '15509051-0', 6, 4, 3),
    (174, TIMESTAMP(@lunes + INTERVAL 96 DAY, '09:30:00'), '9061933-0', '14044229-1', 6, 4, 5),
    (175, TIMESTAMP(@lunes + INTERVAL 105 DAY, '15:00:00'), '7396116-5', '20402710-2', 3, 10, 1),
    (176, TIMESTAMP(@lunes + INTERVAL -308 DAY, '09:00:00'), '19265539-0', '7980815-6', 3, 7, 3),
    (177, TIMESTAMP(@lunes + INTERVAL 115 DAY, '11:30:00'), '23207008-0', '10730012-0', 3, 10, 1),
    (178, TIMESTAMP(@lunes + INTERVAL -152 DAY, '15:30:00'), '7845266-8', '11433012-4', 1, 6, 5),
    (179, TIMESTAMP(@lunes + INTERVAL -182 DAY, '08:30:00'), '23040017-2', '7351205-0', 1, 4, 5),
    (180, TIMESTAMP(@lunes + INTERVAL -308 DAY, '16:30:00'), '14334531-9', '7455421-0', 8, 10, 2);
INSERT INTO Cita (idCita, fechaHoraCita, RUTPaciente, RUTMedico, idCentro, idEspecialidad, idEstadoCita) VALUES
    (181, TIMESTAMP(@lunes + INTERVAL -376 DAY, '12:30:00'), '10742460-1', '6387481-7', 2, 1, 4),
    (182, TIMESTAMP(@lunes + INTERVAL -142 DAY, '15:00:00'), '20860207-1', '21781464-2', 5, 1, 3),
    (183, TIMESTAMP(@lunes + INTERVAL -283 DAY, '12:00:00'), '7644095-6', '14920785-6', 3, 6, 5),
    (184, TIMESTAMP(@lunes + INTERVAL -53 DAY, '09:30:00'), '18566616-6', '7980815-6', 3, 1, 3),
    (185, TIMESTAMP(@lunes + INTERVAL -201 DAY, '10:00:00'), '9389579-7', '7980815-6', 3, 7, 3),
    (186, TIMESTAMP(@lunes + INTERVAL -63 DAY, '09:00:00'), '16287597-3', '21895568-1', 5, 7, 3),
    (187, TIMESTAMP(@lunes + INTERVAL -228 DAY, '14:30:00'), '7396116-5', '7441955-0', 2, 7, 4),
    (188, TIMESTAMP(@lunes + INTERVAL -233 DAY, '10:30:00'), '9391546-1', '21895568-1', 1, 1, 5),
    (189, TIMESTAMP(@lunes + INTERVAL -450 DAY, '17:30:00'), '7529497-2', '15509051-0', 3, 2, 3),
    (190, TIMESTAMP(@lunes + INTERVAL -423 DAY, '10:30:00'), '21029931-9', '7351205-0', 1, 4, 5),
    (191, TIMESTAMP(@lunes + INTERVAL -452 DAY, '16:00:00'), '14495860-8', '7351205-0', 1, 4, 5),
    (192, TIMESTAMP(@lunes + INTERVAL -38 DAY, '11:30:00'), '25100970-8', '7455421-0', 8, 3, 3),
    (193, TIMESTAMP(@lunes + INTERVAL 16 DAY, '16:30:00'), '18370299-8', '15509051-0', 6, 2, 2),
    (194, TIMESTAMP(@lunes + INTERVAL -277 DAY, '17:30:00'), '7644095-6', '10232182-0', 4, 8, 5),
    (195, TIMESTAMP(@lunes + INTERVAL -329 DAY, '17:30:00'), '19265539-0', '7441955-0', 2, 7, 5),
    (196, TIMESTAMP(@lunes + INTERVAL -453 DAY, '09:00:00'), '18370299-8', '17584561-5', 7, 7, 5),
    (197, TIMESTAMP(@lunes + INTERVAL -166 DAY, '14:30:00'), '10742460-1', '14920785-6', 3, 8, 5),
    (198, TIMESTAMP(@lunes + INTERVAL -39 DAY, '10:30:00'), '13837869-1', '21781464-2', 5, 1, 3),
    (199, TIMESTAMP(@lunes + INTERVAL -19 DAY, '08:30:00'), '14174617-0', '7980815-6', 6, 1, 3),
    (200, TIMESTAMP(@lunes + INTERVAL -353 DAY, '15:30:00'), '8389045-2', '10730012-0', 3, 10, 4),
    (201, TIMESTAMP(@lunes + INTERVAL 50 DAY, '08:30:00'), '20038242-0', '14117398-7', 3, 5, 1),
    (202, TIMESTAMP(@lunes + INTERVAL -90 DAY, '12:30:00'), '7302450-1', '8532032-7', 3, 1, 3),
    (203, TIMESTAMP(@lunes + INTERVAL -249 DAY, '12:30:00'), '9986034-0', '20661724-1', 6, 4, 5),
    (204, TIMESTAMP(@lunes + INTERVAL -396 DAY, '11:30:00'), '19277538-8', '15509051-0', 3, 4, 3),
    (205, TIMESTAMP(@lunes + INTERVAL -72 DAY, '17:00:00'), '12172169-4', '21781464-2', 5, 1, 3),
    (206, TIMESTAMP(@lunes + INTERVAL -82 DAY, '11:00:00'), '19277538-8', '7441955-0', 2, 7, 3),
    (207, TIMESTAMP(@lunes + INTERVAL -129 DAY, '11:30:00'), '9061933-0', '14044229-1', 6, 4, 5),
    (208, TIMESTAMP(@lunes + INTERVAL -75 DAY, '11:00:00'), '22748304-0', '15509051-0', 3, 4, 3),
    (209, TIMESTAMP(@lunes + INTERVAL 17 DAY, '10:30:00'), '5340614-9', '7351205-0', 1, 4, 5),
    (210, TIMESTAMP(@lunes + INTERVAL -436 DAY, '12:00:00'), '22096865-0', '17727180-2', 2, 8, 3),
    (211, TIMESTAMP(@lunes + INTERVAL -37 DAY, '14:00:00'), '18370299-8', '17665120-2', 8, 2, 3),
    (212, TIMESTAMP(@lunes + INTERVAL -115 DAY, '08:30:00'), '7642648-1', '6387481-7', 2, 9, 5),
    (213, TIMESTAMP(@lunes + INTERVAL -362 DAY, '08:00:00'), '17006085-7', '20661724-1', 3, 4, 5),
    (214, TIMESTAMP(@lunes + INTERVAL -94 DAY, '13:00:00'), '22754131-8', '7455421-0', 8, 10, 5),
    (215, TIMESTAMP(@lunes + INTERVAL -240 DAY, '15:30:00'), '7302450-1', '6358976-4', 4, 7, 1),
    (216, TIMESTAMP(@lunes + INTERVAL -165 DAY, '14:30:00'), '22748304-0', '7728987-9', 8, 10, 3),
    (217, TIMESTAMP(@lunes + INTERVAL -98 DAY, '10:00:00'), '7443709-5', '15509051-0', 3, 4, 5),
    (218, TIMESTAMP(@lunes + INTERVAL -345 DAY, '14:00:00'), '12313676-4', '7455421-0', 8, 10, 3),
    (219, TIMESTAMP(@lunes + INTERVAL -105 DAY, '13:30:00'), '14920542-K', '8169968-2', 5, 8, 3),
    (220, TIMESTAMP(@lunes + INTERVAL -94 DAY, '08:00:00'), '14495860-8', '7728987-9', 8, 10, 4),
    (221, TIMESTAMP(@lunes + INTERVAL -88 DAY, '13:30:00'), '16505153-K', '6468706-9', 3, 6, 3),
    (222, TIMESTAMP(@lunes + INTERVAL -392 DAY, '14:30:00'), '18370299-8', '7351205-0', 1, 4, 5),
    (223, TIMESTAMP(@lunes + INTERVAL -193 DAY, '15:30:00'), '14336742-8', '17584561-5', 7, 7, 4),
    (224, TIMESTAMP(@lunes + INTERVAL -5 DAY, '10:30:00'), '23040017-2', '7980815-6', 6, 1, 3),
    (225, TIMESTAMP(@lunes + INTERVAL -52 DAY, '10:00:00'), '13837869-1', '16677723-2', 1, 8, 3),
    (226, TIMESTAMP(@lunes + INTERVAL -361 DAY, '13:30:00'), '7302450-1', '17665120-2', 6, 2, 5),
    (227, TIMESTAMP(@lunes + INTERVAL -98 DAY, '15:30:00'), '10742460-1', '20661724-1', 3, 4, 4),
    (228, TIMESTAMP(@lunes + INTERVAL -410 DAY, '08:00:00'), '19265539-0', '17142625-1', 1, 7, 5),
    (229, TIMESTAMP(@lunes + INTERVAL -294 DAY, '15:30:00'), '18370299-8', '21781464-2', 5, 1, 3),
    (230, TIMESTAMP(@lunes + INTERVAL -263 DAY, '09:00:00'), '5038623-6', '21781464-2', 5, 1, 3),
    (231, TIMESTAMP(@lunes + INTERVAL -202 DAY, '09:00:00'), '9793975-6', '14117398-7', 5, 8, 4),
    (232, TIMESTAMP(@lunes + INTERVAL -445 DAY, '10:00:00'), '14336742-8', '6358976-4', 7, 7, 3),
    (233, TIMESTAMP(@lunes + INTERVAL 0 DAY, '08:00:00'), '18085780-K', '17665120-2', 6, 10, 1),
    (234, TIMESTAMP(@lunes + INTERVAL -259 DAY, '11:30:00'), '7033823-8', '17727180-2', 2, 6, 3),
    (235, TIMESTAMP(@lunes + INTERVAL 21 DAY, '16:30:00'), '17006085-7', '20661724-1', 3, 4, 1),
    (236, TIMESTAMP(@lunes + INTERVAL -378 DAY, '08:00:00'), '15727672-7', '7455421-0', 8, 10, 3),
    (237, TIMESTAMP(@lunes + INTERVAL -324 DAY, '11:00:00'), '7302450-1', '16677723-2', 1, 8, 4),
    (238, TIMESTAMP(@lunes + INTERVAL -184 DAY, '13:30:00'), '18812517-4', '14920785-6', 3, 6, 3),
    (239, TIMESTAMP(@lunes + INTERVAL -114 DAY, '13:00:00'), '10940891-3', '8169968-2', 5, 8, 4),
    (240, TIMESTAMP(@lunes + INTERVAL -380 DAY, '12:30:00'), '9793975-6', '20661724-1', 6, 4, 5);
INSERT INTO Cita (idCita, fechaHoraCita, RUTPaciente, RUTMedico, idCentro, idEspecialidad, idEstadoCita) VALUES
    (241, TIMESTAMP(@hoy, '08:30:00'), '17006085-7', '7980815-6', 3, 1, 2),
    (242, TIMESTAMP(@hoy, '09:30:00'), '8389045-2', '7980815-6', 3, 2, 2),
    (243, TIMESTAMP(@hoy, '10:30:00'), '15246860-1', '7980815-6', 3, 7, 1),
    (244, TIMESTAMP(@hoy, '12:00:00'), '13837869-1', '7980815-6', 6, 1, 2),
    (245, TIMESTAMP(@hoy, '14:30:00'), '23483090-2', '7980815-6', 6, 2, 1),
    (246, TIMESTAMP(@hoy, '15:30:00'), '5999828-5', '7980815-6', 6, 1, 1),
    (247, TIMESTAMP(@hoy, '17:00:00'), '14627226-6', '7980815-6', 3, 7, 1),
    (248, TIMESTAMP(@lunes + INTERVAL 9 DAY, '08:00:00'), '7302450-1', '11433012-4', 1, 2, 2),
    (249, TIMESTAMP(@lunes + INTERVAL 18 DAY, '08:00:00'), '7302450-1', '15509051-0', 3, 2, 1),
    (250, TIMESTAMP(@lunes + INTERVAL -41 DAY, '08:00:00'), '11433012-4', '21781464-2', 5, 1, 3),
    (251, TIMESTAMP(@lunes + INTERVAL 24 DAY, '08:00:00'), '11433012-4', '21781464-2', 5, 1, 1),
    (252, TIMESTAMP(@lunes + INTERVAL 59 DAY, '14:30:00'), '10194119-1', '17584561-5', 7, 7, 5);

-- Atenciones, diagnosticos y recetas
INSERT INTO Atencion (idAtencion, idCita, motivo, observaciones) VALUES
    (1, 4, 'Malestar general', 'Se solicitan examenes complementarios de control.'),
    (2, 5, 'Control de rutina', 'Se ajusta tratamiento actual.'),
    (3, 8, 'Dolor de cabeza recurrente', 'Se deriva a especialista para evaluacion adicional.'),
    (4, 12, 'Control post consulta anterior', 'Sin hallazgos relevantes al examen fisico.'),
    (5, 17, 'Chequeo preventivo', 'Paciente refiere mejoria respecto a consulta previa.'),
    (6, 18, 'Malestar general', 'Buena evolucion clinica.'),
    (7, 27, 'Control de rutina', 'Paciente refiere mejoria respecto a consulta previa.'),
    (8, 30, 'Evaluacion de examenes', 'Buena evolucion clinica.'),
    (9, 32, 'Control de rutina', 'Se solicitan examenes complementarios de control.'),
    (10, 34, 'Dolor persistente', 'Buena evolucion clinica.'),
    (11, 36, 'Dolor de cabeza recurrente', 'Paciente estable, se indica tratamiento ambulatorio.'),
    (12, 37, 'Control post consulta anterior', 'Se deriva a especialista para evaluacion adicional.'),
    (13, 38, 'Seguimiento de tratamiento', 'Paciente estable, se indica tratamiento ambulatorio.'),
    (14, 41, 'Sintomas respiratorios', 'Paciente estable, se indica tratamiento ambulatorio.'),
    (15, 43, 'Evaluacion de examenes', 'Sin hallazgos relevantes al examen fisico.'),
    (16, 46, 'Chequeo preventivo', 'Paciente refiere mejoria respecto a consulta previa.'),
    (17, 47, 'Seguimiento de tratamiento', 'Sin hallazgos relevantes al examen fisico.'),
    (18, 51, 'Evaluacion de examenes', 'Se ajusta tratamiento actual.'),
    (19, 52, 'Seguimiento de tratamiento', 'Se solicitan examenes complementarios de control.'),
    (20, 54, 'Molestias digestivas', 'Cuadro leve, se cita a control en 2 semanas.'),
    (21, 56, 'Molestias digestivas', 'Buena evolucion clinica.'),
    (22, 57, 'Chequeo preventivo', 'Cuadro leve, se cita a control en 2 semanas.'),
    (23, 63, 'Chequeo preventivo', 'Sin hallazgos relevantes al examen fisico.'),
    (24, 65, 'Control post consulta anterior', 'Paciente refiere mejoria respecto a consulta previa.'),
    (25, 66, 'Evaluacion de examenes', 'Se solicitan examenes complementarios de control.'),
    (26, 70, 'Dolor persistente', 'Se solicitan examenes complementarios de control.'),
    (27, 74, 'Dolor de cabeza recurrente', 'Paciente estable, se indica tratamiento ambulatorio.'),
    (28, 75, 'Evaluacion de examenes', 'Se ajusta tratamiento actual.'),
    (29, 77, 'Evaluacion de examenes', 'Se ajusta tratamiento actual.'),
    (30, 83, 'Evaluacion de examenes', 'Sin hallazgos relevantes al examen fisico.'),
    (31, 84, 'Dolor persistente', 'Sin hallazgos relevantes al examen fisico.'),
    (32, 87, 'Malestar general', 'Se ajusta tratamiento actual.'),
    (33, 90, 'Molestias digestivas', 'Paciente refiere mejoria respecto a consulta previa.'),
    (34, 92, 'Malestar general', 'Cuadro leve, se cita a control en 2 semanas.'),
    (35, 99, 'Dolor persistente', 'Se solicitan examenes complementarios de control.'),
    (36, 100, 'Molestias digestivas', 'Paciente refiere mejoria respecto a consulta previa.'),
    (37, 103, 'Seguimiento de tratamiento', 'Cuadro leve, se cita a control en 2 semanas.'),
    (38, 104, 'Evaluacion de examenes', 'Se solicitan examenes complementarios de control.'),
    (39, 106, 'Dolor de cabeza recurrente', 'Cuadro leve, se cita a control en 2 semanas.'),
    (40, 108, 'Molestias digestivas', 'Paciente estable, se indica tratamiento ambulatorio.'),
    (41, 110, 'Control de rutina', 'Se deriva a especialista para evaluacion adicional.'),
    (42, 111, 'Dolor de cabeza recurrente', 'Paciente estable, se indica tratamiento ambulatorio.'),
    (43, 112, 'Chequeo preventivo', 'Paciente refiere mejoria respecto a consulta previa.'),
    (44, 113, 'Control de rutina', 'Paciente refiere mejoria respecto a consulta previa.'),
    (45, 115, 'Control de rutina', 'Buena evolucion clinica.'),
    (46, 118, 'Dolor persistente', 'Sin hallazgos relevantes al examen fisico.'),
    (47, 123, 'Control de rutina', 'Se deriva a especialista para evaluacion adicional.'),
    (48, 125, 'Molestias digestivas', 'Paciente refiere mejoria respecto a consulta previa.'),
    (49, 126, 'Control de rutina', 'Se solicitan examenes complementarios de control.'),
    (50, 127, 'Malestar general', 'Buena evolucion clinica.'),
    (51, 128, 'Control post consulta anterior', 'Paciente refiere mejoria respecto a consulta previa.'),
    (52, 130, 'Molestias digestivas', 'Se ajusta tratamiento actual.'),
    (53, 136, 'Sintomas respiratorios', 'Se solicitan examenes complementarios de control.'),
    (54, 139, 'Dolor persistente', 'Cuadro leve, se cita a control en 2 semanas.'),
    (55, 140, 'Control post consulta anterior', 'Paciente refiere mejoria respecto a consulta previa.'),
    (56, 141, 'Malestar general', 'Se solicitan examenes complementarios de control.'),
    (57, 145, 'Dolor de cabeza recurrente', 'Cuadro leve, se cita a control en 2 semanas.'),
    (58, 147, 'Control de rutina', 'Buena evolucion clinica.'),
    (59, 151, 'Seguimiento de tratamiento', 'Se deriva a especialista para evaluacion adicional.'),
    (60, 153, 'Chequeo preventivo', 'Se deriva a especialista para evaluacion adicional.');
INSERT INTO Atencion (idAtencion, idCita, motivo, observaciones) VALUES
    (61, 156, 'Dolor persistente', 'Paciente refiere mejoria respecto a consulta previa.'),
    (62, 157, 'Control post consulta anterior', 'Se ajusta tratamiento actual.'),
    (63, 159, 'Dolor de cabeza recurrente', 'Se deriva a especialista para evaluacion adicional.'),
    (64, 161, 'Malestar general', 'Paciente estable, se indica tratamiento ambulatorio.'),
    (65, 169, 'Malestar general', 'Se solicitan examenes complementarios de control.'),
    (66, 170, 'Control de rutina', 'Cuadro leve, se cita a control en 2 semanas.'),
    (67, 173, 'Seguimiento de tratamiento', 'Se deriva a especialista para evaluacion adicional.'),
    (68, 176, 'Sintomas respiratorios', 'Cuadro leve, se cita a control en 2 semanas.'),
    (69, 182, 'Dolor persistente', 'Sin hallazgos relevantes al examen fisico.'),
    (70, 184, 'Control post consulta anterior', 'Se solicitan examenes complementarios de control.'),
    (71, 185, 'Sintomas respiratorios', 'Buena evolucion clinica.'),
    (72, 186, 'Molestias digestivas', 'Se ajusta tratamiento actual.'),
    (73, 189, 'Control de rutina', 'Sin hallazgos relevantes al examen fisico.'),
    (74, 192, 'Dolor de cabeza recurrente', 'Paciente estable, se indica tratamiento ambulatorio.'),
    (75, 198, 'Chequeo preventivo', 'Se deriva a especialista para evaluacion adicional.'),
    (76, 199, 'Sintomas respiratorios', 'Paciente estable, se indica tratamiento ambulatorio.'),
    (77, 202, 'Chequeo preventivo', 'Buena evolucion clinica.'),
    (78, 204, 'Dolor de cabeza recurrente', 'Se solicitan examenes complementarios de control.'),
    (79, 205, 'Dolor de cabeza recurrente', 'Se ajusta tratamiento actual.'),
    (80, 206, 'Molestias digestivas', 'Cuadro leve, se cita a control en 2 semanas.'),
    (81, 208, 'Control post consulta anterior', 'Se ajusta tratamiento actual.'),
    (82, 210, 'Malestar general', 'Se solicitan examenes complementarios de control.'),
    (83, 211, 'Sintomas respiratorios', 'Paciente estable, se indica tratamiento ambulatorio.'),
    (84, 216, 'Molestias digestivas', 'Se deriva a especialista para evaluacion adicional.'),
    (85, 218, 'Control de rutina', 'Buena evolucion clinica.'),
    (86, 219, 'Evaluacion de examenes', 'Se deriva a especialista para evaluacion adicional.'),
    (87, 221, 'Malestar general', 'Sin hallazgos relevantes al examen fisico.'),
    (88, 224, 'Dolor de cabeza recurrente', 'Sin hallazgos relevantes al examen fisico.'),
    (89, 225, 'Malestar general', 'Se ajusta tratamiento actual.'),
    (90, 229, 'Seguimiento de tratamiento', 'Cuadro leve, se cita a control en 2 semanas.'),
    (91, 230, 'Dolor de cabeza recurrente', 'Paciente estable, se indica tratamiento ambulatorio.'),
    (92, 232, 'Dolor de cabeza recurrente', 'Buena evolucion clinica.'),
    (93, 234, 'Control post consulta anterior', 'Se ajusta tratamiento actual.'),
    (94, 236, 'Control post consulta anterior', 'Se ajusta tratamiento actual.'),
    (95, 238, 'Dolor persistente', 'Se deriva a especialista para evaluacion adicional.'),
    (96, 250, 'Control de presion arterial', 'Paciente medico de la red. Presion levemente elevada, se indica control en 3 meses.');

INSERT INTO Atencion_Diagnostico (idAtencion, codigoDiagnostico) VALUES
    (1, 'K21'),
    (2, 'E11'),
    (2, 'R51'),
    (3, 'J02'),
    (4, 'I10'),
    (5, 'J06'),
    (6, 'M25'),
    (7, 'J06'),
    (8, 'J06'),
    (8, 'J45'),
    (9, 'L20'),
    (9, 'N39'),
    (10, 'J06'),
    (11, 'J45'),
    (12, 'E11'),
    (13, 'L20'),
    (14, 'H10'),
    (15, 'L20'),
    (16, 'K21'),
    (17, 'L20'),
    (18, 'E11'),
    (19, 'I10'),
    (20, 'E11'),
    (20, 'J00'),
    (21, 'L20'),
    (22, 'H10'),
    (23, 'E66'),
    (24, 'J02'),
    (24, 'J06'),
    (25, 'J00'),
    (25, 'M25'),
    (26, 'J02'),
    (27, 'J00'),
    (28, 'I10'),
    (29, 'J45'),
    (30, 'F41'),
    (31, 'J02'),
    (31, 'J06'),
    (32, 'J02'),
    (33, 'F41'),
    (33, 'L20'),
    (34, 'L20'),
    (35, 'R51'),
    (36, 'K21'),
    (36, 'N39'),
    (37, 'E11'),
    (37, 'L20'),
    (38, 'J06'),
    (39, 'E66'),
    (40, 'F41'),
    (41, 'J45'),
    (42, 'E11'),
    (42, 'R51'),
    (43, 'F41'),
    (44, 'J00'),
    (45, 'F41'),
    (46, 'E11'),
    (47, 'F41'),
    (48, 'M54'),
    (49, 'K21');
INSERT INTO Atencion_Diagnostico (idAtencion, codigoDiagnostico) VALUES
    (50, 'E11'),
    (51, 'M54'),
    (52, 'J00'),
    (53, 'J00'),
    (54, 'M25'),
    (55, 'E66'),
    (56, 'J45'),
    (57, 'F41'),
    (58, 'M25'),
    (58, 'N39'),
    (59, 'L20'),
    (60, 'J00'),
    (61, 'E66'),
    (61, 'F41'),
    (62, 'H10'),
    (63, 'L20'),
    (64, 'E11'),
    (64, 'H10'),
    (65, 'I10'),
    (66, 'J06'),
    (66, 'R51'),
    (67, 'J06'),
    (68, 'I10'),
    (69, 'L20'),
    (70, 'L20'),
    (71, 'I10'),
    (71, 'J02'),
    (72, 'R51'),
    (73, 'R51'),
    (74, 'J02'),
    (75, 'K21'),
    (76, 'K21'),
    (77, 'E66'),
    (78, 'J06'),
    (79, 'M54'),
    (80, 'J00'),
    (80, 'J45'),
    (81, 'J02'),
    (81, 'J06'),
    (82, 'E11'),
    (82, 'I10'),
    (83, 'E11'),
    (84, 'I10'),
    (85, 'K21'),
    (86, 'I10'),
    (86, 'M25'),
    (87, 'M25'),
    (88, 'J06'),
    (89, 'N39'),
    (90, 'J00'),
    (90, 'L20'),
    (91, 'K21'),
    (92, 'F41'),
    (93, 'J00'),
    (93, 'L20'),
    (94, 'H10'),
    (94, 'J45'),
    (95, 'L20'),
    (96, 'I10');

INSERT INTO Receta (idReceta, idAtencion, medicamento, dosis, diasTratamiento) VALUES
    (1, 1, 'Sertralina', '50 mg cada 24 horas', 7),
    (2, 2, 'Azitromicina', '500 mg cada 24 horas por 3 dias', 14),
    (3, 2, 'Enalapril', '10 mg cada 12 horas', 15),
    (4, 2, 'Ranitidina', '150 mg cada 12 horas', 3),
    (5, 3, 'Omeprazol', '20 mg cada 24 horas en ayunas', 3),
    (6, 4, 'Azitromicina', '500 mg cada 24 horas por 3 dias', 10),
    (7, 5, 'Prednisona', '20 mg cada 24 horas', 10),
    (8, 6, 'Enalapril', '10 mg cada 12 horas', 3),
    (9, 7, 'Loratadina', '10 mg cada 24 horas', 7),
    (10, 8, 'Salbutamol inhalador', '2 puff cada 6 horas si hay sintomas', 3),
    (11, 9, 'Furosemida', '40 mg cada 24 horas', 10),
    (12, 9, 'Omeprazol', '20 mg cada 24 horas en ayunas', 7),
    (13, 10, 'Diclofenaco', '50 mg cada 12 horas', 30),
    (14, 11, 'Clonazepam', '0.5 mg cada noche', 15),
    (15, 11, 'Furosemida', '40 mg cada 24 horas', 30),
    (16, 11, 'Omeprazol', '20 mg cada 24 horas en ayunas', 5),
    (17, 12, 'Omeprazol', '20 mg cada 24 horas en ayunas', 5),
    (18, 13, 'Amoxicilina', '250 mg cada 8 horas', 7),
    (19, 13, 'Ibuprofeno', '100 mg cada 8 horas', 3),
    (20, 14, 'Clonazepam', '0.5 mg cada noche', 7),
    (21, 14, 'Omeprazol', '20 mg cada 24 horas en ayunas', 7),
    (22, 15, 'Amoxicilina', '250 mg cada 8 horas', 7),
    (23, 15, 'Ibuprofeno', '100 mg cada 8 horas', 3),
    (24, 16, 'Naproxeno', '250 mg cada 12 horas', 30),
    (25, 17, 'Amoxicilina', '250 mg cada 8 horas', 7),
    (26, 17, 'Salbutamol inhalador', '2 puff cada 6 horas', 7),
    (27, 18, 'Sertralina', '50 mg cada 24 horas', 14),
    (28, 19, 'Naproxeno', '250 mg cada 12 horas', 30),
    (29, 19, 'Ranitidina', '150 mg cada 12 horas', 5),
    (30, 20, 'Azitromicina', '500 mg cada 24 horas por 3 dias', 3),
    (31, 20, 'Naproxeno', '250 mg cada 12 horas', 5),
    (32, 21, 'Omeprazol', '20 mg cada 24 horas en ayunas', 15),
    (33, 22, 'Furosemida', '40 mg cada 24 horas', 7),
    (34, 23, 'Atorvastatina', '20 mg cada noche', 7),
    (35, 23, 'Ibuprofeno', '400 mg cada 8 horas', 10),
    (36, 24, 'Naproxeno', '250 mg cada 12 horas', 7),
    (37, 25, 'Ibuprofeno', '400 mg cada 8 horas', 15),
    (38, 26, 'Loratadina', '10 mg cada 24 horas', 30),
    (39, 27, 'Atorvastatina', '20 mg cada noche', 30),
    (40, 28, 'Prednisona', '20 mg cada 24 horas', 3),
    (41, 29, 'Atorvastatina', '20 mg cada noche', 14),
    (42, 30, 'Azitromicina', '500 mg cada 24 horas por 3 dias', 10),
    (43, 31, 'Furosemida', '40 mg cada 24 horas', 5),
    (44, 32, 'Naproxeno', '250 mg cada 12 horas', 5),
    (45, 32, 'Salbutamol inhalador', '2 puff cada 6 horas si hay sintomas', 10),
    (46, 33, 'Sertralina', '50 mg cada 24 horas', 15),
    (47, 34, 'Clonazepam', '0.5 mg cada noche', 30),
    (48, 35, 'Losartan', '50 mg cada 24 horas', 15),
    (49, 35, 'Salbutamol inhalador', '2 puff cada 6 horas si hay sintomas', 3),
    (50, 36, 'Atorvastatina', '20 mg cada noche', 30),
    (51, 37, 'Ciprofloxacino', '500 mg cada 12 horas por 7 dias', 5),
    (52, 38, 'Atorvastatina', '20 mg cada noche', 5),
    (53, 38, 'Ibuprofeno', '400 mg cada 8 horas', 3),
    (54, 39, 'Furosemida', '40 mg cada 24 horas', 14),
    (55, 39, 'Omeprazol', '20 mg cada 24 horas en ayunas', 14),
    (56, 40, 'Loratadina', '10 mg cada 24 horas', 5),
    (57, 41, 'Paracetamol', '500 mg cada 8 horas', 10),
    (58, 42, 'Omeprazol', '20 mg cada 24 horas en ayunas', 10),
    (59, 43, 'Naproxeno', '250 mg cada 12 horas', 5),
    (60, 43, 'Prednisona', '20 mg cada 24 horas', 7);
INSERT INTO Receta (idReceta, idAtencion, medicamento, dosis, diasTratamiento) VALUES
    (61, 44, 'Salbutamol inhalador', '2 puff cada 6 horas si hay sintomas', 3),
    (62, 45, 'Losartan', '50 mg cada 24 horas', 14),
    (63, 46, 'Clonazepam', '0.5 mg cada noche', 30),
    (64, 47, 'Sertralina', '50 mg cada 24 horas', 10),
    (65, 48, 'Clonazepam', '0.5 mg cada noche', 3),
    (66, 49, 'Loratadina', '10 mg cada 24 horas', 7),
    (67, 50, 'Diclofenaco', '50 mg cada 12 horas', 15),
    (68, 51, 'Amoxicilina', '500 mg cada 8 horas por 7 dias', 5),
    (69, 51, 'Sertralina', '50 mg cada 24 horas', 10),
    (70, 52, 'Loratadina', '10 mg cada 24 horas', 3),
    (71, 53, 'Amoxicilina', '250 mg cada 8 horas', 7),
    (72, 53, 'Ibuprofeno', '100 mg cada 8 horas', 3),
    (73, 54, 'Omeprazol', '20 mg cada 24 horas en ayunas', 3),
    (74, 55, 'Ibuprofeno', '400 mg cada 8 horas', 3),
    (75, 56, 'Azitromicina', '500 mg cada 24 horas por 3 dias', 5),
    (76, 57, 'Amoxicilina', '500 mg cada 8 horas por 7 dias', 10),
    (77, 58, 'Losartan', '50 mg cada 24 horas', 15),
    (78, 59, 'Paracetamol', '500 mg cada 8 horas', 3),
    (79, 60, 'Diclofenaco', '50 mg cada 12 horas', 7),
    (80, 61, 'Sertralina', '50 mg cada 24 horas', 30),
    (81, 62, 'Ciprofloxacino', '500 mg cada 12 horas por 7 dias', 10),
    (82, 63, 'Azitromicina', '500 mg cada 24 horas por 3 dias', 7),
    (83, 63, 'Insulina NPH', '10 UI subcutanea cada 12 horas', 5),
    (84, 64, 'Omeprazol', '20 mg cada 24 horas en ayunas', 3),
    (85, 65, 'Loratadina', '10 mg cada 24 horas', 14),
    (86, 65, 'Metformina', '850 mg cada 12 horas', 3),
    (87, 66, 'Clonazepam', '0.5 mg cada noche', 10),
    (88, 67, 'Furosemida', '40 mg cada 24 horas', 14),
    (89, 68, 'Ibuprofeno', '400 mg cada 8 horas', 5),
    (90, 69, 'Enalapril', '10 mg cada 12 horas', 3),
    (91, 70, 'Ibuprofeno', '400 mg cada 8 horas', 7),
    (92, 71, 'Losartan', '50 mg cada 24 horas', 30),
    (93, 72, 'Atorvastatina', '20 mg cada noche', 7),
    (94, 73, 'Ciprofloxacino', '500 mg cada 12 horas por 7 dias', 5),
    (95, 74, 'Amoxicilina', '250 mg cada 8 horas', 7),
    (96, 74, 'Paracetamol', '10 mg/kg cada 8 horas', 5),
    (97, 75, 'Clonazepam', '0.5 mg cada noche', 10),
    (98, 76, 'Enalapril', '10 mg cada 12 horas', 5),
    (99, 77, 'Atorvastatina', '20 mg cada noche', 5),
    (100, 78, 'Naproxeno', '250 mg cada 12 horas', 3),
    (101, 79, 'Naproxeno', '250 mg cada 12 horas', 15),
    (102, 80, 'Sertralina', '50 mg cada 24 horas', 15),
    (103, 81, 'Loratadina', '10 mg cada 24 horas', 14),
    (104, 82, 'Ranitidina', '150 mg cada 12 horas', 15),
    (105, 83, 'Azitromicina', '500 mg cada 24 horas por 3 dias', 5),
    (106, 84, 'Ranitidina', '150 mg cada 12 horas', 7),
    (107, 85, 'Diclofenaco', '50 mg cada 12 horas', 7),
    (108, 86, 'Naproxeno', '250 mg cada 12 horas', 5),
    (109, 87, 'Insulina NPH', '10 UI subcutanea cada 12 horas', 10),
    (110, 88, 'Diclofenaco', '50 mg cada 12 horas', 14),
    (111, 89, 'Loratadina', '10 mg cada 24 horas', 14),
    (112, 90, 'Diclofenaco', '50 mg cada 12 horas', 10),
    (113, 90, 'Furosemida', '40 mg cada 24 horas', 10),
    (114, 91, 'Enalapril', '10 mg cada 12 horas', 7),
    (115, 92, 'Ciprofloxacino', '500 mg cada 12 horas por 7 dias', 15),
    (116, 93, 'Paracetamol', '500 mg cada 8 horas', 15),
    (117, 94, 'Naproxeno', '250 mg cada 12 horas', 10),
    (118, 95, 'Ciprofloxacino', '500 mg cada 12 horas por 7 dias', 7),
    (119, 95, 'Ranitidina', '150 mg cada 12 horas', 30),
    (120, 96, 'Enalapril', '10 mg cada 24 horas', 30);

SET @carga_datos = NULL;
