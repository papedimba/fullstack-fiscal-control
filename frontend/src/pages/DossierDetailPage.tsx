import React, { useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useApi } from '../hooks/useApi';
import { Dossier, Etape } from '../types';
import { StatutDossierBadge, StatutEtapeBadge, TypeEtapeLabel, TypeControleLabel, TYPE_ETAPE } from '../components/StatusBadge';
import { useAuth } from '../contexts/AuthContext';
import api from '../services/api';
import { ArrowLeft, Plus, Edit2, Trash2, Save, X, CheckCircle, Clock, AlertTriangle } from 'lucide-react';

interface DossierDetail { dossier: Dossier; etapes: Etape[]; alertes: any[] }

const ETAPE_ORDER: string[] = ['avis','notification','pv','enquete','rapport','redressement','reponse_contribuable','decision_finale'];

export default function DossierDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { user } = useAuth();
  const navigate = useNavigate();
  const { data, loading, refetch } = useApi<DossierDetail>(`/dossiers/${id}`);
  const [showAddEtape, setShowAddEtape] = useState(false);
  const [editEtape, setEditEtape] = useState<Etape | null>(null);
  const [editDossier, setEditDossier] = useState(false);
  const [newEtape, setNewEtape] = useState({ type_etape: 'avis', date_prevue: '', commentaire: '' });
  const [dossierForm, setDossierForm] = useState<Partial<Dossier>>({});
  const [submitting, setSubmitting] = useState(false);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="animate-spin rounded-full h-10 w-10 border-4 border-primary-200 border-t-primary-800" /></div>;
  if (!data) return <div className="text-center text-gray-400 py-16">Dossier introuvable</div>;

  const { dossier, etapes } = data;
  const canEdit = user?.role !== 'agent' || dossier.agent_id === user?.id;
  const canDelete = user?.role === 'directeur' || user?.role === 'sous_directeur';

  const addEtape = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.post(`/dossiers/${id}/etapes`, newEtape);
      setShowAddEtape(false);
      setNewEtape({ type_etape: 'avis', date_prevue: '', commentaire: '' });
      refetch();
    } catch (err: any) { alert(err.response?.data?.error || 'Erreur'); }
    finally { setSubmitting(false); }
  };

  const saveEtape = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editEtape) return;
    setSubmitting(true);
    try {
      await api.put(`/etapes/${editEtape.id}`, editEtape);
      setEditEtape(null);
      refetch();
    } catch (err: any) { alert(err.response?.data?.error || 'Erreur'); }
    finally { setSubmitting(false); }
  };

  const deleteEtape = async (etapeId: number) => {
    if (!confirm('Supprimer cette étape ?')) return;
    await api.delete(`/etapes/${etapeId}`);
    refetch();
  };

  const saveDossier = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    try {
      await api.put(`/dossiers/${id}`, dossierForm);
      setEditDossier(false);
      refetch();
    } catch (err: any) { alert(err.response?.data?.error || 'Erreur'); }
    finally { setSubmitting(false); }
  };

  const deleteDossier = async () => {
    if (!confirm(`Supprimer définitivement le dossier ${dossier.numero_dossier} ?`)) return;
    await api.delete(`/dossiers/${id}`);
    navigate('/dossiers');
  };

  const progressPct = etapes.length
    ? Math.round((etapes.filter(e => e.statut === 'termine').length / etapes.length) * 100)
    : 0;

  return (
    <div className="space-y-6">
      {/* Back + title */}
      <div className="flex items-center gap-3">
        <Link to="/dossiers" className="p-2 rounded-lg hover:bg-gray-100 text-gray-500">
          <ArrowLeft className="h-5 w-5" />
        </Link>
        <div className="flex-1">
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold text-gray-900 font-mono">{dossier.numero_dossier}</h1>
            <StatutDossierBadge statut={dossier.statut} />
          </div>
          <p className="text-gray-500 text-sm">{dossier.contribuable_nom} — {dossier.nif}</p>
        </div>
        {canDelete && (
          <button onClick={deleteDossier} className="btn-danger text-sm flex items-center gap-1">
            <Trash2 className="h-4 w-4" /> Supprimer
          </button>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Info dossier */}
        <div className="card lg:col-span-1 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="font-semibold text-gray-900">Informations</h2>
            {canEdit && !editDossier && (
              <button onClick={() => { setEditDossier(true); setDossierForm({ statut: dossier.statut, observations: dossier.observations }); }}
                className="text-primary-700 hover:text-primary-900 text-sm flex items-center gap-1">
                <Edit2 className="h-3.5 w-3.5" /> Modifier
              </button>
            )}
          </div>

          {editDossier ? (
            <form onSubmit={saveDossier} className="space-y-3">
              <div>
                <label className="label text-xs">Statut</label>
                <select className="input" value={dossierForm.statut} onChange={e => setDossierForm(f => ({ ...f, statut: e.target.value as any }))}>
                  {['ouvert','en_cours','suspendu','cloture','contentieux'].map(s => <option key={s} value={s}>{s}</option>)}
                </select>
              </div>
              <div>
                <label className="label text-xs">Observations</label>
                <textarea className="input" rows={3} value={dossierForm.observations} onChange={e => setDossierForm(f => ({ ...f, observations: e.target.value }))} />
              </div>
              <div className="flex gap-2">
                <button type="button" onClick={() => setEditDossier(false)} className="btn-secondary flex-1 text-sm py-1.5">Annuler</button>
                <button type="submit" disabled={submitting} className="btn-primary flex-1 text-sm py-1.5 flex items-center justify-center gap-1">
                  <Save className="h-3.5 w-3.5" /> Sauvegarder
                </button>
              </div>
            </form>
          ) : (
            <dl className="space-y-3 text-sm">
              {[
                { label: 'Contribuable', value: dossier.contribuable_nom },
                { label: 'NIF', value: dossier.nif },
                { label: 'Type d\'entreprise', value: dossier.type_entreprise },
                { label: 'Secteur', value: dossier.secteur_activite },
                { label: 'Type de contrôle', value: <TypeControleLabel type={dossier.type_controle} /> },
                { label: 'Agent', value: dossier.agent_nom },
                { label: 'Chef de brigade', value: dossier.chef_brigade_nom || '—' },
                { label: 'Brigade', value: dossier.brigade_nom || '—' },
                { label: 'Date ouverture', value: new Date(dossier.date_ouverture).toLocaleDateString('fr-FR') },
                { label: 'Date clôture', value: dossier.date_cloture ? new Date(dossier.date_cloture).toLocaleDateString('fr-FR') : '—' },
              ].map(({ label, value }) => (
                <div key={label} className="flex justify-between items-start gap-2">
                  <dt className="text-gray-500 flex-shrink-0">{label}</dt>
                  <dd className="text-gray-900 font-medium text-right">{value}</dd>
                </div>
              ))}
              {dossier.observations && (
                <div className="pt-2 border-t border-gray-100">
                  <p className="text-gray-500 text-xs mb-1">Observations</p>
                  <p className="text-gray-700">{dossier.observations}</p>
                </div>
              )}
            </dl>
          )}

          {/* Progress */}
          <div className="pt-2 border-t border-gray-100">
            <div className="flex justify-between text-xs text-gray-500 mb-1">
              <span>Avancement</span>
              <span>{progressPct}%</span>
            </div>
            <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
              <div className="h-full bg-primary-600 rounded-full transition-all" style={{ width: `${progressPct}%` }} />
            </div>
            <p className="text-xs text-gray-500 mt-1">{etapes.filter(e => e.statut === 'termine').length} / {etapes.length} étapes terminées</p>
          </div>
        </div>

        {/* Étapes */}
        <div className="lg:col-span-2 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="font-semibold text-gray-900">Procédure de contrôle</h2>
            {canEdit && (
              <button onClick={() => setShowAddEtape(true)} className="btn-primary text-sm flex items-center gap-1 py-1.5">
                <Plus className="h-4 w-4" /> Ajouter étape
              </button>
            )}
          </div>

          {/* Timeline */}
          <div className="space-y-3">
            {etapes.length === 0 ? (
              <div className="card text-center text-gray-400 py-10">
                <Clock className="h-10 w-10 mx-auto mb-2 opacity-30" />
                <p>Aucune étape enregistrée</p>
              </div>
            ) : etapes.map((etape, idx) => (
              <div key={etape.id} className={`card p-4 border-l-4 ${etape.statut === 'termine' ? 'border-green-400' : etape.statut === 'retard' ? 'border-red-400' : etape.statut === 'en_cours' ? 'border-blue-400' : 'border-gray-200'}`}>
                {editEtape?.id === etape.id ? (
                  <form onSubmit={saveEtape} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                      <div>
                        <label className="label text-xs">Statut</label>
                        <select className="input" value={editEtape.statut} onChange={e => setEditEtape(prev => prev ? { ...prev, statut: e.target.value as any } : null)}>
                          {['en_attente','en_cours','termine','retard'].map(s => <option key={s} value={s}>{s}</option>)}
                        </select>
                      </div>
                      <div>
                        <label className="label text-xs">Date prévue</label>
                        <input type="date" className="input" value={editEtape.date_prevue || ''} onChange={e => setEditEtape(prev => prev ? { ...prev, date_prevue: e.target.value } : null)} />
                      </div>
                      <div>
                        <label className="label text-xs">Date réalisation</label>
                        <input type="date" className="input" value={editEtape.date_realisation || ''} onChange={e => setEditEtape(prev => prev ? { ...prev, date_realisation: e.target.value } : null)} />
                      </div>
                    </div>
                    <div>
                      <label className="label text-xs">Commentaire</label>
                      <textarea className="input" rows={2} value={editEtape.commentaire} onChange={e => setEditEtape(prev => prev ? { ...prev, commentaire: e.target.value } : null)} />
                    </div>
                    <div className="flex gap-2">
                      <button type="button" onClick={() => setEditEtape(null)} className="btn-secondary text-sm py-1 px-3 flex items-center gap-1"><X className="h-3 w-3" />Annuler</button>
                      <button type="submit" disabled={submitting} className="btn-primary text-sm py-1 px-3 flex items-center gap-1"><Save className="h-3 w-3" />Sauvegarder</button>
                    </div>
                  </form>
                ) : (
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                      <div className="flex-shrink-0 mt-0.5">
                        {etape.statut === 'termine' ? <CheckCircle className="h-5 w-5 text-green-500" /> :
                         etape.statut === 'retard' ? <AlertTriangle className="h-5 w-5 text-red-500" /> :
                         etape.statut === 'en_cours' ? <Clock className="h-5 w-5 text-blue-500" /> :
                         <div className="h-5 w-5 rounded-full border-2 border-gray-300" />}
                      </div>
                      <div>
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="font-medium text-gray-900"><TypeEtapeLabel type={etape.type_etape} /></span>
                          <StatutEtapeBadge statut={etape.statut} />
                        </div>
                        <div className="text-xs text-gray-500 mt-1 space-x-3">
                          {etape.date_prevue && <span>Prévue: {new Date(etape.date_prevue).toLocaleDateString('fr-FR')}</span>}
                          {etape.date_realisation && <span className="text-green-600">Réalisée: {new Date(etape.date_realisation).toLocaleDateString('fr-FR')}</span>}
                        </div>
                        {etape.commentaire && <p className="text-sm text-gray-600 mt-1">{etape.commentaire}</p>}
                      </div>
                    </div>
                    {canEdit && (
                      <div className="flex gap-1 flex-shrink-0">
                        <button onClick={() => setEditEtape(etape)} className="p-1.5 rounded hover:bg-gray-100 text-gray-500"><Edit2 className="h-3.5 w-3.5" /></button>
                        <button onClick={() => deleteEtape(etape.id)} className="p-1.5 rounded hover:bg-red-50 text-red-500"><Trash2 className="h-3.5 w-3.5" /></button>
                      </div>
                    )}
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* Modal ajout étape */}
      {showAddEtape && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div className="flex items-center justify-between p-6 border-b">
              <h2 className="text-lg font-semibold">Ajouter une étape</h2>
              <button onClick={() => setShowAddEtape(false)} className="text-gray-400 hover:text-gray-600"><X className="h-5 w-5" /></button>
            </div>
            <form onSubmit={addEtape} className="p-6 space-y-4">
              <div>
                <label className="label">Type d'étape *</label>
                <select className="input" value={newEtape.type_etape} onChange={e => setNewEtape(f => ({ ...f, type_etape: e.target.value }))}>
                  {ETAPE_ORDER.map(k => <option key={k} value={k}>{TYPE_ETAPE[k]}</option>)}
                </select>
              </div>
              <div>
                <label className="label">Date prévue</label>
                <input type="date" className="input" value={newEtape.date_prevue} onChange={e => setNewEtape(f => ({ ...f, date_prevue: e.target.value }))} />
              </div>
              <div>
                <label className="label">Commentaire</label>
                <textarea className="input" rows={3} value={newEtape.commentaire} onChange={e => setNewEtape(f => ({ ...f, commentaire: e.target.value }))} />
              </div>
              <div className="flex gap-3">
                <button type="button" onClick={() => setShowAddEtape(false)} className="btn-secondary flex-1">Annuler</button>
                <button type="submit" disabled={submitting} className="btn-primary flex-1">{submitting ? 'Ajout...' : 'Ajouter'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
