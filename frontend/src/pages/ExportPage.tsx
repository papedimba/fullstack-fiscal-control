import React, { useState } from 'react';
import { Download, FileSpreadsheet, FileText, Calendar } from 'lucide-react';

export default function ExportPage() {
  const [annee, setAnnee] = useState(String(new Date().getFullYear()));
  const [mois, setMois] = useState('');
  const [trimestre, setTrimestre] = useState('');
  const [periodeType, setPeriodeType] = useState<'annuelle' | 'mensuelle' | 'trimestrielle'>('annuelle');
  const [loading, setLoading] = useState(false);

  const buildQuery = () => {
    const params = new URLSearchParams();
    params.set('annee', annee);
    if (periodeType === 'mensuelle' && mois) params.set('mois', mois);
    if (periodeType === 'trimestrielle' && trimestre) params.set('trimestre', trimestre);
    return params.toString();
  };

  const downloadExcel = async () => {
    setLoading(true);
    try {
      const token = localStorage.getItem('token');
      const res = await fetch(`/api/export/excel?${buildQuery()}`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      if (!res.ok) throw new Error('Erreur export');
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `rapport_fiscal_${annee}${mois ? '_' + mois : ''}${trimestre ? '_T' + trimestre : ''}.xlsx`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (e) {
      alert('Erreur lors de l\'export Excel');
    } finally {
      setLoading(false);
    }
  };

  const downloadJson = async () => {
    setLoading(true);
    try {
      const token = localStorage.getItem('token');
      const res = await fetch(`/api/export/json?${buildQuery()}`, {
        headers: { Authorization: `Bearer ${token}` }
      });
      const data = await res.json();
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `rapport_fiscal_${annee}.json`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      alert('Erreur lors de l\'export JSON');
    } finally {
      setLoading(false);
    }
  };

  const currentYear = new Date().getFullYear();
  const years = Array.from({ length: 6 }, (_, i) => currentYear - i);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Rapports & Export</h1>
        <p className="text-gray-500 text-sm">Générez et téléchargez les rapports de contrôle fiscal</p>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Configuration */}
        <div className="card space-y-5">
          <div className="flex items-center gap-2">
            <Calendar className="h-5 w-5 text-primary-700" />
            <h2 className="font-semibold text-gray-900">Paramètres de la période</h2>
          </div>

          <div>
            <label className="label">Type de rapport</label>
            <div className="flex gap-2">
              {([['annuelle', 'Annuel'], ['mensuelle', 'Mensuel'], ['trimestrielle', 'Trimestriel']] as const).map(([v, l]) => (
                <button key={v} onClick={() => { setPeriodeType(v); setMois(''); setTrimestre(''); }}
                  className={`flex-1 py-2 px-3 rounded-lg text-sm font-medium border transition-colors ${periodeType === v ? 'bg-primary-800 text-white border-primary-800' : 'bg-white text-gray-700 border-gray-200 hover:border-primary-300'}`}>
                  {l}
                </button>
              ))}
            </div>
          </div>

          <div>
            <label className="label">Année</label>
            <select className="input" value={annee} onChange={e => setAnnee(e.target.value)}>
              {years.map(y => <option key={y} value={y}>{y}</option>)}
            </select>
          </div>

          {periodeType === 'mensuelle' && (
            <div>
              <label className="label">Mois</label>
              <select className="input" value={mois} onChange={e => setMois(e.target.value)}>
                <option value="">Sélectionner...</option>
                {['01','02','03','04','05','06','07','08','09','10','11','12'].map((m, i) => (
                  <option key={m} value={m}>{new Date(2024, i, 1).toLocaleDateString('fr-FR', { month: 'long' })}</option>
                ))}
              </select>
            </div>
          )}

          {periodeType === 'trimestrielle' && (
            <div>
              <label className="label">Trimestre</label>
              <select className="input" value={trimestre} onChange={e => setTrimestre(e.target.value)}>
                <option value="">Sélectionner...</option>
                <option value="1">1er trimestre (Jan–Mar)</option>
                <option value="2">2ème trimestre (Avr–Juin)</option>
                <option value="3">3ème trimestre (Jul–Sep)</option>
                <option value="4">4ème trimestre (Oct–Déc)</option>
              </select>
            </div>
          )}

          <div className="pt-2 border-t border-gray-100">
            <p className="text-sm text-gray-500">
              Rapport sélectionné : <strong className="text-gray-800">
                {periodeType === 'annuelle' && `Annuel ${annee}`}
                {periodeType === 'mensuelle' && mois && `${new Date(parseInt(annee), parseInt(mois)-1, 1).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' })}`}
                {periodeType === 'mensuelle' && !mois && `Mensuel ${annee} — sélectionner un mois`}
                {periodeType === 'trimestrielle' && trimestre && `T${trimestre} ${annee}`}
                {periodeType === 'trimestrielle' && !trimestre && `Trimestriel ${annee} — sélectionner`}
              </strong>
            </p>
          </div>
        </div>

        {/* Export options */}
        <div className="space-y-4">
          <div className="card hover:border-green-300 border-2 border-transparent transition-all cursor-pointer" onClick={downloadExcel}>
            <div className="flex items-start gap-4">
              <div className="p-3 bg-green-50 rounded-xl">
                <FileSpreadsheet className="h-8 w-8 text-green-700" />
              </div>
              <div className="flex-1">
                <h3 className="font-semibold text-gray-900">Export Excel</h3>
                <p className="text-sm text-gray-500 mt-1">Fichier .xlsx avec liste complète des dossiers, statuts, agents et statistiques. Mise en forme colorée.</p>
                <button
                  disabled={loading}
                  className="mt-3 btn-primary text-sm flex items-center gap-2 py-1.5 px-3 bg-green-700 hover:bg-green-600"
                  onClick={e => { e.stopPropagation(); downloadExcel(); }}
                >
                  <Download className="h-4 w-4" />
                  {loading ? 'Export en cours...' : 'Télécharger (.xlsx)'}
                </button>
              </div>
            </div>
          </div>

          <div className="card hover:border-blue-300 border-2 border-transparent transition-all cursor-pointer">
            <div className="flex items-start gap-4">
              <div className="p-3 bg-blue-50 rounded-xl">
                <FileText className="h-8 w-8 text-blue-700" />
              </div>
              <div className="flex-1">
                <h3 className="font-semibold text-gray-900">Export JSON</h3>
                <p className="text-sm text-gray-500 mt-1">Données brutes au format JSON pour intégration avec d'autres systèmes ou analyses avancées.</p>
                <button
                  disabled={loading}
                  className="mt-3 btn-primary text-sm flex items-center gap-2 py-1.5 px-3"
                  onClick={e => { e.stopPropagation(); downloadJson(); }}
                >
                  <Download className="h-4 w-4" />
                  {loading ? 'Export en cours...' : 'Télécharger (.json)'}
                </button>
              </div>
            </div>
          </div>

          <div className="card bg-primary-50 border border-primary-200">
            <div className="flex items-start gap-3">
              <div className="p-2 bg-primary-100 rounded-lg">
                <FileText className="h-5 w-5 text-primary-700" />
              </div>
              <div>
                <h3 className="font-semibold text-primary-900 text-sm">Export PDF</h3>
                <p className="text-xs text-primary-700 mt-1">Rapport PDF disponible via l'impression navigateur (Ctrl+P) depuis la page des statistiques.</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Guide */}
      <div className="card bg-gray-50">
        <h3 className="font-semibold text-gray-800 mb-3">Guide des rapports</h3>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
          <div>
            <p className="font-medium text-gray-800 mb-1">Rapport annuel</p>
            <p>Vue d'ensemble de tous les dossiers de l'année avec synthèse statistique.</p>
          </div>
          <div>
            <p className="font-medium text-gray-800 mb-1">Rapport mensuel</p>
            <p>Dossiers ouverts durant le mois sélectionné avec détails des étapes.</p>
          </div>
          <div>
            <p className="font-medium text-gray-800 mb-1">Rapport trimestriel</p>
            <p>Synthèse par trimestre pour la reddition des comptes à la hiérarchie.</p>
          </div>
        </div>
      </div>
    </div>
  );
}
