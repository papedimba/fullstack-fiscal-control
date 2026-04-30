import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { Alerte } from '../types';
import { AlerteTypeBadge } from '../components/StatusBadge';
import api from '../services/api';
import { Bell, BellOff, CheckCheck, AlertTriangle, Info } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';
import { fr } from 'date-fns/locale';

export default function AlertesPage() {
  const [filterType, setFilterType] = useState('');
  const [filterLu, setFilterLu] = useState('');
  const [refreshKey, setRefreshKey] = useState(0);

  const query = new URLSearchParams();
  if (filterType) query.set('type', filterType);
  if (filterLu !== '') query.set('lu', filterLu);

  const { data: alertes, loading, refetch } = useApi<Alerte[]>(`/alertes?${query}`, [filterType, filterLu, refreshKey]);

  const markRead = async (id: number) => {
    await api.put(`/alertes/${id}/read`);
    refetch();
  };

  const markAllRead = async () => {
    await api.put('/alertes/mark-all-read');
    refetch();
    setRefreshKey(k => k + 1);
  };

  const unread = alertes?.filter(a => !a.lu).length ?? 0;
  const critiques = alertes?.filter(a => a.type === 'critique').length ?? 0;

  const iconMap = { critique: AlertTriangle, avertissement: Bell, info: Info };

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Alertes & Délais</h1>
          <p className="text-gray-500 text-sm">{unread} non lue(s) · {critiques} critique(s)</p>
        </div>
        {unread > 0 && (
          <button onClick={markAllRead} className="btn-secondary flex items-center gap-2 text-sm">
            <CheckCheck className="h-4 w-4" /> Tout marquer comme lu
          </button>
        )}
      </div>

      {/* Filters */}
      <div className="card p-4 flex flex-wrap gap-3">
        <select className="input w-auto" value={filterType} onChange={e => setFilterType(e.target.value)}>
          <option value="">Tous les types</option>
          <option value="critique">Critique</option>
          <option value="avertissement">Avertissement</option>
          <option value="info">Info</option>
        </select>
        <select className="input w-auto" value={filterLu} onChange={e => setFilterLu(e.target.value)}>
          <option value="">Toutes</option>
          <option value="false">Non lues</option>
          <option value="true">Lues</option>
        </select>
      </div>

      {loading ? (
        <div className="flex items-center justify-center h-48">
          <div className="animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-800" />
        </div>
      ) : !alertes?.length ? (
        <div className="text-center text-gray-400 py-16">
          <BellOff className="h-12 w-12 mx-auto mb-3 opacity-30" />
          <p>Aucune alerte</p>
        </div>
      ) : (
        <div className="space-y-2">
          {alertes.map(alerte => {
            const IconComp = iconMap[alerte.type] || Bell;
            return (
              <div key={alerte.id}
                className={`card p-4 flex items-start gap-4 transition-colors ${!alerte.lu ? 'bg-white border-l-4 ' + (alerte.type === 'critique' ? 'border-red-400' : alerte.type === 'avertissement' ? 'border-amber-400' : 'border-blue-400') : 'opacity-70'}`}
              >
                <div className={`p-2 rounded-lg flex-shrink-0 ${alerte.type === 'critique' ? 'bg-red-50' : alerte.type === 'avertissement' ? 'bg-amber-50' : 'bg-blue-50'}`}>
                  <IconComp className={`h-5 w-5 ${alerte.type === 'critique' ? 'text-red-600' : alerte.type === 'avertissement' ? 'text-amber-600' : 'text-blue-600'}`} />
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <AlerteTypeBadge type={alerte.type} />
                    {!alerte.lu && <span className="w-2 h-2 bg-primary-600 rounded-full flex-shrink-0" />}
                  </div>
                  <p className="text-sm text-gray-800 mt-1">{alerte.message}</p>
                  <div className="flex items-center gap-3 mt-1 text-xs text-gray-500">
                    {alerte.numero_dossier && (
                      <Link to={`/dossiers/${alerte.dossier_id}`} className="text-primary-700 hover:underline font-mono">
                        {alerte.numero_dossier}
                      </Link>
                    )}
                    {alerte.contribuable_nom && <span>{alerte.contribuable_nom}</span>}
                    <span>{formatDistanceToNow(new Date(alerte.created_at), { addSuffix: true, locale: fr })}</span>
                  </div>
                </div>
                {!alerte.lu && (
                  <button onClick={() => markRead(alerte.id)}
                    className="flex-shrink-0 text-xs text-gray-400 hover:text-gray-600 flex items-center gap-1 py-1 px-2 rounded hover:bg-gray-100">
                    <CheckCheck className="h-3.5 w-3.5" /> Lu
                  </button>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
