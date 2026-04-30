import { Response } from 'express';
import { AuthRequest } from '../middleware/auth';
import { getDb } from '../db/database';
import { auditAfter } from '../middleware/audit';
import { Etape } from '../types';

export function listEtapes(req: AuthRequest, res: Response): void {
  const db = getDb();
  const { dossier_id } = req.params;
  const etapes = db.prepare('SELECT * FROM etapes WHERE dossier_id = ? ORDER BY created_at ASC').all(dossier_id);
  res.json(etapes);
}

export function createEtape(req: AuthRequest, res: Response): void {
  const db = getDb();
  const { dossier_id } = req.params;
  const { type_etape, date_prevue, commentaire } = req.body;
  if (!type_etape) { res.status(400).json({ error: 'Type d\'étape requis' }); return; }

  const result = db.prepare(
    'INSERT INTO etapes (dossier_id, type_etape, date_prevue, commentaire) VALUES (?,?,?,?)'
  ).run(dossier_id, type_etape, date_prevue ?? null, commentaire ?? '');

  // Update dossier status if still 'ouvert'
  const dossier = db.prepare('SELECT statut FROM dossiers WHERE id = ?').get(dossier_id) as { statut: string } | undefined;
  if (dossier?.statut === 'ouvert') {
    db.prepare('UPDATE dossiers SET statut = ? WHERE id = ?').run('en_cours', dossier_id);
  }

  auditAfter(req.user!.userId, 'CREATE', 'etapes', result.lastInsertRowid as number, `Ajout étape ${type_etape}`);
  res.status(201).json({ id: result.lastInsertRowid });
}

export function updateEtape(req: AuthRequest, res: Response): void {
  const db = getDb();
  const { id } = req.params;
  const etape = db.prepare('SELECT * FROM etapes WHERE id = ?').get(id) as Etape | undefined;
  if (!etape) { res.status(404).json({ error: 'Étape non trouvée' }); return; }

  const { statut, date_prevue, date_realisation, commentaire } = req.body;

  // Auto-check if date_prevue passed and not done → retard
  let finalStatut = statut ?? etape.statut;
  if (date_prevue && !date_realisation && finalStatut !== 'termine') {
    const prevue = new Date(date_prevue);
    if (prevue < new Date()) finalStatut = 'retard';
  }

  db.prepare(`UPDATE etapes SET statut=?, date_prevue=?, date_realisation=?, commentaire=? WHERE id=?`)
    .run(finalStatut, date_prevue ?? etape.date_prevue, date_realisation ?? etape.date_realisation,
      commentaire ?? etape.commentaire, id);

  // Create alert if retard
  if (finalStatut === 'retard' && etape.statut !== 'retard') {
    const dossier = db.prepare('SELECT numero_dossier FROM dossiers WHERE id = ?').get(etape.dossier_id) as { numero_dossier: string };
    db.prepare('INSERT INTO alertes (dossier_id, etape_id, message, type) VALUES (?,?,?,?)')
      .run(etape.dossier_id, id, `Retard détecté sur l'étape "${type_etape_label(etape.type_etape)}" du dossier ${dossier.numero_dossier}`, 'critique');
  }

  auditAfter(req.user!.userId, 'UPDATE', 'etapes', Number(id), `Mise à jour étape ${etape.type_etape}`);
  res.json({ success: true });
}

export function deleteEtape(req: AuthRequest, res: Response): void {
  const db = getDb();
  const { id } = req.params;
  db.prepare('DELETE FROM etapes WHERE id = ?').run(id);
  res.json({ success: true });
}

function type_etape_label(type: string): string {
  const labels: Record<string, string> = {
    avis: 'Avis de vérification',
    notification: 'Notification',
    pv: 'Procès-verbal',
    enquete: 'Enquête',
    rapport: 'Rapport',
    redressement: 'Notification de redressement',
    reponse_contribuable: 'Réponse du contribuable',
    decision_finale: 'Décision finale',
  };
  return labels[type] ?? type;
}

// Refresh all etapes statuts to detect new retards
export function refreshRetards(req: AuthRequest, res: Response): void {
  const db = getDb();
  const today = new Date().toISOString().split('T')[0];
  const retards = db.prepare(`
    SELECT e.*, d.numero_dossier FROM etapes e
    JOIN dossiers d ON e.dossier_id = d.id
    WHERE e.date_prevue IS NOT NULL AND e.date_prevue < ? AND e.statut NOT IN ('termine','retard')
      AND d.statut NOT IN ('cloture')
  `).all(today) as (Etape & { numero_dossier: string })[];

  let count = 0;
  for (const e of retards) {
    db.prepare('UPDATE etapes SET statut = ? WHERE id = ?').run('retard', e.id);
    const existing = db.prepare('SELECT id FROM alertes WHERE etape_id = ? AND type = ?').get(e.id, 'critique');
    if (!existing) {
      db.prepare('INSERT INTO alertes (dossier_id, etape_id, message, type) VALUES (?,?,?,?)')
        .run(e.dossier_id, e.id, `Retard détecté sur l'étape "${type_etape_label(e.type_etape)}" du dossier ${e.numero_dossier}`, 'critique');
    }
    count++;
  }
  res.json({ updated: count });
}
