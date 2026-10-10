-- Catatan Keuangan V20 - Adaptive Learning
-- Alternatif untuk hosting tanpa akses `php yii migrate`.
-- Setelah SQL ini dijalankan, `php yii migrate` tetap aman dijalankan kemudian
-- karena migration V20 bersifat idempotent dan hanya akan mencatat status migration.

CREATE TABLE IF NOT EXISTS `assistant_training_example` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `message_hash` VARCHAR(64) NOT NULL,
  `source_message` TEXT NOT NULL,
  `normalized_text` VARCHAR(500) NOT NULL,
  `tokens_json` TEXT NULL,
  `parsed_json` TEXT NULL,
  `confirmed_json` TEXT NOT NULL,
  `correction_json` TEXT NULL,
  `was_corrected` TINYINT(1) NOT NULL DEFAULT 0,
  `use_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_used_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_ai_example_user_created` (`user_id`,`created_at`),
  KEY `ix_ai_example_user_hash` (`user_id`,`message_hash`),
  CONSTRAINT `fk_ai_example_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assistant_token_stat` (
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(80) NOT NULL,
  `field_name` VARCHAR(40) NOT NULL,
  `field_value` VARCHAR(190) NOT NULL,
  `positive_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_seen_at` DATETIME NOT NULL,
  PRIMARY KEY (`user_id`,`token`,`field_name`,`field_value`),
  KEY `ix_ai_token_user_field` (`user_id`,`field_name`,`field_value`),
  CONSTRAINT `fk_ai_token_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
