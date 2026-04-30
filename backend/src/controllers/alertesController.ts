import { Response } from 'express';
import { AuthRequest } from '../middleware/auth';
import { getDb } from '../db/database';

export function listAlertes(req: AuthRequest, res: Response): void {
  const db = getDb();
  const user = req.user!;

  let where = '1=1';
  const params: any[] = [];

  if (user.role === 'agent') {
    where += ' AND d.agent_id = ?';
    params.push(user.userId);
  } else if (user.role === 'chef_brigade') {
    where += ' AND d.brigade_id = ?';
    params.push(user.brigade_id);
  }

  const { type, lu } = req.query;
  if (type) { where += ' AND a.type = ?'; params.push(type); }
  if (lu !== undefined) { where += ' AND a.lu = ?'; params.push(lu === 'true' ? 1 : 0); }

  const alertes = db.prepare(`
    SELECT a.*, d.numero_dossier, c.nom as contribuable_nom
    FROM alertes a
    JOIN dossiers d ON a.dossier_id = d.id
    JOIN contribuables c ON d.contribuable_id = c.id
    WHERE ${where}
    ORDER BY a.created_at DESC
    LIMIT 100
  `).all(...params);

  res.json(alertes);
}

export function markRead(req: AuthRequest, res: Response): void {
  const db = getDb();
  const { id } = req.params;
  db.prepare('UPDATE alertes SET lu = 1 WHERE id = ?').run(id);
  res.json({ success: true });
}

export function markAllRead(req: AuthRequest, res: Response): void {
  const db = getDb();
  const user = req.user!;
  let where = '1=1';
  const params: any[] = [];
  if (user.role === 'agent') {
    where = 'dossier_id IN (SELECT id FROM dossiers WHERE agent_id = ?)';
    params.push(user.userId);
  } else if (user.role === 'chef_brigade') {
    where = 'dossier_id IN (SELECT id FROM dossiers WHERE brigade_id = ?)';
    params.push(user.brigade_id);
  }
  db.prepare(`UPDATE alertes SET lu = 1 WHERE ${where}`).run(...params);
  res.json({ success: true });
}

export function countUnread(req: AuthRequest, res: Response): void {
  const db = getDb();
  const user = req.user!;
  let where = 'a.lu = 0';
  const params: any[] = [];
  if (user.role === 'agent') {
    where += ' AND d.agent_id = ?';
    params.push(user.userId);
  } else if (user.role === 'chef_brigade') {
    where += ' AND d.brigade_id = ?';
    params.push(user.brigade_id);
  }
  const result = db.prepare(`
    SELECT COUNT(*) as cnt FROM alertes a JOIN dossiers d ON a.dossier_id = d.id WHERE ${where}
  `).get(...params) as { cnt: number };
  res.json({ count: result.cnt });
}
