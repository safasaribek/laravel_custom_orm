-- Mini ORM Demo — Tablo Oluşturma
-- Bu script Docker başlarken otomatik çalışır.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- -------------------------------------------------------------------------
-- users
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255)    NOT NULL,
    `email`      VARCHAR(255)    NOT NULL,
    `status`     VARCHAR(50)     NOT NULL DEFAULT 'active',
    `age`        TINYINT UNSIGNED         DEFAULT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- profiles (hasOne demo)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `profiles` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `bio`        TEXT                     DEFAULT NULL,
    `avatar`     VARCHAR(500)             DEFAULT NULL,
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `profiles_user_id_unique` (`user_id`),
    CONSTRAINT `fk_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- posts (belongsTo / hasMany demo)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `posts` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT UNSIGNED NOT NULL,
    `title`      VARCHAR(500)    NOT NULL,
    `body`       TEXT                     DEFAULT NULL,
    `status`     VARCHAR(50)     NOT NULL DEFAULT 'draft',
    `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `posts_user_id_idx` (`user_id`),
    CONSTRAINT `fk_posts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- tags (belongsToMany demo)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags` (
    `id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100)    NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `tags_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- post_tag (pivot tablo)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `post_tag` (
    `post_id` BIGINT UNSIGNED NOT NULL,
    `tag_id`  BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`post_id`, `tag_id`),
    CONSTRAINT `fk_pt_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_pt_tag`  FOREIGN KEY (`tag_id`)  REFERENCES `tags`  (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- products (genişletilmiş model örneği)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id`         BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255)      NOT NULL,
    `price`      DECIMAL(10, 2)    NOT NULL DEFAULT 0.00,
    `stock`      INT UNSIGNED      NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
