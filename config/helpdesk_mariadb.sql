-- ============================================================
--  HELPDESK - Base de Datos MariaDB
--  Archivo: helpdesk_mariadb.sql
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ------------------------------------------------------------
-- 1. CREAR Y SELECCIONAR LA BASE DE DATOS
-- ------------------------------------------------------------

DROP DATABASE IF EXISTS helpdesk;
CREATE DATABASE helpdesk
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE helpdesk;

-- ------------------------------------------------------------
-- 2. TABLAS
-- ------------------------------------------------------------

-- Tabla: oficina
CREATE TABLE oficina (
  id              INT          NOT NULL AUTO_INCREMENT,
  name            VARCHAR(100) NOT NULL,
  location        VARCHAR(150) DEFAULT NULL,
  location_detail TEXT         DEFAULT NULL,
  is_active       TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: trabajadores
--   role -> ENUM reemplaza al TYPE user_role de PostgreSQL
CREATE TABLE trabajadores (
  id         INT                    NOT NULL AUTO_INCREMENT,
  role       ENUM('admin','tecnico') NOT NULL,
  dni        VARCHAR(20)            NOT NULL,
  first_name VARCHAR(100)           NOT NULL,
  last_name  VARCHAR(100)           NOT NULL,
  email      VARCHAR(100)           DEFAULT NULL,
  phone      VARCHAR(20)            DEFAULT NULL,
  office_id  INT                    DEFAULT NULL,
  password   VARCHAR(255)           NOT NULL,
  created_at DATETIME               NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active  TINYINT(1)             NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_trabajadores_dni   (dni),
  UNIQUE KEY uq_trabajadores_email (email),
  CONSTRAINT fk_trabajadores_oficina
    FOREIGN KEY (office_id) REFERENCES oficina (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: usuarios
CREATE TABLE usuarios (
  id         INT          NOT NULL AUTO_INCREMENT,
  doc_type   ENUM('DNI','CE') NOT NULL DEFAULT 'DNI',
  dni        VARCHAR(20)  NOT NULL,
  first_name VARCHAR(100) NOT NULL,
  last_name  VARCHAR(100) NOT NULL,
  email      VARCHAR(100) DEFAULT NULL,
  phone      VARCHAR(20)  DEFAULT NULL,
  office_id  INT          DEFAULT NULL,
  password   VARCHAR(255) NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_active  TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_dni   (dni),
  CONSTRAINT fk_usuarios_oficina
    FOREIGN KEY (office_id) REFERENCES oficina (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: tickets
--   id NO es AUTO_INCREMENT: lo asigna el trigger generate_ticket_id
--   category y status -> ENUM reemplaza los TYPE de PostgreSQL
CREATE TABLE tickets (
  id            INT          NOT NULL,
  user_id       INT          NOT NULL,
  technician_id INT          DEFAULT NULL,
  office_id     INT          DEFAULT NULL,
  category      ENUM('Instalacion','Software','Hardware','Internet','Otro') DEFAULT NULL,
  title         VARCHAR(200) NOT NULL,
  description   TEXT         DEFAULT NULL,
  tech_comment  TEXT         DEFAULT NULL,
  status        ENUM('Pendiente','En camino','En proceso','Atendido','Rechazado')
                             NOT NULL DEFAULT 'Pendiente',
  attended_at   DATETIME     DEFAULT NULL,
  closed_at     DATETIME     DEFAULT NULL,
  rating        INT          DEFAULT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tickets_status      (status),
  KEY idx_tickets_technician  (technician_id),
  KEY idx_tickets_user        (user_id),
  CONSTRAINT fk_tickets_usuario
    FOREIGN KEY (user_id)       REFERENCES usuarios    (id),
  CONSTRAINT fk_tickets_tecnico
    FOREIGN KEY (technician_id) REFERENCES trabajadores (id),
  CONSTRAINT fk_tickets_oficina
    FOREIGN KEY (office_id)     REFERENCES oficina (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: ticket_history
CREATE TABLE ticket_history (
  id         INT      NOT NULL AUTO_INCREMENT,
  ticket_id  INT      NOT NULL,
  status     ENUM('Pendiente','En camino','En proceso','Atendido','Rechazado') NOT NULL,
  comment    TEXT     DEFAULT NULL,
  changed_by INT      DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_th_ticket
    FOREIGN KEY (ticket_id)  REFERENCES tickets      (id) ON DELETE CASCADE,
  CONSTRAINT fk_th_trabajador
    FOREIGN KEY (changed_by) REFERENCES trabajadores (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: ticket_files
CREATE TABLE ticket_files (
  id          INT      NOT NULL AUTO_INCREMENT,
  ticket_id   INT      NOT NULL,
  file_path   TEXT     NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_tf_ticket
    FOREIGN KEY (ticket_id) REFERENCES tickets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. TRIGGER
-- ------------------------------------------------------------

-- Trigger: generate_ticket_id
--   Genera el ID del ticket con formato YYYYMM + secuencia (ej: 202607001).
--   Equivalente a la funcion generate_ticket_id() de PostgreSQL.
DELIMITER $$
CREATE TRIGGER trigger_generate_ticket_id
BEFORE INSERT ON tickets
FOR EACH ROW
BEGIN
  DECLARE v_prefix INT;
  DECLARE v_max_id INT;

  -- Prefijo numerico YYYYMM (ej: 202607)
  SET v_prefix = CAST(DATE_FORMAT(NOW(), '%Y%m') AS UNSIGNED);

  -- Sin LIKE: division entera evita problemas de collation
  SELECT MAX(id) INTO v_max_id
  FROM tickets
  WHERE id DIV 1000 = v_prefix;

  IF v_max_id IS NULL THEN
    SET NEW.id = v_prefix * 1000 + 1;  -- primer ticket del mes: YYYYMM001
  ELSE
    SET NEW.id = v_max_id + 1;
  END IF;
END$$
DELIMITER ;

-- ------------------------------------------------------------
-- 4. STORED PROCEDURES
-- ------------------------------------------------------------

-- Procedure: asignar_tecnico
--   Asigna un tecnico a un ticket y registra el historial.
DELIMITER $$
CREATE PROCEDURE asignar_tecnico(
  IN p_ticket_id     INT,
  IN p_technician_id INT,
  IN p_admin_id      INT
)
BEGIN
  UPDATE tickets
  SET technician_id = p_technician_id,
      status        = 'En camino'
  WHERE id = p_ticket_id;

  INSERT INTO ticket_history (ticket_id, status, changed_by, comment)
  VALUES (p_ticket_id, 'En camino', p_admin_id, 'Tecnico asignado');
END$$
DELIMITER ;

-- Procedure: create_ticket
--   Crea un ticket y registra el historial.
--   Devuelve el nuevo id por parametro OUT.
--   p_office_id: oficina del ticket (puede ser NULL).
DELIMITER $$
CREATE PROCEDURE create_ticket(
  IN  p_user_id     INT,
  IN  p_category    VARCHAR(50),
  IN  p_title       VARCHAR(200),
  IN  p_description TEXT,
  IN  p_office_id   INT,
  OUT p_new_id      INT
)
BEGIN
  DECLARE v_prefix INT;

  INSERT INTO tickets (user_id, category, title, description, office_id)
  VALUES (p_user_id, p_category, p_title, p_description, p_office_id);

  -- Recuperar el id generado por el trigger (sin LIKE para evitar colision de collation)
  SET v_prefix = CAST(DATE_FORMAT(NOW(), '%Y%m') AS UNSIGNED);
  SELECT MAX(id) INTO p_new_id
  FROM tickets
  WHERE id DIV 1000 = v_prefix;

  INSERT INTO ticket_history (ticket_id, status, changed_by, comment)
  VALUES (p_new_id, 'Pendiente', NULL, 'Ticket creado');
END$$
DELIMITER ;

-- Procedure: create_trabajador
--   Registra un nuevo trabajador (admin o tecnico).
DELIMITER $$
CREATE PROCEDURE create_trabajador(
  IN p_role       VARCHAR(20),
  IN p_dni        VARCHAR(20),
  IN p_first_name VARCHAR(100),
  IN p_last_name  VARCHAR(100),
  IN p_email      VARCHAR(100),
  IN p_phone      VARCHAR(20),
  IN p_office_id  INT,
  IN p_password   VARCHAR(255)
)
BEGIN
  INSERT INTO trabajadores
    (role, dni, first_name, last_name, email, phone, office_id, password)
  VALUES
    (p_role, p_dni, p_first_name, p_last_name, p_email, p_phone, p_office_id, p_password);
END$$
DELIMITER ;

-- Procedure: create_usuario
--   Registra un nuevo usuario cliente.
DELIMITER $$
CREATE PROCEDURE create_usuario(
  IN p_dni        VARCHAR(20),
  IN p_first_name VARCHAR(100),
  IN p_last_name  VARCHAR(100),
  IN p_email      VARCHAR(100),
  IN p_phone      VARCHAR(20),
  IN p_office_id  INT,
  IN p_password   VARCHAR(255)
)
BEGIN
  INSERT INTO usuarios
    (dni, first_name, last_name, email, phone, office_id, password)
  VALUES
    (p_dni, p_first_name, p_last_name, p_email, p_phone, p_office_id, p_password);
END$$
DELIMITER ;

-- Procedure: login_user
--   Busca al usuario en trabajadores y en usuarios y retorna sus datos.
--   Equivalente a RETURN QUERY UNION de PostgreSQL.
DELIMITER $$
CREATE PROCEDURE login_user(
  IN p_dni      VARCHAR(20),
  IN p_password VARCHAR(255)
)
BEGIN
  -- Retorna id, role, first_name, last_name y office_id para trabajadores
  SELECT t.id, t.role AS role, t.first_name, t.last_name, t.office_id
  FROM trabajadores t
  WHERE t.dni = p_dni AND t.password = p_password

  UNION

  -- Retorna office_id para usuarios (clientes)
  SELECT u.id, 'usuario' AS role, u.first_name, u.last_name, u.office_id
  FROM usuarios u
  WHERE u.dni = p_dni AND u.password = p_password;
END$$
DELIMITER ;

-- Procedure: search_office
--   Busca oficinas por nombre (LIKE, insensible a mayusculas con utf8mb4_unicode_ci).
--   Equivalente a ILIKE de PostgreSQL.
DELIMITER $$
CREATE PROCEDURE search_office(
  IN p_name VARCHAR(100)
)
BEGIN
  SELECT id, name, location, location_detail
  FROM oficina
  WHERE name LIKE CONCAT('%', p_name, '%');
END$$
DELIMITER ;

-- Procedure: update_ticket_status
--   Actualiza el estado de un ticket y registra el historial.
DELIMITER $$
CREATE PROCEDURE update_ticket_status(
  IN p_ticket_id     INT,
  IN p_technician_id INT,
  IN p_status        VARCHAR(20),
  IN p_comment       TEXT
)
BEGIN
  UPDATE tickets
  SET status      = p_status,
      attended_at = CASE
                      WHEN p_status = 'Atendido' THEN NOW()
                      ELSE attended_at
                    END
  WHERE id = p_ticket_id
    AND technician_id = p_technician_id;

  INSERT INTO ticket_history (ticket_id, status, changed_by, comment)
  VALUES (p_ticket_id, p_status, p_technician_id, p_comment);
END$$
DELIMITER ;

-- ------------------------------------------------------------
-- 5. DATOS DE EJEMPLO
-- ------------------------------------------------------------

-- Oficinas
INSERT INTO oficina (id, name, location, location_detail, is_active) VALUES
(1, 'Gerencia General',              'Edificio Principal',     'Piso 1',              1),
(2, 'Recursos Humanos',              'Edificio Administrativo','Piso 2',              1),
(3, 'Contabilidad',                  'Edificio Financiero',    'Piso 3',              1),
(4, 'Tecnologias de la Informacion', 'Edificio TI',            'Piso 2 - Oficina 201',1),
(5, 'Logistica',                     'Almacen Central',        'Zona Industrial',     1);

-- Trabajadores
INSERT INTO trabajadores (id, role, dni, first_name, last_name, email, phone, office_id, password, created_at, is_active) VALUES
(1, 'admin',   '70000001', 'Carlos', 'Ramirez', 'admin1@empresa.com',  '999999999', 4, '123456', '2026-04-13 18:39:14', 1),
(2, 'tecnico', '70000002', 'Luis',   'Quispe',  'luis.ti@empresa.com', '987654322', 4, '123456', '2026-04-13 18:39:14', 1),
(3, 'tecnico', '70000003', 'Ana',    'Flores',  'ana.ti@empresa.com',  '987654323', 4, '123456', '2026-04-13 18:39:14', 1),
(4, 'tecnico', '70000004', 'Jorge',  'Perez',   'jorge.ti@empresa.com','987654324', 4, '123456', '2026-04-13 18:39:14', 1);

-- Usuarios
INSERT INTO usuarios (id, dni, first_name, last_name, email, phone, office_id, password, created_at, is_active) VALUES
(1, '70000005', 'Maria', 'Lopez',  'maria@empresa.com', '987654325', 2,    '123456',  '2026-04-13 18:39:14', 1),
(2, '70000006', 'Pedro', 'Gomez',  'pedro@empresa.com', '987654326', 3,    '123456',  '2026-04-13 18:39:14', 1),
(3, '70000007', 'Lucia', 'Torres', 'lucia@empresa.com', '987654327', 5,    '123456',  '2026-04-13 18:39:14', 1),
(4, '70000008', 'Jose',  'Vargas', 'jose@empresa.com',  '987654328', 2,    '123456',  '2026-04-13 18:39:14', 1),
(5, '70000009', 'Pedro', 'Aquino', NULL,                '999999999', 3,    '70000009','2026-04-15 11:02:49', 1),
(6, '99999999', 'Test',  'Test',   NULL,                '123',       NULL, '99999999','2026-04-15 11:32:46', 1),
(7, '99999998', 'Jane',  'Doe',    NULL,                '333',       NULL, '99999998','2026-04-15 11:33:12', 1);

-- Tickets (se suspende el trigger para insertar IDs historicos)
DROP TRIGGER IF EXISTS trigger_generate_ticket_id;

INSERT INTO tickets (id, user_id, technician_id, office_id, category, title, description, tech_comment, status, attended_at, created_at) VALUES
(202604001, 1, 2,    2, 'Software',    'Error en sistema contable', 'No puedo acceder al sistema contable',        'Se reinicio el servidor de la DB.',                     'Atendido',  '2026-04-13 18:39:14', '2026-04-13 18:39:14'),
(202604002, 2, 3,    3, 'Hardware',    'PC no enciende',            'El equipo no responde al presionar el boton', '',                                                      'En proceso', NULL,                 '2026-04-13 18:39:14'),
(202604003, 3, NULL, 5, 'Internet',    'Sin conexion',              'No hay acceso a internet en mi area',         '',                                                      'Pendiente',  NULL,                 '2026-04-13 18:39:14'),
(202604004, 4, 4,    2, 'Instalacion', 'Instalar impresora',        'Necesito instalar impresora nueva',           'No es politica del area instalar impresoras personales.','Rechazado', '2026-04-13 18:39:14','2026-04-13 18:39:14'),
(202604005, 1, 2,    2, 'Software',    'Office no funciona',        'Word se cierra automaticamente',              '',                                                      'En proceso', NULL,                 '2026-04-13 18:39:14'),
(202604006, 1, 2,    3, 'Otro',        'adasjdlasjld',              'asdalsdjlk',                                  'mensaje asd',                                           'Atendido',  '2026-04-14 10:04:57','2026-04-14 10:00:52'),
(202604007, 1, 2,    3, 'Software',    'asdasdasd',                 'asdasdasd',                                   '',                                                      'Atendido',  '2026-04-14 10:04:06','2026-04-14 10:02:51'),
(202604008, 5, 2,    3, 'Internet',    'web',                       'aaaddsd',                                     'ok',                                                    'Atendido',  '2026-04-15 11:45:40','2026-04-15 11:02:49'),
(202604009, 7, 2,    NULL,'Software',  'X',                         'Y',                                           NULL,                                                    'En camino',  NULL,                 '2026-04-15 11:33:12'),
(202605000, 1, NULL, 2, 'Software',    'office xd',                 '123',                                         NULL,                                                    'Pendiente',  NULL,                 '2026-05-04 16:01:34'),
(202605001, 1, NULL, 2, 'Instalacion', 'asd',                       'zxc',                                         NULL,                                                    'Pendiente',  NULL,                 '2026-05-04 16:02:59'),
(202605002, 2, NULL, 3, 'Hardware',    'adaswvwfe',                 'qefq3r',                                      NULL,                                                    'Pendiente',  NULL,                 '2026-05-04 16:03:33');

-- Recrear el trigger despues de los datos historicos
DELIMITER $$
CREATE TRIGGER trigger_generate_ticket_id
BEFORE INSERT ON tickets
FOR EACH ROW
BEGIN
  DECLARE v_prefix INT;
  DECLARE v_max_id INT;

  SET v_prefix = CAST(DATE_FORMAT(NOW(), '%Y%m') AS UNSIGNED);

  SELECT MAX(id) INTO v_max_id
  FROM tickets
  WHERE id DIV 1000 = v_prefix;

  IF v_max_id IS NULL THEN
    SET NEW.id = v_prefix * 1000 + 1;
  ELSE
    SET NEW.id = v_max_id + 1;
  END IF;
END$$
DELIMITER ;

-- Historial de tickets
INSERT INTO ticket_history (id, ticket_id, status, comment, changed_by, created_at) VALUES
(1,  202604001, 'Pendiente',  'Ticket creado',                                          1,    '2026-04-13 18:39:14'),
(2,  202604001, 'En proceso', 'Asignado a tecnico',                                     1,    '2026-04-13 18:39:14'),
(3,  202604001, 'Atendido',   'Solucionado',                                            2,    '2026-04-13 18:39:14'),
(4,  202604002, 'Pendiente',  'Ticket creado',                                          1,    '2026-04-13 18:39:14'),
(5,  202604002, 'En proceso', 'Asignado a tecnico',                                     1,    '2026-04-13 18:39:14'),
(6,  202604003, 'Pendiente',  'Ticket creado',                                          1,    '2026-04-13 18:39:14'),
(7,  202604004, 'Pendiente',  'Ticket creado',                                          1,    '2026-04-13 18:39:14'),
(8,  202604004, 'Rechazado',  'Solicitud fuera de alcance',                             1,    '2026-04-13 18:39:14'),
(9,  202604005, 'Pendiente',  'Ticket creado',                                          1,    '2026-04-13 18:39:14'),
(10, 202604005, 'En proceso', 'Asignado a tecnico',                                     1,    '2026-04-13 18:39:14'),
(11, 202604001, 'Atendido',   'El administrador reescribio los detalles del ticket',    1,    '2026-04-13 21:32:40'),
(12, 202604006, 'Pendiente',  'Ticket creado',                                          NULL, '2026-04-14 10:00:52'),
(13, 202604006, 'En camino',  'Tecnico asignado',                                       1,    '2026-04-14 10:01:28'),
(14, 202604007, 'Pendiente',  'Ticket creado',                                          NULL, '2026-04-14 10:02:51'),
(15, 202604007, 'En camino',  'Tecnico asignado',                                       1,    '2026-04-14 10:03:09'),
(16, 202604006, 'En proceso', 'El tecnico actualizo el estado a En proceso',            2,    '2026-04-14 10:03:30'),
(17, 202604007, 'En proceso', 'El tecnico actualizo el estado a En proceso',            2,    '2026-04-14 10:03:52'),
(18, 202604007, 'Atendido',   'El tecnico actualizo el estado a Atendido',              2,    '2026-04-14 10:04:06'),
(19, 202604006, 'Atendido',   'El tecnico actualizo el estado a Atendido',              2,    '2026-04-14 10:04:57'),
(20, 202604008, 'Pendiente',  'Ticket creado desde el portal publico',                  NULL, '2026-04-15 11:02:49'),
(21, 202604009, 'Pendiente',  'Ticket creado desde el portal publico',                  NULL, '2026-04-15 11:33:12'),
(22, 202604008, 'En camino',  'Tecnico asignado',                                       1,    '2026-04-15 11:43:45'),
(23, 202604008, 'En proceso', 'El tecnico actualizo el estado a En proceso',            2,    '2026-04-15 11:45:26'),
(24, 202604008, 'Atendido',   'El tecnico actualizo el estado a Atendido',              2,    '2026-04-15 11:45:40'),
(25, 202604009, 'En camino',  'Tecnico asignado',                                       1,    '2026-04-21 18:37:59'),
(26, 202605000, 'Pendiente',  'Ticket creado (Admin)',                                  1,    '2026-05-04 16:01:34'),
(27, 202605001, 'Pendiente',  'Ticket creado (Admin)',                                  1,    '2026-05-04 16:02:59'),
(28, 202605002, 'Pendiente',  'Ticket creado (Admin)',                                  1,    '2026-05-04 16:03:33');

-- Archivos adjuntos
INSERT INTO ticket_files (id, ticket_id, file_path, uploaded_at) VALUES
(1, 202604001, 'uploads/ticket1_img1.jpg', '2026-04-13 18:39:14'),
(2, 202604001, 'uploads/ticket1_img2.jpg', '2026-04-13 18:39:14'),
(3, 202604002, 'uploads/ticket2_img1.jpg', '2026-04-13 18:39:14'),
(4, 202604003, 'uploads/ticket3_img1.jpg', '2026-04-13 18:39:14'),
(5, 202604004, 'uploads/ticket4_img1.jpg', '2026-04-13 18:39:14'),
(6, 202604005, 'uploads/ticket5_img1.jpg', '2026-04-13 18:39:14');

SET foreign_key_checks = 1;

-- ============================================================
-- FIN DEL SCRIPT
-- ============================================================
