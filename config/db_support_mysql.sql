-- ============================================================
-- MySQL Workbench Database Dump
-- Convertido desde PostgreSQL (db_support.sql)
-- Base de datos: helpdesk
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ------------------------------------------------------------
-- Crear y seleccionar la base de datos
-- ------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS helpdesk
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE helpdesk;

-- ============================================================
-- TABLAS
-- ============================================================

-- ------------------------------------------------------------
-- Tabla: oficina
-- (equivalente a public.oficina en PostgreSQL)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oficina (
    id          INT          NOT NULL AUTO_INCREMENT,
    name        VARCHAR(100) NOT NULL,
    location    VARCHAR(150) NULL,
    location_detail TEXT     NULL,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabla: usuarios
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id          INT          NOT NULL AUTO_INCREMENT,
    dni         VARCHAR(20)  NOT NULL,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NULL,
    phone       VARCHAR(20)  NULL,
    office_id   INT          NULL,
    password    TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY usuarios_dni_key   (dni),
    UNIQUE KEY usuarios_email_key (email),
    CONSTRAINT fk_usuarios_oficina
        FOREIGN KEY (office_id) REFERENCES oficina(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabla: trabajadores
-- (role usa ENUM en lugar del TYPE de PostgreSQL)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trabajadores (
    id          INT          NOT NULL AUTO_INCREMENT,
    role        ENUM('admin','tecnico') NOT NULL,
    dni         VARCHAR(20)  NOT NULL,
    first_name  VARCHAR(100) NOT NULL,
    last_name   VARCHAR(100) NOT NULL,
    email       VARCHAR(100) NULL,
    phone       VARCHAR(20)  NULL,
    office_id   INT          NULL,
    password    TEXT         NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY trabajadores_dni_key   (dni),
    UNIQUE KEY trabajadores_email_key (email),
    CONSTRAINT fk_trabajadores_oficina
        FOREIGN KEY (office_id) REFERENCES oficina(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabla: tickets
-- (category y status usan ENUM en lugar de TYPEs de PostgreSQL)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    id              INT          NOT NULL,
    user_id         INT          NOT NULL,
    technician_id   INT          NULL,
    office_id       INT          NULL,
    category        ENUM('Instalacion','Software','Hardware','Internet','Otro') NULL,
    title           VARCHAR(200) NOT NULL,
    description     TEXT         NULL,
    tech_comment    TEXT         NULL,
    status          ENUM('Pendiente','En camino','En proceso','Atendido','Rechazado')
                                 NOT NULL DEFAULT 'Pendiente',
    attended_at     DATETIME     NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tickets_status      (status),
    KEY idx_tickets_user        (user_id),
    KEY idx_tickets_technician  (technician_id),
    CONSTRAINT fk_tickets_usuario
        FOREIGN KEY (user_id)       REFERENCES usuarios(id),
    CONSTRAINT fk_tickets_tecnico
        FOREIGN KEY (technician_id) REFERENCES trabajadores(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE,
    CONSTRAINT fk_tickets_oficina
        FOREIGN KEY (office_id)     REFERENCES oficina(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabla: ticket_history
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_history (
    id          INT          NOT NULL AUTO_INCREMENT,
    ticket_id   INT          NOT NULL,
    status      ENUM('Pendiente','En camino','En proceso','Atendido','Rechazado') NOT NULL,
    comment     TEXT         NULL,
    changed_by  INT          NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_th_ticket
        FOREIGN KEY (ticket_id)   REFERENCES tickets(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_th_trabajador
        FOREIGN KEY (changed_by)  REFERENCES trabajadores(id)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ------------------------------------------------------------
-- Tabla: ticket_files
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ticket_files (
    id          INT          NOT NULL AUTO_INCREMENT,
    ticket_id   INT          NOT NULL,
    file_path   TEXT         NOT NULL,
    uploaded_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_tf_ticket
        FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TRIGGER: generate_ticket_id
-- Equivalente al trigger de PostgreSQL que genera IDs con
-- formato YYYYMM + secuencia de 3 dígitos (ej: 202604001)
-- ============================================================

DELIMITER $$

CREATE TRIGGER trigger_generate_ticket_id
BEFORE INSERT ON tickets
FOR EACH ROW
BEGIN
    DECLARE v_prefix   INT;
    DECLARE v_max_id   INT;

    -- Prefijo año-mes actual en formato YYYYMM
    SET v_prefix := CAST(DATE_FORMAT(NOW(), '%Y%m') AS UNSIGNED);

    -- Buscar el ticket_id máximo que empiece con ese prefijo
    SELECT MAX(id) INTO v_max_id
    FROM tickets
    WHERE id LIKE CONCAT(v_prefix, '%');

    IF v_max_id IS NULL THEN
        SET NEW.id = CAST(CONCAT(v_prefix, '001') AS UNSIGNED);
    ELSE
        SET NEW.id = v_max_id + 1;
    END IF;
END$$

DELIMITER ;


-- ============================================================
-- STORED PROCEDURES (equivalentes a las funciones de PostgreSQL)
-- ============================================================

DELIMITER $$

-- ------------------------------------------------------------
-- SP: create_ticket
-- Crea un nuevo ticket e inserta el primer registro de historial.
-- Retorna el id del ticket creado en el parámetro OUT.
-- ------------------------------------------------------------
CREATE PROCEDURE create_ticket(
    IN  p_user_id     INT,
    IN  p_category    VARCHAR(50),
    IN  p_title       VARCHAR(200),
    IN  p_description TEXT,
    OUT p_ticket_id   INT
)
BEGIN
    -- Crear ticket (queda en Pendiente por defecto)
    INSERT INTO tickets(user_id, category, title, description)
    VALUES (p_user_id, p_category, p_title, p_description);

    SET p_ticket_id := LAST_INSERT_ID();

    -- Historial inicial
    INSERT INTO ticket_history(ticket_id, status, changed_by, comment)
    VALUES (p_ticket_id, 'Pendiente', NULL, 'Ticket creado');
END$$


-- ------------------------------------------------------------
-- SP: asignar_tecnico
-- Asigna un técnico a un ticket y registra el cambio en historial.
-- ------------------------------------------------------------
CREATE PROCEDURE asignar_tecnico(
    IN p_ticket_id      INT,
    IN p_technician_id  INT,
    IN p_admin_id       INT
)
BEGIN
    -- Actualizar ticket
    UPDATE tickets
    SET
        technician_id = p_technician_id,
        status        = 'En camino'
    WHERE id = p_ticket_id;

    -- Historial
    INSERT INTO ticket_history(ticket_id, status, changed_by, comment)
    VALUES (p_ticket_id, 'En camino', p_admin_id, 'Técnico asignado');
END$$


-- ------------------------------------------------------------
-- SP: update_ticket_status
-- Actualiza el estado de un ticket y registra en historial.
-- ------------------------------------------------------------
CREATE PROCEDURE update_ticket_status(
    IN p_ticket_id      INT,
    IN p_technician_id  INT,
    IN p_status         VARCHAR(20),
    IN p_comment        TEXT
)
BEGIN
    -- Actualizar ticket
    UPDATE tickets
    SET
        status      = p_status,
        attended_at = CASE
                          WHEN p_status = 'Atendido' THEN NOW()
                          ELSE attended_at
                      END
    WHERE id            = p_ticket_id
      AND technician_id = p_technician_id;

    -- Historial
    INSERT INTO ticket_history(ticket_id, status, changed_by, comment)
    VALUES (p_ticket_id, p_status, p_technician_id, p_comment);
END$$


-- ------------------------------------------------------------
-- SP: create_trabajador
-- Inserta un nuevo trabajador (admin o técnico).
-- ------------------------------------------------------------
CREATE PROCEDURE create_trabajador(
    IN p_role       VARCHAR(10),
    IN p_dni        VARCHAR(20),
    IN p_first_name VARCHAR(100),
    IN p_last_name  VARCHAR(100),
    IN p_email      VARCHAR(100),
    IN p_phone      VARCHAR(20),
    IN p_office_id  INT,
    IN p_password   TEXT
)
BEGIN
    INSERT INTO trabajadores(role, dni, first_name, last_name, email, phone, office_id, password)
    VALUES (p_role, p_dni, p_first_name, p_last_name, p_email, p_phone, p_office_id, p_password);
END$$


-- ------------------------------------------------------------
-- SP: create_usuario
-- Inserta un nuevo usuario (cliente).
-- ------------------------------------------------------------
CREATE PROCEDURE create_usuario(
    IN p_dni        VARCHAR(20),
    IN p_first_name VARCHAR(100),
    IN p_last_name  VARCHAR(100),
    IN p_email      VARCHAR(100),
    IN p_phone      VARCHAR(20),
    IN p_office_id  INT,
    IN p_password   TEXT
)
BEGIN
    INSERT INTO usuarios(dni, first_name, last_name, email, phone, office_id, password)
    VALUES (p_dni, p_first_name, p_last_name, p_email, p_phone, p_office_id, p_password);
END$$


-- ------------------------------------------------------------
-- SP: login_user
-- Autentica a un usuario por DNI y contraseña.
-- Retorna id, role, first_name, last_name.
-- (MySQL no soporta RETURNS TABLE; se usa un SELECT directo
--  que la aplicación PHP puede consumir con una sola llamada.)
-- ------------------------------------------------------------
CREATE PROCEDURE login_user(
    IN p_dni      VARCHAR(20),
    IN p_password TEXT
)
BEGIN
    -- Trabajadores (admin / tecnico)
    SELECT
        t.id,
        CAST(t.role AS CHAR) AS role,
        t.first_name,
        t.last_name
    FROM trabajadores t
    WHERE t.dni      = p_dni
      AND t.password = p_password

    UNION

    -- Usuarios (clientes)
    SELECT
        u.id,
        'usuario' AS role,
        u.first_name,
        u.last_name
    FROM usuarios u
    WHERE u.dni      = p_dni
      AND u.password = p_password;
END$$


-- ------------------------------------------------------------
-- SP: search_office
-- Busca oficinas por nombre (búsqueda parcial, equivalente a ILIKE).
-- MySQL LIKE es case-insensitive por defecto con utf8mb4_unicode_ci.
-- ------------------------------------------------------------
CREATE PROCEDURE search_office(
    IN p_name VARCHAR(100)
)
BEGIN
    SELECT
        o.id,
        o.name,
        o.location,
        o.location_detail
    FROM oficina o
    WHERE o.name LIKE CONCAT('%', p_name, '%');
END$$

DELIMITER ;


-- ============================================================
-- DATOS INICIALES (equivalentes a los COPY ... FROM stdin)
-- ============================================================

-- ------------------------------------------------------------
-- Datos: oficina
-- ------------------------------------------------------------
INSERT INTO oficina (id, name, location, location_detail, is_active) VALUES
(1, 'Gerencia General',              'Edificio Principal',     'Piso 1',              1),
(2, 'Recursos Humanos',              'Edificio Administrativo','Piso 2',              1),
(3, 'Contabilidad',                  'Edificio Financiero',    'Piso 3',              1),
(4, 'Tecnologías de la Información', 'Edificio TI',            'Piso 2 - Oficina 201',1),
(5, 'Logística',                     'Almacén Central',        'Zona Industrial',     1);


-- ------------------------------------------------------------
-- Datos: usuarios
-- ------------------------------------------------------------
INSERT INTO usuarios (id, dni, first_name, last_name, email, phone, office_id, password, created_at, is_active) VALUES
(1, '70000005', 'Maria',  'Lopez',  'maria@empresa.com', '987654325', 2,    '123456',   '2026-04-13 18:39:14', 1),
(2, '70000006', 'Pedro',  'Gomez',  'pedro@empresa.com', '987654326', 3,    '123456',   '2026-04-13 18:39:14', 1),
(3, '70000007', 'Lucia',  'Torres', 'lucia@empresa.com', '987654327', 5,    '123456',   '2026-04-13 18:39:14', 1),
(4, '70000008', 'Jose',   'Vargas', 'jose@empresa.com',  '987654328', 2,    '123456',   '2026-04-13 18:39:14', 1),
(5, '70000009', 'Pedro',  'Aquino', NULL,                '999999999', 3,    '70000009', '2026-04-15 11:02:49', 1),
(6, '99999999', 'Test',   'Test',   NULL,                '123',       NULL, '99999999', '2026-04-15 11:32:46', 1),
(7, '99999998', 'Jane',   'Doe',    NULL,                '333',       NULL, '99999998', '2026-04-15 11:33:12', 1);


-- ------------------------------------------------------------
-- Datos: trabajadores
-- ------------------------------------------------------------
INSERT INTO trabajadores (id, role, dni, first_name, last_name, email, phone, office_id, password, created_at, is_active) VALUES
(1, 'admin',   '70000001', 'Carlos', 'Ramirez', 'admin1@empresa.com',   '999999999', 4, '123456', '2026-04-13 18:39:14', 1),
(2, 'tecnico', '70000002', 'Luis',   'Quispe',  'luis.ti@empresa.com',  '987654322', 4, '123456', '2026-04-13 18:39:14', 1),
(3, 'tecnico', '70000003', 'Ana',    'Flores',  'ana.ti@empresa.com',   '987654323', 4, '123456', '2026-04-13 18:39:14', 1),
(4, 'tecnico', '70000004', 'Jorge',  'Perez',   'jorge.ti@empresa.com', '987654324', 4, '123456', '2026-04-13 18:39:14', 1);


-- ------------------------------------------------------------
-- Datos: tickets
-- NOTA: Se deshabilita el trigger temporalmente para insertar
--       los IDs históricos sin que sean regenerados.
-- ------------------------------------------------------------
SET @OLD_SQL_MODE = @@SQL_MODE;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- Deshabilitar el trigger para cargar datos históricos
DROP TRIGGER IF EXISTS trigger_generate_ticket_id;

INSERT INTO tickets (id, user_id, technician_id, office_id, category, title, description, tech_comment, status, attended_at, created_at) VALUES
(202604001, 1,    2,    2,    'Software',    'Error en sistema contable',    'No puedo acceder al sistema contable',         'Se reinició el servidor de la DB.',                     'Atendido',   '2026-04-13 18:39:14', '2026-04-13 18:39:14'),
(202604002, 2,    3,    3,    'Hardware',    'PC no enciende',               'El equipo no responde al presionar el botón',  '',                                                      'En proceso', NULL,                  '2026-04-13 18:39:14'),
(202604003, 3,    NULL, 5,    'Internet',    'Sin conexión',                 'No hay acceso a internet en mi área',          '',                                                      'Pendiente',  NULL,                  '2026-04-13 18:39:14'),
(202604004, 4,    4,    2,    'Instalacion', 'Instalar impresora',           'Necesito instalar impresora nueva',            'No es política del área instalar impresoras personales.','Rechazado',  '2026-04-13 18:39:14', '2026-04-13 18:39:14'),
(202604005, 1,    2,    2,    'Software',    'Office no funciona',           'Word se cierra automáticamente',               '',                                                      'En proceso', NULL,                  '2026-04-13 18:39:14'),
(202604006, 1,    2,    3,    'Otro',        'adasjdlasjld',                 'asdalsdjlk',                                   'mensaje asd',                                           'Atendido',   '2026-04-14 10:04:57', '2026-04-14 10:00:52'),
(202604007, 1,    2,    3,    'Software',    'asdasdasd',                    'asdasdasd',                                    '',                                                      'Atendido',   '2026-04-14 10:04:06', '2026-04-14 10:02:51'),
(202604008, 5,    2,    3,    'Internet',    'web',                          'aaaddsd',                                      'ok',                                                    'Atendido',   '2026-04-15 11:45:40', '2026-04-15 11:02:49'),
(202604009, 7,    2,    NULL, 'Software',    'X',                            'Y',                                            NULL,                                                    'En camino',  NULL,                  '2026-04-15 11:33:12'),
(202605000, 1,    NULL, 2,    'Software',    'office xd',                    '123',                                          NULL,                                                    'Pendiente',  NULL,                  '2026-05-04 16:01:34'),
(202605001, 1,    NULL, 2,    'Instalacion', 'asd',                          'zxc',                                          NULL,                                                    'Pendiente',  NULL,                  '2026-05-04 16:02:59'),
(202605002, 2,    NULL, 3,    'Hardware',    'adaswvwfe',                    'qefq3r',                                       NULL,                                                    'Pendiente',  NULL,                  '2026-05-04 16:03:33');

SET SQL_MODE = @OLD_SQL_MODE;

-- Restaurar el trigger para futuros INSERTs
DELIMITER $$

CREATE TRIGGER trigger_generate_ticket_id
BEFORE INSERT ON tickets
FOR EACH ROW
BEGIN
    DECLARE v_prefix   INT;
    DECLARE v_max_id   INT;

    SET v_prefix := CAST(DATE_FORMAT(NOW(), '%Y%m') AS UNSIGNED);

    SELECT MAX(id) INTO v_max_id
    FROM tickets
    WHERE id LIKE CONCAT(v_prefix, '%');

    IF v_max_id IS NULL THEN
        SET NEW.id = CAST(CONCAT(v_prefix, '001') AS UNSIGNED);
    ELSE
        SET NEW.id = v_max_id + 1;
    END IF;
END$$

DELIMITER ;


-- ------------------------------------------------------------
-- Datos: ticket_history
-- ------------------------------------------------------------
INSERT INTO ticket_history (id, ticket_id, status, comment, changed_by, created_at) VALUES
( 1, 202604001, 'Pendiente',  'Ticket creado',                                        1,    '2026-04-13 18:39:14'),
( 2, 202604001, 'En proceso', 'Asignado a técnico',                                   1,    '2026-04-13 18:39:14'),
( 3, 202604001, 'Atendido',   'Solucionado',                                          2,    '2026-04-13 18:39:14'),
( 4, 202604002, 'Pendiente',  'Ticket creado',                                        1,    '2026-04-13 18:39:14'),
( 5, 202604002, 'En proceso', 'Asignado a técnico',                                   1,    '2026-04-13 18:39:14'),
( 6, 202604003, 'Pendiente',  'Ticket creado',                                        1,    '2026-04-13 18:39:14'),
( 7, 202604004, 'Pendiente',  'Ticket creado',                                        1,    '2026-04-13 18:39:14'),
( 8, 202604004, 'Rechazado',  'Solicitud fuera de alcance',                           1,    '2026-04-13 18:39:14'),
( 9, 202604005, 'Pendiente',  'Ticket creado',                                        1,    '2026-04-13 18:39:14'),
(10, 202604005, 'En proceso', 'Asignado a técnico',                                   1,    '2026-04-13 18:39:14'),
(11, 202604001, 'Atendido',   'El administrador reescribió los detalles del ticket',  1,    '2026-04-13 21:32:40'),
(12, 202604006, 'Pendiente',  'Ticket creado',                                        NULL, '2026-04-14 10:00:52'),
(13, 202604006, 'En camino',  'Técnico asignado',                                     1,    '2026-04-14 10:01:28'),
(14, 202604007, 'Pendiente',  'Ticket creado',                                        NULL, '2026-04-14 10:02:51'),
(15, 202604007, 'En camino',  'Técnico asignado',                                     1,    '2026-04-14 10:03:09'),
(16, 202604006, 'En proceso', 'El técnico actualizó el estado a En proceso',          2,    '2026-04-14 10:03:30'),
(17, 202604007, 'En proceso', 'El técnico actualizó el estado a En proceso',          2,    '2026-04-14 10:03:52'),
(18, 202604007, 'Atendido',   'El técnico actualizó el estado a Atendido',            2,    '2026-04-14 10:04:06'),
(19, 202604006, 'Atendido',   'El técnico actualizó el estado a Atendido',            2,    '2026-04-14 10:04:57'),
(20, 202604008, 'Pendiente',  'Ticket creado desde el portal público',                NULL, '2026-04-15 11:02:49'),
(21, 202604009, 'Pendiente',  'Ticket creado desde el portal público',                NULL, '2026-04-15 11:33:12'),
(22, 202604008, 'En camino',  'Técnico asignado',                                     1,    '2026-04-15 11:43:45'),
(23, 202604008, 'En proceso', 'El técnico actualizó el estado a En proceso',          2,    '2026-04-15 11:45:26'),
(24, 202604008, 'Atendido',   'El técnico actualizó el estado a Atendido',            2,    '2026-04-15 11:45:40'),
(25, 202604009, 'En camino',  'Técnico asignado',                                     1,    '2026-04-21 18:37:59'),
(26, 202605000, 'Pendiente',  'Ticket creado (Admin)',                                1,    '2026-05-04 16:01:34'),
(27, 202605001, 'Pendiente',  'Ticket creado (Admin)',                                1,    '2026-05-04 16:02:59'),
(28, 202605002, 'Pendiente',  'Ticket creado (Admin)',                                1,    '2026-05-04 16:03:33');


-- ------------------------------------------------------------
-- Datos: ticket_files
-- ------------------------------------------------------------
INSERT INTO ticket_files (id, ticket_id, file_path, uploaded_at) VALUES
(1, 202604001, 'uploads/ticket1_img1.jpg', '2026-04-13 18:39:14'),
(2, 202604001, 'uploads/ticket1_img2.jpg', '2026-04-13 18:39:14'),
(3, 202604002, 'uploads/ticket2_img1.jpg', '2026-04-13 18:39:14'),
(4, 202604003, 'uploads/ticket3_img1.jpg', '2026-04-13 18:39:14'),
(5, 202604004, 'uploads/ticket4_img1.jpg', '2026-04-13 18:39:14'),
(6, 202604005, 'uploads/ticket5_img1.jpg', '2026-04-13 18:39:14');


-- ============================================================
-- Restaurar configuración
-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
