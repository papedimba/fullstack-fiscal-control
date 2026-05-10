-- ============================================================
--  Correctif : mise à jour des mots de passe
--  À exécuter dans phpMyAdmin si vous avez déjà importé setup.sql
--  Mot de passe : Admin1234!
-- ============================================================

USE `fiscal_control`;

UPDATE `users`
SET `password_hash` = '$2y$12$5LF1pwF/AquUXaH1GW4DuO8THrThwfGRQRQSI1mR3WMb4Qq.bAf9.'
WHERE `email` IN (
  'directeur@fiscalite.ci',
  'sousdirecteur@fiscalite.ci',
  'chef.brigade.a@fiscalite.ci',
  'chef.brigade.b@fiscalite.ci',
  'agent1@fiscalite.ci',
  'agent2@fiscalite.ci',
  'agent3@fiscalite.ci'
);

-- Vérification
SELECT email, role,
       CASE WHEN password_hash LIKE '$2y$12$5LF1%' THEN '✓ OK' ELSE '✗ ERREUR' END AS statut
FROM users;
