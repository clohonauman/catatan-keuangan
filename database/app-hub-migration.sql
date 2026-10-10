-- Application Hub + Google Login + SSO code exchange
-- Jalankan sekali pada database production sebelum deploy controller/view baru.
-- Tidak mengubah tabel transaksi atau data keuangan yang sudah ada.

CREATE TABLE IF NOT EXISTS `app_catalog` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(90) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `icon` VARCHAR(500) NOT NULL DEFAULT '📱',
  `app_url` VARCHAR(1000) NOT NULL,
  `callback_url` VARCHAR(1000) NULL,
  `premium_required` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 100,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `internal_key` VARCHAR(30) NULL,
  `client_id` VARCHAR(80) NULL,
  `client_secret_hash` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_app_catalog_slug` (`slug`),
  UNIQUE KEY `ux_app_catalog_client_id` (`client_id`),
  KEY `ix_app_catalog_active_order` (`is_active`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `app_sso_code` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `app_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `code_hash` CHAR(64) NOT NULL,
  `state_hash` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_app_sso_code_hash` (`code_hash`),
  KEY `ix_app_sso_code_expiry` (`expires_at`,`used_at`),
  CONSTRAINT `fk_app_sso_code_app` FOREIGN KEY (`app_id`) REFERENCES `app_catalog` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_app_sso_code_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_google_identity` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `google_sub` VARCHAR(255) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_google_identity_sub` (`google_sub`),
  UNIQUE KEY `ux_google_identity_user` (`user_id`),
  KEY `ix_google_identity_email` (`email`),
  CONSTRAINT `fk_google_identity_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Catat migrasi di tabel Yii agar perintah yii migrate berikutnya tidak mencoba membuat tabel yang sama.
CREATE TABLE IF NOT EXISTS `migration` (
  `version` VARCHAR(180) NOT NULL,
  `apply_time` INT NOT NULL,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `migration` (`version`, `apply_time`)
VALUES ('m261010_000001_create_application_hub', UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE `version` = VALUES(`version`);
