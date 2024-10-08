-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               11.1.3-MariaDB - Source distribution
-- Server OS:                    Linux
-- HeidiSQL Version:             12.6.0.6765
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table kv.auth
CREATE TABLE IF NOT EXISTS `auth` (
  `auth_id` int(11) NOT NULL AUTO_INCREMENT,
  `bucket_id` int(11) NOT NULL,
  `auth_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `identifier` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `secret` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `can_list` tinyint(1) DEFAULT NULL,
  `can_read` tinyint(1) DEFAULT NULL,
  `can_create` tinyint(1) DEFAULT NULL,
  `can_edit` tinyint(1) DEFAULT NULL,
  `can_delete` tinyint(1) DEFAULT NULL,
  `can_admin` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`auth_id`),
  KEY `auth_bucket_id` (`bucket_id`),
  CONSTRAINT `auth_bucket_id` FOREIGN KEY (`bucket_id`) REFERENCES `buckets` (`bucket_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table kv.buckets
CREATE TABLE IF NOT EXISTS `buckets` (
  `bucket_id` int(11) NOT NULL AUTO_INCREMENT,
  `bucket_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `email` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `frozen` tinyint(4) NOT NULL,
  PRIMARY KEY (`bucket_id`) USING BTREE,
  UNIQUE KEY `bucket_name` (`bucket_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

-- Dumping structure for table kv.objects
CREATE TABLE IF NOT EXISTS `objects` (
  `key` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `bucket_id` int(11) NOT NULL,
  `text_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `numeric_value` int(11) DEFAULT NULL,
  `type` enum('TEXT','NUMBER','JSON', 'BLOB') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL DEFAULT 'TEXT',
  `mime` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`key`),
  KEY `object_bucket_id` (`bucket_id`),
  CONSTRAINT `object_bucket_id` FOREIGN KEY (`bucket_id`) REFERENCES `buckets` (`bucket_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data exporting was unselected.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
