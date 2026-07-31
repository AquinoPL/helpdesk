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