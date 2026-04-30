export type Role = 'directeur' | 'sous_directeur' | 'chef_brigade' | 'agent';
export type TypeControle = 'verification_comptabilite' | 'verification_ponctuelle' | 'droit_communication' | 'controle_sur_pieces';
export type StatutDossier = 'ouvert' | 'en_cours' | 'suspendu' | 'cloture' | 'contentieux';
export type TypeEtape = 'avis' | 'notification' | 'pv' | 'enquete' | 'rapport' | 'redressement' | 'reponse_contribuable' | 'decision_finale';
export type StatutEtape = 'en_attente' | 'en_cours' | 'termine' | 'retard';

export interface User {
  id: number;
  nom: string;
  prenom: string;
  email: string;
  role: Role;
  brigade_id: number | null;
  brigade_nom?: string;
  actif?: number;
  created_at?: string;
}

export interface Contribuable {
  id: number;
  nom: string;
  nif: string;
  type_entreprise: string;
  adresse: string;
  secteur_activite: string;
}

export interface Dossier {
  id: number;
  numero_dossier: string;
  contribuable_id: number;
  contribuable_nom: string;
  nif: string;
  type_entreprise: string;
  secteur_activite: string;
  agent_id: number;
  agent_nom: string;
  chef_brigade_nom?: string;
  brigade_nom?: string;
  date_ouverture: string;
  date_cloture?: string;
  type_controle: TypeControle;
  statut: StatutDossier;
  observations: string;
  nb_etapes?: number;
  nb_retards?: number;
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
  etape_id?: number;
  message: string;
  type: 'critique' | 'avertissement' | 'info';
  lu: number;
  numero_dossier?: string;
  contribuable_nom?: string;
  created_at: string;
}

export interface DashboardStats {
  totaux: {
    total: number;
    enCours: number;
    ouvert: number;
    cloture: number;
    suspendu: number;
    contentieux: number;
    alertesNonLues: number;
    retards: number;
  };
  parType: { type_controle: string; cnt: number }[];
  parAgent: { agent: string; cnt: number }[];
  parMois: { mois: string; cnt: number }[];
  retardsParEtape: { type_etape: string; cnt: number }[];
  progression: { annee: string; clotures: number; en_cours: number; total: number }[];
}
