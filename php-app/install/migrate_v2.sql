-- ============================================================
--  Migration v2 — Nouvelles tables
--  À exécuter dans phpMyAdmin après setup.sql
-- ============================================================

USE `fiscal_control`;

-- Commentaires internes par dossier
CREATE TABLE IF NOT EXISTS `commentaires` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `dossier_id` INT NOT NULL,
  `user_id`    INT NOT NULL,
  `message`    TEXT NOT NULL,
  `created_at` DATETIME DEFAULT NOW(),
  FOREIGN KEY (`dossier_id`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pièces jointes par dossier
CREATE TABLE IF NOT EXISTS `pieces_jointes` (
  `id`           INT AUTO_INCREMENT PRIMARY KEY,
  `dossier_id`   INT NOT NULL,
  `user_id`      INT NOT NULL,
  `nom_original` VARCHAR(255) NOT NULL,
  `nom_stockage` VARCHAR(255) NOT NULL,
  `mime_type`    VARCHAR(100) DEFAULT '',
  `taille`       INT DEFAULT 0,
  `created_at`   DATETIME DEFAULT NOW(),
  FOREIGN KEY (`dossier_id`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Historique des modifications par dossier
CREATE TABLE IF NOT EXISTS `dossier_historique` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `dossier_id` INT NOT NULL,
  `user_id`    INT DEFAULT NULL,
  `action`     VARCHAR(150) NOT NULL,
  `details`    TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT NOW(),
  FOREIGN KEY (`dossier_id`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Réinitialisation de mot de passe
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(150) NOT NULL,
  `token`      VARCHAR(64)  NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used`       TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Secrets TOTP pour l'authentification à deux facteurs
CREATE TABLE IF NOT EXISTS `totp_secrets` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL UNIQUE,
  `secret`     VARCHAR(64) NOT NULL,
  `actif`      TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT NOW(),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
