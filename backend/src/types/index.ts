export type Role = 'directeur' | 'sous_directeur' | 'chef_brigade' | 'agent';

export type TypeControle =
  | 'verification_comptabilite'
  | 'verification_ponctuelle'
  | 'droit_communication'
  | 'controle_sur_pieces';

export type StatutDossier =
  | 'ouvert'
  | 'en_cours'
  | 'suspendu'
  | 'cloture'
  | 'contentieux';

export type TypeEtape =
  | 'avis'
  | 'notification'
  | 'pv'
  | 'enquete'
  | 'rapport'
  | 'redressement'
  | 'reponse_contribuable'
  | 'decision_finale';

export type StatutEtape = 'en_attente' | 'en_cours' | 'termine' | 'retard';

export interface User {
  id: number;
  nom: string;
  prenom: string;
  email: string;
  password_hash: string;
  role: Role;
  brigade_id: number | null;
  actif: number;
  created_at: string;
}

export interface Brigade {
  id: number;
  nom: string;
  chef_id: number | null;
}

export interface Contribuable {
  id: number;
  nom: string;
  nif: string;
  type_entreprise: string;
  adresse: string;
  secteur_activite: string;
  created_at: string;
}

export interface Dossier {
  id: number;
  numero_dossier: string;
  contribuable_id: number;
  agent_id: number;
  chef_brigade_id: number | null;
  brigade_id: number | null;
  date_ouverture: string;
  date_cloture: string | null;
  type_controle: TypeControle;
  statut: StatutDossier;
  observations: string;
  created_at: string;
}

export interface Etape {
  id: number;
  dossier_id: number;
  type_etape: TypeEtape;
  date_prevue: string | null;
  date_realisation: string | null;
  statut: StatutEtape;
  commentaire: string;
  created_at: string;
}

export interface Alerte {
  id: number;
  dossier_id: number;
  etape_id: number | null;
  message: string;
  type: 'critique' | 'avertissement' | 'info';
  lu: number;
  created_at: string;
}

export interface AuditLog {
  id: number;
  user_id: number;
  action: string;
  table_name: string;
  record_id: number | null;
  details: string;
  created_at: string;
}

export interface JwtPayload {
  userId: number;
  email: string;
  role: Role;
  brigade_id: number | null;
}
