
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrator','Pełny dostęp do systemu'),(2,'User','Podstawowy użytkownik');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'dashboard.view','Podgląd dashboardu'),(2,'users.view','Podgląd użytkowników'),(3,'users.manage','Zarządzanie użytkownikami'),(4,'roles.view','Podgląd ról'),(5,'roles.manage','Zarządzanie rolami'),(6,'permissions.view','Podgląd uprawnień'),(7,'permissions.manage','Zarządzanie uprawnieniami'),(8,'pages.view','Podgląd stron'),(9,'pages.manage','Zarządzanie dostępem do stron');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `pages` WRITE;
/*!40000 ALTER TABLE `pages` DISABLE KEYS */;
INSERT INTO `pages` VALUES (1,NULL,'home','Start',1,1,10,'2026-04-16 10:27:06','2026-04-16 10:27:06'),(2,NULL,'login','Logowanie',1,0,20,'2026-04-16 10:27:06','2026-04-16 10:27:06'),(3,NULL,'dashboard','Dashboard',0,1,20,'2026-04-16 10:27:06','2026-04-16 12:30:39'),(4,9,'users','Użytkownicy',0,1,10,'2026-04-16 10:27:06','2026-04-16 12:30:39'),(5,9,'roles','Role',0,1,20,'2026-04-16 10:27:06','2026-04-16 12:30:39'),(6,9,'permissions','Uprawnienia',0,1,30,'2026-04-16 10:27:06','2026-04-16 12:30:39'),(7,NULL,'forbidden','Brak dostępu',1,0,999,'2026-04-16 10:27:06','2026-04-16 10:27:06'),(8,NULL,'settings','Ustawienia',0,1,50,'2026-04-16 12:29:24','2026-04-16 12:29:24'),(9,8,'access_management','Zarządzaj dostępem',0,1,10,'2026-04-16 12:29:24','2026-04-16 12:30:29');
/*!40000 ALTER TABLE `pages` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
INSERT INTO `role_permissions` VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(2,1);
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `page_permissions` WRITE;
/*!40000 ALTER TABLE `page_permissions` DISABLE KEYS */;
INSERT INTO `page_permissions` VALUES (3,1),(4,2),(4,3),(5,4),(5,5),(6,6),(6,7);
/*!40000 ALTER TABLE `page_permissions` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

