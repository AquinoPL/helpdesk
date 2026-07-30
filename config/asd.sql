CREATE DATABASE  IF NOT EXISTS `helpdesk` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `helpdesk`;
-- MySQL dump 10.13  Distrib 8.0.45, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: helpdesk
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- ============================================================
-- ESTRUCTURA DE TABLAS
-- ============================================================

--
-- Table structure for table `oficina`
--

DROP TABLE IF EXISTS `oficina`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `oficina` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `location` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_detail` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_files`
--

DROP TABLE IF EXISTS `ticket_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_files` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ticket_id` int NOT NULL,
  `file_path` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_tf_ticket` (`ticket_id`),
  CONSTRAINT `fk_tf_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ticket_history`
--

DROP TABLE IF EXISTS `ticket_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=103 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `trabajadores`
--

DROP TABLE IF EXISTS `trabajadores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- ============================================================
-- INSERCION DE DATOS
-- (solo se conservan: oficinas y el usuario administrador)
-- ============================================================

--
-- Dumping data for table `oficina`
--

LOCK TABLES `oficina` WRITE;
/*!40000 ALTER TABLE `oficina` DISABLE KEYS */;
INSERT INTO `oficina` VALUES (1,'Gerencia General','Edificio Principal','Piso 1',1),(2,'Recursos Humanos','Edificio Administrativo','Piso 2',1),(3,'Contabilidad','Edificio Financiero','Piso 3',1),(4,'(SGTIC) Tecnologias de la Informacion','Edificio TI','Piso 2 - Oficina 201',1),(5,'Logistica','Almacen Central','Zona Industrial',1),(6,'hotel collasuyo','terminal collasuyo','https://www.google.com/maps/place/Terminal+Terrestre+Collasuyo/@-17.9864835,-70.2466617,18z/data=!4m6!3m5!1s0x915acf88ba2e02a7:0xbb80c9dd6d6ef420!8m2!3d-17.9866331!4d-70.2458551!16s%2Fg%2F1hd_342ln?entry=ttu&g_ep=EgoyMDI2MDcxNS4wIKXMDSoASAFQAw%3D%3D',1);
/*!40000 ALTER TABLE `oficina` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `ticket_files`
-- (sin datos)
--

--
-- Dumping data for table `ticket_history`
-- (sin datos)
--

--
-- Dumping data for table `tickets`
-- (sin datos)
--

--
-- Dumping data for table `trabajadores`
-- (solo el usuario administrador)
--

LOCK TABLES `trabajadores` WRITE;
/*!40000 ALTER TABLE `trabajadores` DISABLE KEYS */;
INSERT INTO `trabajadores` VALUES (1,'admin','70000001','Carlos','Ramirez','admin1@empresa.com','999999999',4,'123456','2026-04-13 18:39:14',1);
/*!40000 ALTER TABLE `trabajadores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `usuarios`
-- (sin datos)
--

--
-- Dumping routines for database 'helpdesk'
--
/*!50003 DROP PROCEDURE IF EXISTS `asignar_tecnico` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `create_ticket` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
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

  -- Recuperar el id generado por el trigger (sin LIKE para evitar colision de collation)
  SET v_prefix = CAST(DATE_FORMAT(NOW(), '%Y%m') AS UNSIGNED);
  SELECT MAX(id) INTO p_new_id
  FROM tickets
  WHERE id DIV 1000 = v_prefix;

  INSERT INTO ticket_history (ticket_id, status, changed_by, comment)
  VALUES (p_new_id, 'Pendiente', NULL, 'Ticket creado');
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `create_trabajador` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `create_usuario` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `login_user` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `login_user`(
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
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `search_office` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `update_ticket_status` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
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
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
-- ============================================================
-- TRIGGER: Generacion automatica de ID de ticket (YYYYMM###)
-- ============================================================

/*!50003 DROP TRIGGER IF EXISTS `trigger_generate_ticket_id` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` TRIGGER `trigger_generate_ticket_id`
BEFORE INSERT ON `tickets`
FOR EACH ROW
BEGIN
    DECLARE v_prefix  BIGINT UNSIGNED;
    DECLARE v_max_id  BIGINT UNSIGNED;

    -- Genera ID formato YYYYMM### (ej: 202607025)
    SET v_prefix = CAST(DATE_FORMAT(NOW(), '%Y%m') AS UNSIGNED);
    SELECT MAX(id) INTO v_max_id FROM tickets WHERE id DIV 1000 = v_prefix;
    IF v_max_id IS NULL THEN
        SET NEW.id = v_prefix * 1000 + 1;
    ELSE
        SET NEW.id = v_max_id + 1;
    END IF;
    SET @last_inserted_ticket_id = NEW.id;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-30  9:07:44

--
-- Dumping data for table `trabajadores` (técnicos adicionales)
--

LOCK TABLES `trabajadores` WRITE;
/*!40000 ALTER TABLE `trabajadores` DISABLE KEYS */;
INSERT INTO `trabajadores` VALUES
(2,'tecnico','70000002','Luis','Quispe','luis.ti@empresa.com','987654322',4,'123456','2026-04-13 18:39:14',1),
(3,'tecnico','70000003','Ana','Flores','ana.ti@empresa.com','987654323',4,'123456','2026-04-13 18:39:14',1),
(4,'tecnico','70000004','Jorge','Perez','jorge.ti@empresa.com','987654324',4,'123456','2026-04-13 18:39:14',1);
/*!40000 ALTER TABLE `trabajadores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES
(1,'DNI','70000005','Maria','Lopez','maria@empresa.com','991388807',4,'123456','2026-04-13 18:39:14',1,1),
(2,'DNI','70000006','Pedro','Gomez','pedro@empresa.com','987654326',3,'123456','2026-04-13 18:39:14',1,1),
(3,'DNI','70000007','Lucia','Torres','lucia@empresa.com','987654327',5,'123456','2026-04-13 18:39:14',1,1),
(4,'DNI','70000008','Jose','Vargas','jose@empresa.com','98989898',2,'123456','2026-04-13 18:39:14',1,1);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;