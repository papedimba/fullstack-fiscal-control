import React from 'react';

const STATUT_DOSSIER: Record<string, { label: string; className: string }> = {
  ouvert:      { label: 'Ouvert',       className: 'bg-blue-100 text-blue-800' },
  en_cours:    { label: 'En cours',     className: 'bg-amber-100 text-amber-800' },
  suspendu:    { label: 'Suspendu',     className: 'bg-gray-100 text-gray-700' },
  cloture:     { label: 'Clôturé',      className: 'bg-green-100 text-green-800' },
  contentieux: { label: 'Contentieux',  className: 'bg-red-100 text-red-800' },
};

const STATUT_ETAPE: Record<string, { label: string; className: string }> = {
  en_attente: { label: 'En attente', className: 'bg-gray-100 text-gray-600' },
  en_cours:   { label: 'En cours',   className: 'bg-blue-100 text-blue-700' },
  termine:    { label: 'Terminé',    className: 'bg-green-100 text-green-700' },
  retard:     { label: 'Retard',     className: 'bg-red-100 text-red-700' },
};

const TYPE_ETAPE: Record<string, string> = {
  avis:                 'Avis de vérification',
  notification:         'Notification',
  pv:                   'Procès-verbal',
  enquete:              'Enquête',
  rapport:              'Rapport',
  redressement:         'Notification de redressement',
  reponse_contribuable: 'Réponse du contribuable',
  decision_finale:      'Décision finale',
};

const TYPE_CONTROLE: Record<string, string> = {
  verification_comptabilite: 'Vérification comptabilité',
  verification_ponctuelle:   'Vérification ponctuelle',
  droit_communication:       'Droit de communication',
  controle_sur_pieces:       'Contrôle sur pièces',
};

const ALERTE_TYPE: Record<string, { label: string; className: string }> = {
  critique:      { label: 'Critique',     className: 'bg-red-100 text-red-700' },
  avertissement: { label: 'Avertissement', className: 'bg-amber-100 text-amber-700' },
  info:          { label: 'Info',          className: 'bg-blue-100 text-blue-700' },
};

export function StatutDossierBadge({ statut }: { statut: string }) {
  const cfg = STATUT_DOSSIER[statut] || { label: statut, className: 'bg-gray-100 text-gray-600' };
  return <span className={`badge ${cfg.className}`}>{cfg.label}</span>;
}

export function StatutEtapeBadge({ statut }: { statut: string }) {
  const cfg = STATUT_ETAPE[statut] || { label: statut, className: 'bg-gray-100 text-gray-600' };
  return <span className={`badge ${cfg.className}`}>{cfg.label}</span>;
}

export function TypeEtapeLabel({ type }: { type: string }) {
  return <span>{TYPE_ETAPE[type] || type}</span>;
}

export function TypeControleLabel({ type }: { type: string }) {
  return <span>{TYPE_CONTROLE[type] || type}</span>;
}

export function AlerteTypeBadge({ type }: { type: string }) {
  const cfg = ALERTE_TYPE[type] || { label: type, className: 'bg-gray-100 text-gray-600' };
  return <span className={`badge ${cfg.className}`}>{cfg.label}</span>;
}

export { TYPE_ETAPE, TYPE_CONTROLE };
