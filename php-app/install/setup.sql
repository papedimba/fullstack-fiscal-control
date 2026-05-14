-- ============================================================
--  Tableau de Bord – Suivi du Contrôle Fiscal
--  Script MySQL – Création de la base de données
-- ============================================================

CREATE DATABASE IF NOT EXISTS `fiscal_control`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `fiscal_control`;

-- -------------------------------------------------------
--  Tables
-- -------------------------------------------------------

CREATE TABLE IF NOT EXISTS `brigades` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `nom`        VARCHAR(100) NOT NULL,
  `chef_id`    INT DEFAULT NULL,
  `created_at` DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT AUTO_INCREMENT PRIMARY KEY,
  `nom`           VARCHAR(100) NOT NULL,
  `prenom`        VARCHAR(100) NOT NULL,
  `email`         VARCHAR(150) UNIQUE NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('directeur','sous_directeur','chef_brigade','agent') NOT NULL,
  `brigade_id`    INT DEFAULT NULL,
  `actif`         TINYINT(1) DEFAULT 1,
  `created_at`    DATETIME DEFAULT NOW(),
  FOREIGN KEY (`brigade_id`) REFERENCES `brigades`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `contribuables` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `nom`             VARCHAR(200) NOT NULL,
  `nif`             VARCHAR(50) UNIQUE NOT NULL,
  `type_entreprise` VARCHAR(50) NOT NULL,
  `adresse`         VARCHAR(255) DEFAULT '',
  `secteur_activite` VARCHAR(100) DEFAULT '',
  `created_at`      DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `dossiers` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `numero_dossier`  VARCHAR(20) UNIQUE NOT NULL,
  `contribuable_id` INT NOT NULL,
  `agent_id`        INT NOT NULL,
  `chef_brigade_id` INT DEFAULT NULL,
  `brigade_id`      INT DEFAULT NULL,
  `date_ouverture`  DATE NOT NULL,
  `date_cloture`    DATE DEFAULT NULL,
  `type_controle`   ENUM('verification_comptabilite','verification_ponctuelle','droit_communication','controle_sur_pieces') NOT NULL,
  `statut`          ENUM('ouvert','en_cours','suspendu','cloture','contentieux') NOT NULL DEFAULT 'ouvert',
  `observations`    TEXT DEFAULT NULL,
  `created_at`      DATETIME DEFAULT NOW(),
  FOREIGN KEY (`contribuable_id`) REFERENCES `contribuables`(`id`),
  FOREIGN KEY (`agent_id`)        REFERENCES `users`(`id`),
  FOREIGN KEY (`chef_brigade_id`) REFERENCES `users`(`id`),
  FOREIGN KEY (`brigade_id`)      REFERENCES `brigades`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `etapes` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `dossier_id`       INT NOT NULL,
  `type_etape`       ENUM('avis','notification','pv','enquete','rapport','redressement','reponse_contribuable','decision_finale') NOT NULL,
  `date_prevue`      DATE DEFAULT NULL,
  `date_realisation` DATE DEFAULT NULL,
  `statut`           ENUM('en_attente','en_cours','termine','retard') NOT NULL DEFAULT 'en_attente',
  `commentaire`      TEXT DEFAULT NULL,
  `created_at`       DATETIME DEFAULT NOW(),
  FOREIGN KEY (`dossier_id`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `alertes` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `dossier_id`  INT NOT NULL,
  `etape_id`    INT DEFAULT NULL,
  `message`     TEXT NOT NULL,
  `type`        ENUM('critique','avertissement','info') NOT NULL DEFAULT 'avertissement',
  `lu`          TINYINT(1) DEFAULT 0,
  `created_at`  DATETIME DEFAULT NOW(),
  FOREIGN KEY (`dossier_id`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`etape_id`)   REFERENCES `etapes`(`id`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id`          INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT DEFAULT NULL,
  `action`      VARCHAR(50) NOT NULL,
  `table_name`  VARCHAR(50) DEFAULT NULL,
  `record_id`   INT DEFAULT NULL,
  `details`     TEXT DEFAULT NULL,
  `created_at`  DATETIME DEFAULT NOW(),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `commentaires` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `dossier_id` INT NOT NULL,
  `user_id`    INT NOT NULL,
  `message`    TEXT NOT NULL,
  `created_at` DATETIME DEFAULT NOW(),
  FOREIGN KEY (`dossier_id`) REFERENCES `dossiers`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(150) NOT NULL,
  `token`      VARCHAR(64)  NOT NULL UNIQUE,
  `expires_at` DATETIME NOT NULL,
  `used`       TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT NOW()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `totp_secrets` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT NOT NULL UNIQUE,
  `secret`     VARCHAR(64) NOT NULL,
  `actif`      TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT NOW(),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------
--  Données initiales (mot de passe : Admin1234!)
-- -------------------------------------------------------
-- Hash bcrypt généré par PHP : password_hash('Admin1234!', PASSWORD_BCRYPT)
-- Hash bcrypt de 'Admin1234!' généré par password_hash('Admin1234!', PASSWORD_BCRYPT)
SET @hash = '$2y$12$5LF1pwF/AquUXaH1GW4DuO8THrThwfGRQRQSI1mR3WMb4Qq.bAf9.';

INSERT IGNORE INTO `brigades` (`id`,`nom`) VALUES (1,'Brigade A'),(2,'Brigade B'),(3,'Brigade C');

INSERT IGNORE INTO `users` (`id`,`nom`,`prenom`,`email`,`password_hash`,`role`) VALUES
(1,'DIALLO','Mamadou','directeur@fiscalite.ci',@hash,'directeur'),
(2,'KONÉ','Fatima','sousdirecteur@fiscalite.ci',@hash,'sous_directeur');

INSERT IGNORE INTO `users` (`id`,`nom`,`prenom`,`email`,`password_hash`,`role`,`brigade_id`) VALUES
(3,'TRAORÉ','Ibrahim','chef.brigade.a@fiscalite.ci',@hash,'chef_brigade',1),
(4,'COULIBALY','Aminata','chef.brigade.b@fiscalite.ci',@hash,'chef_brigade',2),
(5,'BAMBA','Sékou','agent1@fiscalite.ci',@hash,'agent',1),
(6,'OUATTARA','Mariam','agent2@fiscalite.ci',@hash,'agent',1),
(7,'SANOGO','Dramane','agent3@fiscalite.ci',@hash,'agent',2);

UPDATE `brigades` SET `chef_id` = 3 WHERE `id` = 1;
UPDATE `brigades` SET `chef_id` = 4 WHERE `id` = 2;

INSERT IGNORE INTO `contribuables` (`id`,`nom`,`nif`,`type_entreprise`,`adresse`,`secteur_activite`) VALUES
(1,'SOCIÉTÉ IVOIRIENNE DE TRANSIT SIT SA','CI-ABJ-2019-B-12345','SA','Abidjan, Zone 4','Transport & Logistique'),
(2,'AGRO-INDUSTRIES CÔTE D\'IVOIRE SARL','CI-ABJ-2018-B-67890','SARL','Abidjan, Yopougon','Agro-industrie'),
(3,'BANQUE NATIONALE DE DÉVELOPPEMENT BND','CI-ABJ-2015-B-11111','SA','Abidjan, Plateau','Finance & Banque'),
(4,'CONSTRUCTION MODERNE CM SA','CI-ABJ-2020-B-22222','SA','Abidjan, Cocody','BTP'),
(5,'IMPORT-EXPORT DAKAR ABIDJAN IEDA','CI-ABJ-2017-B-33333','SARL','Abidjan, Treichville','Commerce International');

INSERT IGNORE INTO `dossiers` (`id`,`numero_dossier`,`contribuable_id`,`agent_id`,`chef_brigade_id`,`brigade_id`,`date_ouverture`,`type_controle`,`statut`) VALUES
(1,'DCF-2024-001',1,5,3,1,'2024-01-15','verification_comptabilite','en_cours'),
(2,'DCF-2024-002',2,6,3,1,'2024-02-01','verification_ponctuelle','en_cours'),
(3,'DCF-2024-003',3,7,4,2,'2024-03-10','controle_sur_pieces','ouvert'),
(4,'DCF-2024-004',4,5,3,1,'2024-04-05','verification_comptabilite','suspendu'),
(5,'DCF-2024-005',5,6,4,2,'2024-05-20','droit_communication','cloture'),
(6,'DCF-2025-001',1,7,4,2,'2025-01-10','verification_comptabilite','en_cours'),
(7,'DCF-2025-002',2,5,3,1,'2025-02-15','verification_ponctuelle','en_cours');

INSERT IGNORE INTO `etapes` (`dossier_id`,`type_etape`,`date_prevue`,`date_realisation`,`statut`,`commentaire`) VALUES
(1,'avis','2024-01-20','2024-01-19','termine','Avis envoyé'),
(1,'notification','2024-02-01','2024-02-01','termine','Notification émise'),
(1,'pv','2024-03-01',NULL,'retard','En attente PV'),
(1,'enquete','2024-04-01',NULL,'en_attente',''),
(2,'avis','2024-02-10','2024-02-09','termine','OK'),
(2,'notification','2024-03-01','2024-03-05','termine','Légère retard'),
(2,'rapport','2024-06-01',NULL,'en_cours','En rédaction'),
(3,'avis','2024-03-20','2024-03-20','termine','OK'),
(3,'notification','2024-04-15',NULL,'en_attente',''),
(5,'avis','2024-05-25','2024-05-24','termine',''),
(5,'notification','2024-06-10','2024-06-10','termine',''),
(5,'rapport','2024-08-01','2024-07-28','termine',''),
(5,'decision_finale','2024-09-01','2024-09-01','termine','Dossier clôturé'),
(6,'avis','2025-01-20','2025-01-20','termine',''),
(6,'notification','2025-02-15',NULL,'en_cours','');

INSERT IGNORE INTO `alertes` (`dossier_id`,`etape_id`,`message`,`type`) VALUES
(1,3,'Le procès-verbal du dossier DCF-2024-001 est en retard de 60 jours','critique'),
(2,7,'Le rapport du dossier DCF-2024-002 approche de son échéance','avertissement'),
(6,15,'La notification du dossier DCF-2025-001 est en cours','info');
