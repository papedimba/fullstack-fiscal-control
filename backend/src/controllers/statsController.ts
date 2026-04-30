import { Response } from 'express';
import { AuthRequest } from '../middleware/auth';
import { getDb } from '../db/database';

export function getDashboardStats(req: AuthRequest, res: Response): void {
  const db = getDb();
  const user = req.user!;
  let where = '1=1';
  const params: any[] = [];
  if (user.role === 'agent') { where += ' AND d.agent_id = ?'; params.push(user.userId); }
  else if (user.role === 'chef_brigade') { where += ' AND d.brigade_id = ?'; params.push(user.brigade_id); }

  const total = (db.prepare(`SELECT COUNT(*) as cnt FROM dossiers d WHERE ${where}`).get(...params) as any).cnt;
  const enCours = (db.prepare(`SELECT COUNT(*) as cnt FROM dossiers d WHERE ${where} AND d.statut = 'en_cours'`).get(...params) as any).cnt;
  const ouvert = (db.prepare(`SELECT COUNT(*) as cnt FROM dossiers d WHERE ${where} AND d.statut = 'ouvert'`).get(...params) as any).cnt;
  const cloture = (db.prepare(`SELECT COUNT(*) as cnt FROM dossiers d WHERE ${where} AND d.statut = 'cloture'`).get(...params) as any).cnt;
  const suspendu = (db.prepare(`SELECT COUNT(*) as cnt FROM dossiers d WHERE ${where} AND d.statut = 'suspendu'`).get(...params) as any).cnt;
  const contentieux = (db.prepare(`SELECT COUNT(*) as cnt FROM dossiers d WHERE ${where} AND d.statut = 'contentieux'`).get(...params) as any).cnt;

  const alertesNonLues = (db.prepare(`
    SELECT COUNT(*) as cnt FROM alertes a JOIN dossiers d ON a.dossier_id = d.id WHERE a.lu = 0 AND ${where}
  `).get(...params) as any).cnt;

  const retards = (db.prepare(`
    SELECT COUNT(*) as cnt FROM etapes e JOIN dossiers d ON e.dossier_id = d.id WHERE e.statut = 'retard' AND ${where}
  `).get(...params) as any).cnt;

  // Par type de contrôle
  const parType = db.prepare(`
    SELECT type_controle, COUNT(*) as cnt FROM dossiers d WHERE ${where} GROUP BY type_controle
  `).all(...params);

  // Par agent (top 10)
  const parAgent = db.prepare(`
    SELECT u.nom || ' ' || u.prenom as agent, COUNT(d.id) as cnt
    FROM dossiers d JOIN users u ON d.agent_id = u.id
    WHERE ${where}
    GROUP BY d.agent_id ORDER BY cnt DESC LIMIT 10
  `).all(...params);

  // Par mois (12 derniers mois)
  const parMois = db.prepare(`
    SELECT strftime('%Y-%m', d.date_ouverture) as mois, COUNT(*) as cnt
    FROM dossiers d WHERE ${where}
    AND d.date_ouverture >= date('now','-12 months')
    GROUP BY mois ORDER BY mois ASC
  `).all(...params);

  // Taux de retard par étape
  const retardsParEtape = db.prepare(`
    SELECT e.type_etape, COUNT(*) as cnt
    FROM etapes e JOIN dossiers d ON e.dossier_id = d.id
    WHERE e.statut = 'retard' AND ${where}
    GROUP BY e.type_etape ORDER BY cnt DESC
  `).all(...params);

  // Progression annuelle
  const progression = db.prepare(`
    SELECT strftime('%Y', d.date_ouverture) as annee,
           SUM(CASE WHEN d.statut='cloture' THEN 1 ELSE 0 END) as clotures,
           SUM(CASE WHEN d.statut NOT IN ('cloture') THEN 1 ELSE 0 END) as en_cours,
           COUNT(*) as total
    FROM dossiers d WHERE ${where}
    GROUP BY annee ORDER BY annee DESC LIMIT 5
  `).all(...params);

  res.json({
    totaux: { total, enCours, ouvert, cloture, suspendu, contentieux, alertesNonLues, retards },
    parType,
    parAgent,
    parMois,
    retardsParEtape,
    progression,
  });
}

export function getAuditLogs(req: AuthRequest, res: Response): void {
  const db = getDb();
  const logs = db.prepare(`
    SELECT al.*, u.nom || ' ' || u.prenom as utilisateur
    FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC LIMIT 200
  `).all();
  res.json(logs);
}
