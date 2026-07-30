CREATE DATABASE IF NOT EXISTS `helpdesk` ;
USE `helpdesk`;

DROP TABLE IF EXISTS `oficina`;
CREATE TABLE `oficina` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_detail` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `trabajadores`;
CREATE TABLE `trabajadores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role` enum('admin','tecnico') COLLATE utf8mb4_unicode_ci NOT NULL,
  `dni` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_trabajadores_dni` (`dni`),
  UNIQUE KEY `uq_trabajadores_email` (`email`),
  KEY `fk_trabajadores_oficina` (`office_id`),
  CONSTRAINT `fk_trabajadores_oficina` FOREIGN KEY (`office_id`) REFERENCES `oficina` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `doc_type` enum('DNI','CE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DNI',
  `dni` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_registered` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_dni` (`dni`),
  KEY `fk_usuarios_oficina` (`office_id`),
  CONSTRAINT `fk_usuarios_oficina` FOREIGN KEY (`office_id`) REFERENCES `oficina` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `tickets`;
CREATE TABLE `tickets` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `technician_id` int DEFAULT NULL,
  `office_id` int DEFAULT NULL,
  `category` enum('Instalacion','Software','Hardware','Internet','Otro') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `tech_comment` text COLLATE utf8mb4_unicode_ci,
  `status` enum('Pendiente','En camino','En proceso','Atendido','Rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pendiente',
  `attended_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` datetime DEFAULT NULL,
  `rating` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tickets_status` (`status`),
  KEY `idx_tickets_technician` (`technician_id`),
  KEY `idx_tickets_user` (`user_id`),
  KEY `fk_tickets_oficina` (`office_id`),
  CONSTRAINT `fk_tickets_oficina` FOREIGN KEY (`office_id`) REFERENCES `oficina` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tickets_tecnico` FOREIGN KEY (`technician_id`) REFERENCES `trabajadores` (`id`),
  CONSTRAINT `fk_tickets_usuario` FOREIGN KEY (`user_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ticket_files`;
CREATE TABLE `ticket_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `file_path` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_tf_ticket` (`ticket_id`),
  CONSTRAINT `fk_tf_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `ticket_history`;
CREATE TABLE `ticket_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `status` enum('Pendiente','En camino','En proceso','Atendido','Rechazado') COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` text COLLATE utf8mb4_unicode_ci,
  `changed_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_th_ticket` (`ticket_id`),
  KEY `fk_th_trabajador` (`changed_by`),
  CONSTRAINT `fk_th_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_th_trabajador` FOREIGN KEY (`changed_by`) REFERENCES `trabajadores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `trabajadores` (id, role, dni, first_name, last_name, email, phone, office_id, password, created_at, is_active)
VALUES (1,'admin','70000001','Carlos','Ramirez','admin1@empresa.com','999999999',4,'123456','2026-04-13 18:39:14',1);

DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `asignar_tecnico`(
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
END ;;
DELIMITER ;

DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `create_ticket`(
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

  SET v_prefix = CAST(DATE_FORMAT(NOW(), '%Y%m') AS UNSIGNED);
  SELECT MAX(id) INTO p_new_id
  FROM tickets
  WHERE id DIV 1000 = v_prefix;

  INSERT INTO ticket_history (ticket_id, status, changed_by, comment)
  VALUES (p_new_id, 'Pendiente', NULL, 'Ticket creado');
END ;;
DELIMITER ;

DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `create_trabajador`(
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
END ;;
DELIMITER ;

DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `create_usuario`(
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
END ;;
DELIMITER ;

DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `login_user`(
  IN p_dni      VARCHAR(20),
  IN p_password VARCHAR(255)
)
BEGIN
  SELECT t.id, t.role AS role, t.first_name, t.last_name, t.office_id
  FROM trabajadores t
  WHERE t.dni = p_dni AND t.password = p_password

  UNION

  SELECT u.id, 'usuario' AS role, u.first_name, u.last_name, u.office_id
  FROM usuarios u
  WHERE u.dni = p_dni AND u.password = p_password;
END ;;
DELIMITER ;

DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `search_office`(
  IN p_name VARCHAR(100)
)
BEGIN
  SELECT id, name, location, location_detail
  FROM oficina
  WHERE name LIKE CONCAT('%', p_name, '%');
END ;;
DELIMITER ;

DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `update_ticket_status`(
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
END ;;
DELIMITER ;