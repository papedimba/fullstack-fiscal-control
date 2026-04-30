import { Response } from 'express';
import { AuthRequest } from '../middleware/auth';
import { getDb } from '../db/database';
import { auditAfter } from '../middleware/audit';
import { Dossier } from '../types';

function buildDossierQuery(user: AuthRequest['user']) {
  let where = '1=1';
  const params: any[] = [];
  if (user?.role === 'agent') {
    where += ' AND d.agent_id = ?';
    params.push(user.userId);
  } else if (user?.role === 'chef_brigade') {
    where += ' AND d.brigade_id = ?';
    params.push(user.brigade_id);
  }
  return { where, params };
}

export function listDossiers(req: AuthRequest, res: Response): void {
  const db = getDb();
  const { where, params } = buildDossierQuery(req.user);

  const { statut, type_controle, agent_id, search } = req.query;
  let filter = where;
  if (statut) { filter += ' AND d.statut = ?'; params.push(statut); }
  if (type_controle) { filter += ' AND d.type_controle = ?'; params.push(type_controle); }
  if (agent_id) { filter += ' AND d.agent_id = ?'; params.push(agent_id); }
  if (search) { filter += ' AND (c.nom LIKE ? OR d.numero_dossier LIKE ? OR c.nif LIKE ?)'; const s = `%${search}%`; params.push(s, s, s); }

  const dossiers = db.prepare(`
    SELECT d.*, c.nom as contribuable_nom, c.nif, c.type_entreprise, c.secteur_activite,
           u.nom || ' ' || u.prenom as agent_nom,
           cb.nom || ' ' || cb.prenom as chef_brigade_nom,
           b.nom as brigade_nom,
           (SELECT COUNT(*) FROM etapes e WHERE e.dossier_id = d.id) as nb_etapes,
           (SELECT COUNT(*) FROM etapes e WHERE e.dossier_id = d.id AND e.statut = 'retard') as nb_retards
    FROM dossiers d
    JOIN contribuables c ON d.contribuable_id = c.id
    JOIN users u ON d.agent_id = u.id
    LEFT JOIN users cb ON d.chef_brigade_id = cb.id
    LEFT JOIN brigades b ON d.brigade_id = b.id
    WHERE ${filter}
    ORDER BY d.created_at DESC
  `).all(...params);
  res.json(dossiers);
}

export function getDossier(req: AuthRequest, res: Response): void {
  const db = getDb();
  const { id } = req.params;
  const dossier = db.prepare(`
    SELECT d.*, c.nom as contribuable_nom, c.nif, c.type_entreprise, c.adresse, c.secteur_activite,
           u.nom || ' ' || u.prenom as agent_nom, u.email as agent_email,
           cb.nom || ' ' || cb.prenom as chef_brigade_nom,
           b.nom as brigade_nom
    FROM dossiers d
    JOIN contribuables c ON d.contribuable_id = c.id
    JOIN users u ON d.agent_id = u.id
    LEFT JOIN users cb ON d.chef_brigade_id = cb.id
    LEFT JOIN brigades b ON d.brigade_id = b.id
    WHERE d.id = ?
  `).get(id);
  if (!dossier) { res.status(404).json({ error: 'Dossier non trouvé' }); return; }

  const etapes = db.prepare('SELECT * FROM etapes WHERE dossier_id = ? ORDER BY created_at ASC').all(id);
  const alertes = db.prepare('SELECT * FROM alertes WHERE dossier_id = ? ORDER BY created_at DESC').all(id);
  res.json({ dossier, etapes, alertes });
}

export function createDossier(req: AuthRequest, res: Response): void {
  const db = getDb();
  const { contribuable_id, agent_id, chef_brigade_id, brigade_id, date_ouverture, type_controle, observations } = req.body;
  if (!contribuable_id || !agent_id || !date_ouverture || !type_controle) {
    res.status(400).json({ error: 'Champs obligatoires manquants' });
    return;
  }
  const year = new Date(date_ouverture).getFullYear();
  const last = db.prepare(`SELECT numero_dossier FROM dossiers WHERE numero_dossier LIKE ? ORDER BY id DESC LIMIT 1`)
    .get(`DCF-${year}-%`) as { numero_dossier: string } | undefined;
  let seq = 1;
  if (last) {
    const parts = last.numero_dossier.split('-');
    seq = parseInt(parts[parts.length - 1]) + 1;
  }
  const numero_dossier = `DCF-${year}-${String(seq).padStart(3, '0')}`;

  const result = db.prepare(`
    INSERT INTO dossiers (numero_dossier, contribuable_id, agent_id, chef_brigade_id, brigade_id, date_ouverture, type_controle, observations)
    VALUES (?,?,?,?,?,?,?,?)
  `).run(numero_dossier, contribuable_id, agent_id, chef_brigade_id ?? null, brigade_id ?? null, date_ouverture, type_controle, observations ?? '');

  auditAfter(req.user!.userId, 'CREATE', 'dossiers', result.lastInsertRowid as number, `Création dossier ${numero_dossier}`);
  res.status(201).json({ id: result.lastInsertRowid, numero_dossier });
}

export function updateDossier(req: AuthRequest, res: Response): void {
  const db = getDb();
  const { id } = req.params;
  const d = db.prepare('SELECT * FROM dossiers WHERE id = ?').get(id) as Dossier | undefined;
  if (!d) { res.status(404).json({ error: 'Dossier non trouvé' }); return; }

  const { statut, observations, agent_id, chef_brigade_id, brigade_id, date_cloture } = req.body;
  db.prepare(`UPDATE dossiers SET statut=?, observations=?, agent_id=?, chef_brigade_id=?, brigade_id=?, date_cloture=? WHERE id=?`)
    .run(statut ?? d.statut, observations ?? d.observations, agent_id ?? d.agent_id,
      chef_brigade_id !== undefined ? chef_brigade_id : d.chef_brigade_id,
      brigade_id !== undefined ? brigade_id : d.brigade_id,
      date_cloture !== undefined ? date_cloture : d.date_cloture, id);
  auditAfter(req.user!.userId, 'UPDATE', 'dossiers', Number(id), `Mise à jour dossier`);
  res.json({ success: true });
}

export function deleteDossier(req: AuthRequest, res: Response): void {
  const db = getDb();
  const { id } = req.params;
  db.prepare('DELETE FROM dossiers WHERE id = ?').run(id);
  auditAfter(req.user!.userId, 'DELETE', 'dossiers', Number(id), `Suppression dossier`);
  res.json({ success: true });
}

// Contribuables
export function listContribuables(_req: AuthRequest, res: Response): void {
  const db = getDb();
  const contribuables = db.prepare('SELECT * FROM contribuables ORDER BY nom ASC').all();
  res.json(contribuables);
}

export function createContribuable(req: AuthRequest, res: Response): void {
  const db = getDb();
  const { nom, nif, type_entreprise, adresse, secteur_activite } = req.body;
  if (!nom || !nif || !type_entreprise) { res.status(400).json({ error: 'Champs obligatoires manquants' }); return; }
  const existing = db.prepare('SELECT id FROM contribuables WHERE nif = ?').get(nif);
  if (existing) { res.status(409).json({ error: 'NIF déjà enregistré' }); return; }
  const result = db.prepare('INSERT INTO contribuables (nom, nif, type_entreprise, adresse, secteur_activite) VALUES (?,?,?,?,?)')
    .run(nom, nif, type_entreprise, adresse ?? '', secteur_activite ?? '');
  auditAfter(req.user!.userId, 'CREATE', 'contribuables', result.lastInsertRowid as number, `Création contribuable ${nom}`);
  res.status(201).json({ id: result.lastInsertRowid });
}
