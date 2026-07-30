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
-- Dumping data for table `oficina`
--

LOCK TABLES `oficina` WRITE;
/*!40000 ALTER TABLE `oficina` DISABLE KEYS */;
INSERT INTO `oficina` VALUES (1,'Gerencia General','Edificio Principal','Piso 1',1),(2,'Recursos Humanos','Edificio Administrativo','Piso 2',1),(3,'Contabilidad','Edificio Financiero','Piso 3',1),(4,'(SGTIC) Tecnologias de la Informacion','Edificio TI','Piso 2 - Oficina 201',1),(5,'Logistica','Almacen Central','Zona Industrial',1),(6,'hotel collasuyo','terminal collasuyo','https://www.google.com/maps/place/Terminal+Terrestre+Collasuyo/@-17.9864835,-70.2466617,18z/data=!4m6!3m5!1s0x915acf88ba2e02a7:0xbb80c9dd6d6ef420!8m2!3d-17.9866331!4d-70.2458551!16s%2Fg%2F1hd_342ln?entry=ttu&g_ep=EgoyMDI2MDcxNS4wIKXMDSoASAFQAw%3D%3D',1);
/*!40000 ALTER TABLE `oficina` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `ticket_files`
--

LOCK TABLES `ticket_files` WRITE;
/*!40000 ALTER TABLE `ticket_files` DISABLE KEYS */;
INSERT INTO `ticket_files` VALUES (1,202604001,'uploads/ticket1_img1.jpg','2026-04-13 18:39:14'),(2,202604001,'uploads/ticket1_img2.jpg','2026-04-13 18:39:14'),(3,202604002,'uploads/ticket2_img1.jpg','2026-04-13 18:39:14'),(5,202604004,'uploads/ticket4_img1.jpg','2026-04-13 18:39:14'),(6,202604005,'uploads/ticket5_img1.jpg','2026-04-13 18:39:14'),(13,202607005,'uploads/2026-07/ticket_202607005_1783534079_1783534068652211399961607375207.jpg','2026-07-08 13:07:59'),(14,202607006,'uploads/2026-07/ticket_202607006_1784558714_computadoraamd.webp','2026-07-20 09:45:14'),(17,202607007,'uploads/2026-07/ticket_202607007_1784752124_computadoraamd.webp','2026-07-22 15:28:44'),(18,202607007,'uploads/2026-07/ticket_202607007_1784752124_cliente.html','2026-07-22 15:28:44'),(19,202607008,'uploads/2026-07/ticket_202607008_1784752397_LaptopocomputadoradeescritorioKarinaTapiaDigitalTrendsenEspanol.webp','2026-07-22 15:33:17'),(20,202607008,'uploads/2026-07/ticket_202607008_1784752397_admin.html','2026-07-22 15:33:17'),(21,202607009,'uploads/2026-07/ticket_202607009_1784753535_LaptopocomputadoradeescritorioKarinaTapiaDigitalTrendsenEspanol.webp','2026-07-22 15:52:15'),(22,202607009,'uploads/2026-07/ticket_202607009_1784753535_admin.html','2026-07-22 15:52:15'),(23,202607011,'uploads/2026-07/ticket_202607011_1784754374_computadoraamd.webp','2026-07-22 16:06:14'),(29,202607013,'uploads/2026-07/ticket_202607013_1784846423_computadoraamd.webp','2026-07-23 17:40:23'),(30,202607014,'uploads/2026-07/ticket_202607014_1784847624_computadoraamd.webp','2026-07-23 18:00:24'),(31,202607014,'uploads/2026-07/ticket_202607014_1784847624_CapturasHelpdeskAltodelaAlianza.docx','2026-07-23 18:00:24'),(32,202607015,'uploads/2026-07/ticket_202607015_1784906614_17171887541717188754jpg.jpg','2026-07-24 10:23:34'),(35,202607018,'uploads/2026-07/ticket_202607018_1784908591_GeminiGeneratedImage4yn1v24yn1v24yn11.png','2026-07-24 10:56:31'),(36,202607019,'uploads/2026-07/ticket_202607019_1784908686_computadoraamd.webp','2026-07-24 10:58:06'),(37,202607020,'uploads/2026-07/ticket_202607020_1784908764_GeminiGeneratedImage4yn1v24yn1v24yn11.png','2026-07-24 10:59:24'),(40,202607021,'uploads/2026-07/ticket_202607021_1784910841_computadoraamd.webp','2026-07-24 11:34:01'),(41,202607022,'uploads/2026-07/ticket_202607022_1784910882_17849108467792852338799168394861.jpg','2026-07-24 11:34:42'),(42,202607023,'uploads/2026-07/ticket_202607023_1784916235_17171887541717188754jpg.jpg','2026-07-24 13:03:55');
/*!40000 ALTER TABLE `ticket_files` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `ticket_history`
--

LOCK TABLES `ticket_history` WRITE;
/*!40000 ALTER TABLE `ticket_history` DISABLE KEYS */;
INSERT INTO `ticket_history` VALUES (1,202604001,'Pendiente','Ticket creado',1,'2026-04-13 18:39:14'),(2,202604001,'En proceso','Asignado a tecnico',1,'2026-04-13 18:39:14'),(3,202604001,'Atendido','Solucionado',2,'2026-04-13 18:39:14'),(4,202604002,'Pendiente','Ticket creado',1,'2026-04-13 18:39:14'),(5,202604002,'En proceso','Asignado a tecnico',1,'2026-04-13 18:39:14'),(7,202604004,'Pendiente','Ticket creado',1,'2026-04-13 18:39:14'),(8,202604004,'Rechazado','Solicitud fuera de alcance',1,'2026-04-13 18:39:14'),(9,202604005,'Pendiente','Ticket creado',1,'2026-04-13 18:39:14'),(10,202604005,'En proceso','Asignado a tecnico',1,'2026-04-13 18:39:14'),(11,202604001,'Atendido','El administrador reescribio los detalles del ticket',1,'2026-04-13 21:32:40'),(12,202604006,'Pendiente','Ticket creado',NULL,'2026-04-14 10:00:52'),(13,202604006,'En camino','Tecnico asignado',1,'2026-04-14 10:01:28'),(14,202604007,'Pendiente','Ticket creado',NULL,'2026-04-14 10:02:51'),(15,202604007,'En camino','Tecnico asignado',1,'2026-04-14 10:03:09'),(16,202604006,'En proceso','El tecnico actualizo el estado a En proceso',2,'2026-04-14 10:03:30'),(17,202604007,'En proceso','El tecnico actualizo el estado a En proceso',2,'2026-04-14 10:03:52'),(18,202604007,'Atendido','El tecnico actualizo el estado a Atendido',2,'2026-04-14 10:04:06'),(19,202604006,'Atendido','El tecnico actualizo el estado a Atendido',2,'2026-04-14 10:04:57'),(20,202604008,'Pendiente','Ticket creado desde el portal publico',NULL,'2026-04-15 11:02:49'),(21,202604009,'Pendiente','Ticket creado desde el portal publico',NULL,'2026-04-15 11:33:12'),(22,202604008,'En camino','Tecnico asignado',1,'2026-04-15 11:43:45'),(23,202604008,'En proceso','El tecnico actualizo el estado a En proceso',2,'2026-04-15 11:45:26'),(24,202604008,'Atendido','El tecnico actualizo el estado a Atendido',2,'2026-04-15 11:45:40'),(25,202604009,'En camino','Tecnico asignado',1,'2026-04-21 18:37:59'),(34,202607005,'Pendiente','Ticket creado desde el portal público',NULL,'2026-07-08 13:07:59'),(35,202607005,'En camino','Tecnico asignado',1,'2026-07-08 13:09:38'),(36,202607005,'En proceso','El técnico actualizó el estado a En proceso',2,'2026-07-16 12:38:07'),(37,202607005,'Atendido','El técnico actualizó el estado a Atendido',2,'2026-07-16 12:38:37'),(38,202607006,'Pendiente','Ticket creado desde el portal público',NULL,'2026-07-20 09:45:14'),(39,202607006,'En camino','Tecnico asignado',1,'2026-07-20 09:47:26'),(40,202607006,'En proceso','El técnico actualizó el estado a En proceso',3,'2026-07-20 09:48:54'),(41,202607006,'Rechazado','El técnico actualizó el estado a Rechazado',3,'2026-07-20 09:49:18'),(48,202604002,'Atendido','El técnico actualizó el estado a Atendido',3,'2026-07-20 11:55:15'),(49,202604009,'En proceso','El técnico actualizó el estado a En proceso',2,'2026-07-20 12:00:03'),(50,202604009,'Atendido','El técnico actualizó el estado a Atendido',2,'2026-07-20 12:00:08'),(51,202604005,'Rechazado','El técnico actualizó el estado a Rechazado',2,'2026-07-20 12:00:23'),(57,202607007,'Pendiente','Ticket creado desde el portal público',NULL,'2026-07-22 15:28:44'),(58,202607008,'Pendiente','Ticket creado desde el portal público',NULL,'2026-07-22 15:33:17'),(59,202607009,'Pendiente','Ticket creado desde el portal público',NULL,'2026-07-22 15:52:15'),(61,202607011,'Pendiente','Ticket creado',NULL,'2026-07-22 16:06:14'),(62,202607011,'En camino','Tecnico asignado',1,'2026-07-22 16:18:22'),(69,202607011,'En proceso','El técnico actualizó el estado a En proceso',6,'2026-07-23 17:21:28'),(70,202607011,'Atendido','El técnico actualizó el estado a Atendido',6,'2026-07-23 17:21:40'),(71,202607013,'Pendiente','Ticket creado',NULL,'2026-07-23 17:40:23'),(72,202607013,'En camino','Tecnico asignado',1,'2026-07-23 17:41:12'),(73,202607014,'Pendiente','Ticket creado desde el portal público',NULL,'2026-07-23 18:00:24'),(74,202607013,'En proceso','El técnico actualizó el estado a En proceso',3,'2026-07-24 10:21:54'),(75,202607013,'Atendido','El técnico actualizó el estado a Atendido',3,'2026-07-24 10:22:00'),(76,202607015,'Pendiente','Ticket creado desde el portal público',NULL,'2026-07-24 10:23:34'),(79,202607015,'En camino','Técnico reasignado por el administrador',1,'2026-07-24 10:29:08'),(80,202607015,'En proceso','El técnico actualizó el estado a En proceso',6,'2026-07-24 10:29:37'),(81,202607015,'Atendido','El técnico actualizó el estado a Atendido',6,'2026-07-24 10:31:52'),(84,202607018,'Pendiente','Ticket creado desde el portal público',NULL,'2026-07-24 10:56:31'),(85,202607019,'Pendiente','Ticket creado desde el portal público',NULL,'2026-07-24 10:58:06'),(86,202607020,'Pendiente','Ticket creado desde el portal público',NULL,'2026-07-24 10:59:24'),(87,202607020,'En camino','Tecnico asignado',1,'2026-07-24 11:00:41'),(88,202607020,'En proceso','El técnico actualizó el estado a En proceso',4,'2026-07-24 11:02:35'),(89,202607020,'Rechazado','El técnico actualizó el estado a Rechazado',4,'2026-07-24 11:03:20'),(92,202607021,'Pendiente','Ticket creado (Admin)',1,'2026-07-24 11:34:01'),(93,202607022,'Pendiente','Ticket creado desde el portal público',NULL,'2026-07-24 11:34:42'),(94,202607022,'Rechazado','ya no trabajo aqui',1,'2026-07-24 11:35:28'),(96,202607021,'Rechazado','asddad',1,'2026-07-24 11:45:33'),(97,202607019,'En camino','Tecnico asignado',1,'2026-07-24 11:45:46'),(98,202607019,'Rechazado','asdadasd',1,'2026-07-24 11:45:52'),(99,202607023,'Pendiente','Ticket creado',NULL,'2026-07-24 13:03:55'),(100,202607023,'En camino','Ticket auto-asignado por el técnico',6,'2026-07-24 13:06:04'),(102,202607024,'Pendiente','Ticket creado desde el portal público',NULL,'2026-07-25 17:05:24');
/*!40000 ALTER TABLE `ticket_history` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
INSERT INTO `tickets` VALUES (202604001,1,2,2,'Software','Error en sistema contable','No puedo acceder al sistema contable','Se reinicio el servidor de la DB.','Atendido','2026-04-13 18:39:14','2026-04-13 18:39:14',NULL,NULL),(202604002,2,3,3,'Hardware','PC no enciende','El equipo no responde al presionar el boton','','Atendido','2026-07-20 11:55:15','2026-04-13 18:39:14',NULL,NULL),(202604004,4,4,2,'Instalacion','Instalar impresora','Necesito instalar impresora nueva','No es politica del area instalar impresoras personales.','Rechazado','2026-04-13 18:39:14','2026-04-13 18:39:14',NULL,NULL),(202604005,1,2,2,'Software','Office no funciona','Word se cierra automaticamente','wqrqd','Rechazado',NULL,'2026-04-13 18:39:14',NULL,NULL),(202604006,1,2,3,'Otro','adasjdlasjld','asdalsdjlk','mensaje asd','Atendido','2026-04-14 10:04:57','2026-04-14 10:00:52',NULL,NULL),(202604007,1,2,3,'Software','asdasdasd','asdasdasd','','Atendido','2026-04-14 10:04:06','2026-04-14 10:02:51',NULL,NULL),(202604008,5,2,3,'Internet','web','aaaddsd','ok','Atendido','2026-04-15 11:45:40','2026-04-15 11:02:49',NULL,NULL),(202604009,7,2,NULL,'Software','X','Y',NULL,'Atendido','2026-07-20 12:00:08','2026-04-15 11:33:12',NULL,NULL),(202607005,1,2,3,'Hardware','Kiaioaks','',NULL,'Atendido','2026-07-16 12:38:37','2026-07-08 13:07:59',NULL,NULL),(202607006,8,3,4,'Hardware','asddf','weffq','asd','Rechazado',NULL,'2026-07-20 09:45:14',NULL,NULL),(202607007,32,NULL,4,'Hardware','Mdaa','sin conexion',NULL,'Pendiente',NULL,'2026-07-22 15:28:44',NULL,NULL),(202607008,32,NULL,4,'Hardware','Mdaa','sin conexion',NULL,'Pendiente',NULL,'2026-07-22 15:33:17',NULL,NULL),(202607009,32,NULL,5,'Hardware','Mdaa','sin conexion',NULL,'Pendiente',NULL,'2026-07-22 15:52:15',NULL,NULL),(202607011,8,6,4,'Hardware','asd','zccc',NULL,'Atendido','2026-07-23 17:21:40','2026-07-22 16:06:14',NULL,NULL),(202607013,8,3,4,'Internet','prueba','detalle',NULL,'Atendido','2026-07-24 10:22:00','2026-07-23 17:40:23',NULL,NULL),(202607014,35,NULL,4,'Internet','sin internet','asd',NULL,'Pendiente',NULL,'2026-07-23 18:00:24',NULL,NULL),(202607015,36,6,4,'Hardware','asd','adwd',NULL,'Atendido','2026-07-24 10:31:52','2026-07-24 10:23:34',NULL,NULL),(202607018,4,NULL,2,'Hardware','no prende mi pc','mi pc no prende necesito hacer trabajo urgente',NULL,'Pendiente',NULL,'2026-07-24 10:56:31',NULL,NULL),(202607019,37,NULL,4,'Instalacion','ASD','QWEQE','asdadasd','Rechazado',NULL,'2026-07-24 10:58:06',NULL,NULL),(202607020,38,4,4,'Hardware','no prende mi pc','mi pc no prende necesito hacer trabajo urgente','no tengo tiempo\r\nenchufa la pc p','Rechazado',NULL,'2026-07-24 10:59:24',NULL,NULL),(202607021,38,NULL,4,'Internet','asdad','asdadsa','asddad','Rechazado',NULL,'2026-07-24 11:34:01',NULL,NULL),(202607022,39,NULL,5,'Internet','Proxy','Activeme el proxy,\r\nMándame al de la foto, el tocó mi PC',NULL,'Rechazado',NULL,'2026-07-24 11:34:42',NULL,NULL),(202607023,8,6,4,'Internet','siga','asd',NULL,'En camino',NULL,'2026-07-24 13:03:55',NULL,NULL),(202607024,43,NULL,3,'Instalacion','asd','addad',NULL,'Pendiente',NULL,'2026-07-25 17:05:24',NULL,NULL);
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Dumping data for table `trabajadores`
--

LOCK TABLES `trabajadores` WRITE;
/*!40000 ALTER TABLE `trabajadores` DISABLE KEYS */;
INSERT INTO `trabajadores` VALUES (1,'admin','70000001','Carlos','Ramirez','admin1@empresa.com','999999999',4,'123456','2026-04-13 18:39:14',1),(2,'tecnico','70000002','Luis','Quispe','luis.ti@empresa.com','987654322',4,'123456','2026-04-13 18:39:14',1),(3,'tecnico','70000003','Ana','Flores','ana.ti@empresa.com','987654323',4,'123456','2026-04-13 18:39:14',1),(4,'tecnico','70000004','Jorge','Perez','jorge.ti@empresa.com','987654324',4,'123456','2026-04-13 18:39:14',1),(6,'tecnico','87654321','Pedro','Aquino',NULL,'991388807',NULL,'123456','2026-07-22 13:31:14',1);
/*!40000 ALTER TABLE `trabajadores` ENABLE KEYS */;
UNLOCK TABLES;

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

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'DNI','70000005','Maria','Lopez','maria@empresa.com','991388807',4,'123456','2026-04-13 18:39:14',1,1),(2,'DNI','70000006','Pedro','Gomez','pedro@empresa.com','987654326',3,'123456','2026-04-13 18:39:14',1,1),(3,'DNI','70000007','Lucia','Torres','lucia@empresa.com','987654327',5,'123456','2026-04-13 18:39:14',1,1),(4,'DNI','70000008','Jose','Vargas','jose@empresa.com','98989898',2,'123456','2026-04-13 18:39:14',1,1),(5,'DNI','70000009','Pedro','Aquino',NULL,'999999999',3,'70000009','2026-04-15 11:02:49',1,0),(7,'DNI','99999998','Jane','Doe',NULL,'999999999',3,'99999998','2026-04-15 11:33:12',1,0),(8,'DNI','77128110','Pedro','Aquino','aquino@gmail.com','999999999',4,'123456','2026-07-20 09:45:14',1,1),(31,'DNI','77128111','Pedro','Aquino','aquino@gmail.com','991388807',3,'@Aquino123','2026-07-22 12:00:20',1,1),(32,'DNI','12345678','Pedro','Aquino',NULL,'Q341211',4,'12345678','2026-07-22 15:28:44',1,0),(35,'DNI','9988776655','Juan','Perez',NULL,'999999999',4,'9988776655','2026-07-23 18:00:24',1,0),(36,'DNI','913201384','dasfdfsf','qwd',NULL,'92992929922',4,'913201384','2026-07-24 10:23:34',1,0),(37,'DNI','62624377','Pedro','Aquino',NULL,'991388807',6,'62624377','2026-07-24 10:58:06',1,0),(38,'DNI','77777777','Danu','Moon',NULL,'98989898',4,'77777777','2026-07-24 10:59:24',1,0),(39,'DNI','74208710','Milton','No está',NULL,'98870356',5,'74208710','2026-07-24 11:34:42',1,0),(40,'DNI','70000001','qwdedq','qwdqdqw',NULL,'999999999',2,'123456','2026-07-24 11:59:58',1,1),(43,'DNI','56565565','pedro','asd',NULL,'122555554',3,'56565565','2026-07-25 17:05:24',1,0);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

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
