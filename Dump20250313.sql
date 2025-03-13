-- MySQL dump 10.13  Distrib 8.0.41, for macos15 (x86_64)
--
-- Host: localhost    Database: app
-- ------------------------------------------------------
-- Server version	9.2.0

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
-- Table structure for table `badge`
--

DROP TABLE IF EXISTS `badge`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `badge` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rules` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `badge`
--

LOCK TABLES `badge` WRITE;
/*!40000 ALTER TABLE `badge` DISABLE KEYS */;
/*!40000 ALTER TABLE `badge` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctrine_migration_versions`
--

DROP TABLE IF EXISTS `doctrine_migration_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctrine_migration_versions` (
  `version` varchar(191) COLLATE utf8mb3_unicode_ci NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `execution_time` int DEFAULT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctrine_migration_versions`
--

LOCK TABLES `doctrine_migration_versions` WRITE;
/*!40000 ALTER TABLE `doctrine_migration_versions` DISABLE KEYS */;
INSERT INTO `doctrine_migration_versions` VALUES ('DoctrineMigrations\\Version20250313071040','2025-03-13 07:12:26',143),('DoctrineMigrations\\Version20250313071826','2025-03-13 07:18:53',50),('DoctrineMigrations\\Version20250313072005','2025-03-13 07:20:13',36),('DoctrineMigrations\\Version20250313072054','2025-03-13 07:21:01',30),('DoctrineMigrations\\Version20250313072137','2025-03-13 07:22:44',51),('DoctrineMigrations\\Version20250313072821','2025-03-13 07:28:29',19),('DoctrineMigrations\\Version20250313073019','2025-03-13 07:30:36',59),('DoctrineMigrations\\Version20250313075531','2025-03-13 07:55:44',40),('DoctrineMigrations\\Version20250313075629','2025-03-13 07:57:47',32),('DoctrineMigrations\\Version20250313153254','2025-03-13 15:33:07',90);
/*!40000 ALTER TABLE `doctrine_migration_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `early_access`
--

DROP TABLE IF EXISTS `early_access`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `early_access` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `early_access`
--

LOCK TABLES `early_access` WRITE;
/*!40000 ALTER TABLE `early_access` DISABLE KEYS */;
INSERT INTO `early_access` VALUES (1,'2025-03-13 07:37:58','test@gmail.com');
/*!40000 ALTER TABLE `early_access` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `favorites`
--

DROP TABLE IF EXISTS `favorites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `favorites` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `learner` int DEFAULT NULL,
  `question` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `IDX_E46960F58EF3834` (`learner`),
  KEY `IDX_E46960F5B6F7494E` (`question`),
  CONSTRAINT `FK_E46960F58EF3834` FOREIGN KEY (`learner`) REFERENCES `learner` (`id`),
  CONSTRAINT `FK_E46960F5B6F7494E` FOREIGN KEY (`question`) REFERENCES `question` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `favorites`
--

LOCK TABLES `favorites` WRITE;
/*!40000 ALTER TABLE `favorites` DISABLE KEYS */;
INSERT INTO `favorites` VALUES (2,127,131,'2025-03-13 14:26:02'),(3,127,137,'2025-03-13 14:30:01'),(4,127,111,'2025-03-13 14:31:17'),(5,127,67,'2025-03-13 14:33:12');
/*!40000 ALTER TABLE `favorites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `grade`
--

DROP TABLE IF EXISTS `grade`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `grade` (
  `id` int NOT NULL AUTO_INCREMENT,
  `number` int DEFAULT NULL,
  `active` smallint DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grade`
--

LOCK TABLES `grade` WRITE;
/*!40000 ALTER TABLE `grade` DISABLE KEYS */;
INSERT INTO `grade` VALUES (1,12,1),(2,11,1),(3,10,1);
/*!40000 ALTER TABLE `grade` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `learner`
--

DROP TABLE IF EXISTS `learner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `learner` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grade` int DEFAULT NULL,
  `uid` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notification_hour` smallint NOT NULL DEFAULT '0',
  `role` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'learner',
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastSeen` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `school_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `school_latitude` double DEFAULT NULL,
  `school_longitude` double DEFAULT NULL,
  `terms` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `curriculum` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `private_school` tinyint(1) DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` double NOT NULL DEFAULT '0',
  `rating_cancelled` datetime DEFAULT NULL,
  `points` int NOT NULL DEFAULT '0',
  `streak` int NOT NULL DEFAULT '0',
  `streak_last_updated` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '8.png',
  PRIMARY KEY (`id`),
  KEY `learner_grade_idx` (`grade`),
  CONSTRAINT `FK_8EF3834595AAE34` FOREIGN KEY (`grade`) REFERENCES `grade` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=162 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `learner`
--

LOCK TABLES `learner` WRITE;
/*!40000 ALTER TABLE `learner` DISABLE KEYS */;
INSERT INTO `learner` VALUES (24,1,'L6p1GBBNhoME4d7b9HMm5jKwkuA3','Olwethu',0,'admin','2025-02-02 09:01:59','2025-02-20 17:42:34','','',0,0,'','',0,'williamsarthur983@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(25,1,'cSXav6YSkYVuzH6rNjeTYWoNW392','Mnelisi',1,'admin','2025-02-09 09:01:59','2025-02-20 17:22:06','','',0,0,'','',0,'mnelisi079@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(26,1,'vxEfE21QscXxEv1VH7KcF2TJdaY2','Talent',0,'admin','2025-02-09 09:01:59','2025-02-20 17:38:15','','',0,0,'','',0,'msindazwetalent775@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(28,3,'BbYmf2Ro8rWQbzW7idIxSZVSaL82','Mluleki',1,'admin','2025-02-09 09:01:59','2025-02-09 09:48:44','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(30,1,'QWTsky2pqdM9KCTUp3OpxU2d4sr1','Ben',1,'admin','2025-02-09 09:01:59','2025-02-13 13:31:50','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',2,0,'2025-03-13 09:53:02','8.png'),(31,1,'AkLpFo7YHtULFOD8JGsMEsQOcUB2','Boitumelo Victoria',1,'admin','2025-02-11 07:09:18','2025-02-20 17:13:14','','',0,0,'','',0,'victoriamalimabe@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(32,1,'wqOfQE4940WBsFHBybjfV2Cino42','Lebogang Mochemi',1,'admin','2025-02-12 06:32:03','2025-02-20 12:16:22','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(33,2,'oxciLOvBmrXtycWeVkeljg0KMhH3','Elelwani',18,'admin','2025-02-12 07:02:25','2025-02-20 15:47:59','','',0,0,'','',1,'esthermudau2@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(34,1,'88Htc5jaCEhVXI5kghqgKa6sRx42','Benedict Nkosi',1,'admin','2025-02-12 10:51:25','2025-02-13 07:37:37','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(35,1,'eyKo8h8VzPbUsPSkq7FxWPtHogv2','Ipeleng tlhomelang',1,'admin','2025-02-12 17:58:26','2025-02-20 12:06:08','','',0,0,'','',0,'tlhomelangcaroline@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(36,1,'OX79fiWD4OaDxC7KPcijmdrnf2v2','Benedict Nkosi',1,'admin','2025-02-14 02:59:33','2025-02-20 13:54:20','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(37,1,'6IQiZAqlvjcfHu9XxInDcukWkwI2','Benedict',0,'learner','2025-02-15 07:31:21','2025-02-15 07:31:21','Parkhill','123 station road',1234,123,'[1,2,4]','[\"AAA\"]',0,NULL,0,'2025-02-02 09:01:59',1,0,'2025-03-13 09:53:02','8.png'),(38,1,'vPhmNbmoZOPgAscloH9U9PaYHIy1','Bot',0,'learner','2025-02-16 16:52:32','2025-02-16 16:52:32','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(45,1,'7kOK6u5OOwcpakzshGyNbFZ12j13','Martin',0,'learner','2025-02-17 03:59:25','2025-02-17 03:59:25','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(46,1,'qH0ivtLRiKbmqEUhB6FLq4y2ezA3','Lethabo Mathabatha',0,'learner','2025-02-17 09:37:25','2025-02-17 09:37:25','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(47,1,'3dfmy0a1NqarelGlnzzbmHNVDSS2','Thuthukani Mthiyane',0,'learner','2025-02-17 11:12:58','2025-02-17 11:12:58','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(48,1,'v8mTQEvYxshN8KaNzLRU3RpviEg1','Zama',0,'learner','2025-02-17 13:15:36','2025-02-17 13:15:36','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(49,1,'7X35M7ZqvfRvL1mTfhxt9vVWpoS2','Mandisa',0,'learner','2025-02-17 13:35:03','2025-02-17 13:35:03','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(51,1,'HsSXhVBMtNYkEO1Y8TcLFGXt1lS2','Simphiwe Zulu ',0,'learner','2025-02-17 14:59:39','2025-02-17 14:59:39','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(54,1,'xNQTYXztDgNHcOcemznDDOm9YC72','Khulekani Mdletshe',0,'learner','2025-02-19 05:47:51','2025-02-19 05:47:51','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(55,1,'9FUID4V0xehLQx7SRZNpkmsgPcn1','Alexis Njabulo Mbatha',0,'learner','2025-02-20 06:56:42','2025-02-20 06:56:42','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(61,2,'JcYhFY3HgjcpGvKDFz8lII1ubmu2','Riaan Williams',0,'learner','2025-02-21 06:03:23','2025-02-21 06:03:23','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(79,1,'zRfx600SAyTuxpGwwq8e6773am23','Mandlha',0,'learner','2025-02-24 14:26:48','2025-02-24 14:26:48','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(80,1,'l6mN1ozox9MGF4IVB18fap6j3zJ2','Sibonelo Msabala',0,'learner','2025-02-24 14:40:31','2025-02-24 14:40:31','','',0,0,'','',0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(107,1,'106064765367862918314','Benedict Nkosi',18,'admin','2025-02-28 19:14:58','2025-02-28 19:14:58','Parktown High School for Girls','Parktown High School for Girls, Tyrone Avenue, Parkview, Randburg, South Africa',-26.1556,28.0219,'1,2,3,4','IEB,CAPS',1,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(108,1,'114536966648828162487','Lethabo Mathabatha',17,'admin','2025-03-01 06:56:44','2025-03-01 06:56:44','Bryanston High School','Bryanston High School, Tramore Road, Bryanston, Sandton, South Africa',-26.0737,28.0247,NULL,NULL,0,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(111,1,'105367622186051621984','Benedict Nkosi',18,'admin','2025-03-01 22:15:21','2025-03-01 22:15:21','Parktown Boys\' High School','Parktown Boys\' High School, Wellington Road, Parktown, Johannesburg, South Africa',-26.1848,28.0365,'1,2,3,4','IEB,CAPS',1,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(112,3,'117747176374628266638','Exam Quiz',18,'admin','2025-03-02 15:14:45','2025-03-02 15:14:45','Parktown High School for Girls','Parktown High School for Girls, Tyrone Avenue, Parkview, Randburg, South Africa',-26.1556,28.0219,'1,2,3,4','IEB,CAPS',1,NULL,0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(120,1,'9a8pKTYr5IcZQ7GfZUimQm9GcbH2','test10',18,'learner','2025-03-04 05:16:29','2025-03-04 05:16:29','Parklands College - Secondary Faculty','Parklands College - Secondary Faculty, College Avenue, Milnerton Rural, Cape Town, South Africa',-33.7986,18.5054,'4,3','CAPS',0,'test10@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(127,1,'GEoSVVisf0bolD40ri3nkJfV5Gw2','test20',18,'learner','2025-03-05 10:55:51','2025-03-13 14:57:05','Parktown High School for Girls','Parktown High School for Girls, Tyrone Avenue, Parkview, Randburg, South Africa',-26.1556,28.0219,'\"1, 2\"','\"CAPS, IEB\"',1,'test20@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(129,1,'ODih7ib0FEaOg54llQJWGQuw1x43','mnelisi079',18,'admin','2025-03-06 06:57:33','2025-03-06 06:57:33','Greenfield','Greenfield, Umbrella St, Greenfields, Katlehong, South Africa',-26.3814,28.1269,'1,2,3,4','CAPS,IEB',0,'mnelisi079@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(130,1,'RFYqlYtZTefzsWMsaRZz1nWe2Vs2','sibusiso87rn?? Roelof Botha has become one of the most prominent Venture Capital investors in the US',18,'learner','2025-03-06 07:14:02','2025-03-06 07:14:02','Impoqabulungu Senior Secondary School','Impoqabulungu Senior Secondary School, Mandini, South Africa',-29.1565,31.4122,'1,2,3,4','CAPS,IEB',1,'sibusiso87rn@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(131,1,'kHET2eUX2wdJMppqvklzDQ1JzPn1','simmaemae',18,'learner','2025-03-06 07:14:29','2025-03-06 07:14:29','Hoërskool Standerton','Hoërskool Standerton, Von Backstrom Street, Standerton, South Africa',-26.9423,29.2489,'1,2,3,4','CAPS,IEB',0,'simmaemae@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(132,1,'98m3bDlrYJcaUbhyEXWdBJK55BG2','dee61004',18,'admin','2025-03-06 08:15:00','2025-03-06 08:15:00','Lizwi Secondary School','Lizwi Secondary School, Richards Bay - New, Richards Bay, South Africa',-28.7422,32.0995,'1,2,3,4','CAPS,IEB',0,'dee61004@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(134,3,'BU9jQLGb6EVDzLWdk7DpBFkk6Jp2','esthermudau2',18,'admin','2025-03-06 13:02:57','2025-03-06 13:02:57','Thohoyandou Secondary School','Thohoyandou Secondary School, Block P West, Main Road, Thohoyandou-P, Thohoyandou, South Africa',-22.9785,30.4543,'1,2,3,4','CAPS,IEB',0,'esthermudau2@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(135,2,'hIRShGVDlPSL09gPd6ikCStMqDs2','sesethumanjezi2',18,'learner','2025-03-06 15:47:53','2025-03-06 15:47:53','Masiyile Senior Secondary School','Masiyile Senior Secondary School, Village 2 North, Cape Town, South Africa',-34.0302,18.6678,'1,2,3,4','CAPS,IEB',0,'sesethumanjezi2@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(136,1,'CbOoKPVyNsRf8j2MMoCcc559cy62','tlhomelangcaroline',18,'admin','2025-03-06 18:27:22','2025-03-06 18:27:22','Kebinelang Secondary','Kebinelang Secondary, Manthe, South Africa',-27.5431,24.8877,'3,4,2,1','CAPS,IEB',1,'tlhomelangcaroline@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(140,3,'dIEFi6217lfIyzO9yji1njd4Wan2','test02',18,'learner','2025-03-07 08:32:27','2025-03-07 08:32:27','Parklands College - Secondary Faculty','Parklands College - Secondary Faculty, College Avenue, Milnerton Rural, Cape Town, South Africa',-33.7986,18.5054,'1,2,3,4','CAPS,IEB',0,'test02@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(141,1,'5Mh6jcJRelhEG7jTG00fDn6ls9c2','ricardomudinyane',18,'learner','2025-03-07 08:37:42','2025-03-07 08:37:42','Tshivhase Senior Secondary School','Tshivhase Senior Secondary School, Tshitereke, Thohoyandou, South Africa',-22.8874,30.4869,'1,2,3,4','CAPS,IEB',0,'ricardomudinyane@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(142,1,'FSmnmjCTGDOGmcvGCacf5d1PKG42','williamsarthur983',18,'admin','2025-03-07 09:34:14','2025-03-07 09:34:14','Parkhill Secondary School','Parkhill Secondary School, Park Station Road, Greenwood Park, Durban, South Africa',-29.786,31.0202,'1,2,3,4','CAPS,IEB',0,'williamsarthur983@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(143,2,'z839xBeGsTShj4ixzFBzC6pxpU93','Boitumelo',18,'learner','2025-03-07 09:49:01','2025-03-07 09:49:01','Sekgutlong High School','Sekgutlong High School, Monontsha, Phuthaditjhaba, South Africa',-28.5693,28.7444,'1,2,3,4','CAPS,IEB',0,'victoriamalimabe@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(144,1,'wBX5XFbzUCNGJgewnOVemIw9EOv2','Benedict Nkosi',18,'admin','2025-03-07 15:55:02','2025-03-07 15:55:02','Parkhill Secondary School','Parkhill Secondary School, Park Station Road, Greenwood Park, Durban, South Africa',-29.786,31.0202,'1,2,3,4','CAPS,IEB',0,'nkosi@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(145,1,'c27vnJRSMKMM86CaGW8EjbGPROf1','njivarmbatha',18,'learner','2025-03-07 17:01:57','2025-03-07 17:01:57','Nkombose High School','Nkombose High School, Nkombose, South Africa',-28.3941,32.1598,'1,2,3,4','CAPS,IEB',0,'njivarmbatha@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(146,1,'9XWCgnA83mgJVBx2Z7lyNk8c6Uk1','test03',18,'learner','2025-03-07 17:24:01','2025-03-07 17:24:01','Parktown High School for Girls','Parktown High School for Girls, Tyrone Avenue, Parkview, Randburg, South Africa',-26.1556,28.0219,'1,2,3,4','IEB,CAPS',1,'test03@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(147,2,'oZ8rn7R7X9MMUOWKXAs6g9wUSZV2','willwhiteapple',18,'learner','2025-03-07 20:04:56','2025-03-07 20:04:56','Affies','Affies, Lynnwood Road, Elandspoort 357-Jr, Pretoria, South Africa',-25.7568,28.2225,'1,2,3,4','CAPS,IEB',1,'willwhiteapple@gmail.com',0,'2025-02-02 09:01:59',0,0,'2025-03-13 09:53:02','8.png'),(148,1,'ASrcpbPFjSTxpb5AGVBId9cxXNf2','lethabomathabatha.m',18,'admin','2025-03-08 17:15:17','2025-03-08 17:15:17','Bryanston High School','Bryanston High School, Tramore Road, Bryanston, Sandton, South Africa',-26.0737,28.0247,'1,2,3,4','CAPS,IEB',0,'lethabomathabatha.m@gmail.com',0,NULL,0,0,'2025-03-13 09:53:02','8.png'),(149,1,'nIrWWcN1yOVuNlhON3iVicyCoEr1','msindazwetalent775',18,'admin','2025-03-09 05:10:03','2025-03-09 05:10:03','Nkombose High School','Nkombose High School, Nkombose, South Africa',-28.3941,32.1598,'1,2,3,4','CAPS,IEB',0,'msindazwetalent775@gmail.com',0,NULL,1,0,'2025-03-13 09:53:02','8.png'),(150,1,'qjxoS4LjOKYJDBsEYaBdZbksDHF2','karabomatlala12',18,'learner','2025-03-10 18:17:46','2025-03-10 18:17:46','Star Schools Braamfontein - Rewrite Centre & Adult','Star Schools Braamfontein - Rewrite Centre & Adult Matric, Smit Street, Braamfontein, Johannesburg, ',-26.195,28.0358,'1,2,3,4','CAPS,IEB',0,'karabomatlala12@icloud.com',0,NULL,8,0,'2025-03-13 09:53:02','8.png'),(151,1,'KydTvUWBJJNbG9UiWTGdo9w7SoB2','ronatsosane',18,'learner','2025-03-10 19:23:51','2025-03-10 19:23:51','Hoërskool President High School','Hoërskool President High School, Rifle Range Road, Ridgeway, Johannesburg, South Africa',-26.2581,27.999,'1,2,3,4','CAPS,IEB',0,'ronatsosane@gmail.com',0,NULL,59,0,'2025-03-13 09:53:02','8.png'),(152,3,'VmVARBZ1ZjWtEUEmQstzhyp5imv2','buyanalathiwe',18,'learner','2025-03-11 06:12:17','2025-03-11 06:12:17','Kingsridge High School for Girls','Kingsridge High School for Girls, Queens Road, Qonce, South Africa',-32.8773,27.395,'1,2,3,4','CAPS,IEB',0,'buyanalathiwe@gmali.com',0,NULL,2,0,'2025-03-13 09:53:02','8.png'),(153,1,'AW4oLehH0VQhqWn2M5PzzLW3liI2','nomatham.makhanya',18,'learner','2025-03-11 07:44:03','2025-03-11 07:44:03','Nova Pioneer Paulshof','Nova Pioneer Paulshof, Stone Haven Road, Paulshof, Sandton, South Africa',-26.0326,28.0502,'1,2,3,4','CAPS,IEB',1,'nomatham.makhanya@gmail.com',0,NULL,0,0,'2025-03-13 09:53:02','8.png'),(154,1,'cbJpceNximca793DyO5hZCJUra33','zane.singh',18,'learner','2025-03-11 08:12:58','2025-03-11 08:12:58','Star College','Star College, Kinloch Avenue, Westville, Durban, South Africa',-29.8115,30.9084,'1,2,3,4','CAPS,IEB',0,'zane.singh@gmail.com',0,NULL,0,0,'2025-03-13 09:53:02','8.png'),(155,3,'DimNlqmtqNdNdA5La4mat3yp11u1','Sibonelo',18,'learner','2025-03-11 14:52:54','2025-03-11 14:52:54','Hibberdene Academy','Hibberdene Academy, Catalina, Hibberdene, South Africa',-30.5703,30.5722,'1,2,3,4','CAPS,IEB',1,'stmsabala@gmail.com',0,NULL,0,0,'2025-03-13 09:53:02','8.png'),(156,3,'OuE4XArRyETX5KaEFFUGfCeFTSq1','thembakgotso',18,'learner','2025-03-11 16:53:20','2025-03-11 16:53:20','Eureka High School','Eureka High School, Geduld Rd, Paul Krugersoord, Springs, South Africa',-26.2401,28.4346,'2,3,4','CAPS,IEB',1,'thembakgotso@icloud.com',0,NULL,0,0,'2025-03-13 09:53:02','8.png'),(157,1,'hkjasoasasdd','Benedict',18,'learner','2025-03-13 08:24:51','2025-03-13 08:24:51','Parkhill','123 station road',1234,123,'[\"1\",\"2\",\"4\"]','[\"IEB\",\"CAPS\"]',NULL,'nkosi.b@gmail.com',0,NULL,0,0,'2025-03-13 08:24:51','8.png'),(158,1,'cOhxo8EXfvajt4dceAFxkdZCPfZ2','apple 07',18,'learner','2025-03-13 15:09:25','2025-03-13 15:21:28','Parklands College - Secondary Faculty','Parklands College - Secondary Faculty, College Avenue, Milnerton Rural, Cape Town, South Africa',-33.7985645,18.5054116,'1,3,4,2','CAPS,IEB',NULL,'test07@apple.com',0,NULL,0,0,'2025-03-13 15:09:25','8.png'),(159,1,'0q4EpXVXw2NW9vA93SBm50u9kLE3','test11',18,'learner','2025-03-13 15:34:13','2025-03-13 15:34:13','Parklands College - Junior Preparatory & Christopher Robin Pre-Primary','Parklands College - Junior Preparatory & Christopher Robin Pre-Primary, Raats Drive, Parklands, Cape Town, South Africa',-33.8177821,18.5090121,'1,2,3,4','CAPS',NULL,'test11@gmail.com',0,NULL,0,0,'2025-03-13 15:34:13','8.png'),(160,1,'DW3gZ6qCzEM6EiVDbFGF8OPBudp1','test12',18,'learner','2025-03-13 15:39:29','2025-03-13 15:39:29','Parklands College - Secondary Faculty','Parklands College - Secondary Faculty, College Avenue, Milnerton Rural, Cape Town, South Africa',-33.7985645,18.5054116,'1,2,3,4','IEB',1,'test12@apple.com',0,NULL,0,0,'2025-03-13 15:39:29','8.png'),(161,1,'4doJEWB7DTSkcDkOBdrHoQPkZtc2','test13',18,'learner','2025-03-13 15:42:32','2025-03-13 15:42:32','Paarl Boys\' High School','Paarl Boys\' High School, Auret Street, Hoog-En-Droog, Paarl, South Africa',-33.7392983,18.961013,'1,2,3,4','IEB,CAPS',0,'test13@aaples.com',0,NULL,0,0,'2025-03-13 15:42:32','8.png');
/*!40000 ALTER TABLE `learner` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `learner_badges`
--

DROP TABLE IF EXISTS `learner_badges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `learner_badges` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `learner` int DEFAULT NULL,
  `badge` bigint DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `IDX_5AF0C8798EF3834` (`learner`),
  KEY `IDX_5AF0C879FEF0481D` (`badge`),
  CONSTRAINT `FK_5AF0C8798EF3834` FOREIGN KEY (`learner`) REFERENCES `learner` (`id`),
  CONSTRAINT `FK_5AF0C879FEF0481D` FOREIGN KEY (`badge`) REFERENCES `badge` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `learner_badges`
--

LOCK TABLES `learner_badges` WRITE;
/*!40000 ALTER TABLE `learner_badges` DISABLE KEYS */;
/*!40000 ALTER TABLE `learner_badges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `learner_streak`
--

DROP TABLE IF EXISTS `learner_streak`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `learner_streak` (
  `id` int NOT NULL AUTO_INCREMENT,
  `learner_id` int NOT NULL,
  `current_streak` int NOT NULL,
  `longest_streak` int NOT NULL,
  `questions_answered_today` int NOT NULL,
  `last_answered_at` datetime NOT NULL,
  `last_streak_update_date` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_3B8C80CA6209CB66` (`learner_id`),
  CONSTRAINT `FK_3B8C80CA6209CB66` FOREIGN KEY (`learner_id`) REFERENCES `learner` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `learner_streak`
--

LOCK TABLES `learner_streak` WRITE;
/*!40000 ALTER TABLE `learner_streak` DISABLE KEYS */;
INSERT INTO `learner_streak` VALUES (1,144,1,1,1,'2025-03-13 13:38:09','2025-03-13 13:38:09'),(2,127,1,1,7,'2025-03-13 13:51:57','2025-03-13 13:42:52');
/*!40000 ALTER TABLE `learner_streak` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `learnersubjects`
--

DROP TABLE IF EXISTS `learnersubjects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `learnersubjects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `learner` int DEFAULT NULL,
  `subject` int DEFAULT NULL,
  `higherGrade` tinyint(1) DEFAULT NULL,
  `overideTerm` tinyint(1) DEFAULT NULL,
  `last_updated` datetime DEFAULT NULL,
  `percentage` double DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `learnersubject_learner_idx` (`learner`),
  KEY `learnersubject_subject_idx` (`subject`),
  CONSTRAINT `FK_E64CCD338EF3834` FOREIGN KEY (`learner`) REFERENCES `learner` (`id`),
  CONSTRAINT `FK_E64CCD33FBCE3E7A` FOREIGN KEY (`subject`) REFERENCES `subject` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `learnersubjects`
--

LOCK TABLES `learnersubjects` WRITE;
/*!40000 ALTER TABLE `learnersubjects` DISABLE KEYS */;
/*!40000 ALTER TABLE `learnersubjects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `question`
--

DROP TABLE IF EXISTS `question`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `question` (
  `id` int NOT NULL AUTO_INCREMENT,
  `capturer` int DEFAULT NULL,
  `reviewer` int DEFAULT NULL,
  `subject` int DEFAULT NULL,
  `question` longtext COLLATE utf8mb4_unicode_ci,
  `type` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `context` longtext COLLATE utf8mb4_unicode_ci,
  `answer` mediumtext COLLATE utf8mb4_unicode_ci,
  `options` json DEFAULT NULL,
  `term` int DEFAULT NULL,
  `image_path` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `explanation` longtext COLLATE utf8mb4_unicode_ci,
  `higher_grade` smallint DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  `posted` tinyint(1) DEFAULT '0',
  `year` int NOT NULL,
  `answer_image` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created` datetime DEFAULT CURRENT_TIMESTAMP,
  `question_image_path` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `comment` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ai_explanation` longtext COLLATE utf8mb4_unicode_ci,
  `curriculum` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CAPS',
  `updated` datetime DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `IDX_B6F7494EBCE217CC` (`capturer`),
  KEY `IDX_B6F7494EE0472730` (`reviewer`),
  KEY `question_subject` (`subject`),
  CONSTRAINT `FK_B6F7494EBCE217CC` FOREIGN KEY (`capturer`) REFERENCES `learner` (`id`),
  CONSTRAINT `FK_B6F7494EE0472730` FOREIGN KEY (`reviewer`) REFERENCES `learner` (`id`),
  CONSTRAINT `FK_B6F7494EFBCE3E7A` FOREIGN KEY (`subject`) REFERENCES `subject` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `question`
--

LOCK TABLES `question` WRITE;
/*!40000 ALTER TABLE `question` DISABLE KEYS */;
INSERT INTO `question` VALUES (46,36,38,34,'Use TABLE 1 above to write down the letter of the explanation or definition (A to G) of: Surface area','multiple_choice','','E','{\"option1\": \"A\", \"option2\": \"F\", \"option3\": \"G\", \"option4\": \"E\"}',4,'6798a6696d0b2.png','',0,1,0,2023,NULL,'rejected','2025-02-21 10:00:13',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(47,38,38,34,'Use TABLE 1 to write down the letter of the explanation or definition.','multiple_choice','','[\"A\"]','{\"option1\": \"A\", \"option2\": \"B\", \"option3\": \"C\", \"option4\": \"D\"}',4,'6798a6696d0b2.png','',0,1,0,2023,'','approved','2025-02-27 12:54:20','','new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(48,38,38,34,'Use TABLE 1 above to write down the letter of the explanation or definition (A to G) of: Speed','multiple_choice','','[\"F\"]','{\"option1\": \"F\", \"option2\": \"G\", \"option3\": \"E\", \"option4\": \"H\"}',4,'6798a6696d0b2.png','',0,1,0,2023,NULL,'approved','2025-02-21 10:00:01',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(49,38,38,34,'Write down how many streets Mr Masunte must cross before turning into Winchester Street.','multiple_choice','Use the information given to answer the questions that follow','[\"3\"]','{\"option1\": \"1\", \"option2\": \"2\", \"option3\": \"3\", \"option4\": \"4\"}',4,'6798a9b0b3826.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:59:00',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(50,38,38,34,'Name the street that goes over the Klip River.','multiple_choice','Use the information given to answer the questions that follow','[\"Iffley\"]','{\"option1\": \"Baker Street\", \"option2\": \"Harvard Avenue\", \"option3\": \"Main Street\", \"option4\": \"Iffley\"}',4,'6798a9b0b3826.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:58:44',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(52,38,38,34,'	Calculate the total distance from the guesthouse to his destination.\n \n','multiple_choice','Use the information given to answer the questions that follow','[\"3385m\"]','{\"option1\": \"2456m\", \"option2\": \"3760m\", \"option3\": \"4200m\", \"option4\": \"3385m\"}',4,'6798a9b0b3826.png','\n[\"Tot. dist. = 980 m + 435 m +870 m + 1 100 m = 3 385 m\"]',0,1,0,2023,NULL,'approved','2025-02-21 09:58:34',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(54,38,38,23,'Identify the session during which the second largest number of music artists were listened to.','multiple_choice','Use TABLE 1 to answer the questions that follow.','[\"B\"]','{\"option1\": \"A\", \"option2\": \"B\", \"option3\": \"C\", \"option4\": \"D\"}',4,'6798aab9a5f00.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:31:13',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(55,38,38,34,'Determine how many different types of screws are needed to assemble the different parts of the chair.','multiple_choice','Use the information given to answer the questions that follow','[\"3\"]','{\"option1\": \"1\", \"option2\": \"2\", \"option3\": \"3\", \"option4\": \"4\"}',4,'6798acacacef6.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:58:21',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(56,38,38,23,'Calculate the increase in the number of songs streamed over the three sessions.','multiple_choice','Use TABLE 1 to answer the questions that follow','[\"1797\"]','{\"option1\": \"1600\", \"option2\": \"2000\", \"option3\": \"1900\", \"option4\": \"1797\"}',4,'6798aab9a5f00.png','[\"Increase = 88 706 141 \\u2013 88 704 344 = 1 797 \"]',0,1,0,2023,NULL,'approved','2025-02-21 09:43:19',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(57,38,38,23,'Determine, as a unit ratio, in the form 1 : ..., the number of paid users to the number of free users during session A','multiple_choice','Use TABLE 1 to answer the questions that follow','[\"1 : 11,77\"]','{\"option1\": \"1 : 5,77\", \"option2\": \"1 : 15,77\", \"option3\": \"1 : 10,77\", \"option4\": \"1 : 11,77\"}',4,'6798aab9a5f00.png','[\"690 160 : 8 120 031 = 1 : 11,77\"]',0,1,0,2023,NULL,'approved','2025-02-21 09:43:02',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(64,38,38,34,'The number of screws used in step 4','multiple_choice','Use the steps to assemble a chair to identify the following','[\"4 \"]','{\"option1\": \"2\", \"option2\": \"3\", \"option3\": \"4\", \"option4\": \"5\"}',4,'6798c6df2904c.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:58:09',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(65,38,38,34,'Name the tool needed to assemble the chair.','multiple_choice','Use the information given to answer the questions that follow','[\"Allen key\"]','{\"option1\": \"Hammer\", \"option2\": \"Screwdriver\", \"option3\": \"Paintbrush\", \"option4\": \"Allen key\"}',4,'6798c982e5a9e.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:57:52',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(66,38,38,34,'	Identify the component of the chair that comes as a pair.','multiple_choice','Use the information below to answer the questions that follow','[\"Chair arms\"]','{\"option1\": \"Chair legs\", \"option2\": \"Chair back\", \"option3\": \"Chair cushion\", \"option4\": \"Chair arms\"}',4,'6798cb56083aa.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:57:36',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(67,38,38,23,'Give the acronym for value-added tax.','multiple_choice','Use the pie chart below to answer the questions that follow.','[\"VAT\"]','{\"option1\": \"VATC\", \"option2\": \"VAB\", \"option3\": \"VAS\", \"option4\": \"VAT\"}',4,'6798cb89cf2de.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:42:40',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(69,38,38,23,'Determine the total price for ONE music CD','multiple_choice','Use the pie chart to answer the questions that follow.','[\"R99,00\"]','{\"option1\": \"R89,00\", \"option2\": \"R109,00\", \"option3\": \"R120,00\", \"option4\": \"R99,00\"}',4,'null','Total price\n= R18,05 + R41,84 + R12,16 + R8,33 + R0,11 +\nR6,98 + R11,53\n= R99,00\n',0,1,0,2023,NULL,'approved','2025-02-21 09:42:17',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(70,38,38,34,'Write down the total number of chairs around the oval-shaped table','multiple_choice','Use the information below to answer the questions that follow','[\"20\"]','{\"option1\": \"21\", \"option2\": \"22\", \"option3\": \"20\", \"option4\": \"23\"}',4,'6798ccd5d9c06.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:56:17',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(71,38,38,23,'Calculate the amount that the music artist receives for one music CD as a percentage of the amount received by the music store.','multiple_choice','Use the pie chart to answer the questions that follow','[\"19,91%\"]','{\"option1\": \"15.57%\", \"option2\": \"23.47%\", \"option3\": \"10.36%\", \"option4\": \"19,91%\"}',4,'null','\n',0,1,0,2023,NULL,'approved','2025-02-21 09:41:47',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(73,38,120,23,'Determine the amount of money a music artist will make if he sells 210 000 copies of his music CD','multiple_choice','Use the pie chart to answer the questions that follow.','[\"R1 749 300\"]','{\"option1\": \"R2 300 000\", \"option2\": \"R1 500 000\", \"option3\": \"R1 900 000\", \"option4\": \"R1 749 300\"}',4,'6798ce0527c96.png','Amount\n= 210 000 × R8,33\n= R1 749 300 \n',0,1,0,2023,NULL,'rejected','2025-02-21 09:41:15',NULL,'Image not clear','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(75,38,38,23,'Calculate how many music CDs must be sold for a writer, who writes ONE song, to receive R16,50.\n \n','multiple_choice','Use the pie chart to answer the questions that follow.','[\"150\"]','{\"option1\": \"200\", \"option2\": \"150\", \"option3\": \"153\", \"option4\": \"160\"}',4,'6798cf10c5dd3.png','',0,1,0,2023,'6798cf05bcb4f.png','approved','2025-02-21 09:40:54',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(76,38,38,34,'Which ONE of the following statements regarding the conference room layout is TRUE?','multiple_choice','Use the information below to answer the questions that follow','[\"The screen is opposite the door leading into the room\"]','{\"option1\": \"The screen is on the eastern side of the room\", \"option2\": \"The screen covers some windows\", \"option3\": \"The screen is opposite the door leading into the room\", \"option4\": \"None of the above\"}',4,'6798cefcedb10.png','',0,1,0,2023,'','approved','2025-02-27 09:11:49','','new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(77,38,38,23,'Complete the following statement:\nAn artist, with a gross average monthly income of R83 409, qualifies for a vehicle priced at...\n','multiple_choice','Use TABLE 2 to answer the questions that follow','[\"R1 000 000,00\"]','{\"option1\": \"R500 000,00\", \"option2\": \"R1 500 000,00\", \"option3\": \"R2 000 000,00\", \"option4\": \"R1 000 000,00\"}',4,'6798d3ca73794.png','Price of a vehicle\n= R1 000 000,00 \n',0,1,0,2023,NULL,'approved','2025-02-21 09:40:12',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(78,38,38,34,'The actual outside length of the conference room is 12 m.\n\nMeasure the outside length of the conference room on the layout plan in cm','multiple_choice','Use the information below to answer the questions that follow','[\"12,7 cm \"]','{\"option1\": \"9 cm\", \"option2\": \"15 cm\", \"option3\": \"20 cm\", \"option4\": \"12,7 cm \"}',4,'6798d44b959fe.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:55:52',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(79,38,38,23,'Round off the monthly repayment of a vehicle costing R2 000 000 to the nearest thousand.\n \n','multiple_choice','Use TABLE 2 to answer the questions that follow.','[\"R42 000\"]','{\"option1\": \"R41 000\", \"option2\": \"R43 000\", \"option3\": \"R45 000\", \"option4\": \"R42 000\"}',4,'null','Monthly repayment\n= R41 610,78\n= R42 000\n',0,1,0,2023,NULL,'approved','2025-02-21 09:39:55',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(80,38,38,34,'The actual outside length of the conference room is 12 m.\n\n	Hence, calculate the scale used in this layout plan','multiple_choice','Use the information below to answer the questions that follow','[\"1 : 96\"]','{\"option1\": \"1 : 50\", \"option2\": \"1 : 100\", \"option3\": \"1 : 150\", \"option4\": \"1 : 96\"}',4,'6798d77048dcb.png','125 mm : 12 m\n125	 : 12 000',0,1,0,2023,NULL,'approved','2025-02-21 09:55:40',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(81,38,38,34,'Calculate the maximum number of packed bottled water that can fit on this half of the table.','multiple_choice','Shown  are the pictures and the dimensions of the top of the rectangular refreshment table and the packed bottled water','[\"7 packs\"]','{\"option1\": \"6 packs\", \"option2\": \"8 packs\", \"option3\": \"5 packs\", \"option4\": \"7 packs\"}',4,'6798e0ab34012.png','',0,1,0,2023,'','approved','2025-02-27 12:54:35','','new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(82,38,38,34,'	State the general direction of the Atterbury Road off-ramp from the Fountains Circle.','multiple_choice','On ANNEXURE A is a road map and area information directing the conference attendees to the Pretoria Hotel.\nUse ANNEXURE A to answer the questions that follow','[\"South East \"]','{\"option1\": \"North West\", \"option2\": \"North East\", \"option3\": \"South West\", \"option4\": \"South East \"}',4,'6798e4705a69e.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:55:14',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(83,38,38,34,'Complete: Pretoria Hotel is at the corner of ... and ... Streets.','multiple_choice','On ANNEXURE A is a road map and area information directing the conference attendees to the Pretoria Hotel.\nUse ANNEXURE A to answer the questions that follow','[\"Tram Kloof Street and Albert Street\"]','{\"option1\": \"Main Street and Oak Street\", \"option2\": \"Baker Street and Elm Street\", \"option3\": \"Park Avenue and Maple Street\", \"option4\": \"Tram Kloof Street and Albert Street\"}',4,'6798e5feeaa04.png','',0,1,0,2023,NULL,'approved','2025-02-17 06:08:45',NULL,'Answer might be too long for a single type question','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(84,38,38,34,'State the probability of having a traffic light at Brooklyn Circle','multiple_choice','On ANNEXURE A is a road map and area information directing the conference attendees to the Pretoria Hotel.\nUse ANNEXURE A to answer the questions that follow','[\"0 %\"]','{\"option1\": \"50%\", \"option2\": \"25%\", \"option3\": \"75%\", \"option4\": \"0 %\"}',4,'6798fb76281b1.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:54:59',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(85,38,38,23,'Write down the type of account David has.','multiple_choice','David is a 68-year-old man who works at a grocery store in Swellendam.\nANNEXURE given shows an extract of David\'s Bank Statement for the period 1 November 2022 to 1 December 2022. Some amounts have been omitted','[\"Elite cheque account\"]','{\"option1\": \"Savings account\", \"option2\": \"Platinum debit account\", \"option3\": \"Business account\", \"option4\": \"Elite cheque account\"}',4,'67992e1e1c802.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:39:29',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(86,38,38,23,'Determine the total amount paid for service fees ','multiple_choice','Use ANNEXURE given to answer the questions that follow','[\"R180,60\"]','{\"option1\": \"R230,40\", \"option2\": \"R150,20\", \"option3\": \"R200,55\", \"option4\": \"R180,60\"}',4,'67993b93d5589.png','Total fees\nR1,60 + R69,00 + R110,00\n= R180,60 ',0,1,0,2023,NULL,'approved','2025-02-21 09:39:14',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(87,38,38,34,'	A receptionist at the Pretoria Hotel has to report for work by 05:30\n\nShe takes 10 minutes to walk from home to board a taxi.\n•	She leaves home at 04:55.\n•	She rides in a taxi for 20 minutes.\n•	She walks 5 minutes from the taxi stop to the hotel.\n\nVerify whether or not the receptionist will get to work on time.\n \n','multiple_choice','On ANNEXURE A is a road map and area information directing the conference attendees to the Pretoria Hotel.\nUse ANNEXURE A to answer the questions that follow','[\"05:30 \"]','{\"option1\": \"06:00\", \"option2\": \"05:00\", \"option3\": \"04:50\", \"option4\": \"05:30 \"}',4,'6799422013505.png','Arrival time \n= 04:55 + 10 min + 20 min + 5 min\n= 05:30\nThe receptionist will be on time for work',0,1,0,2023,NULL,'approved','2025-02-18 11:28:13',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(88,38,38,34,'	A female carp can lay 2,7 million eggs.\nWrite 2,7 million in full, using numerals only\n','multiple_choice','Andrew and Duncan went fishing for carp on a friend\'s farm.\nNOTE: Carp is a large freshwater fish that can be eaten by humans\n','[\"2 700 000  \"]','{\"option1\": \"2700000\", \"option2\": \"27 000\", \"option3\": \"27000\", \"option4\": \"2 700 000  \"}',4,NULL,'Two million seven hundred thousand',0,1,0,2023,NULL,'approved','2025-02-18 11:33:00',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(89,38,38,34,'	Andrew caught a carp with a mass of 2,375 kg. Duncan caught two carp, one weighing 1,2 kg and the other 750 g.\nDetermine, in kg, the total mass of the carp they caught\n','multiple_choice','Andrew and Duncan went fishing for carp on a friend\'s farm.\nNOTE: Carp is a large freshwater fish that can be eaten by humans\n','[\"4,325 kg \"]','{\"option1\": \"5,325 kg\", \"option2\": \"3,425 kg\", \"option3\": \"4,226 kg\", \"option4\": \"4,325 kg \"}',4,NULL,'\n',0,1,0,2023,NULL,'approved','2025-02-18 11:32:41',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(90,38,38,34,'	Calculate, in m3, the total capacity of all the holes dug for the required posts.\nYou may use the formula:\nVolume = length > width > depth\n','multiple_choice','Use the information below to answer the questions that follow','[\"0,648 m3\"]','{\"option1\": \"0.500 m3\", \"option2\": \"0.700 m3\", \"option3\": \"0.750 m3\", \"option4\": \"0,648 m3\"}',4,'6799b5e1712f4.png','Volume = 30 cm × 30 cm × 60 cm\n              = 54 000 cm3	\nTota volumel  =	54 000	\\1000000m3 × 12 \n          = 0,648 m3	\n',0,1,0,2023,NULL,'approved','2025-02-18 11:32:10',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(91,38,38,34,'0,75 m3 of concrete requires 5,5 bags of cement.\nOne level wheelbarrow full of river sand weighs 102 kg.\n\nCalculate the mass of river sand needed to make 1 m3 of concrete in kg\n','multiple_choice','	The concrete is made from a mixture of cement, river sand and stone in the ratio as illustrated ','[\"1 496 kg\"]','{\"option1\": \"1300 kg\", \"option2\": \"1600 kg\", \"option3\": \"1700 kg\", \"option4\": \"1 496 kg\"}',4,'6799b7eaaf256.png','5,5 bags of cement make 0,75 ?3\nFor 1 m3 the cement \n\n= 5,5 = 7,33. bags 	\n0,75\nBut 1 bag cement mix with 2 wheelbarrows of sand\n\nNumber of wheelbarrows of sand\n\n= 7,333… × 2 \n= 14,666.\n	Mass of the sand = 102 × 14,6666…\n       \n',0,1,0,2023,NULL,'approved','2025-02-18 11:31:58',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(92,38,38,34,'	Calculate, in cm2, the total area of all the post sides that have to be painted.','multiple_choice','Use the information below to answer the questions that follow.','[\"48 000 cm2\"]','{\"option1\": \"50000 cm2\", \"option2\": \"52000 cm2\", \"option3\": \"45000 cm2\", \"option4\": \"48 000 cm2\"}',4,'6799b8fe1050b.png','Area of rectangle\n= 1,6 m × 125 mm	\n= 160 cm × 12,5 cm	\n= 2 000 cm2\n\nTotal surface area\n= 2 000 cm2 × 2 sides × 12 posts\n= 48 000 cm2	\n',0,1,0,2023,NULL,'approved','2025-02-18 11:31:44',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(93,38,38,34,'	The spread rate of the paint is 12,46 litre/m°.\nCalculate how many litres of paint is needed to paint 52 704 cm2.\n','multiple_choice','Use the information below to answer the questions that follow','[\"66 \"]','{\"option1\": \"50\", \"option2\": \"75\", \"option3\": \"80\", \"option4\": \"66 \"}',4,'6799ba7043cb0.png','Area in m2 \n= 52 704 ÷ 1002\n= 5,2704 m2	\nNumber of litres needed \n= 5,2704 × 12,46	\n= 65,669…	\n≈ 66\n',0,1,0,2023,NULL,'approved','2025-02-18 11:31:00',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(94,38,38,34,'Write, in simplified form, the ratio of the width to the length of the raised runway.','multiple_choice','A program inspiring people of all ages and genders usually ends with a fashion show.\nANNEXURE B shows the layout of the runways and the seating arrangements at the fashion show.\nNext to the floor runway are single seats arranged in rows. Each round table next to the raised runway can seat a maximum of 10 adults.\nEach of the runways is 4 feet wide.\nNOTE: 1 m = 3,28084 feet\n\nUse the information below and ANNEXURE B to answer the questions that follow\n','[\"1 : 6 \"]','{\"option1\": \"2 : 5\", \"option2\": \"3 : 7\", \"option3\": \"5 : 4\", \"option4\": \"1 : 6 \"}',4,'6799bba16da42.png','  4 : 24\n= 1 : 6 \n',0,1,0,2023,NULL,'approved','2025-02-18 11:30:30',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(95,38,38,34,'	Convert the length of the floor runway to meters','multiple_choice','A program inspiring people of all ages and genders usually ends with a fashion show.\nANNEXURE B shows the layout of the runways and the seating arrangements at the fashion show.\nNext to the floor runway are single seats arranged in rows. Each round table next to the raised runway can seat a maximum of 10 adults.\nEach of the runways is 4 feet wide.\nNOTE: 1 m = 3,28084 feet\nUse the information below and ANNEXURE B to answer the questions that follow','[\"16,459199 m\"]','{\"option1\": \"12.3904 m\", \"option2\": \"18.2003 m\", \"option3\": \"20.5867 m\", \"option4\": \"16,459199 m\"}',4,'6799bdc3865a1.png','Length of runway\n54	÷ 3,28084\n= 16,45919  m\n',0,1,0,2023,NULL,'approved','2025-02-18 11:29:35',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(96,38,38,34,'Calculate the area of the top of ONE round table in m2','multiple_choice','The diameter of the round table is 1,8288 m.\nYou may use the following formulae in the questions that follow:\nArea of a circle = 3,142 X radius2\nCircumference of a circle = 3,142 X diameter\n','[\"2,627112 m2\\\\t\"]','{\"option1\": \"1.996 m2\\\\t\", \"option2\": \"3.152 m2\\\\t\", \"option3\": \"2.732 m2\\\\t\", \"option4\": \"2,627112 m2\\\\t\"}',4,'6799c36134022.png','Radius =1,8288m ÷2=0,9144m\nArea of a circle \n= 3,142 × (0,9144 ?)2\n= 2,627112 m2	\n',0,1,0,2023,NULL,'approved','2025-02-21 09:54:23',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(97,38,38,34,'	Each person occupies an equal length of the outer edge around the round table.\n\nDetermine the maximum length allocated to each person seated around the round table.\n ','multiple_choice','The diameter of the round table is 1,8288 m.\nYou may use the following formulae in the questions that follow:\nArea of a circle = 3,142 X radius2\nCircumference of a circle = 3,142 X diameter\n','[\"0,5746 m\"]','{\"option1\": \"0,6543 m\", \"option2\": \"0,7214 m\", \"option3\": \"0,6832 m\", \"option4\": \"0,5746 m\"}',4,'6799c518f3064.png','Circumference = 3,142 × 1,8288 m\n                           = 5,7460896 m \nLength allocated=5,7460896 m÷10\n                              =0,5746 m',0,1,0,2023,NULL,'approved','2025-02-18 11:31:25',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(98,38,38,34,'	Write down the body size for a girl with a mass of 55 kg and a height of\n1,6 m.\n','multiple_choice','The girls participating in the fashion show need dresses that fit well. The fashion show uses an equal number of girls for each size.\n\nANNEXURE C shows a body type chart used to select the correct dress size.	\nUse ANNEXURE C and the information below to answer the questions that follow.','[\"XS\"]','{\"option1\": \"S\", \"option2\": \"M\", \"option3\": \"L\", \"option4\": \"XS\"}',4,'6799c5e6e3b89.png','',0,1,0,2023,NULL,'approved','2025-02-18 11:30:01',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(99,38,38,34,'State the mass of a girl with a height of 1,75 m wearing dress size 14-16','multiple_choice','The girls participating in the fashion show need dresses that fit well. The fashion show uses an equal number of girls for each size.\n\nANNEXURE C shows a body type chart used to select the correct dress size\nUse ANNEXURE C and the information below to answer the questions that follow.','[\"80 kg \"]','{\"option1\": \"65 kg\", \"option2\": \"100 kg\", \"option3\": \"90 kg\", \"option4\": \"80 kg \"}',4,'6799c6685ed0e.png','',0,1,0,2023,NULL,'approved','2025-02-18 11:28:59',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(100,38,38,34,'	Calculate the body mass index (BMI) of a girl who weighs 70 kg and is 1,50 m tall.\nYou may use the formula: BMI = 	mass (kg)	÷ (height in metres)2\n','multiple_choice','The girls participating in the fashion show need dresses that fit well. The fashion show uses an equal number of girls for each size.\n\nANNEXURE C shows a body type chart used to select the correct dress size\nUse ANNEXURE C and the information below to answer the questions that follow.','[\"31,11 kg\\/m2\"]','{\"option1\": \"31 kg/m2\", \"option2\": \"31,11 kg/m2\", \"option3\": \"22,2  kg/m2\", \"option4\": \"33,11 kg/m2\"}',4,'6799c87036a58.png','BMI  = 70 kg ÷(1,50 m)2\n         =31,11 kg/m2',0,1,0,2023,NULL,'approved','2025-02-02 16:47:08',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(101,38,38,34,'	Write, as a percentage, the probability of randomly selecting a girl who weighs 50 kg and wears an XS dress','multiple_choice','The girls participating in the fashion show need dresses that fit well. The fashion show uses an equal number of girls for each size.\nANNEXURE C shows a body type chart used to select the correct dress size\nUse ANNEXURE C and the information below to answer the questions that follow.','[\"100%\"]','{\"option1\": \"25%\", \"option2\": \"50%\", \"option3\": \"75%\", \"option4\": \"100%\"}',4,'6799c8fc4b842.png','',0,1,0,2023,NULL,'approved','2025-02-18 11:31:12',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(102,38,38,34,'Bonolo stated that the probability of randomly selecting a girl wearing a dress with body size smaller than XXL is 0,833.\nVerify, with calculations, whether her statement is VALID.\n','multiple_choice','The girls participating in the fashion show need dresses that fit well. The fashion show uses an equal number of girls for each size.\n\nANNEXURE C shows a body type chart used to select the correct dress size\nUse ANNEXURE C and the information below to answer the questions that follow.','[\"0,833\"]','{\"option1\": \"0.750\", \"option2\": \"0.910\", \"option3\": \"0.770\", \"option4\": \"0,833\"}',4,'6799c9fdbe9fd.png','P=5÷6\n=0,833\n\nValid',0,1,0,2023,NULL,'approved','2025-02-18 11:30:48',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(103,38,38,34,'Determine the surface area of a cube.\nYou may use the formula:	Surface area of a cube = 6 ^ side length2\n','multiple_choice','Use the information given to answer the question that follows.','[\"121,5 cm2\"]','{\"option1\": \"150 cm2\", \"option2\": \"90 cm2\", \"option3\": \"200 cm2\", \"option4\": \"121,5 cm2\"}',4,'6799cb1e72cb3.png','Surface area of a cube \n= 6 × (4,5 cm)2	\n\n= 121,5 cm2\n',0,1,0,2023,NULL,'approved','2025-02-18 11:30:15',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(104,38,38,34,'	Calculate the total mass (in kg) of a wall built with 60 big blocks of ice.\nNOTE: 1 kg = 0,001 ton\n','multiple_choice','Use the information given to answer the questions that follow','[\"120 000 kg \"]','{\"option1\": \"50 000 kg\", \"option2\": \"150 000 kg\", \"option3\": \"90 000 kg\", \"option4\": \"120 000 kg \"}',4,'6799cbef96608.png','1 ton = 1 000 kg\n\n1 000 kg × 2 = 2 000 kg \nMass of 60 blocks\n= 2 000 × 60\n= 120 000 kg \n',0,1,0,2023,NULL,'approved','2025-02-18 11:29:48',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(105,38,38,34,'	A block of ice was carved out to make a circular opening. The carved-out ice was melted resulting in water with a volume of 38 500 cm3.\n\nCalculate the volume of the ice that was carved out.\nYou may use the formula: Volume of water = volume of ice < 0,92\n','multiple_choice','Use the information below to answer the questions that follow.','[\"41 847,826 cm3 \"]','{\"option1\": \"35,000 cm3\", \"option2\": \"42,500 cm3\", \"option3\": \"40,000 cm3\", \"option4\": \"41 847,826 cm3 \"}',4,'6799cd74c7615.png','38 500 cm3	= volume of ice × 0,92\n38500 ÷ 0,92cm3\n=41 847,826 cm3',0,1,0,2023,NULL,'approved','2025-02-18 11:29:23',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(106,38,38,34,'	Determine, in nautical miles, the difference in the distances from Tokyo to Honolulu and from Washington to Anchorage','multiple_choice','Alaska is one of the states in the USA. Anchorage is the largest city in Alaska.\nANNEXURE D shows a part of the globe indicating the shortest distances, in nautical miles, between Anchorage and a few selected cities in the world.\nNOTE: 1 nautical mile = 1,151 miles 1 km = 0,6215 miles\n\nUse ANNEXURE D and the information below to answer the questions that follow','[\"450\"]','{\"option1\": \"300\", \"option2\": \"500\", \"option3\": \"600\", \"option4\": \"450\"}',4,'6799ceae6142f.png','Difference \n= 3 350 – 2 900\n= 450 nautical miles \n',0,1,0,2023,NULL,'approved','2025-02-18 11:29:10',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(110,38,38,23,'Identify the item that was overbudgeted for by more than R40 million','multiple_choice','Use TABLE 4 to answer the questions that follow.','[\"Grants\"]','{\"option1\": \"Infrastructure Development\", \"option2\": \"Capital Expenditure\", \"option3\": \"Operational Costs\", \"option4\": \"Grants\"}',4,'6799dbde53cf4.png','',0,1,0,2023,'','approved','2025-02-27 12:54:47','','new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(111,38,38,23,'Determine the adjusted budgeted amount for the taxes, levies and tariffs','multiple_choice','Use TABLE 4 to answer the questions that follow.','[\"R180 767\"]','{\"option1\": \"R190 800\", \"option2\": \"R170 500\", \"option3\": \"R200 467\", \"option4\": \"R180 767\"}',4,'6799de3548bd3.png','Amount in thousands\nR340 688 – (R111 769 + R48 152)\n= R180 767\n',0,1,0,2023,NULL,'approved','2025-02-21 09:38:32',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(112,38,38,23,'Show how the total net surplus/deficit amount for the original budgeted amount was calculated','multiple_choice','Use TABLE 4 to answer the questions that follow','[\"R9099\"]','{\"option1\": \"R9099\", \"option2\": \"R9093\", \"option3\": \"R9099\", \"option4\": \"R9093\"}',4,'6799df4fdeb17.png','Net deficit \nR313 792 – R322 891\n= − R9 099\n',0,1,0,2023,NULL,'approved','2025-02-02 16:47:08',NULL,'Image checked by AI: Image is not text only','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(113,38,38,23,'The actual total expenditure (Y) shows a net surplus amount of 2,53% of the total income.\nShow, using calculations, that the table value of the actual amount for the total expenditure (Z) to the nearest whole number is R309 547.\n \n','multiple_choice','Use TABLE 4 to answer the questions that follow in Rands','[\"R309 547\"]','{\"option1\": \"R315 689\", \"option2\": \"R306 452\", \"option3\": \"R311 230\", \"option4\": \"R309 547\"}',4,'6799e2bb7bb3a.png','\n',0,1,0,2023,'6799e2bb7bb3a.png','approved','2025-02-21 09:38:12',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(114,38,38,34,'	Convert, to kilometres, the distance from Berlin to Anchorage','multiple_choice','Alaska is one of the states in the USA. Anchorage is the largest city in Alaska.\nANNEXURE D shows a part of the globe indicating the shortest distances, in nautical miles, between Anchorage and a few selected cities in the world.\nNOTE: 1 nautical mile = 1,151 miles 1 km = 0,6215 miles\nUse ANNEXURE D and the information below to answer the questions that follow.','[\"7 315,285599 km.\"]','{\"option1\": \"5456.23 km\", \"option2\": \"6930.72 km\", \"option3\": \"8124.57 km\", \"option4\": \"7 315,285599 km.\"}',4,'6799e40708181.png','Distance in miles \n\n=3 950 × 1,151	\n= 4 546,45 miles.\nDistance in km \n=4546,45 ÷0,6215\n=7 315,285599 km.\n',0,1,0,2023,NULL,'approved','2025-02-18 11:28:48',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(115,38,38,23,'Identify the only descriptor where the age group 5 to 17 years old are fewer than the age group under 5 years old.','multiple_choice','Use the graph to answer the questions that follow.','[\"Male\"]','{\"option1\": \"Female\", \"option2\": \"Fluid\", \"option3\": \"Other\", \"option4\": \"Male\"}',4,'6799e4855f365.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:38:01',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(116,38,38,23,'Determine the difference in percentages of the two age groups for the\nfemale descriptor','multiple_choice','Use the graph to answer the questions that follow in percent','[\"10,4% \"]','{\"option1\": \"6.8%\", \"option2\": \"15.3%\", \"option3\": \"8.2%\", \"option4\": \"10,4% \"}',4,'6799e512b6908.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:37:46',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(117,38,38,34,'Calculate the average speed of the ship, rounded to TWO decimal places, in nautical miles per hour.\n\nYou may use the formula: Distance = speed X time\n','multiple_choice','Cargo needs to be shipped from Los Angeles to Honolulu and then from Honolulu to Tokyo.\nPhenyo searched the internet to determine how long it would take the cargo to reach its destination. Shown below are the search results. Some information has been omitted.\n','[\"10,68 \"]','{\"option1\": \"11.95\", \"option2\": \"9.32\", \"option3\": \"10.01\", \"option4\": \"10,68 \"}',4,'6799e5c959e40.png','10 days 4 hours = 244 hours  \n2 607 = speed × 10 days4 hours\n2 607 = speed× 244 hours\n2607÷244 = speed\n=10,68 m/h\n',0,1,0,2023,NULL,'approved','2025-02-18 11:28:30',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(118,38,38,34,'Hence, determine the date and time of arrival in Tokyo if the ship leaves Honolulu on 24 September at 16:00 and sails at the same average speed','multiple_choice','	Cargo needs to be shipped from Los Angeles to Honolulu and then from Honolulu to Tokyo.\nPhenyo searched the internet to determine how long it would take the cargo to reach its destination. Shown below are the search results. Some information has been omitted.\n','[\"7 October at 17: 40\"]','{\"option1\": \"8 October at 18:30\", \"option2\": \"6 October at 16:10\", \"option3\": \"7 October at 20:50\", \"option4\": \"7 October at 17: 40\"}',4,'6799e80cd3e4d.png','Time = 3350 miles ÷ 10,68 nautical miles  per hour\n         =313,67 hours\n =313,67 hours ÷24 hours\n=13 days and 1,67 hours\nArrival date and time 7 October at 17:40',0,1,0,2023,NULL,'approved','2025-02-18 10:38:11',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(119,38,38,23,'In a rural school, there are 795 learners in the age group 5 to 17 years old. Calculate the number of learners who are NOT overweight or obese.','multiple_choice','Use the graph to answer the questions that follow in','[\"665 learners\"]','{\"option1\": \"700 learners\", \"option2\": \"720 learners\", \"option3\": \"750 learners\", \"option4\": \"665 learners\"}',4,'6799e90b0aa1f.png','\n\n',0,1,0,2023,'6799e90004008.png','approved','2025-02-21 09:37:33',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(120,38,38,23,'Write down the percentage of malnourished children with a head circumference below the 33,5 percentile.','multiple_choice','Use the information and the box and whisker plots below to answer the questions that follow in percent','[\"75% \"]','{\"option1\": \"20%\", \"option2\": \"42%\", \"option3\": \"58%\", \"option4\": \"75% \"}',4,'6799f322c4e1b.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:37:19',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(121,38,38,23,'Calculate the number of children that were below the median head circumference in the children with normal nutritional status','multiple_choice','Use the information and the box and whisker plots to answer the questions that follow.','[\"64,5\"]','{\"option1\": \"64\", \"option2\": \"64,6\", \"option3\": \"64,5\", \"option4\": \"64,7\"}',4,'6799f4de7b845.png','',0,1,0,2023,'6799f4d5a6873.png','approved','2025-02-21 09:37:08',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(122,38,38,23,'Write down the modal in-store price for P&P store.','multiple_choice','Use TABLE 5 to answer the questions that follow.','[\"No mode \"]','{\"option1\": \"$50\", \"option2\": \"$100\", \"option3\": \"$200\", \"option4\": \"No mode \"}',4,'6799f64698fcb.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:36:37',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(123,38,38,23,'Determine the number of items where the in-store and online prices are the same for the W&W store.','multiple_choice','Use TABLE 5 to answer the questions that follow.','[\"7\"]','{\"option1\": \"6\", \"option2\": \"7\", \"option3\": \"8\", \"option4\": \"9\"}',4,'6799f6ab0ef31.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:36:18',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(124,38,38,23,'A one-way trip to the P&P store is R15 per person. Calculate how much Mrs Swartz would be saving if she bought all the items listed in the table directly from the store rather than shopping online.','multiple_choice','Use TABLE 5 to answer the questions that follow in Rands.','[\"R23,06 \"]','{\"option1\": \"R25,10\", \"option2\": \"R30,05\", \"option3\": \"R20,00\", \"option4\": \"R23,06 \"}',4,'6799f8d4b3820.png','Amount saved \n= (R261,80 – R208,74) – (R15× 2)\n= R53,06 – R30\n= R23,06 \n',0,1,0,2023,NULL,'approved','2025-02-21 09:35:54',NULL,'new','- The total in-store price at the P&P store is R208.74, while the total online price is R261.80.\n- To find the savings from shopping in-store rather than online, subtract the in-store total from the online total.\n- Savings calculation: R261.80 (online) - R208.74 (in-store) = R53.06.\n- However, since Mrs. Swartz will make a one-way trip to the P&P store costing R15, this trip cost must be deducted from her total savings.\n- Final savings calculation: R53.06 (savings) - R15 (trip cost) = R38.06.\n- It seems there may have been an error in the stated answer; the expected savings should consider the direct purchase costs and adjustments for travel.','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(125,38,38,23,'Determine the median price of the listed items for in-store shopping in the W&W store.','multiple_choice','Use TABLE 5 to answer the questions that follow.','[\"17,45 \"]','{\"option1\": \"17,54\", \"option2\": \"17,44\", \"option3\": \"17\", \"option4\": \"17,43\"}',4,'6799f952023b9.png','',0,1,0,2023,'6799f9a502099.png','approved','2025-02-21 09:35:41',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(126,38,38,23,'	Name the country with the lowest number of people visiting SA for business.','multiple_choice','Use the graph to answer the questions that follow.','[\"Kenya\"]','{\"option1\": \"Brazil\", \"option2\": \"China\", \"option3\": \"Australia\", \"option4\": \"Kenya\"}',2,'6799fa9d8179b.png','',0,1,0,2024,NULL,'approved','2025-02-18 10:37:16',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(128,38,38,23,'Calculate the probability of randomly selecting an item from lhe P&P store where the in-store price is exactly the same as the online price.','multiple_choice','Use TABLE 5 to answer the questions that follow in percent.','[\"50%\"]','{\"option1\": \"20%\", \"option2\": \"70%\", \"option3\": \"80%\", \"option4\": \"50%\"}',4,'6799fb7117bc6.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:35:10',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(129,38,38,23,'	Write down the country that has the greatest difference in people visiting SA for business, compared to those visiting SA for a holiday','multiple_choice','Use the graph to answer the questions that follow','[\"Malawi\"]','{\"option1\": \"Zimbabwe\", \"option2\": \"South Africa\", \"option3\": \"Namibia\", \"option4\": \"Malawi\"}',2,'6799fba8c36ff.png','',0,1,0,2024,NULL,'approved','2025-02-18 10:37:30',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(130,38,38,23,'Calculate the total number of people visiting SA for study purposes.\n \n','multiple_choice','Use the graph to answer the questions that follow','[\"739\"]','{\"option1\": \"450\", \"option2\": \"820\", \"option3\": \"670\", \"option4\": \"739\"}',2,'6799fc8571570.png','People for Studying Purposes \n\n= 83 + 98 + 475 + 83\n= 739 tourists \n',0,1,0,2024,NULL,'approved','2025-02-18 10:36:59',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(131,38,38,23,'Write down the name of the store that offers the third lowest price.','multiple_choice','The Swartz family also decided to buy and resell doughnuts in packets of four in order to fund the tour. They sourced the prices of doughnuts at four stores.\nTheir target was to sell 100 packets of doughnuts. The fixed cost for the buying and re-packaging of the doughnuts was R201,00.\nThe graphs for the income and expenses for the buying, re-packaging and selling of the packets of doughnuts, as well as the store prices of the doughnuts, are given in ANNEXURE C.\n','[\"P&P store \"]','{\"option1\": \"Checkers Store\", \"option2\": \"Shoprite Store\", \"option3\": \"Woolworths Store\", \"option4\": \"P&P store \"}',4,'6799fcd50ce18.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:34:58',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(132,38,38,23,'Determine the price of ONE teabag if Samuel buys item B','multiple_choice','\nUse TABLE 1 to answer the questions that follow\n','[\"R1,25 \"]','{\"option1\": \"R2,50\", \"option2\": \"R1,75\", \"option3\": \"R0,75\", \"option4\": \"R1,25 \"}',2,'6799fd1f647ba.png','Price of 1 teabag \n= R50,00 ÷ 40\n= R1,25\n',0,1,0,2024,NULL,'approved','2025-02-18 10:36:46',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(133,38,38,23,'Write, as a simplified ratio, the price of item D to the price of item C','multiple_choice','Use TABLE 1 to answer the questions that follow','[\"1,85 : 1 \"]','{\"option1\": \"2:1\", \"option2\": \"1.9:1\", \"option3\": \"1.85:2\", \"option4\": \"1,85 : 1 \"}',2,'6799fe5d7d16e.png','185 : 100 \n1,85 : 1',0,1,0,2024,NULL,'approved','2025-02-18 10:36:28',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(134,38,38,23,'The total cost for buying and re-packaging 50 packets of doughnuts is R701,00. Determine, with calculations, from which store they bought the doughnuts.','multiple_choice','The Swartz family also decided to buy and resell doughnuts in packets of four in order to fund the tour. They sourced the prices of doughnuts at four stores.\nTheir target was to sell 100 packets of doughnuts. The fixed cost for the buying and re-packaging of the doughnuts was R201,00.\nThe graphs for the income and expenses for the buying, re-packaging and selling of the packets of doughnuts, as well as the store prices of the doughnuts, are given in ANNEXURE C.\n','[\"FLM store \"]','{\"option1\": \"Walmart Store\", \"option2\": \"Costco Store\", \"option3\": \"Aldi Store\", \"option4\": \"FLM store \"}',4,'6799fe95263a6.png','Number of doughnuts \n= 50 × 4 \n= 200\n\nCost of packets of doughnuts \n= R701 – R201\n= R500\n\nCost per doughnut\n= R500 ÷ 200 \n= R2,50 \n',0,1,0,2023,NULL,'approved','2025-02-21 09:34:38',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(135,38,38,23,'	Samuel decided to purchase the following items for his wife:\n•	Teapot\n•	Rooibos Goddess tin with 50 pyramid teabags\n•	Gift bag\nDetermine the total cost of his purchase.\n','multiple_choice','Use TABLE 1 to answer the questions that follow.','[\"R301,00\"]','{\"option1\": \"R200,00\", \"option2\": \"R350,00\", \"option3\": \"R500,00\", \"option4\": \"R301,00\"}',2,'6799fed8a01b1.png','Total of purchase \n\n= R185,00 + R100,00 + R16,00\n= R301,00\n',0,1,0,2024,NULL,'approved','2025-02-18 10:36:12',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(136,38,38,23,'Mr Swartz stated that the break-even point was reached before the sale of 20 packets. State, with a reason, whether you agree or disagree with his statement.\n','multiple_choice','Use ANNEXURE C and the information to answer the questions that follow.','[\"Disagree\"]','{\"option1\": \"Agree, because the cost of production decreases with quantity\", \"option2\": \"Agree, because Mr. Swartz has extensive knowledge in this field\", \"option3\": \"Agree, because the sales exceeded expectations\", \"option4\": \"Disagree\"}',4,'6799ff5319adb.png','The expenses are higher than the income ',0,1,0,2023,NULL,'approved','2025-02-21 09:34:23',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(137,38,38,23,'Write down the country whose currency has the same value as the SA rand','multiple_choice','Use the TABLE  to answer the questions that follow','[\"Namibia\"]','{\"option1\": \"United States\", \"option2\": \"Australia\", \"option3\": \"China\", \"option4\": \"Namibia\"}',2,'6799ff6c00630.png','',0,1,0,2024,NULL,'approved','2025-02-18 10:35:44',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(138,38,38,23,'If the selling price increased, write down, whether the break-even point would now be lower or higher.','multiple_choice','The Swartz family also decided to buy and resell doughnuts in packets of four in order to fund the tour. They sourced the prices of doughnuts at four stores.\nTheir target was to sell 100 packets of doughnuts. The fixed cost for the buying and re-packaging of the doughnuts was R201,00.\nThe graphs for the income and expenses for the buying, re-packaging and selling of the packets of doughnuts, as well as the store prices of the doughnuts, are given in ANNEXURE C.\n','[\"Lower\"]','{\"option1\": \"Higher\", \"option2\": \"The same\", \"option3\": \"Unchanged\", \"option4\": \"Lower\"}',4,'679a0036b733c.png','If Income is higher, therefore the break-even point will be reached sooner ',0,1,0,2023,NULL,'approved','2025-02-21 09:34:10',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(139,38,38,23,'Identify the currency that is stronger than the SA rand','multiple_choice','Use the TABLE  to answer the questions that follow','[\"Zambian Kwacha\"]','{\"option1\": \"Euro\", \"option2\": \"US Dollar\", \"option3\": \"British Pound\", \"option4\": \"Zambian Kwacha\"}',2,'679a00645bec8.png','',0,1,0,2024,NULL,'approved','2025-02-18 10:35:58',NULL,'new','## Identifying Stronger Currencies\n\n### Understanding Currency Strength\n- **Currency Conversion Factors**: Refers to the relative value of one currency against another.\n- **Strong Currency**: A currency is considered stronger when it can purchase more of another currency relative to its value.\n\n### Table Overview\n- The table lists several currencies with their respective conversion factors against the South African Rand (ZAR) as of April 27, 2023:\n  - **Malawian Kwacha (MWK)**: 56.211355 units per ZAR\n  - **Kenyan Shilling (KES)**: 7.443462 units per ZAR\n  - **Namibian Dollar (NAD)**: 1.000000 units per ZAR\n  - **Zambian Kwacha (ZMW)**: 0.971016 units per ZAR\n\n### Comparing Currencies to the South African Rand\n- To identify which currency is stronger than the South African rand, we check the \"ZAR per unit\" column.\n- The Zambian Kwacha (ZMW) has a value of **1.029850 ZAR per unit**, which indicates it is stronger than the South African rand since it requires less than one unit of ZMW to equal one unit of ZAR.\n\n### Conclusion\n- **The Zambian Kwacha (ZMW)** is the only currency listed that is stronger based on the conversion factors provided.\n\n***Key Lesson:***  \nThe Zambian Kwacha can buy more than the South African Rand! ??','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(140,38,38,23,'Write down the aircraft operator whose average daily flights increased the most since 2019.','multiple_choice','Use TABLE 6 to answer the questions that follow.','[\"Wizz Air Group \"]','{\"option1\": \"American Airlines Group\", \"option2\": \"British Airways Group\", \"option3\": \"EasyJet Group\", \"option4\": \"Wizz Air Group \"}',4,'679a01630ddc2.png','',0,1,0,2023,NULL,'approved','2025-02-21 09:33:53',NULL,'new','','CAPS','2025-03-09 16:26:02','2025-03-09 16:26:02'),(141,30,30,23,'question with image answer34 1223dfdddsds','multiple_choice','','[\"this is the answer\"]','{\"option1\": \"Probability is the mass of an object in physics \", \"option2\": \" Probability is the speed of light in a vacuum \", \"option3\": \"1232\", \"option4\": \"1223\"}',2,'','',NULL,1,0,2023,'','new','2025-03-13 08:01:54','','new',NULL,'IEB','2025-03-13 08:01:54',NULL),(142,36,36,34,'asas','multiple_choice','asasas','[\"asasas\"]','{\"option1\": \"assas\", \"option2\": \"asas\", \"option3\": \"sdsdsdsd\", \"option4\": \"asasas\"}',1,'','',NULL,1,0,2025,'','new','2025-03-13 10:05:12','','new',NULL,'CAPS','2025-03-13 10:05:12',NULL);
/*!40000 ALTER TABLE `question` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `result`
--

DROP TABLE IF EXISTS `result`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `result` (
  `id` int NOT NULL AUTO_INCREMENT,
  `question` int DEFAULT NULL,
  `learner` int DEFAULT NULL,
  `outcome` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `duration` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `result_question` (`question`),
  KEY `result_learner_idx` (`learner`),
  CONSTRAINT `FK_136AC1138EF3834` FOREIGN KEY (`learner`) REFERENCES `learner` (`id`),
  CONSTRAINT `FK_136AC113B6F7494E` FOREIGN KEY (`question`) REFERENCES `question` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `result`
--

LOCK TABLES `result` WRITE;
/*!40000 ALTER TABLE `result` DISABLE KEYS */;
INSERT INTO `result` VALUES (1,46,30,'incorrect','2025-03-13 08:31:58',0),(2,46,30,'incorrect','2025-03-13 08:33:26',0),(3,46,30,'correct','2025-03-13 08:33:38',60),(4,47,30,'correct','2025-03-13 08:33:55',60),(5,139,144,'correct','2025-03-13 13:38:09',0),(6,138,127,'correct','2025-03-13 13:42:52',0),(7,132,127,'correct','2025-03-13 13:46:43',0),(8,56,127,'correct','2025-03-13 13:46:55',0),(9,135,127,'correct','2025-03-13 13:50:40',0),(10,67,127,'correct','2025-03-13 13:50:55',0),(11,133,127,'incorrect','2025-03-13 13:51:41',0),(12,133,127,'incorrect','2025-03-13 13:51:57',0),(13,131,127,'incorrect','2025-03-13 14:28:42',0),(14,111,127,'incorrect','2025-03-13 14:31:56',0);
/*!40000 ALTER TABLE `result` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subject`
--

DROP TABLE IF EXISTS `subject`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subject` (
  `id` int NOT NULL AUTO_INCREMENT,
  `grade` int DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `subject_grade_idx` (`grade`),
  CONSTRAINT `FK_FBCE3E7A595AAE34` FOREIGN KEY (`grade`) REFERENCES `grade` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subject`
--

LOCK TABLES `subject` WRITE;
/*!40000 ALTER TABLE `subject` DISABLE KEYS */;
INSERT INTO `subject` VALUES (5,1,'Agricultural Sciences P1',1),(7,1,'Business Studies P1',1),(14,1,'Economics P1',1),(17,1,'Geography P1',1),(18,1,'History P1',1),(22,1,'Life Sciences P1',1),(23,1,'Mathematical Literacy P1',1),(24,1,'Mathematics P1',1),(28,1,'Physical Sciences P1',1),(34,1,'Mathematical Literacy P2',1),(35,1,'Physical Sciences P2',1),(37,1,'Mathematics P2',1),(38,1,'Life Sciences P2',1),(40,1,'Geography P2',1),(41,1,'Business Studies P2',1),(42,1,'Economics P2',1),(43,1,'Agricultural Sciences P2',1),(45,2,'Agricultural Sciences P1',1),(46,2,'Agricultural Sciences P2',1),(47,1,'History P2',1),(49,2,'Business Studies P1',1),(50,2,'Economics P1',1),(51,2,'Geography P1',1),(52,2,'History P1',1),(53,2,'Life Sciences P1',1),(54,2,'Mathematical Literacy P1',1),(55,2,'Mathematics P1',1),(56,2,'Physical Sciences P1',1),(57,2,'Mathematical Literacy P2',1),(58,2,'Physical Sciences P2',1),(59,2,'Mathematics P2',1),(60,2,'Life Sciences P2',1),(61,2,'Geography P2',1),(62,2,'Business Studies P2',1),(63,2,'Economics P2',1),(67,2,'History P2',1),(68,3,'Agricultural Sciences P1',1),(69,3,'Business Studies P1',1),(70,3,'Economics P1',1),(71,3,'Geography P1',1),(72,3,'History P1',1),(73,3,'Life Sciences P1',1),(74,3,'Mathematical Literacy P1',1),(75,3,'Mathematics P1',1),(76,3,'Physical Sciences P1',1),(77,3,'Mathematical Literacy P2',1),(78,3,'Physical Sciences P2',1),(79,3,'Mathematics P2',1),(80,3,'Life Sciences P2',1),(81,3,'Geography P2',1),(82,3,'Business Studies P2',1),(83,3,'Economics P2',1),(84,3,'Agricultural Sciences P2',1),(85,3,'Agricultural Sciences P1',1),(86,3,'Agricultural Sciences P2',1),(87,3,'History P2',1),(88,1,'Life orientation P1',1),(89,1,'Life orientation P2',1),(90,2,'Life orientation P1',1),(91,2,'Life orientation P2',1),(92,3,'Life orientation P1',1),(93,3,'Life orientation P2',1),(94,3,'Tourism P1',1),(95,3,'Tourism P2',1),(96,2,'Tourism P1',1),(97,2,'Tourism P2',1),(98,1,'Tourism P1',1),(99,1,'Tourism P2',1);
/*!40000 ALTER TABLE `subject` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subject_points`
--

DROP TABLE IF EXISTS `subject_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subject_points` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `learner` int DEFAULT NULL,
  `subject` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `points` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `subject_points_learner_idx` (`learner`),
  KEY `subject_points_subject_idx` (`subject`),
  CONSTRAINT `FK_AFA3470E8EF3834` FOREIGN KEY (`learner`) REFERENCES `learner` (`id`),
  CONSTRAINT `FK_AFA3470EFBCE3E7A` FOREIGN KEY (`subject`) REFERENCES `subject` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subject_points`
--

LOCK TABLES `subject_points` WRITE;
/*!40000 ALTER TABLE `subject_points` DISABLE KEYS */;
INSERT INTO `subject_points` VALUES (1,30,34,'2025-03-13 08:31:58',2),(2,144,23,'2025-03-13 13:38:09',0),(3,127,23,'2025-03-13 13:42:52',0);
/*!40000 ALTER TABLE `subject_points` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscription`
--

DROP TABLE IF EXISTS `subscription`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subscription` (
  `id` int NOT NULL AUTO_INCREMENT,
  `phone_number` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_A3C664D36B01BC5B` (`phone_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscription`
--

LOCK TABLES `subscription` WRITE;
/*!40000 ALTER TABLE `subscription` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscription` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_8D93D649E7927C74` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-03-13 20:11:49
