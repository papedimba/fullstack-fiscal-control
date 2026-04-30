import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import Layout from './components/Layout';
import LoginPage from './pages/LoginPage';
import DashboardPage from './pages/DashboardPage';
import DossiersPage from './pages/DossiersPage';
import DossierDetailPage from './pages/DossierDetailPage';
import ProceduresPage from './pages/ProceduresPage';
import AlertesPage from './pages/AlertesPage';
import StatistiquesPage from './pages/StatistiquesPage';
import ExportPage from './pages/ExportPage';
import UtilisateursPage from './pages/UtilisateursPage';

function ProtectedRoute({ children, adminOnly = false }: { children: React.ReactNode; adminOnly?: boolean }) {
  const { user, isLoading } = useAuth();
  if (isLoading) return <div className="flex items-center justify-center h-screen"><div className="animate-spin rounded-full h-10 w-10 border-4 border-primary-200 border-t-primary-800" /></div>;
  if (!user) return <Navigate to="/login" replace />;
  if (adminOnly && user.role !== 'directeur' && user.role !== 'sous_directeur') return <Navigate to="/dashboard" replace />;
  return <Layout>{children}</Layout>;
}

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/login" element={<PublicRoute><LoginPage /></PublicRoute>} />
          <Route path="/dashboard" element={<ProtectedRoute><DashboardPage /></ProtectedRoute>} />
          <Route path="/dossiers" element={<ProtectedRoute><DossiersPage /></ProtectedRoute>} />
          <Route path="/dossiers/:id" element={<ProtectedRoute><DossierDetailPage /></ProtectedRoute>} />
          <Route path="/procedures" element={<ProtectedRoute><ProceduresPage /></ProtectedRoute>} />
          <Route path="/alertes" element={<ProtectedRoute><AlertesPage /></ProtectedRoute>} />
          <Route path="/statistiques" element={<ProtectedRoute><StatistiquesPage /></ProtectedRoute>} />
          <Route path="/export" element={<ProtectedRoute><ExportPage /></ProtectedRoute>} />
          <Route path="/utilisateurs" element={<ProtectedRoute adminOnly><UtilisateursPage /></ProtectedRoute>} />
          <Route path="/" element={<Navigate to="/dashboard" replace />} />
          <Route path="*" element={<Navigate to="/dashboard" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

function PublicRoute({ children }: { children: React.ReactNode }) {
  const { user, isLoading } = useAuth();
  if (isLoading) return null;
  if (user) return <Navigate to="/dashboard" replace />;
  return <>{children}</>;
}
