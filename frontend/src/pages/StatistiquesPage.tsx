import React, { useState } from 'react';
import { useApi } from '../hooks/useApi';
import { DashboardStats } from '../types';
import { Bar, Doughnut, Line, Radar } from 'react-chartjs-2';
import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement,
  LineElement, PointElement, ArcElement, RadialLinearScale,
  Title, Tooltip, Legend, Filler
} from 'chart.js';
import { TrendingUp, TrendingDown } from 'lucide-react';

ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, ArcElement, RadialLinearScale, Title, Tooltip, Legend, Filler);

const TYPE_LABELS: Record<string, string> = {
  verification_comptabilite: 'Vérif. comptabilité',
  verification_ponctuelle:   'Vérif. ponctuelle',
  droit_communication:       'Droit de communication',
  controle_sur_pieces:       'Contrôle sur pièces',
};

const ETAPE_LABELS: Record<string, string> = {
  avis: 'Avis', notification: 'Notification', pv: 'PV', enquete: 'Enquête',
  rapport: 'Rapport', redressement: 'Redressement', reponse_contribuable: 'Réponse', decision_finale: 'Décision',
};

export default function StatistiquesPage() {
  const { data: stats, loading } = useApi<DashboardStats>('/stats/dashboard');

  if (loading) return <div className="flex items-center justify-center h-64"><div className="animate-spin rounded-full h-10 w-10 border-4 border-primary-200 border-t-primary-800" /></div>;
  if (!stats) return null;

  const { parType, parAgent, parMois, retardsParEtape, progression, totaux } = stats;

  const tauxCloture = totaux.total > 0 ? Math.round((totaux.cloture / totaux.total) * 100) : 0;
  const tauxRetard = totaux.total > 0 ? Math.round((totaux.retards / totaux.total) * 100) : 0;

  const moisLabels = parMois.map(m => {
    const [y, mo] = m.mois.split('-');
    return new Date(parseInt(y), parseInt(mo) - 1).toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
  });

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Statistiques</h1>
        <p className="text-gray-500 text-sm">Analyse complète des contrôles fiscaux</p>
      </div>

      {/* KPIs synthèse */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div className="card p-4 text-center">
          <p className="text-3xl font-bold text-primary-800">{totaux.total}</p>
          <p className="text-sm text-gray-500 mt-1">Total dossiers</p>
        </div>
        <div className="card p-4 text-center">
          <div className="flex items-center justify-center gap-1">
            <p className="text-3xl font-bold text-green-700">{tauxCloture}%</p>
            <TrendingUp className="h-5 w-5 text-green-600" />
          </div>
          <p className="text-sm text-gray-500 mt-1">Taux de clôture</p>
        </div>
        <div className="card p-4 text-center">
          <div className="flex items-center justify-center gap-1">
            <p className="text-3xl font-bold text-red-700">{tauxRetard}%</p>
            <TrendingDown className="h-5 w-5 text-red-600" />
          </div>
          <p className="text-sm text-gray-500 mt-1">Taux de retard</p>
        </div>
        <div className="card p-4 text-center">
          <p className="text-3xl font-bold text-amber-700">{totaux.enCours}</p>
          <p className="text-sm text-gray-500 mt-1">En cours de traitement</p>
        </div>
      </div>

      {/* Progression annuelle */}
      {progression.length > 0 && (
        <div className="card">
          <h2 className="text-base font-semibold text-gray-800 mb-4">Progression annuelle</h2>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gray-50 border-b">
                  <th className="px-4 py-2 text-left text-xs text-gray-500 uppercase">Année</th>
                  <th className="px-4 py-2 text-left text-xs text-gray-500 uppercase">Total</th>
                  <th className="px-4 py-2 text-left text-xs text-gray-500 uppercase">Clôturés</th>
                  <th className="px-4 py-2 text-left text-xs text-gray-500 uppercase">En cours</th>
                  <th className="px-4 py-2 text-left text-xs text-gray-500 uppercase">Taux clôture</th>
                </tr>
              </thead>
              <tbody>
                {progression.map(p => (
                  <tr key={p.annee} className="border-b border-gray-100">
                    <td className="px-4 py-2 font-bold text-gray-900">{p.annee}</td>
                    <td className="px-4 py-2">{p.total}</td>
                    <td className="px-4 py-2 text-green-700">{p.clotures}</td>
                    <td className="px-4 py-2 text-amber-700">{p.en_cours}</td>
                    <td className="px-4 py-2">
                      <div className="flex items-center gap-2">
                        <div className="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                          <div className="h-full bg-green-500 rounded-full" style={{ width: `${p.total > 0 ? (p.clotures / p.total) * 100 : 0}%` }} />
                        </div>
                        <span className="text-xs text-gray-600">{p.total > 0 ? Math.round((p.clotures / p.total) * 100) : 0}%</span>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Charts grid */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Évolution mensuelle */}
        <div className="card">
          <h2 className="text-base font-semibold text-gray-800 mb-4">Évolution mensuelle</h2>
          <Line
            data={{
              labels: moisLabels,
              datasets: [{
                label: 'Dossiers ouverts',
                data: parMois.map(m => m.cnt),
                borderColor: '#1E3A5F',
                backgroundColor: 'rgba(30,58,95,0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#1E3A5F',
                pointRadius: 5,
              }]
            }}
            options={{ responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }}
          />
        </div>

        {/* Par type de contrôle */}
        <div className="card">
          <h2 className="text-base font-semibold text-gray-800 mb-4">Répartition par type</h2>
          {parType.length > 0 ? (
            <Doughnut
              data={{
                labels: parType.map(t => TYPE_LABELS[t.type_controle] || t.type_controle),
                datasets: [{
                  data: parType.map(t => t.cnt),
                  backgroundColor: ['#1E3A5F', '#3B6EB3', '#628BC2', '#89A8D1'],
                  borderWidth: 3, borderColor: '#fff',
                }]
              }}
              options={{ responsive: true, plugins: { legend: { position: 'bottom' } } }}
            />
          ) : <p className="text-gray-400 text-center py-8 text-sm">Aucune donnée</p>}
        </div>

        {/* Par agent */}
        <div className="card">
          <h2 className="text-base font-semibold text-gray-800 mb-4">Dossiers par agent</h2>
          {parAgent.length > 0 ? (
            <Bar
              data={{
                labels: parAgent.map(a => a.agent.split(' ').slice(0, 2).join(' ')),
                datasets: [{
                  label: 'Dossiers',
                  data: parAgent.map(a => a.cnt),
                  backgroundColor: parAgent.map((_, i) => `hsl(${210 + i * 15}, 65%, ${35 + i * 4}%)`),
                  borderRadius: 6,
                }]
              }}
              options={{ responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { font: { size: 11 } } } } }}
            />
          ) : <p className="text-gray-400 text-center py-8 text-sm">Aucune donnée</p>}
        </div>

        {/* Retards par étape */}
        <div className="card">
          <h2 className="text-base font-semibold text-gray-800 mb-4">Retards par étape</h2>
          {retardsParEtape.length > 0 ? (
            <Bar
              data={{
                labels: retardsParEtape.map(r => ETAPE_LABELS[r.type_etape] || r.type_etape),
                datasets: [{
                  label: 'Retards',
                  data: retardsParEtape.map(r => r.cnt),
                  backgroundColor: '#EF4444',
                  borderRadius: 6,
                }]
              }}
              options={{ responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }}
            />
          ) : <p className="text-gray-400 text-center py-8 text-sm">Aucun retard enregistré</p>}
        </div>
      </div>
    </div>
  );
}
