import { Response } from 'express';
import { AuthRequest } from '../middleware/auth';
import { getDb } from '../db/database';
import ExcelJS from 'exceljs';

export async function exportExcel(req: AuthRequest, res: Response): Promise<void> {
  const db = getDb();
  const { periode, annee, mois, trimestre } = req.query;

  let where = '1=1';
  const params: any[] = [];
  if (annee) {
    where += ' AND strftime(\'%Y\', d.date_ouverture) = ?';
    params.push(String(annee));
  }
  if (mois && annee) {
    where += ' AND strftime(\'%m\', d.date_ouverture) = ?';
    params.push(String(mois).padStart(2, '0'));
  }
  if (trimestre && annee) {
    const t = parseInt(String(trimestre));
    const moisDebut = (t - 1) * 3 + 1;
    const moisFin = t * 3;
    where += ` AND CAST(strftime('%m', d.date_ouverture) AS INTEGER) BETWEEN ? AND ?`;
    params.push(moisDebut, moisFin);
  }

  const dossiers = db.prepare(`
    SELECT d.numero_dossier, c.nom as contribuable, c.nif, c.type_entreprise, c.secteur_activite,
           d.type_controle, d.statut, d.date_ouverture, d.date_cloture,
           u.nom || ' ' || u.prenom as agent,
           cb.nom || ' ' || cb.prenom as chef_brigade,
           b.nom as brigade,
           (SELECT COUNT(*) FROM etapes e WHERE e.dossier_id = d.id AND e.statut = 'retard') as retards
    FROM dossiers d
    JOIN contribuables c ON d.contribuable_id = c.id
    JOIN users u ON d.agent_id = u.id
    LEFT JOIN users cb ON d.chef_brigade_id = cb.id
    LEFT JOIN brigades b ON d.brigade_id = b.id
    WHERE ${where}
    ORDER BY d.date_ouverture DESC
  `).all(...params);

  const workbook = new ExcelJS.Workbook();
  workbook.creator = 'Tableau de Bord Fiscal';
  workbook.created = new Date();

  // Feuille dossiers
  const sheet = workbook.addWorksheet('Dossiers');
  sheet.columns = [
    { header: 'N° Dossier', key: 'numero_dossier', width: 18 },
    { header: 'Contribuable', key: 'contribuable', width: 35 },
    { header: 'NIF', key: 'nif', width: 22 },
    { header: 'Type entreprise', key: 'type_entreprise', width: 15 },
    { header: 'Secteur', key: 'secteur_activite', width: 20 },
    { header: 'Type de contrôle', key: 'type_controle', width: 25 },
    { header: 'Statut', key: 'statut', width: 15 },
    { header: 'Date ouverture', key: 'date_ouverture', width: 15 },
    { header: 'Date clôture', key: 'date_cloture', width: 15 },
    { header: 'Agent', key: 'agent', width: 25 },
    { header: 'Chef de brigade', key: 'chef_brigade', width: 25 },
    { header: 'Brigade', key: 'brigade', width: 15 },
    { header: 'Retards', key: 'retards', width: 10 },
  ];

  // Header style
  sheet.getRow(1).eachCell(cell => {
    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1E3A5F' } };
    cell.font = { bold: true, color: { argb: 'FFFFFFFF' }, size: 11 };
    cell.alignment = { vertical: 'middle', horizontal: 'center' };
  });
  sheet.getRow(1).height = 22;

  for (const row of dossiers as any[]) {
    const added = sheet.addRow(row);
    if (row.statut === 'cloture') {
      added.eachCell(c => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD4EDDA' } }; });
    } else if ((row.retards as number) > 0) {
      added.eachCell(c => { c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFFEEBA' } }; });
    }
  }

  // Feuille statistiques
  const statsSheet = workbook.addWorksheet('Statistiques');
  const totalRow = db.prepare(`
    SELECT statut, COUNT(*) as cnt FROM dossiers d WHERE ${where} GROUP BY statut
  `).all(...params) as any[];

  statsSheet.addRow(['Statistiques des dossiers']);
  statsSheet.getRow(1).font = { bold: true, size: 14 };
  statsSheet.addRow([]);
  statsSheet.addRow(['Statut', 'Nombre']);
  for (const r of totalRow) statsSheet.addRow([r.statut, r.cnt]);

  res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  res.setHeader('Content-Disposition', `attachment; filename="rapport_fiscal_${new Date().toISOString().split('T')[0]}.xlsx"`);
  await workbook.xlsx.write(res);
  res.end();
}

export function exportJson(req: AuthRequest, res: Response): void {
  const db = getDb();
  const { annee } = req.query;
  let where = '1=1';
  const params: any[] = [];
  if (annee) { where += ' AND strftime(\'%Y\', d.date_ouverture) = ?'; params.push(String(annee)); }

  const dossiers = db.prepare(`
    SELECT d.*, c.nom as contribuable_nom, c.nif,
           u.nom || ' ' || u.prenom as agent_nom
    FROM dossiers d
    JOIN contribuables c ON d.contribuable_id = c.id
    JOIN users u ON d.agent_id = u.id
    WHERE ${where} ORDER BY d.date_ouverture DESC
  `).all(...params);

  res.json({ generated_at: new Date().toISOString(), total: dossiers.length, dossiers });
}
