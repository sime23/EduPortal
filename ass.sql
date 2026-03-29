-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: assweb
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `assignments`
--

DROP TABLE IF EXISTS `assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assignments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `class_id` int unsigned DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `master_correction` varchar(255) DEFAULT NULL,
  `deadline` datetime NOT NULL,
  `created_by` int unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_assignments_teacher` (`created_by`),
  KEY `idx_deadline` (`deadline`),
  KEY `idx_class_id` (`class_id`),
  CONSTRAINT `fk_assignments_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_teacher` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assignments`
--

LOCK TABLES `assignments` WRITE;
/*!40000 ALTER TABLE `assignments` DISABLE KEYS */;
INSERT INTO `assignments` VALUES (1,NULL,'Research Paper ÔÇô Climate Change','Write a 2000-word research paper covering the causes, effects, and mitigation strategies of climate change. Cite at least 5 peer-reviewed sources.',NULL,NULL,'2026-03-31 03:30:03',1,'2026-03-24 03:30:03'),(2,NULL,'Math Problem Set #4','Complete all exercises in Chapter 7 (Differential Equations). Show all working steps clearly.',NULL,NULL,'2026-03-24 21:30:03',1,'2026-03-24 03:30:03'),(3,NULL,'UX Case Study Presentation','Prepare a 10-slide presentation analysing the UX of a popular mobile application of your choice.',NULL,NULL,'2026-04-07 03:30:03',1,'2026-03-24 03:30:03'),(4,1,'Coding Project','Code a php website application\r\nYour report should have the structure of the file below','assign_1774319759_8fcc9325.pdf',NULL,'2026-03-24 14:35:00',2,'2026-03-24 03:35:59'),(6,1,'Cyber Security','Discussion and research on cybersecurity',NULL,NULL,'2026-03-28 18:32:00',2,'2026-03-28 16:32:19');
/*!40000 ALTER TABLE `assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class_students`
--

DROP TABLE IF EXISTS `class_students`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class_students` (
  `class_id` int unsigned NOT NULL,
  `student_id` int unsigned NOT NULL,
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`class_id`,`student_id`),
  KEY `fk_class_students_student` (`student_id`),
  CONSTRAINT `fk_class_students_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_class_students_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class_students`
--

LOCK TABLES `class_students` WRITE;
/*!40000 ALTER TABLE `class_students` DISABLE KEYS */;
INSERT INTO `class_students` VALUES (1,3,'2026-03-24 03:33:42'),(1,4,'2026-03-24 03:33:42'),(1,5,'2026-03-24 03:33:42'),(1,7,'2026-03-29 13:04:25'),(1,8,'2026-03-29 13:04:48'),(1,9,'2026-03-28 16:34:59'),(1,11,'2026-03-29 13:16:29'),(2,7,'2026-03-24 14:39:06'),(2,8,'2026-03-24 14:39:06'),(2,9,'2026-03-24 14:39:06');
/*!40000 ALTER TABLE `class_students` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `classes`
--

DROP TABLE IF EXISTS `classes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `classes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int unsigned NOT NULL,
  `class_name` varchar(200) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_classes_teacher` (`teacher_id`),
  CONSTRAINT `fk_classes_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `classes`
--

LOCK TABLES `classes` WRITE;
/*!40000 ALTER TABLE `classes` DISABLE KEYS */;
INSERT INTO `classes` VALUES (1,2,'CS50','2026-03-24 03:33:42'),(2,2,'ICT Law','2026-03-24 14:37:43');
/*!40000 ALTER TABLE `classes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submissions`
--

DROP TABLE IF EXISTS `submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `submissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `assignment_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `grade` varchar(10) DEFAULT NULL,
  `feedback` text,
  `correction_path` varchar(255) DEFAULT NULL,
  `attempts` int unsigned NOT NULL DEFAULT '1',
  `submitted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_one_sub_per_student` (`assignment_id`,`user_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_submissions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submissions`
--

LOCK TABLES `submissions` WRITE;
/*!40000 ALTER TABLE `submissions` DISABLE KEYS */;
INSERT INTO `submissions` VALUES (2,4,4,'sub_1774321485_dcfdf5b0.docx',NULL,NULL,NULL,1,'2026-03-24 04:04:45');
/*!40000 ALTER TABLE `submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','teacher','admin') NOT NULL DEFAULT 'student',
  `profile_pic` varchar(255) NOT NULL DEFAULT 'default_avatar.png',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Sime Bryan','simebryan2003@gmail.com','$2y$10$w6vL.Ido1xlWu4r7lRBmxut5R1c82dm6g7DRsSeh1Np8vFG.rtFJO','admin','default_avatar.png','2026-03-24 03:30:03'),(2,'Michou','michou@gmail.com','$2y$10$7x7vDsGKVL96Qs/AfboP.eSRTt6SBRopP8WDQKJUJcYGr7hZPKkzi','teacher','default_avatar.png','2026-03-24 03:32:26'),(3,'Alice Johnson','alice@gmail.com','$2y$10$EsO3z38njdYuVxOHH11ao.RSC00sHq0DdzKQKg.mOT0Dk6Sp3vneK','student','default_avatar.png','2026-03-24 03:33:42'),(4,'Bob Smith','bob@gmail.com','$2y$10$CDhaGw1eYN769sMmZTxZReqRSV4Toej0h98ABFmyelWHQ6D51KtCC','student','default_avatar.png','2026-03-24 03:33:42'),(5,'Charlie Davis','charlie@gmail.com','$2y$10$rqIfewu/dEUUWFJ6JehmoeeCdctxEulHfjwgvJmcPu0Dc80zgrXVG','student','default_avatar.png','2026-03-24 03:33:42'),(7,'ash anih','ash@gmail.com','$2y$10$OxjCPZ3syXzd1CwD0q6Koua1I77idxcGl8QkwNOiEAlOP4riQ2GVi','student','default_avatar.png','2026-03-24 14:39:06'),(8,'larrissa nina','larissa@gmail.com','$2y$10$u3g1IS3/hHZAngVO/iXT9.87YxYHox1ONILDgJyTHtHpA5GxxWgp2','student','default_avatar.png','2026-03-24 14:39:06'),(9,'amida fouche','amida@gmail.com','$2y$10$2VNRTtEvMywQUItN0WA3G.ALQPjIVDQ5yjQe4PSN2vy5v8ZRMVegm','student','default_avatar.png','2026-03-24 14:39:06'),(11,'prince','prince@gmail.com','$2y$10$zq4SlsdgfhSj3N6vCn8Y3Oaj0nB4RbEHf./BVjO6H7gxWVUmyP/NS','student','default_avatar.png','2026-03-29 13:16:19');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-29 14:13:37
