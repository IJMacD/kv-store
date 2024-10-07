-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               PostgreSQL 17.0 on x86_64-pc-linux-gnu, compiled by gcc (Debian 12.2.0-14) 12.2.0, 64-bit
-- Server OS:
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES  */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table public.auth
CREATE TABLE IF NOT EXISTS "auth" (
	"auth_id" SERIAL NOT NULL,
	"bucket_id" INTEGER NOT NULL,
	"auth_type" VARCHAR NOT NULL,
	"identifier" VARCHAR NULL DEFAULT NULL,
	"secret" VARCHAR NULL DEFAULT NULL,
	"expires_at" TIMESTAMP NULL DEFAULT NULL,
	"can_list" BOOLEAN NOT NULL DEFAULT false,
	"can_read" BOOLEAN NOT NULL DEFAULT false,
	"can_create" BOOLEAN NOT NULL DEFAULT false,
	"can_edit" BOOLEAN NOT NULL DEFAULT false,
	"can_delete" BOOLEAN NOT NULL DEFAULT false,
	"can_admin" BOOLEAN NOT NULL DEFAULT false,
	PRIMARY KEY ("auth_id"),
	CONSTRAINT "auth_bucket_id" FOREIGN KEY ("bucket_id") REFERENCES "buckets" ("bucket_id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Data exporting was unselected.

-- Dumping structure for table public.buckets
CREATE TABLE IF NOT EXISTS "buckets" (
	"bucket_id" SERIAL NOT NULL,
	"bucket_name" VARCHAR NOT NULL,
	"email" VARCHAR NULL DEFAULT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	"frozen" BOOLEAN NOT NULL DEFAULT false,
	PRIMARY KEY ("bucket_id"),
	UNIQUE "bucket_name" ("bucket_name")
);

-- Data exporting was unselected.

-- Dumping structure for table public.objects
CREATE TABLE IF NOT EXISTS "objects" (
	"key" VARCHAR NOT NULL,
	"bucket_id" INTEGER NOT NULL,
	"value" TEXT NULL DEFAULT NULL,
	"numeric_value" DOUBLE PRECISION NULL DEFAULT NULL,
	"type" VARCHAR NOT NULL,
	"mime" VARCHAR NOT NULL,
	"created_at" TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY ("key"),
	CONSTRAINT "objects_bucket_id" FOREIGN KEY ("bucket_id") REFERENCES "buckets" ("bucket_id") ON UPDATE NO ACTION ON DELETE CASCADE
);

-- Data exporting was unselected.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
