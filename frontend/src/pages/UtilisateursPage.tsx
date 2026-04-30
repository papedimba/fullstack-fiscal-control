import React, { useState } from 'react';
import { useApi } from '../hooks/useApi';
import { User } from '../types';
import api from '../services/api';
import { Plus, Edit2, Power, X, Shield, Users } from 'lucide-react';

const ROLE_LABELS: Record<string, string> = {
  directeur: 'Directeur',
  sous_directeur: 'Sous-Directeur',
  chef_brigade: 'Chef de Brigade',
  agent: 'Agent',
};

const ROLE_COLORS: Record<string, string> = {
  directeur: 'bg-purple-100 text-purple-800',
  sous_directeur: 'bg-blue-100 text-blue-800',
  chef_brigade: 'bg-amber-100 text-amber-800',
  agent: 'bg-gray-100 text-gray-700',
};

interface Brigade { id: number; nom: string; chef_nom: string }

export default function UtilisateursPage() {
  const { data: users, loading, refetch } = useApi<User[]>('/users');
  const { data: brigades } = useApi<Brigade[]>('/brigades');
  const [showForm, setShowForm] = useState(false);
  const [editUser, setEditUser] = useState<User | null>(null);
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState({
    nom: '', prenom: '', email: '', password: '', role: 'agent', brigade_id: '',
  });

  const openCreate = () => {
    setEditUser(null);
    setForm({ nom: '', prenom: '', email: '', password: '', role: 'agent', brigade_id: '' });
    setFormError('');
    setShowForm(true);
  };

  const openEdit = (u: User) => {
    setEditUser(u);
    setForm({ nom: u.nom, prenom: u.prenom, email: u.email, password: '', role: u.role, brigade_id: String(u.brigade_id || '') });
    setFormError('');
    setShowForm(true);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setFormError('');
    setSubmitting(true);
    try {
      const payload = { ...form, brigade_id: form.brigade_id ? parseInt(form.brigade_id) : null };
      if (editUser) {
        await api.put(`/users/${editUser.id}`, payload);
      } else {
        await api.post('/users', payload);
      }
      setShowForm(false);
      refetch();
    } catch (err: any) {
      setFormError(err.response?.data?.error || 'Erreur');
    } finally {
      setSubmitting(false);
    }
  };

  const toggleActive = async (u: User) => {
    if (!confirm(`${u.actif ? 'Désactiver' : 'Réactiver'} l'utilisateur ${u.prenom} ${u.nom} ?`)) return;
    await api.put(`/users/${u.id}`, { ...u, actif: u.actif ? 0 : 1 });
    refetch();
  };

  const byRole = (role: string) => users?.filter(u => u.role === role) ?? [];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-gray-900">Utilisateurs & Droits</h1>
          <p className="text-gray-500 text-sm">{users?.length ?? 0} utilisateur(s) enregistré(s)</p>
        </div>
        <button onClick={openCreate} className="btn-primary flex items-center gap-2">
          <Plus className="h-4 w-4" /> Nouvel utilisateur
        </button>
      </div>

      {/* Brigades */}
      {brigades && brigades.length > 0 && (
        <div className="card">
          <div className="flex items-center gap-2 mb-4">
            <Shield className="h-5 w-5 text-primary-700" />
            <h2 className="font-semibold text-gray-900">Brigades</h2>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            {brigades.map(b => (
              <div key={b.id} className="bg-gray-50 rounded-lg p-3 border border-gray-200">
                <p className="font-medium text-gray-900">{b.nom}</p>
                <p className="text-sm text-gray-500 mt-0.5">Chef : {b.chef_nom || '—'}</p>
              </div>
            ))}
          </div>
        </div>
      )}

      {loading ? (
        <div className="flex items-center justify-center h-48"><div className="animate-spin rounded-full h-8 w-8 border-4 border-primary-200 border-t-primary-800" /></div>
      ) : (
        <div className="space-y-6">
          {['directeur', 'sous_directeur', 'chef_brigade', 'agent'].map(role => {
            const roleUsers = byRole(role);
            if (roleUsers.length === 0) return null;
            return (
              <div key={role}>
                <div className="flex items-center gap-2 mb-3">
                  <Users className="h-4 w-4 text-gray-500" />
                  <h2 className="font-semibold text-gray-700">{ROLE_LABELS[role]}s ({roleUsers.length})</h2>
                </div>
                <div className="card p-0 overflow-hidden">
                  <table className="w-full text-sm">
                    <thead className="bg-gray-50 border-b">
                      <tr>
                        {['Nom complet', 'Email', 'Rôle', 'Brigade', 'Statut', 'Inscription', ''].map(h => (
                          <th key={h} className="px-4 py-3 text-left text-xs text-gray-500 uppercase font-semibold">{h}</th>
                        ))}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                      {roleUsers.map(u => (
                        <tr key={u.id} className={`hover:bg-gray-50 ${!u.actif ? 'opacity-50' : ''}`}>
                          <td className="px-4 py-3">
                            <div className="flex items-center gap-2">
                              <div className="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-xs font-bold text-primary-800">
                                {u.prenom?.[0]}{u.nom?.[0]}
                              </div>
                              <div>
                                <p className="font-medium text-gray-900">{u.prenom} {u.nom}</p>
                              </div>
                            </div>
                          </td>
                          <td className="px-4 py-3 text-gray-600">{u.email}</td>
                          <td className="px-4 py-3">
                            <span className={`badge ${ROLE_COLORS[u.role]}`}>{ROLE_LABELS[u.role]}</span>
                          </td>
                          <td className="px-4 py-3 text-gray-600">{(u as any).brigade_nom || '—'}</td>
                          <td className="px-4 py-3">
                            <span className={`badge ${u.actif ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'}`}>
                              {u.actif ? 'Actif' : 'Inactif'}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-gray-400 text-xs">
                            {u.created_at ? new Date(u.created_at).toLocaleDateString('fr-FR') : ''}
                          </td>
                          <td className="px-4 py-3">
                            <div className="flex items-center gap-1">
                              <button onClick={() => openEdit(u)} className="p-1.5 rounded hover:bg-gray-100 text-gray-500" title="Modifier">
                                <Edit2 className="h-3.5 w-3.5" />
                              </button>
                              <button onClick={() => toggleActive(u)}
                                className={`p-1.5 rounded ${u.actif ? 'hover:bg-red-50 text-red-500' : 'hover:bg-green-50 text-green-500'}`}
                                title={u.actif ? 'Désactiver' : 'Réactiver'}>
                                <Power className="h-3.5 w-3.5" />
                              </button>
                            </div>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Modal */}
      {showForm && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-2xl w-full max-w-md">
            <div className="flex items-center justify-between p-6 border-b">
              <h2 className="text-lg font-semibold">{editUser ? 'Modifier l\'utilisateur' : 'Nouvel utilisateur'}</h2>
              <button onClick={() => setShowForm(false)} className="text-gray-400 hover:text-gray-600"><X className="h-5 w-5" /></button>
            </div>
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
              <div className="grid grid-cols-2 gap-3">
                <div><label className="label">Prénom *</label><input className="input" value={form.prenom} onChange={e => setForm(f => ({ ...f, prenom: e.target.value }))} required /></div>
                <div><label className="label">Nom *</label><input className="input" value={form.nom} onChange={e => setForm(f => ({ ...f, nom: e.target.value }))} required /></div>
              </div>
              <div><label className="label">Email *</label><input type="email" className="input" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} required /></div>
              <div>
                <label className="label">{editUser ? 'Nouveau mot de passe (laisser vide = inchangé)' : 'Mot de passe *'}</label>
                <input type="password" className="input" value={form.password} onChange={e => setForm(f => ({ ...f, password: e.target.value }))} required={!editUser} minLength={8} placeholder={editUser ? '••••••••' : ''} />
              </div>
              <div>
                <label className="label">Rôle *</label>
                <select className="input" value={form.role} onChange={e => setForm(f => ({ ...f, role: e.target.value }))}>
                  {Object.entries(ROLE_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </select>
              </div>
              {(form.role === 'chef_brigade' || form.role === 'agent') && (
                <div>
                  <label className="label">Brigade</label>
                  <select className="input" value={form.brigade_id} onChange={e => setForm(f => ({ ...f, brigade_id: e.target.value }))}>
                    <option value="">—</option>
                    {brigades?.map(b => <option key={b.id} value={b.id}>{b.nom}</option>)}
                  </select>
                </div>
              )}
              {formError && <div className="bg-red-50 text-red-700 text-sm px-4 py-3 rounded-lg border border-red-200">{formError}</div>}
              <div className="flex gap-3 pt-2">
                <button type="button" onClick={() => setShowForm(false)} className="btn-secondary flex-1">Annuler</button>
                <button type="submit" disabled={submitting} className="btn-primary flex-1">{submitting ? 'Enregistrement...' : editUser ? 'Modifier' : 'Créer'}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
