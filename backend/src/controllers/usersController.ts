import { Response } from 'express';
import bcrypt from 'bcryptjs';
import { AuthRequest } from '../middleware/auth';
import { getDb } from '../db/database';
import { auditAfter } from '../middleware/audit';
import { User } from '../types';

export function listUsers(_req: AuthRequest, res: Response): void {
  const db = getDb();
  const users = db.prepare(`
    SELECT u.id, u.nom, u.prenom, u.email, u.role, u.brigade_id, u.actif, u.created_at,
           b.nom as brigade_nom
    FROM users u LEFT JOIN brigades b ON u.brigade_id = b.id
    ORDER BY u.created_at DESC
  `).all();
  res.json(users);
}

export async function createUser(req: AuthRequest, res: Response): Promise<void> {
  const { nom, prenom, email, password, role, brigade_id } = req.body;
  if (!nom || !prenom || !email || !password || !role) {
    res.status(400).json({ error: 'Champs obligatoires manquants' });
    return;
  }
  const db = getDb();
  const existing = db.prepare('SELECT id FROM users WHERE email = ?').get(email);
  if (existing) { res.status(409).json({ error: 'Email déjà utilisé' }); return; }
  const hash = await bcrypt.hash(password, 10);
  const result = db.prepare(
    'INSERT INTO users (nom, prenom, email, password_hash, role, brigade_id) VALUES (?,?,?,?,?,?)'
  ).run(nom, prenom, email, hash, role, brigade_id ?? null);
  auditAfter(req.user!.userId, 'CREATE', 'users', result.lastInsertRowid as number, `Création utilisateur ${email}`);
  res.status(201).json({ id: result.lastInsertRowid });
}

export async function updateUser(req: AuthRequest, res: Response): Promise<void> {
  const { id } = req.params;
  const { nom, prenom, email, role, brigade_id, actif, password } = req.body;
  const db = getDb();
  const user = db.prepare('SELECT * FROM users WHERE id = ?').get(id) as User | undefined;
  if (!user) { res.status(404).json({ error: 'Utilisateur non trouvé' }); return; }

  let hash = user.password_hash;
  if (password) hash = await bcrypt.hash(password, 10);

  db.prepare(`UPDATE users SET nom=?, prenom=?, email=?, password_hash=?, role=?, brigade_id=?, actif=? WHERE id=?`)
    .run(nom ?? user.nom, prenom ?? user.prenom, email ?? user.email, hash, role ?? user.role,
      brigade_id !== undefined ? brigade_id : user.brigade_id, actif !== undefined ? actif : user.actif, id);
  auditAfter(req.user!.userId, 'UPDATE', 'users', Number(id), `Mise à jour utilisateur`);
  res.json({ success: true });
}

export function deleteUser(req: AuthRequest, res: Response): void {
  const { id } = req.params;
  const db = getDb();
  db.prepare('UPDATE users SET actif = 0 WHERE id = ?').run(id);
  auditAfter(req.user!.userId, 'DEACTIVATE', 'users', Number(id), `Désactivation utilisateur`);
  res.json({ success: true });
}

export function listBrigades(_req: AuthRequest, res: Response): void {
  const db = getDb();
  const brigades = db.prepare(`
    SELECT b.*, u.nom || ' ' || u.prenom as chef_nom
    FROM brigades b LEFT JOIN users u ON b.chef_id = u.id
  `).all();
  res.json(brigades);
}
