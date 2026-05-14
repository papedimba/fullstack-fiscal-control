<?php
/**
 * Initialise la base de données (SQLite ou MySQL).
 * Usage : php install/seed.php
 */
require_once __DIR__ . '/../includes/db.php';

$db = DB::get();

// Schéma compatible SQLite et MySQL
$isSqlite = USE_SQLITE;

$tables = <<<SQL
CREATE TABLE IF NOT EXISTS brigades (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  nom        TEXT NOT NULL,
  chef_id    INTEGER DEFAULT NULL,
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  nom           TEXT NOT NULL,
  prenom        TEXT NOT NULL,
  email         TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  role          TEXT NOT NULL CHECK(role IN ('directeur','sous_directeur','chef_brigade','agent')),
  brigade_id    INTEGER DEFAULT NULL REFERENCES brigades(id),
  actif         INTEGER DEFAULT 1,
  created_at    TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS contribuables (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  nom              TEXT NOT NULL,
  nif              TEXT UNIQUE NOT NULL,
  type_entreprise  TEXT NOT NULL,
  adresse          TEXT DEFAULT '',
  secteur_activite TEXT DEFAULT '',
  created_at       TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS dossiers (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  numero_dossier   TEXT UNIQUE NOT NULL,
  contribuable_id  INTEGER NOT NULL REFERENCES contribuables(id),
  agent_id         INTEGER NOT NULL REFERENCES users(id),
  chef_brigade_id  INTEGER DEFAULT NULL REFERENCES users(id),
  brigade_id       INTEGER DEFAULT NULL REFERENCES brigades(id),
  date_ouverture   TEXT NOT NULL,
  date_cloture     TEXT DEFAULT NULL,
  type_controle    TEXT NOT NULL,
  statut           TEXT NOT NULL DEFAULT 'ouvert',
  observations     TEXT DEFAULT '',
  created_at       TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS etapes (
  id               INTEGER PRIMARY KEY AUTOINCREMENT,
  dossier_id       INTEGER NOT NULL REFERENCES dossiers(id) ON DELETE CASCADE,
  type_etape       TEXT NOT NULL,
  date_prevue      TEXT DEFAULT NULL,
  date_realisation TEXT DEFAULT NULL,
  statut           TEXT NOT NULL DEFAULT 'en_attente',
  commentaire      TEXT DEFAULT '',
  created_at       TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS alertes (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  dossier_id  INTEGER NOT NULL REFERENCES dossiers(id) ON DELETE CASCADE,
  etape_id    INTEGER DEFAULT NULL REFERENCES etapes(id) ON DELETE SET NULL,
  message     TEXT NOT NULL,
  type        TEXT NOT NULL DEFAULT 'avertissement',
  lu          INTEGER DEFAULT 0,
  created_at  TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id     INTEGER DEFAULT NULL REFERENCES users(id),
  action      TEXT NOT NULL,
  table_name  TEXT DEFAULT NULL,
  record_id   INTEGER DEFAULT NULL,
  details     TEXT DEFAULT '',
  created_at  TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS commentaires (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  dossier_id  INTEGER NOT NULL REFERENCES dossiers(id) ON DELETE CASCADE,
  user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  message     TEXT NOT NULL,
  created_at  TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS pieces_jointes (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  dossier_id    INTEGER NOT NULL REFERENCES dossiers(id) ON DELETE CASCADE,
  user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE SET NULL,
  nom_original  TEXT NOT NULL,
  nom_stockage  TEXT NOT NULL,
  mime_type     TEXT DEFAULT '',
  taille        INTEGER DEFAULT 0,
  created_at    TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS dossier_historique (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  dossier_id  INTEGER NOT NULL REFERENCES dossiers(id) ON DELETE CASCADE,
  user_id     INTEGER DEFAULT NULL REFERENCES users(id) ON DELETE SET NULL,
  action      TEXT NOT NULL,
  details     TEXT DEFAULT '',
  created_at  TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS password_resets (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  email       TEXT NOT NULL,
  token       TEXT NOT NULL UNIQUE,
  expires_at  TEXT NOT NULL,
  used        INTEGER DEFAULT 0,
  created_at  TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS totp_secrets (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id     INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  secret      TEXT NOT NULL,
  actif       INTEGER DEFAULT 0,
  created_at  TEXT DEFAULT (datetime('now'))
);
SQL;

foreach (explode(';', $tables) as $stmt) {
    $stmt = trim($stmt);
    if ($stmt) $db->exec($stmt);
}

// Check if already seeded
$cnt = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ((int)$cnt > 0) {
    echo "Base de données déjà initialisée ({$cnt} utilisateurs).\n";
    exit(0);
}

$hash = password_hash('Admin1234!', PASSWORD_BCRYPT);

// Brigades
$db->exec("INSERT INTO brigades (nom) VALUES ('Brigade A'),('Brigade B'),('Brigade C')");

// Users
$ins = $db->prepare('INSERT INTO users (nom,prenom,email,password_hash,role,brigade_id) VALUES (?,?,?,?,?,?)');
$ins->execute(['DIALLO','Mamadou','directeur@fiscalite.ci',$hash,'directeur',null]);
$ins->execute(['KONÉ','Fatima','sousdirecteur@fiscalite.ci',$hash,'sous_directeur',null]);
$ins->execute(['TRAORÉ','Ibrahim','chef.brigade.a@fiscalite.ci',$hash,'chef_brigade',1]);
$ins->execute(['COULIBALY','Aminata','chef.brigade.b@fiscalite.ci',$hash,'chef_brigade',2]);
$ins->execute(['BAMBA','Sékou','agent1@fiscalite.ci',$hash,'agent',1]);
$ins->execute(['OUATTARA','Mariam','agent2@fiscalite.ci',$hash,'agent',1]);
$ins->execute(['SANOGO','Dramane','agent3@fiscalite.ci',$hash,'agent',2]);

$db->exec("UPDATE brigades SET chef_id=3 WHERE id=1");
$db->exec("UPDATE brigades SET chef_id=4 WHERE id=2");

// Contribuables
$inc = $db->prepare('INSERT INTO contribuables (nom,nif,type_entreprise,adresse,secteur_activite) VALUES (?,?,?,?,?)');
$inc->execute(['SOCIÉTÉ IVOIRIENNE DE TRANSIT SIT SA','CI-ABJ-2019-B-12345','SA','Abidjan, Zone 4','Transport & Logistique']);
$inc->execute(["AGRO-INDUSTRIES CÔTE D'IVOIRE SARL",'CI-ABJ-2018-B-67890','SARL','Abidjan, Yopougon','Agro-industrie']);
$inc->execute(['BANQUE NATIONALE DE DÉVELOPPEMENT BND','CI-ABJ-2015-B-11111','SA','Abidjan, Plateau','Finance & Banque']);
$inc->execute(['CONSTRUCTION MODERNE CM SA','CI-ABJ-2020-B-22222','SA','Abidjan, Cocody','BTP']);
$inc->execute(['IMPORT-EXPORT DAKAR ABIDJAN IEDA','CI-ABJ-2017-B-33333','SARL','Abidjan, Treichville','Commerce International']);

// Dossiers
$ind = $db->prepare('INSERT INTO dossiers (numero_dossier,contribuable_id,agent_id,chef_brigade_id,brigade_id,date_ouverture,type_controle,statut) VALUES (?,?,?,?,?,?,?,?)');
$ind->execute(['DCF-2024-001',1,5,3,1,'2024-01-15','verification_comptabilite','en_cours']);
$ind->execute(['DCF-2024-002',2,6,3,1,'2024-02-01','verification_ponctuelle','en_cours']);
$ind->execute(['DCF-2024-003',3,7,4,2,'2024-03-10','controle_sur_pieces','ouvert']);
$ind->execute(['DCF-2024-004',4,5,3,1,'2024-04-05','verification_comptabilite','suspendu']);
$ind->execute(['DCF-2024-005',5,6,4,2,'2024-05-20','droit_communication','cloture']);
$ind->execute(['DCF-2025-001',1,7,4,2,'2025-01-10','verification_comptabilite','en_cours']);
$ind->execute(['DCF-2025-002',2,5,3,1,'2025-02-15','verification_ponctuelle','en_cours']);

// Etapes
$ine = $db->prepare('INSERT INTO etapes (dossier_id,type_etape,date_prevue,date_realisation,statut,commentaire) VALUES (?,?,?,?,?,?)');
$ine->execute([1,'avis','2024-01-20','2024-01-19','termine','Avis envoyé']);
$ine->execute([1,'notification','2024-02-01','2024-02-01','termine','Notification émise']);
$ine->execute([1,'pv','2024-03-01',null,'retard','En attente PV']);
$ine->execute([1,'enquete','2024-04-01',null,'en_attente','']);
$ine->execute([2,'avis','2024-02-10','2024-02-09','termine','OK']);
$ine->execute([2,'notification','2024-03-01','2024-03-05','termine','Légère retard']);
$ine->execute([2,'rapport','2024-06-01',null,'en_cours','En rédaction']);
$ine->execute([3,'avis','2024-03-20','2024-03-20','termine','OK']);
$ine->execute([3,'notification','2024-04-15',null,'en_attente','']);
$ine->execute([5,'avis','2024-05-25','2024-05-24','termine','']);
$ine->execute([5,'notification','2024-06-10','2024-06-10','termine','']);
$ine->execute([5,'rapport','2024-08-01','2024-07-28','termine','']);
$ine->execute([5,'decision_finale','2024-09-01','2024-09-01','termine','Dossier clôturé']);
$ine->execute([6,'avis','2025-01-20','2025-01-20','termine','']);
$ine->execute([6,'notification','2025-02-15',null,'en_cours','']);

// Alertes
$db->exec("INSERT INTO alertes (dossier_id,etape_id,message,type) VALUES (1,3,'Le procès-verbal du dossier DCF-2024-001 est en retard de 60 jours','critique')");
$db->exec("INSERT INTO alertes (dossier_id,etape_id,message,type) VALUES (2,7,'Le rapport du dossier DCF-2024-002 approche de son échéance','avertissement')");
$db->exec("INSERT INTO alertes (dossier_id,etape_id,message,type) VALUES (6,15,'La notification du dossier DCF-2025-001 est en cours','info')");

echo "✓ Base de données initialisée avec succès !\n";
echo "  7 utilisateurs · 3 brigades · 5 contribuables · 7 dossiers · 15 étapes · 3 alertes\n";
echo "  Mot de passe de tous les comptes : Admin1234!\n";
