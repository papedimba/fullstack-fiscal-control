import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { Dossier } from '../types';
import { StatutDossierBadge, StatutEtapeBadge, TypeEtapeLabel } from '../components/StatusBadge';
import { GitBranch, AlertTriangle, CheckCircle, Clock } from 'lucide-react';

interface DossierWithEtapes extends Dossier {
  etapes: any[];
}

export default function ProceduresPage() {
  const [filter, setFilter] = useState<'tous' | 'retard' | 'en_cours'>('tous');
  const { data: dossiers, loading } = useApi<Dossier[]>('/dossiers');

  const filtered = dossiers?.filter(d => {
    if (filter === 'retard') return (d.nb_retards ?? 0) > 0;
    if (filter === 'en_cours') return d.statut === 'en_cours';
    return true;
  }) ?? [];

  const totalRetards = dossiers?.reduce((s, d) => s + (d.nb_retards ?? 0), 0) ?? 0;
  const enCours = dossiers?.filter(d => d.statut === 'en_cours').length ?? 0;
  const termines = dossiers?.filter(d => d.statut === 'cloture').length ?? 0;

  return (
    <div className="space-y-5">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Suivi des Procédures</h1>
        <p className="text-gray-500 text-sm">Progression des étapes par dossier</p>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-3 gap-4">
        <div className="card p-4 text-center cursor-pointer hover:border-amber-300 border-2 border-transparent transition-colors" onClick={() => setFilter('en_cours')}>
          <Clock className="h-6 w-6 text-amber-600 mx-auto mb-1" />
          <p className="text-2xl font-bold text-gray-900">{enCours}</p>
          <p className="text-xs text-gray-500">En cours</p>
        </div>
        <div className="card p-4 text-center cursor-pointer hover:border-red-300 border-2 border-transparent transition-colors" onClick={() => setFilter('retard')}>
          <AlertTriangle className="h-6 w-6 text-red-600 mx-auto mb-1" />
          <p className="text-2xl font-bold text-gray-900">{totalRetards}</p>
          <p className="text-xs text-gray-500">Étapes en retard</p>
        </div>
        <div className="card p-4 text-center cursor-pointer hover:border-green-300 border-2 border-transparent transition-colors" onClick={() => setFilter('tous')}>
          <CheckCircle className="h-6 w-6 text-green-600 mx-auto mb-1" />
          <p className="text-2xl font-bold text-gray-900">{termines}</p>
          <p className="text-xs text-gray-500">Clôturés</p>
        </div>
      </div>

      {/* Filter tabs */}
      <div className="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
        {([['tous', 'Tous'], ['en_cours', 'En cours'], ['retard', 'Avec retards']] as const).map(([val, label]) => (
          <button key={val} onClick={() => setFilter(val)}
            className={`px-4 py-1.5 rounded-md text-sm font-medium transition-colors ${filter === val ? 'bg-white shadow text-gray-900' : 'text-gray-600 hover:text-gray-900'}`}>
            {label}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-48">
          <div className="animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-800" />
        </div>
      ) : filtered.length === 0 ? (
        <div className="text-center text-gray-400 py-16">
          <GitBranch className="h-12 w-12 mx-auto mb-3 opacity-30" />
          <p>Aucun dossier dans cette catégorie</p>
        </div>
      ) : (
        <div className="space-y-4">
          {filtered.map(dossier => (
            <DossierProcedureCard key={dossier.id} dossier={dossier} />
          ))}
        </div>
      )}
    </div>
  );
}

function DossierProcedureCard({ dossier }: { dossier: Dossier }) {
  const { data } = useApi<{ dossier: Dossier; etapes: any[] }>(`/dossiers/${dossier.id}`);
  const etapes = data?.etapes ?? [];
  const hasRetard = (dossier.nb_retards ?? 0) > 0;

  const progressPct = etapes.length
    ? Math.round((etapes.filter(e => e.statut === 'termine').length / etapes.length) * 100)
    : 0;

  return (
    <div className={`card border-l-4 ${hasRetard ? 'border-red-400' : dossier.statut === 'cloture' ? 'border-green-400' : 'border-primary-400'}`}>
      <div className="flex items-start justify-between gap-4 mb-4">
        <div>
          <div className="flex items-center gap-2 flex-wrap">
            <Link to={`/dossiers/${dossier.id}`} className="font-mono font-bold text-primary-700 hover:underline">
              {dossier.numero_dossier}
            </Link>
            <StatutDossierBadge statut={dossier.statut} />
            {hasRetard && (
              <span className="flex items-center gap-1 text-red-600 text-xs font-medium badge bg-red-50">
                <AlertTriangle className="h-3 w-3" /> {dossier.nb_retards} retard(s)
              </span>
            )}
          </div>
          <p className="text-sm text-gray-600 mt-0.5">{dossier.contribuable_nom} — <span className="text-gray-400">{dossier.agent_nom}</span></p>
        </div>
        <div className="text-right">
          <div className="text-sm font-bold text-gray-900">{progressPct}%</div>
          <div className="text-xs text-gray-500">{etapes.filter(e => e.statut === 'termine').length}/{etapes.length} étapes</div>
        </div>
      </div>

      {/* Progress bar */}
      <div className="h-1.5 bg-gray-100 rounded-full overflow-hidden mb-3">
        <div className="h-full bg-primary-500 rounded-full transition-all" style={{ width: `${progressPct}%` }} />
      </div>

      {/* Steps */}
      {etapes.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {etapes.map(e => (
            <div key={e.id} className="flex items-center gap-1">
              <StatutEtapeBadge statut={e.statut} />
              <span className="text-xs text-gray-500"><TypeEtapeLabel type={e.type_etape} /></span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
