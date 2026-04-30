import React, { useState, useEffect } from 'react';
import { NavLink, useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import api from '../services/api';
import {
  LayoutDashboard, FolderOpen, GitBranch, Bell, BarChart2,
  Download, Users, LogOut, Menu, X, Scale, ChevronDown
} from 'lucide-react';

const ROLE_LABELS: Record<string, string> = {
  directeur: 'Directeur',
  sous_directeur: 'Sous-Directeur',
  chef_brigade: 'Chef de Brigade',
  agent: 'Agent',
};

const NAV_ITEMS = [
  { to: '/dashboard', icon: LayoutDashboard, label: 'Tableau de Bord' },
  { to: '/dossiers', icon: FolderOpen, label: 'Dossiers' },
  { to: '/procedures', icon: GitBranch, label: 'Procédures' },
  { to: '/alertes', icon: Bell, label: 'Alertes' },
  { to: '/statistiques', icon: BarChart2, label: 'Statistiques' },
  { to: '/export', icon: Download, label: 'Rapports & Export' },
];

const ADMIN_ITEMS = [
  { to: '/utilisateurs', icon: Users, label: 'Utilisateurs & Droits' },
];

export default function Layout({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [alertCount, setAlertCount] = useState(0);
  const [userMenuOpen, setUserMenuOpen] = useState(false);

  useEffect(() => {
    api.get('/alertes/count').then(r => setAlertCount(r.data.count)).catch(() => {});
    const interval = setInterval(() => {
      api.get('/alertes/count').then(r => setAlertCount(r.data.count)).catch(() => {});
    }, 30000);
    return () => clearInterval(interval);
  }, []);

  const handleLogout = () => { logout(); navigate('/login'); };

  const canAdmin = user?.role === 'directeur' || user?.role === 'sous_directeur';

  return (
    <div className="flex h-screen bg-gray-50 overflow-hidden">
      {/* Overlay mobile */}
      {sidebarOpen && (
        <div className="fixed inset-0 bg-black/40 z-20 lg:hidden" onClick={() => setSidebarOpen(false)} />
      )}

      {/* Sidebar */}
      <aside className={`fixed lg:static inset-y-0 left-0 z-30 w-64 bg-primary-900 text-white flex flex-col transition-transform duration-300 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'} lg:translate-x-0`}>
        {/* Logo */}
        <div className="flex items-center gap-3 px-6 py-5 border-b border-primary-700">
          <Scale className="h-8 w-8 text-yellow-400 flex-shrink-0" />
          <div>
            <h1 className="font-bold text-sm leading-tight">Contrôle Fiscal</h1>
            <p className="text-primary-300 text-xs">Direction des Vérifications</p>
          </div>
        </div>

        {/* Navigation */}
        <nav className="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">
          {NAV_ITEMS.map(({ to, icon: Icon, label }) => (
            <NavLink
              key={to}
              to={to}
              onClick={() => setSidebarOpen(false)}
              className={({ isActive }) =>
                `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors relative ${isActive ? 'bg-primary-700 text-white' : 'text-primary-200 hover:bg-primary-800 hover:text-white'}`
              }
            >
              <Icon className="h-5 w-5 flex-shrink-0" />
              <span>{label}</span>
              {label === 'Alertes' && alertCount > 0 && (
                <span className="ml-auto bg-red-500 text-white text-xs rounded-full min-w-[20px] h-5 flex items-center justify-center px-1">
                  {alertCount > 99 ? '99+' : alertCount}
                </span>
              )}
            </NavLink>
          ))}

          {canAdmin && (
            <>
              <div className="pt-4 pb-2 px-3">
                <p className="text-primary-400 text-xs font-semibold uppercase tracking-wider">Administration</p>
              </div>
              {ADMIN_ITEMS.map(({ to, icon: Icon, label }) => (
                <NavLink
                  key={to}
                  to={to}
                  onClick={() => setSidebarOpen(false)}
                  className={({ isActive }) =>
                    `flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors ${isActive ? 'bg-primary-700 text-white' : 'text-primary-200 hover:bg-primary-800 hover:text-white'}`
                  }
                >
                  <Icon className="h-5 w-5 flex-shrink-0" />
                  <span>{label}</span>
                </NavLink>
              ))}
            </>
          )}
        </nav>

        {/* User info */}
        <div className="border-t border-primary-700 p-4">
          <div className="relative">
            <button
              onClick={() => setUserMenuOpen(!userMenuOpen)}
              className="w-full flex items-center gap-3 hover:bg-primary-800 rounded-lg p-2 transition-colors"
            >
              <div className="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center text-sm font-bold flex-shrink-0">
                {user?.prenom?.[0]}{user?.nom?.[0]}
              </div>
              <div className="flex-1 text-left min-w-0">
                <p className="text-sm font-medium truncate">{user?.prenom} {user?.nom}</p>
                <p className="text-primary-300 text-xs">{user?.role ? ROLE_LABELS[user.role] : ''}</p>
              </div>
              <ChevronDown className="h-4 w-4 text-primary-300 flex-shrink-0" />
            </button>
            {userMenuOpen && (
              <div className="absolute bottom-full left-0 right-0 mb-1 bg-white rounded-lg shadow-lg border border-gray-200 overflow-hidden">
                <button
                  onClick={handleLogout}
                  className="w-full flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors"
                >
                  <LogOut className="h-4 w-4" /> Déconnexion
                </button>
              </div>
            )}
          </div>
        </div>
      </aside>

      {/* Main */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Top bar mobile */}
        <header className="lg:hidden bg-white border-b border-gray-200 px-4 py-3 flex items-center gap-3">
          <button onClick={() => setSidebarOpen(true)} className="p-1 rounded-lg hover:bg-gray-100">
            <Menu className="h-6 w-6" />
          </button>
          <Scale className="h-6 w-6 text-primary-800" />
          <span className="font-semibold text-primary-900">Contrôle Fiscal</span>
        </header>

        <main className="flex-1 overflow-y-auto p-4 lg:p-6">
          {children}
        </main>
      </div>
    </div>
  );
}
