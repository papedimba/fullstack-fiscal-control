import { Router } from 'express';
import { login, getProfile } from '../controllers/authController';
import { listUsers, createUser, updateUser, deleteUser, listBrigades } from '../controllers/usersController';
import { listDossiers, getDossier, createDossier, updateDossier, deleteDossier, listContribuables, createContribuable } from '../controllers/dossiersController';
import { listEtapes, createEtape, updateEtape, deleteEtape, refreshRetards } from '../controllers/etapesController';
import { listAlertes, markRead, markAllRead, countUnread } from '../controllers/alertesController';
import { getDashboardStats, getAuditLogs } from '../controllers/statsController';
import { exportExcel, exportJson } from '../controllers/exportController';
import { authenticate, requireRoles } from '../middleware/auth';

const router = Router();

// Auth
router.post('/auth/login', login);
router.get('/auth/me', authenticate, getProfile);

// Users (directeur only for write)
router.get('/users', authenticate, requireRoles('directeur', 'sous_directeur'), listUsers);
router.post('/users', authenticate, requireRoles('directeur'), createUser);
router.put('/users/:id', authenticate, requireRoles('directeur'), updateUser);
router.delete('/users/:id', authenticate, requireRoles('directeur'), deleteUser);
router.get('/brigades', authenticate, listBrigades);

// Contribuables
router.get('/contribuables', authenticate, listContribuables);
router.post('/contribuables', authenticate, requireRoles('directeur', 'sous_directeur', 'chef_brigade'), createContribuable);

// Dossiers
router.get('/dossiers', authenticate, listDossiers);
router.post('/dossiers', authenticate, requireRoles('directeur', 'sous_directeur', 'chef_brigade'), createDossier);
router.get('/dossiers/:id', authenticate, getDossier);
router.put('/dossiers/:id', authenticate, updateDossier);
router.delete('/dossiers/:id', authenticate, requireRoles('directeur', 'sous_directeur'), deleteDossier);

// Etapes
router.get('/dossiers/:dossier_id/etapes', authenticate, listEtapes);
router.post('/dossiers/:dossier_id/etapes', authenticate, createEtape);
router.put('/etapes/:id', authenticate, updateEtape);
router.delete('/etapes/:id', authenticate, requireRoles('directeur', 'sous_directeur', 'chef_brigade'), deleteEtape);
router.post('/etapes/refresh-retards', authenticate, requireRoles('directeur', 'sous_directeur'), refreshRetards);

// Alertes
router.get('/alertes', authenticate, listAlertes);
router.get('/alertes/count', authenticate, countUnread);
router.put('/alertes/:id/read', authenticate, markRead);
router.put('/alertes/mark-all-read', authenticate, markAllRead);

// Stats
router.get('/stats/dashboard', authenticate, getDashboardStats);
router.get('/stats/audit', authenticate, requireRoles('directeur', 'sous_directeur'), getAuditLogs);

// Export
router.get('/export/excel', authenticate, exportExcel);
router.get('/export/json', authenticate, exportJson);

export default router;
