import Database from 'better-sqlite3';
import path from 'path';
import bcrypt from 'bcryptjs';

const DB_PATH = path.join(__dirname, '../../data/fiscal_control.db');

let db: Database.Database;

export function getDb(): Database.Database {
  if (!db) {
    const fs = require('fs');
    const dir = path.dirname(DB_PATH);
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
    db = new Database(DB_PATH);
    db.pragma('journal_mode = WAL');
    db.pragma('foreign_keys = ON');
  }
  return db;
}

export function initializeDb(): void {
  const db = getDb();

  db.exec(`
    CREATE TABLE IF NOT EXISTS brigades (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      nom TEXT NOT NULL,
      chef_id INTEGER,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      nom TEXT NOT NULL,
      prenom TEXT NOT NULL,
      email TEXT UNIQUE NOT NULL,
      password_hash TEXT NOT NULL,
      role TEXT NOT NULL CHECK(role IN ('directeur','sous_directeur','chef_brigade','agent')),
      brigade_id INTEGER REFERENCES brigades(id),
      actif INTEGER DEFAULT 1,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS contribuables (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      nom TEXT NOT NULL,
      nif TEXT UNIQUE NOT NULL,
      type_entreprise TEXT NOT NULL,
      adresse TEXT,
      secteur_activite TEXT,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS dossiers (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      numero_dossier TEXT UNIQUE NOT NULL,
      contribuable_id INTEGER NOT NULL REFERENCES contribuables(id),
      agent_id INTEGER NOT NULL REFERENCES users(id),
      chef_brigade_id INTEGER REFERENCES users(id),
      brigade_id INTEGER REFERENCES brigades(id),
      date_ouverture TEXT NOT NULL,
      date_cloture TEXT,
      type_controle TEXT NOT NULL CHECK(type_controle IN ('verification_comptabilite','verification_ponctuelle','droit_communication','controle_sur_pieces')),
      statut TEXT NOT NULL DEFAULT 'ouvert' CHECK(statut IN ('ouvert','en_cours','suspendu','cloture','contentieux')),
      observations TEXT DEFAULT '',
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS etapes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      dossier_id INTEGER NOT NULL REFERENCES dossiers(id) ON DELETE CASCADE,
      type_etape TEXT NOT NULL CHECK(type_etape IN ('avis','notification','pv','enquete','rapport','redressement','reponse_contribuable','decision_finale')),
      date_prevue TEXT,
      date_realisation TEXT,
      statut TEXT NOT NULL DEFAULT 'en_attente' CHECK(statut IN ('en_attente','en_cours','termine','retard')),
      commentaire TEXT DEFAULT '',
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS alertes (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      dossier_id INTEGER NOT NULL REFERENCES dossiers(id) ON DELETE CASCADE,
      etape_id INTEGER REFERENCES etapes(id) ON DELETE CASCADE,
      message TEXT NOT NULL,
      type TEXT NOT NULL DEFAULT 'avertissement' CHECK(type IN ('critique','avertissement','info')),
      lu INTEGER DEFAULT 0,
      created_at TEXT DEFAULT (datetime('now'))
    );

    CREATE TABLE IF NOT EXISTS audit_logs (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      user_id INTEGER REFERENCES users(id),
      action TEXT NOT NULL,
      table_name TEXT,
      record_id INTEGER,
      details TEXT DEFAULT '',
      created_at TEXT DEFAULT (datetime('now'))
    );
  `);

  seedDefaultData(db);
}

function seedDefaultData(db: Database.Database): void {
  const existing = db.prepare('SELECT COUNT(*) as cnt FROM users').get() as { cnt: number };
  if (existing.cnt > 0) return;

  // Brigades
  db.prepare('INSERT INTO brigades (nom) VALUES (?)').run('Brigade A');
  db.prepare('INSERT INTO brigades (nom) VALUES (?)').run('Brigade B');
  db.prepare('INSERT INTO brigades (nom) VALUES (?)').run('Brigade C');

  const hash = bcrypt.hashSync('Admin1234!', 10);

  // Directeur
  db.prepare(`INSERT INTO users (nom, prenom, email, password_hash, role) VALUES (?,?,?,?,?)`)
    .run('DIALLO', 'Mamadou', 'directeur@fiscalite.ci', hash, 'directeur');

  // Sous-directeurs
  db.prepare(`INSERT INTO users (nom, prenom, email, password_hash, role) VALUES (?,?,?,?,?)`)
    .run('KONÉ', 'Fatima', 'sousdirecteur@fiscalite.ci', hash, 'sous_directeur');

  // Chefs de brigade
  db.prepare(`INSERT INTO users (nom, prenom, email, password_hash, role, brigade_id) VALUES (?,?,?,?,?,?)`)
    .run('TRAORÉ', 'Ibrahim', 'chef.brigade.a@fiscalite.ci', hash, 'chef_brigade', 1);
  db.prepare(`INSERT INTO users (nom, prenom, email, password_hash, role, brigade_id) VALUES (?,?,?,?,?,?)`)
    .run('COULIBALY', 'Aminata', 'chef.brigade.b@fiscalite.ci', hash, 'chef_brigade', 2);

  // Agents
  db.prepare(`INSERT INTO users (nom, prenom, email, password_hash, role, brigade_id) VALUES (?,?,?,?,?,?)`)
    .run('BAMBA', 'Sékou', 'agent1@fiscalite.ci', hash, 'agent', 1);
  db.prepare(`INSERT INTO users (nom, prenom, email, password_hash, role, brigade_id) VALUES (?,?,?,?,?,?)`)
    .run('OUATTARA', 'Mariam', 'agent2@fiscalite.ci', hash, 'agent', 1);
  db.prepare(`INSERT INTO users (nom, prenom, email, password_hash, role, brigade_id) VALUES (?,?,?,?,?,?)`)
    .run('SANOGO', 'Dramane', 'agent3@fiscalite.ci', hash, 'agent', 2);

  // Update brigade chiefs
  db.prepare('UPDATE brigades SET chef_id = 3 WHERE id = 1').run();
  db.prepare('UPDATE brigades SET chef_id = 4 WHERE id = 2').run();

  // Sample contribuables
  const contribuables = [
    ['SOCIÉTÉ IVOIRIENNE DE TRANSIT SIT SA', 'CI-ABJ-2019-B-12345', 'SA', 'Abidjan, Zone 4', 'Transport & Logistique'],
    ['AGRO-INDUSTRIES CÔTE D\'IVOIRE SARL', 'CI-ABJ-2018-B-67890', 'SARL', 'Abidjan, Yopougon', 'Agro-industrie'],
    ['BANQUE NATIONALE DE DÉVELOPPEMENT BND', 'CI-ABJ-2015-B-11111', 'SA', 'Abidjan, Plateau', 'Finance & Banque'],
    ['CONSTRUCTION MODERNE CM SA', 'CI-ABJ-2020-B-22222', 'SA', 'Abidjan, Cocody', 'BTP'],
    ['IMPORT-EXPORT DAKAR ABIDJAN IEDA', 'CI-ABJ-2017-B-33333', 'SARL', 'Abidjan, Treichville', 'Commerce International'],
  ];

  for (const c of contribuables) {
    db.prepare('INSERT INTO contribuables (nom, nif, type_entreprise, adresse, secteur_activite) VALUES (?,?,?,?,?)').run(...c);
  }

  // Sample dossiers
  const dossiers = [
    ['DCF-2024-001', 1, 5, 3, 1, '2024-01-15', 'verification_comptabilite', 'en_cours'],
    ['DCF-2024-002', 2, 6, 3, 1, '2024-02-01', 'verification_ponctuelle', 'en_cours'],
    ['DCF-2024-003', 3, 7, 4, 2, '2024-03-10', 'controle_sur_pieces', 'ouvert'],
    ['DCF-2024-004', 4, 5, 3, 1, '2024-04-05', 'verification_comptabilite', 'suspendu'],
    ['DCF-2024-005', 5, 6, 4, 2, '2024-05-20', 'droit_communication', 'cloture'],
    ['DCF-2025-001', 1, 7, 4, 2, '2025-01-10', 'verification_comptabilite', 'en_cours'],
    ['DCF-2025-002', 2, 5, 3, 1, '2025-02-15', 'verification_ponctuelle', 'en_cours'],
  ];

  for (const d of dossiers) {
    db.prepare(`INSERT INTO dossiers (numero_dossier, contribuable_id, agent_id, chef_brigade_id, brigade_id, date_ouverture, type_controle, statut)
      VALUES (?,?,?,?,?,?,?,?)`).run(...d);
  }

  // Sample etapes
  const etapesData = [
    // Dossier 1
    [1, 'avis', '2024-01-20', '2024-01-19', 'termine', 'Avis envoyé'],
    [1, 'notification', '2024-02-01', '2024-02-01', 'termine', 'Notification émise'],
    [1, 'pv', '2024-03-01', null, 'retard', 'En attente PV'],
    [1, 'enquete', '2024-04-01', null, 'en_attente', ''],
    // Dossier 2
    [2, 'avis', '2024-02-10', '2024-02-09', 'termine', 'OK'],
    [2, 'notification', '2024-03-01', '2024-03-05', 'termine', 'Légère retard'],
    [2, 'rapport', '2024-06-01', null, 'en_cours', 'En rédaction'],
    // Dossier 3
    [3, 'avis', '2024-03-20', '2024-03-20', 'termine', 'OK'],
    [3, 'notification', '2024-04-15', null, 'en_attente', ''],
    // Dossier 5 (cloturé)
    [5, 'avis', '2024-05-25', '2024-05-24', 'termine', ''],
    [5, 'notification', '2024-06-10', '2024-06-10', 'termine', ''],
    [5, 'rapport', '2024-08-01', '2024-07-28', 'termine', ''],
    [5, 'decision_finale', '2024-09-01', '2024-09-01', 'termine', 'Dossier clôturé'],
    // Dossier 6
    [6, 'avis', '2025-01-20', '2025-01-20', 'termine', ''],
    [6, 'notification', '2025-02-15', null, 'en_cours', ''],
  ];

  for (const e of etapesData) {
    db.prepare(`INSERT INTO etapes (dossier_id, type_etape, date_prevue, date_realisation, statut, commentaire)
      VALUES (?,?,?,?,?,?)`).run(...e);
  }

  // Sample alertes
  db.prepare(`INSERT INTO alertes (dossier_id, etape_id, message, type) VALUES (?,?,?,?)`)
    .run(1, 3, 'Le procès-verbal du dossier DCF-2024-001 est en retard de 60 jours', 'critique');
  db.prepare(`INSERT INTO alertes (dossier_id, etape_id, message, type) VALUES (?,?,?,?)`)
    .run(2, 7, 'Le rapport du dossier DCF-2024-002 approche de son échéance', 'avertissement');
  db.prepare(`INSERT INTO alertes (dossier_id, etape_id, message, type) VALUES (?,?,?,?)`)
    .run(6, 15, 'La notification du dossier DCF-2025-001 est en cours', 'info');
}
