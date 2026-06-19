-- ============================================================
-- PASO 4: DATOS INICIALES
-- Ejecutar después de 03_triggers_y_procedimientos.sql
-- ============================================================
-- IMPORTANTE: el trigger trigger_generate_ticket_id se desactiva
-- temporalmente para insertar los IDs históricos tal como
-- estaban en la base de datos original.
-- ============================================================

USE helpdesk;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Datos: oficina
-- ------------------------------------------------------------
INSERT INTO oficina (id, name, location, location_detail, is_active) VALUES
(1, 'Gerencia General',              'Edificio Principal',      'Piso 1',               1),
(2, 'Recursos Humanos',              'Edificio Administrativo', 'Piso 2',               1),
(3, 'Contabilidad',                  'Edificio Financiero',     'Piso 3',               1),
(4, 'Tecnologías de la Información', 'Edificio TI',             'Piso 2 - Oficina 201', 1),
(5, 'Logística',                     'Almacén Central',         'Zona Industrial',      1);


-- ------------------------------------------------------------
-- Datos: usuarios
-- ------------------------------------------------------------
INSERT INTO usuarios (id, dni, first_name, last_name, email, phone, office_id, password, created_at, is_active) VALUES
(1, '70000005', 'Maria', 'Lopez',  'maria@empresa.com', '987654325', 2,    '123456',   '2026-04-13 18:39:14', 1),
(2, '70000006', 'Pedro', 'Gomez',  'pedro@empresa.com', '987654326', 3,    '123456',   '2026-04-13 18:39:14', 1),
(3, '70000007', 'Lucia', 'Torres', 'lucia@empresa.com', '987654327', 5,    '123456',   '2026-04-13 18:39:14', 1),
(4, '70000008', 'Jose',  'Vargas', 'jose@empresa.com',  '987654328', 2,    '123456',   '2026-04-13 18:39:14', 1),
(5, '70000009', 'Pedro', 'Aquino', NULL,                '999999999', 3,    '70000009', '2026-04-15 11:02:49', 1),
(6, '99999999', 'Test',  'Test',   NULL,                '123',       NULL, '99999999', '2026-04-15 11:32:46', 1),
(7, '99999998', 'Jane',  'Doe',    NULL,                '333',       NULL, '99999998', '2026-04-15 11:33:12', 1);


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
-- Se deshabilita el trigger para insertar IDs históricos.
-- ------------------------------------------------------------
DROP TRIGGER IF EXISTS trigger_generate_ticket_id;

INSERT INTO tickets (id, user_id, technician_id, office_id, category, title, description, tech_comment, status, attended_at, created_at) VALUES
(202604001, 1, 2,    2,    'Software',    'Error en sistema contable', 'No puedo acceder al sistema contable',         'Se reinició el servidor de la DB.',                      'Atendido',   '2026-04-13 18:39:14', '2026-04-13 18:39:14'),
(202604002, 2, 3,    3,    'Hardware',    'PC no enciende',            'El equipo no responde al presionar el botón',  '',                                                       'En proceso', NULL,                  '2026-04-13 18:39:14'),
(202604003, 3, NULL, 5,    'Internet',    'Sin conexión',              'No hay acceso a internet en mi área',          '',                                                       'Pendiente',  NULL,                  '2026-04-13 18:39:14'),
(202604004, 4, 4,    2,    'Instalacion', 'Instalar impresora',        'Necesito instalar impresora nueva',            'No es política del área instalar impresoras personales.','Rechazado',  '2026-04-13 18:39:14', '2026-04-13 18:39:14'),
(202604005, 1, 2,    2,    'Software',    'Office no funciona',        'Word se cierra automáticamente',               '',                                                       'En proceso', NULL,                  '2026-04-13 18:39:14'),
(202604006, 1, 2,    3,    'Otro',        'adasjdlasjld',              'asdalsdjlk',                                   'mensaje asd',                                            'Atendido',   '2026-04-14 10:04:57', '2026-04-14 10:00:52'),
(202604007, 1, 2,    3,    'Software',    'asdasdasd',                 'asdasdasd',                                    '',                                                       'Atendido',   '2026-04-14 10:04:06', '2026-04-14 10:02:51'),
(202604008, 5, 2,    3,    'Internet',    'web',                       'aaaddsd',                                      'ok',                                                     'Atendido',   '2026-04-15 11:45:40', '2026-04-15 11:02:49'),
(202604009, 7, 2,    NULL, 'Software',    'X',                         'Y',                                            NULL,                                                     'En camino',  NULL,                  '2026-04-15 11:33:12'),
(202605000, 1, NULL, 2,    'Software',    'office xd',                 '123',                                          NULL,                                                     'Pendiente',  NULL,                  '2026-05-04 16:01:34'),
(202605001, 1, NULL, 2,    'Instalacion', 'asd',                       'zxc',                                          NULL,                                                     'Pendiente',  NULL,                  '2026-05-04 16:02:59'),
(202605002, 2, NULL, 3,    'Hardware',    'adaswvwfe',                 'qefq3r',                                       NULL,                                                     'Pendiente',  NULL,                  '2026-05-04 16:03:33');

-- Restaurar el trigger para futuros inserts de la aplicación
DELIMITER $$

CREATE TRIGGER trigger_generate_ticket_id
BEFORE INSERT ON tickets
FOR EACH ROW
BEGIN
    DECLARE v_prefix  INT UNSIGNED;
    DECLARE v_max_id  INT UNSIGNED;

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
( 1, 202604001, 'Pendiente',  'Ticket creado',                                       1,    '2026-04-13 18:39:14'),
( 2, 202604001, 'En proceso', 'Asignado a técnico',                                  1,    '2026-04-13 18:39:14'),
( 3, 202604001, 'Atendido',   'Solucionado',                                         2,    '2026-04-13 18:39:14'),
( 4, 202604002, 'Pendiente',  'Ticket creado',                                       1,    '2026-04-13 18:39:14'),
( 5, 202604002, 'En proceso', 'Asignado a técnico',                                  1,    '2026-04-13 18:39:14'),
( 6, 202604003, 'Pendiente',  'Ticket creado',                                       1,    '2026-04-13 18:39:14'),
( 7, 202604004, 'Pendiente',  'Ticket creado',                                       1,    '2026-04-13 18:39:14'),
( 8, 202604004, 'Rechazado',  'Solicitud fuera de alcance',                          1,    '2026-04-13 18:39:14'),
( 9, 202604005, 'Pendiente',  'Ticket creado',                                       1,    '2026-04-13 18:39:14'),
(10, 202604005, 'En proceso', 'Asignado a técnico',                                  1,    '2026-04-13 18:39:14'),
(11, 202604001, 'Atendido',   'El administrador reescribió los detalles del ticket', 1,    '2026-04-13 21:32:40'),
(12, 202604006, 'Pendiente',  'Ticket creado',                                       NULL, '2026-04-14 10:00:52'),
(13, 202604006, 'En camino',  'Técnico asignado',                                    1,    '2026-04-14 10:01:28'),
(14, 202604007, 'Pendiente',  'Ticket creado',                                       NULL, '2026-04-14 10:02:51'),
(15, 202604007, 'En camino',  'Técnico asignado',                                    1,    '2026-04-14 10:03:09'),
(16, 202604006, 'En proceso', 'El técnico actualizó el estado a En proceso',         2,    '2026-04-14 10:03:30'),
(17, 202604007, 'En proceso', 'El técnico actualizó el estado a En proceso',         2,    '2026-04-14 10:03:52'),
(18, 202604007, 'Atendido',   'El técnico actualizó el estado a Atendido',           2,    '2026-04-14 10:04:06'),
(19, 202604006, 'Atendido',   'El técnico actualizó el estado a Atendido',           2,    '2026-04-14 10:04:57'),
(20, 202604008, 'Pendiente',  'Ticket creado desde el portal público',               NULL, '2026-04-15 11:02:49'),
(21, 202604009, 'Pendiente',  'Ticket creado desde el portal público',               NULL, '2026-04-15 11:33:12'),
(22, 202604008, 'En camino',  'Técnico asignado',                                    1,    '2026-04-15 11:43:45'),
(23, 202604008, 'En proceso', 'El técnico actualizó el estado a En proceso',         2,    '2026-04-15 11:45:26'),
(24, 202604008, 'Atendido',   'El técnico actualizó el estado a Atendido',           2,    '2026-04-15 11:45:40'),
(25, 202604009, 'En camino',  'Técnico asignado',                                    1,    '2026-04-21 18:37:59'),
(26, 202605000, 'Pendiente',  'Ticket creado (Admin)',                               1,    '2026-05-04 16:01:34'),
(27, 202605001, 'Pendiente',  'Ticket creado (Admin)',                               1,    '2026-05-04 16:02:59'),
(28, 202605002, 'Pendiente',  'Ticket creado (Admin)',                               1,    '2026-05-04 16:03:33');


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


SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FIN PASO 4 - Base de datos lista para usar.
-- ============================================================
