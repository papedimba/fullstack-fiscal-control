import React from 'react';
import { useAuth } from '../contexts/AuthContext';
import { useApi } from '../hooks/useApi';
import { DashboardStats } from '../types';
import { Bar, Doughnut, Line } from 'react-chartjs-2';
import {
  Chart as ChartJS, CategoryScale, LinearScale, BarElement,
  LineElement, PointElement, ArcElement, Title, Tooltip, Legend, Filler
} from 'chart.js';
import { FolderOpen, Clock, CheckCircle, AlertTriangle, Bell, TrendingUp, Pause, Gavel } from 'lucide-react';

ChartJS.register(CategoryScale, LinearScale, BarElement, LineElement, PointElement, ArcElement, Title, Tooltip, Legend, Filler);

const TYPE_LABELS: Record<string, string> = {
  verification_comptabilite: 'Vérif. comptabilité',
  verification_ponctuelle:   'Vérif. ponctuelle',
  droit_communication:       'Droit de communication',
  controle_sur_pieces:       'Contrôle sur pièces',
};

export default function DashboardPage() {
  const { user } = useAuth();
  const { data: stats, loading } = useApi<DashboardStats>('/stats/dashboard');

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="animate-spin rounded-full h-10 w-10 border-4 border-primary-200 border-t-primary-800" />
    </div>
  );
  if (!stats) return null;

  const { totaux, parType, parAgent, parMois, retardsParEtape } = stats;

  const kpis = [
    { label: 'Total dossiers', value: totaux.total, icon: FolderOpen, color: 'text-primary-700', bg: 'bg-primary-50' },
    { label: 'En cours', value: totaux.enCours, icon: Clock, color: 'text-amber-700', bg: 'bg-amber-50' },
    { label: 'Clôturés', value: totaux.cloture, icon: CheckCircle, color: 'text-green-700', bg: 'bg-green-50' },
    { label: 'Retards', value: totaux.retards, icon: AlertTriangle, color: 'text-red-700', bg: 'bg-red-50' },
    { label: 'Alertes non lues', value: totaux.alertesNonLues, icon: Bell, color: 'text-orange-700', bg: 'bg-orange-50' },
    { label: 'Suspendus', value: totaux.suspendu, icon: Pause, color: 'text-gray-700', bg: 'bg-gray-50' },
    { label: 'Contentieux', value: totaux.contentieux, icon: Gavel, color: 'text-purple-700', bg: 'bg-purple-50' },
    { label: 'Ouverts', value: totaux.ouvert, icon: TrendingUp, color: 'text-blue-700', bg: 'bg-blue-50' },
  ];

  const moisLabels = parMois.map(m => {
    const [y, mo] = m.mois.split('-');
    return new Date(parseInt(y), parseInt(mo) - 1).toLocaleDateString('fr-FR', { month: 'short', year: '2-digit' });
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">Tableau de Bord</h1>
        <p className="text-gray-500 text-sm mt-1">
          Bonjour, {user?.prenom} {user?.nom} — {new Date().toLocaleDateString('fr-FR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
        </p>
      </div>

      {/* KPIs */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {kpis.map(kpi => (
          <div key={kpi.label} className="card p-4">
            <div className="flex items-center gap-3">
              <div className={`p-2 rounded-lg ${kpi.bg}`}>
                <kpi.icon className={`h-5 w-5 ${kpi.color}`} />
              </div>
              <div>
                <p className="text-2xl font-bold text-gray-900">{kpi.value}</p>
                <p className="text-xs text-gray-500">{kpi.label}</p>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Charts row 1 */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Line - évolution mensuelle */}
        <div className="card lg:col-span-2">
          <h2 className="text-base font-semibold text-gray-800 mb-4">Évolution mensuelle des dossiers</h2>
          <Line
            data={{
              labels: moisLabels,
              datasets: [{
                label: 'Dossiers ouverts',
                data: parMois.map(m => m.cnt),
                borderColor: '#1E3A5F',
                backgroundColor: 'rgba(30,58,95,0.08)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#1E3A5F',
              }]
            }}
            options={{ responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }}
          />
        </div>

        {/* Doughnut - par type */}
        <div className="card">
          <h2 className="text-base font-semibold text-gray-800 mb-4">Par type de contrôle</h2>
          {parType.length > 0 ? (
            <Doughnut
              data={{
                labels: parType.map(t => TYPE_LABELS[t.type_controle] || t.type_controle),
                datasets: [{
                  data: parType.map(t => t.cnt),
                  backgroundColor: ['#1E3A5F', '#3B6EB3', '#628BC2', '#89A8D1'],
                  borderWidth: 2,
                }]
              }}
              options={{ responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }}
            />
          ) : <p className="text-gray-400 text-sm text-center py-8">Aucune donnée</p>}
        </div>
      </div>

      {/* Charts row 2 */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Bar - par agent */}
        <div className="card">
          <h2 className="text-base font-semibold text-gray-800 mb-4">Dossiers par agent (top 10)</h2>
          {parAgent.length > 0 ? (
            <Bar
              data={{
                labels: parAgent.map(a => a.agent),
                datasets: [{
                  label: 'Dossiers',
                  data: parAgent.map(a => a.cnt),
                  backgroundColor: '#3B6EB3',
                  borderRadius: 4,
                }]
              }}
              options={{ responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { font: { size: 11 } } } }, indexAxis: 'y' as const }}
            />
          ) : <p className="text-gray-400 text-sm text-center py-8">Aucune donnée</p>}
        </div>

        {/* Bar - retards par étape */}
        <div className="card">
          <h2 className="text-base font-semibold text-gray-800 mb-4">Retards par étape de procédure</h2>
          {retardsParEtape.length > 0 ? (
            <Bar
              data={{
                labels: retardsParEtape.map(r => {
                  const labels: Record<string,string> = { avis: 'Avis', notification: 'Notification', pv: 'PV', enquete: 'Enquête', rapport: 'Rapport', redressement: 'Redressement', reponse_contribuable: 'Réponse', decision_finale: 'Décision' };
                  return labels[r.type_etape] || r.type_etape;
                }),
                datasets: [{
                  label: 'Retards',
                  data: retardsParEtape.map(r => r.cnt),
                  backgroundColor: '#EF4444',
                  borderRadius: 4,
                }]
              }}
              options={{ responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }}
            />
          ) : <p className="text-gray-400 text-sm text-center py-8">Aucun retard</p>}
        </div>
      </div>
    </div>
  );
}
