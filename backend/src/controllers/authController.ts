import { Request, Response } from 'express';
import bcrypt from 'bcryptjs';
import { getDb } from '../db/database';
import { generateToken } from '../middleware/auth';
import { auditAfter } from '../middleware/audit';
import { User } from '../types';

export async function login(req: Request, res: Response): Promise<void> {
  const { email, password } = req.body;
  if (!email || !password) {
    res.status(400).json({ error: 'Email et mot de passe requis' });
    return;
  }
  const db = getDb();
  const user = db.prepare('SELECT * FROM users WHERE email = ? AND actif = 1').get(email) as User | undefined;
  if (!user) {
    res.status(401).json({ error: 'Identifiants invalides' });
    return;
  }
  const valid = await bcrypt.compare(password, user.password_hash);
  if (!valid) {
    res.status(401).json({ error: 'Identifiants invalides' });
    return;
  }
  const token = generateToken({ userId: user.id, email: user.email, role: user.role, brigade_id: user.brigade_id });
  auditAfter(user.id, 'LOGIN', 'users', user.id, `Connexion de ${user.email}`);
  res.json({
    token,
    user: { id: user.id, nom: user.nom, prenom: user.prenom, email: user.email, role: user.role, brigade_id: user.brigade_id }
  });
}

export function getProfile(req: Request, res: Response): void {
  const authReq = req as any;
  const db = getDb();
  const user = db.prepare('SELECT id,nom,prenom,email,role,brigade_id,created_at FROM users WHERE id = ?').get(authReq.user.userId) as User | undefined;
  if (!user) { res.status(404).json({ error: 'Utilisateur non trouvé' }); return; }
  res.json(user);
}
