import React, { useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { Dossier, Contribuable, User } from '../types';
import { StatutDossierBadge, TypeControleLabel, TYPE_CONTROLE } from '../components/StatusBadge';
import { useAuth } from '../contexts/AuthContext';
import api from '../services/api';
import { Plus, Search, Filter, FolderOpen, AlertTriangle, X } from 'lucide-react';

export default function DossiersPage() {
  const { user } = useAuth();
  const [search, setSearch] = useState('');
  const [statut, setStatut] = useState('');
  const [typeControle, setTypeControle] = useState('');
  const [showForm, setShowForm] = useState(false);
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const query = new URLSearchParams();
  if (search) query.set('search', search);
  if (statut) query.set('statut', statut);
  if (typeControle) query.set('type_controle', typeControle);

  const { data: dossiers, loading, refetch } = useApi<Dossier[]>(`/dossiers?${query}`, [search, statut, typeControle]);
  const { data: contribuables } = useApi<Contribuable[]>('/contribuables');
  const { data: agents } = useApi<User[]>('/users');

  const canCreate = user?.role !== 'agent' || false;

  const [form, setForm] = useState({
    contribuable_id: '', agent_id: String(user?.id || ''),
    date_ouverture: new Date().toISOString().split('T')[0],
    type_controle: 'verification_comptabilite', observations: '',
    new_contribuable_nom: '', new_contribuable_nif: '', new_contribuable_type: 'SA',
    new_contribuable_secteur: '', add_new: false,
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      let contrib_id = parseInt(form.contribuable_id);
      if (form.add_new) {
        const r = await api.post('/contribuables', {
          nom: form.new_contribuable_nom, nif: form.new_contribuable_nif,
          type_entreprise: form.new_contribuable_type, secteur_activite: form.new_contribuable_secteur,
        });
        contrib_id = r.data.id;
      }
      await api.post('/dossiers', {
        contribuable_id: contrib_id, agent_id: parseInt(form.agent_id),
        date_ouverture: form.date_ouverture, type_controle: form.type_controle, observations: form.observations,
      });
      setShowForm(false);
      refetch();
    } catch (err: any) {
      setFormError(err.response?.data?.error || 'Erreur lors de la création');
    } finally {
      setSubmitting(false);
    }
  };

  const canManage = user?.role === 'directeur' || user?.role === 'sous_directeur' || user?.role === 'chef_brigade';

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Dossiers de Contrôle</h1>
          <p className="text-gray-500 text-sm">{dossiers?.length ?? 0} dossier(s)</p>
        </div>
        {canManage && (
          <button onClick={() => setShowForm(true)} className="btn-primary flex items-center gap-2">
            <Plus className="h-4 w-4" /> Nouveau dossier
          </button>
        )}
      </div>

      {/* Filters */}
      <div className="card p-4">
        <div className="flex flex-wrap gap-3">
          <div className="relative flex-1 min-w-[200px]">
            <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
            <input className="input pl-9" placeholder="Rechercher (nom, NIF, n° dossier)..."
              value={search} onChange={e => setSearch(e.target.value)} />
          </div>
          <select className="input w-auto" value={statut} onChange={e => setStatut(e.target.value)}>
            <option value="">Tous les statuts</option>
            <option value="ouvert">Ouvert</option>
            <option value="en_cours">En cours</option>
            <option value="suspendu">Suspendu</option>
            <option value="cloture">Clôturé</option>
            <option value="contentieux">Contentieux</option>
          </select>
          <select className="input w-auto" value={typeControle} onChange={e => setTypeControle(e.target.value)}>
            <option value="">Tous les types</option>
            {Object.entries(TYPE_CONTROLE).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
          {(search || statut || typeControle) && (
            <button onClick={() => { setSearch(''); setStatut(''); setTypeControle(''); }}
              className="btn-secondary flex items-center gap-1 text-sm px-3">
              <X className="h-3 w-3" /> Effacer
            </button>
          )}
        </div>
      </div>

      {/* Table */}
      <div className="card p-0 overflow-hidden">
        {loading ? (
          <div className="flex items-center justify-center h-48">
            <div className="animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-800" />
          </div>
        ) : !dossiers?.length ? (
          <div className="text-center py-16 text-gray-400">
            <FolderOpen className="h-12 w-12 mx-auto mb-3 opacity-30" />
            <p>Aucun dossier trouvé</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 border-b border-gray-200">
                <tr>
                  {['N° Dossier', 'Contribuable', 'Type de contrôle', 'Agent', 'Date ouverture', 'Statut', 'Retards', ''].map(h => (
                    <th key={h} className="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wide">{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {dossiers.map(d => (
                  <tr key={d.id} className="hover:bg-gray-50 transition-colors">
                    <td className="px-4 py-3">
                      <Link to={`/dossiers/${d.id}`} className="font-mono font-semibold text-primary-700 hover:text-primary-900">
                        {d.numero_dossier}
                      </Link>
                    </td>
                    <td className="px-4 py-3">
                      <div className="font-medium text-gray-900 max-w-[200px] truncate">{d.contribuable_nom}</div>
                      <div className="text-xs text-gray-500">{d.nif}</div>
                    </td>
                    <td className="px-4 py-3 text-gray-600"><TypeControleLabel type={d.type_controle} /></td>
                    <td className="px-4 py-3 text-gray-700">{d.agent_nom}</td>
                    <td className="px-4 py-3 text-gray-600">{new Date(d.date_ouverture).toLocaleDateString('fr-FR')}</td>
                    <td className="px-4 py-3"><StatutDossierBadge statut={d.statut} /></td>
                    <td className="px-4 py-3">
                      {(d.nb_retards ?? 0) > 0 && (
                        <span className="flex items-center gap-1 text-red-600 text-xs font-medium">
                          <AlertTriangle className="h-3.5 w-3.5" />{d.nb_retards}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <Link to={`/dossiers/${d.id}`} className="text-primary-700 hover:text-primary-900 text-xs font-medium">
                        Voir →
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Modal création */}
      {showForm && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between p-6 border-b">
              <h2 className="text-lg font-semibold">Nouveau dossier de contrôle</h2>
              <button onClick={() => setShowForm(false)} className="text-gray-400 hover:text-gray-600"><X className="h-5 w-5" /></button>
            </div>
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              <div>
                <label className="label">Type de contrôle *</label>
                <select className="input" value={form.type_controle} onChange={e => setForm(f => ({ ...f, type_controle: e.target.value }))}>
                  {Object.entries(TYPE_CONTROLE).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </select>
              </div>

              <div className="flex items-center gap-2">
                <input type="checkbox" id="add_new" checked={form.add_new} onChange={e => setForm(f => ({ ...f, add_new: e.target.checked }))} className="rounded" />
                <label htmlFor="add_new" className="text-sm text-gray-700">Créer un nouveau contribuable</label>
              </div>

              {form.add_new ? (
                <div className="border border-gray-200 rounded-lg p-4 space-y-3 bg-gray-50">
                  <div><label className="label">Nom / Raison sociale *</label><input className="input" value={form.new_contribuable_nom} onChange={e => setForm(f => ({ ...f, new_contribuable_nom: e.target.value }))} required /></div>
                  <div><label className="label">NIF *</label><input className="input" value={form.new_contribuable_nif} onChange={e => setForm(f => ({ ...f, new_contribuable_nif: e.target.value }))} required /></div>
                  <div><label className="label">Type d'entreprise</label>
                    <select className="input" value={form.new_contribuable_type} onChange={e => setForm(f => ({ ...f, new_contribuable_type: e.target.value }))}>
                      {['SA', 'SARL', 'SAS', 'SNC', 'EI', 'EURL', 'GIE', 'Autre'].map(t => <option key={t}>{t}</option>)}
                    </select>
                  </div>
                  <div><label className="label">Secteur d'activité</label><input className="input" value={form.new_contribuable_secteur} onChange={e => setForm(f => ({ ...f, new_contribuable_secteur: e.target.value }))} /></div>
                </div>
              ) : (
                <div>
                  <label className="label">Contribuable *</label>
                  <select className="input" value={form.contribuable_id} onChange={e => setForm(f => ({ ...f, contribuable_id: e.target.value }))} required={!form.add_new}>
                    <option value="">Sélectionner...</option>
                    {contribuables?.map(c => <option key={c.id} value={c.id}>{c.nom} — {c.nif}</option>)}
                  </select>
                </div>
              )}

              {(user?.role === 'directeur' || user?.role === 'sous_directeur') && (
                <div>
                  <label className="label">Agent assigné *</label>
                  <select className="input" value={form.agent_id} onChange={e => setForm(f => ({ ...f, agent_id: e.target.value }))}>
                    {agents?.filter(u => u.actif !== 0).map(u => <option key={u.id} value={u.id}>{u.prenom} {u.nom} ({u.role})</option>)}
                  </select>
                </div>
              )}

              <div>
                <label className="label">Date d'ouverture *</label>
                <input type="date" className="input" value={form.date_ouverture} onChange={e => setForm(f => ({ ...f, date_ouverture: e.target.value }))} required />
              </div>

              <div>
                <label className="label">Observations</label>
                <textarea className="input" rows={3} value={form.observations} onChange={e => setForm(f => ({ ...f, observations: e.target.value }))} />
              </div>

              {formError && <div className="bg-red-50 text-red-700 text-sm px-4 py-3 rounded-lg border border-red-200">{formError}</div>}

              <div className="flex gap-3 pt-2">
                <button type="button" onClick={() => setShowForm(false)} className="btn-secondary flex-1">Annuler</button>
                <button type="submit" disabled={submitting} className="btn-primary flex-1">{submitting ? 'Création...' : 'Créer le dossier'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
