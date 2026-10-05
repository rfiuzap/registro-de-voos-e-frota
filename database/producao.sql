-- Banco de producao: estrutura completa (com usuarios) + aeronaves publicas iniciais.
-- As aeronaves ficam sem dono; o primeiro usuario cadastrado passa a ser o dono delas.


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aeronaves` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `fabricante` varchar(100) NOT NULL,
  `modelo` varchar(100) NOT NULL,
  `ano` int(11) NOT NULL DEFAULT 0,
  `velocidade_cruzeiro` decimal(10,2) NOT NULL,
  `velocidade_subida` decimal(10,2) NOT NULL DEFAULT 0.00,
  `razao_subida` decimal(10,2) NOT NULL,
  `teto_operacional` int(11) NOT NULL,
  `altitude_cruzeiro_ideal` int(11) NOT NULL,
  `autonomia` time NOT NULL,
  `capacidade_tanque` decimal(10,2) NOT NULL,
  `peso_vazio` decimal(10,2) NOT NULL,
  `peso_maximo_decolagem` decimal(10,2) NOT NULL,
  `carga_util` decimal(10,2) NOT NULL,
  `tipo_combustivel` enum('Avgas','JetA') NOT NULL,
  `potencia` decimal(10,2) NOT NULL,
  `motor` varchar(100) NOT NULL,
  `assentos` int(11) NOT NULL,
  `pressurizado` enum('Sim','Não') NOT NULL,
  `consumo_gph` decimal(10,2) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `valor` decimal(14,2) NOT NULL DEFAULT 0.00,
  `foto` varchar(255) DEFAULT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `visibilidade` enum('publica','privada') NOT NULL DEFAULT 'publica',
  PRIMARY KEY (`id`),
  KEY `idx_aeronaves_usuario` (`usuario_id`),
  CONSTRAINT `fk_aeronaves_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `aeronaves_ocultas` (
  `usuario_id` int(10) unsigned NOT NULL,
  `aeronave_id` int(10) unsigned NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`usuario_id`,`aeronave_id`),
  KEY `idx_aeronaves_ocultas_aeronave` (`aeronave_id`),
  CONSTRAINT `fk_ocultas_aeronave` FOREIGN KEY (`aeronave_id`) REFERENCES `aeronaves` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ocultas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_tentativas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `email` varchar(190) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_tentativas_ip` (`ip`,`criado_em`),
  KEY `idx_login_tentativas_email` (`email`,`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `planejamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `modelo_aeronave` varchar(100) NOT NULL,
  `origem` varchar(10) NOT NULL,
  `destino` varchar(10) NOT NULL,
  `distancia` decimal(10,2) NOT NULL,
  `altitude_cruzeiro` int(11) NOT NULL,
  `tanque_decolagem` decimal(10,2) NOT NULL,
  `velocidade_subida` decimal(10,2) NOT NULL,
  `razao_subida` decimal(10,2) NOT NULL,
  `velocidade_cruzeiro` decimal(10,2) NOT NULL,
  `razao_descida` decimal(10,2) NOT NULL,
  `consumo_gph` decimal(10,2) NOT NULL,
  `tempo_estimado` varchar(20) DEFAULT NULL,
  `consumo_estimado` decimal(10,2) DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_planejamentos_criado_em` (`criado_em`),
  KEY `idx_planejamentos_usuario` (`usuario_id`),
  CONSTRAINT `fk_planejamentos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `senha_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(10) unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expira_em` datetime NOT NULL,
  `usado_em` datetime DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_senha_resets_token` (`token_hash`),
  KEY `idx_senha_resets_usuario` (`usuario_id`),
  CONSTRAINT `fk_senha_resets_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuarios_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `voos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `aeronave` varchar(100) NOT NULL,
  `partida` varchar(100) NOT NULL,
  `destino` varchar(100) NOT NULL,
  `distancia_nm` decimal(10,2) NOT NULL,
  `tanque_decolagem` decimal(10,2) NOT NULL,
  `tanque_pouso` decimal(10,2) NOT NULL,
  `consumo_real` decimal(10,2) NOT NULL,
  `consumo_gph` decimal(10,2) NOT NULL,
  `tempo_voo` time NOT NULL,
  `altitude` int(11) NOT NULL,
  `velocidade` decimal(10,2) NOT NULL,
  `observacoes` text DEFAULT NULL,
  `criado_em` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_voos_partida_destino` (`partida`,`destino`),
  KEY `idx_voos_aeronave` (`aeronave`),
  KEY `idx_voos_criado_em` (`criado_em`),
  KEY `idx_voos_usuario` (`usuario_id`),
  CONSTRAINT `fk_voos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
-- TBO (migracao_tbo.sql): colunas adicionadas depois dos INSERTs posicionais acima.
ALTER TABLE `aeronaves`
  ADD COLUMN `tbo_valor` decimal(14,2) NOT NULL DEFAULT 0.00,
  ADD COLUMN `tbo_horas` int(11) NOT NULL DEFAULT 0;

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;



/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `aeronaves` WRITE;
/*!40000 ALTER TABLE `aeronaves` DISABLE KEYS */;
INSERT INTO `aeronaves` VALUES (4,'Diamond','DA62',2024,185.00,95.00,900.00,20000,12000,'07:20:00',89.00,3787.00,5071.00,1284.00,'JetA',360.00,'Austro Engine AE330',7,'Não',15.00,'2026-08-27 14:52:42',1500000.00,'uploads/258dd25710638bbfbbc423ab.jpg',NULL,'publica');
INSERT INTO `aeronaves` VALUES (5,'Cirrus','S22T',2025,213.00,116.00,1203.00,25000,18000,'04:45:00',92.00,2362.00,3600.00,1238.00,'Avgas',315.00,'Continental TSIO-550-K',5,'Não',18.00,'2026-08-27 14:55:55',1200000.00,'uploads/7a95b919ce3572278b7b610b.webp',NULL,'publica');
INSERT INTO `aeronaves` VALUES (6,'Diamond','DA50 RG',2025,172.00,95.00,1050.00,20000,14000,'05:00:00',50.00,3175.00,4407.00,1232.00,'JetA',300.00,'Continental CD-300',5,'Não',12.00,'2026-08-27 23:41:23',1150000.00,'uploads/4d60aac8a2b5a28d421cd8c2.webp',NULL,'publica');
INSERT INTO `aeronaves` VALUES (7,'Piper','M350',2024,213.00,117.00,1275.00,25000,25000,'06:00:00',120.00,3050.00,4340.00,1290.00,'Avgas',350.00,'Lycoming TIO-540-AE2A',6,'Sim',20.00,'2026-08-27 23:44:28',1500000.00,'uploads/2ea5abd9f5c0668730ee3006.jpg',NULL,'publica');
INSERT INTO `aeronaves` VALUES (8,'Pilatus','PC12 NGX',2023,290.00,135.00,1920.00,30000,28000,'06:15:00',402.00,6803.00,1045.00,2280.00,'JetA',1.00,'PT6E-67XP',10,'Sim',66.00,'2026-08-28 13:29:26',5000000.00,'uploads/bb6931bd811c6988c0dc705f.jpg',NULL,'publica');
INSERT INTO `aeronaves` VALUES (10,'Beechcraft','Kingair C90 GTX',2011,272.00,135.00,1900.00,30000,24000,'04:30:00',384.00,7200.00,1048.00,3285.00,'JetA',1.00,'PT6A-135A',8,'Sim',85.00,'2026-08-28 13:36:24',3400000.00,'uploads/5bb7ccfe445e02e4d5fbc1f3.jpg',NULL,'publica');
INSERT INTO `aeronaves` VALUES (12,'Piper','M500',2025,260.00,130.00,1556.00,30000,26000,'04:00:00',170.00,3436.00,5092.00,1656.00,'JetA',500.00,'PT6A-42A',6,'Sim',37.00,'2026-08-28 13:55:00',2000000.00,'uploads/9bad26756d4f487f0cd0ebb4.jpg',NULL,'publica');
INSERT INTO `aeronaves` VALUES (17,'Daher','Tbm960',2022,330.00,145.00,2000.00,31000,28000,'05:15:00',291.00,4629.00,7394.00,2765.00,'JetA',850.00,'PT6-66D',6,'Sim',57.00,'2026-08-28 14:06:33',5400000.00,'uploads/caaa1d2d2d77cded9b9e4a87.jpg',NULL,'publica');
INSERT INTO `aeronaves` VALUES (18,'Beechcraft','Bonanza G36',2015,176.00,105.00,1050.00,18500,8000,'05:00:00',74.00,2517.00,3650.00,1133.00,'Avgas',300.00,'Continental IO-550-B',6,'Não',15.50,'2026-09-21 23:40:34',900000.00,'uploads/fa3aecf5d550bf0853a1401c.png',NULL,'publica');
INSERT INTO `aeronaves` VALUES (19,'CESSNA','Caravan 208',2011,180.00,115.00,1234.00,25000,10000,'05:40:00',332.00,4570.00,8000.00,3430.00,'JetA',675.00,'PT6A-114A',11,'Não',50.00,'2026-08-28 14:13:25',2250000.00,'uploads/89340e36d36d4de50bbabb2b.png',NULL,'publica');
INSERT INTO `aeronaves` VALUES (20,'Cirrus','VISION G2',2025,311.00,157.00,1216.00,31000,28000,'04:30:00',200.00,3550.00,6040.00,1390.00,'JetA',1846.00,'Williams International FJ33-5A',7,'Sim',65.00,'2026-08-28 14:23:52',3600000.00,'uploads/f9751899f7754e04a7e2cc5f.png',NULL,'publica');
INSERT INTO `aeronaves` VALUES (22,'BEECHCRAFT','Baron 58TC',1979,225.00,115.00,1460.00,25000,18000,'04:45:00',190.00,3950.00,6100.00,2150.00,'Avgas',650.00,'TSIO-520-L',6,'Não',38.00,'2026-08-28 20:29:31',400000.00,'uploads/6f3c847a014793200129f307.png',NULL,'publica');
INSERT INTO `aeronaves` VALUES (25,'Cessna','172P Skyhawk',1983,120.00,75.00,700.00,13000,8000,'04:30:00',43.00,1467.00,2400.00,933.00,'Avgas',160.00,'Lycoming O-320-D2J',4,'Não',8.00,'2026-09-19 18:20:32',150000.00,'uploads/5619b5da63c632b9319d3d13.jpg',NULL,'publica');
/*!40000 ALTER TABLE `aeronaves` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

