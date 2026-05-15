<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/config.php';

Auth::start();

// Déconnexion
if (isset($_GET['logout'])) { Auth::logout(); header('Location: index.php'); exit; }

$user       = Auth::user();
$isLoggedIn = $user !== null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contrôle Fiscal – Tableau de Bord</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: { extend: { colors: { primary: {
        50:'#EEF2F8',100:'#D8E2F0',200:'#B0C5E0',300:'#89A8D1',
        400:'#628BC2',500:'#3B6EB3',600:'#2F5890',700:'#23426C',800:'#1E3A5F',900:'#162B47'
      }}}}
    }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
  <style>
    :root { --c-bg:#F9FAFB;--c-surface:#FFFFFF;--c-border:#E5E7EB;--c-text:#111827;--c-text2:#6B7280; }
    body.dark { --c-bg:#0F172A;--c-surface:#1E293B;--c-border:#334155;--c-text:#F1F5F9;--c-text2:#94A3B8; }
    body { background:var(--c-bg);color:var(--c-text); }
    .sidebar-link { display:flex;align-items:center;gap:.75rem;padding:.625rem .75rem;border-radius:.5rem;font-size:.875rem;font-weight:500;color:#B0C5E0;transition:background .15s,color .15s;cursor:pointer; }
    .sidebar-link:hover,.sidebar-link.active { background:#23426C;color:#fff; }
    .tab-btn { padding:.375rem 1rem;border-radius:.5rem;font-size:.875rem;font-weight:500;background:transparent;color:var(--c-text2);transition:all .15s;cursor:pointer;border:none; }
    .tab-btn.active { background:var(--c-surface);color:var(--c-text);box-shadow:0 1px 3px rgba(0,0,0,.1); }
    .spinner { width:2.5rem;height:2.5rem;border:4px solid #D8E2F0;border-top-color:#1E3A5F;border-radius:50%;animation:spin .7s linear infinite; }
    @keyframes spin{to{transform:rotate(360deg)}}
    .badge { display:inline-flex;align-items:center;padding:.125rem .625rem;border-radius:9999px;font-size:.75rem;font-weight:500; }
    .modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:50;display:flex;align-items:center;justify-content:center;padding:1rem; }
    .modal-box { background:var(--c-surface);color:var(--c-text);border-radius:1rem;box-shadow:0 25px 50px rgba(0,0,0,.25);width:100%;max-width:32rem;max-height:90vh;overflow-y:auto; }
    .card { background:var(--c-surface);border-radius:.75rem;border:1px solid var(--c-border);box-shadow:0 1px 3px rgba(0,0,0,.06);padding:1.5rem; }
    .input { width:100%;background:var(--c-surface);color:var(--c-text);border:1px solid var(--c-border);border-radius:.5rem;padding:.5rem .75rem;font-size:.875rem;outline:none;transition:border .15s,box-shadow .15s; }
    .input:focus { border-color:#3B6EB3;box-shadow:0 0 0 3px rgba(59,110,179,.15); }
    .label { display:block;font-size:.875rem;font-weight:500;color:var(--c-text);margin-bottom:.25rem; }
    .btn-primary { background:#1E3A5F;color:#fff;padding:.5rem 1rem;border-radius:.5rem;font-weight:500;border:none;cursor:pointer;transition:background .15s; }
    .btn-primary:hover { background:#23426C; }
    .btn-primary:disabled { opacity:.5;cursor:not-allowed; }
    .btn-secondary { background:var(--c-surface);color:#1E3A5F;border:1px solid #B0C5E0;padding:.5rem 1rem;border-radius:.5rem;font-weight:500;cursor:pointer;transition:background .15s; }
    .btn-secondary:hover { background:#EEF2F8; }
    .btn-danger { background:#DC2626;color:#fff;padding:.5rem 1rem;border-radius:.5rem;font-weight:500;border:none;cursor:pointer; }
    .btn-danger:hover { background:#B91C1C; }
    .tl-border { border-left:4px solid var(--c-border); }
    .tl-termine { border-color:#22C55E; }
    .tl-retard  { border-color:#EF4444; }
    .tl-en_cours{ border-color:#3B82F6; }
    .progress-bar { height:.5rem;background:var(--c-border);border-radius:9999px;overflow:hidden; }
    .progress-fill { height:100%;background:#1E3A5F;border-radius:9999px;transition:width .3s; }
    #searchDrop { background:var(--c-surface);border:1px solid var(--c-border);border-radius:.5rem;box-shadow:0 8px 24px rgba(0,0,0,.15);max-height:20rem;overflow-y:auto; }
    .kanban-col { flex:0 0 220px;background:var(--c-bg);border-radius:.75rem;padding:.75rem; }
    .kanban-card { background:var(--c-surface);border:1px solid var(--c-border);border-radius:.5rem;padding:.75rem;margin-bottom:.5rem;cursor:pointer; }
    .detail-tab-bar { background:var(--c-bg);padding:.25rem;border-radius:.5rem; }
  </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

<?php if (!$isLoggedIn): ?>
<!-- ===== LOGIN ===== -->
<div class="min-h-screen bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 bg-white/10 rounded-2xl mb-4">
        <svg class="w-9 h-9 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
        </svg>
      </div>
      <h1 class="text-2xl font-bold text-white">Contrôle Fiscal</h1>
      <p class="text-primary-200 mt-1 text-sm">Direction des Vérifications Fiscales Nationale</p>
    </div>
    <div class="bg-white rounded-2xl shadow-2xl p-8">
      <!-- Formulaire de connexion principal -->
      <div id="loginBox">
        <h2 class="text-xl font-semibold mb-6">Connexion</h2>
        <form id="loginForm" class="space-y-5">
          <div>
            <label class="label">Email</label>
            <input type="email" id="loginEmail" class="input" placeholder="votre@email.ci" required>
          </div>
          <div>
            <label class="label">Mot de passe</label>
            <div class="relative">
              <input type="password" id="loginPass" class="input pr-10" placeholder="••••••••" required>
              <button type="button" onclick="togglePass()" class="absolute right-3 top-2.5 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
              </button>
            </div>
          </div>
          <div id="loginErr" class="hidden bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg"></div>
          <button type="submit" id="loginBtn" class="btn-primary w-full py-2.5">Se connecter</button>
          <p class="text-center text-sm"><button type="button" onclick="showResetForm()" class="text-primary-600 hover:underline">Mot de passe oublié ?</button></p>
        </form>
      </div>
      <!-- Étape 2FA -->
      <div id="twoFABox" class="hidden space-y-5">
        <h2 class="text-xl font-semibold mb-2">Authentification 2FA</h2>
        <p class="text-sm text-gray-500">Saisissez le code à 6 chiffres de votre application d'authentification.</p>
        <input type="text" id="totpCode" class="input text-center text-2xl tracking-widest" maxlength="6" placeholder="000000" inputmode="numeric">
        <div id="twoFAErr" class="hidden bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 rounded-lg"></div>
        <button onclick="submit2FA()" class="btn-primary w-full py-2.5">Vérifier</button>
        <p class="text-center text-sm"><button type="button" onclick="showLoginForm()" class="text-gray-400 hover:underline">← Retour</button></p>
      </div>
      <!-- Formulaire reset mot de passe -->
      <div id="resetBox" class="hidden space-y-5">
        <h2 class="text-xl font-semibold mb-2">Mot de passe oublié</h2>
        <p class="text-sm text-gray-500">Entrez votre email pour recevoir un lien de réinitialisation.</p>
        <input type="email" id="resetEmail" class="input" placeholder="votre@email.ci">
        <div id="resetMsg" class="hidden text-sm px-4 py-3 rounded-lg"></div>
        <button onclick="submitReset()" class="btn-primary w-full py-2.5">Envoyer le lien</button>
        <p class="text-center text-sm"><button type="button" onclick="showLoginForm()" class="text-gray-400 hover:underline">← Retour à la connexion</button></p>
      </div>
      <!-- Formulaire nouveau mot de passe (depuis lien email) -->
      <div id="newPassBox" class="hidden space-y-5">
        <h2 class="text-xl font-semibold mb-2">Nouveau mot de passe</h2>
        <input type="password" id="newPass1" class="input" placeholder="Nouveau mot de passe (min. 8 caractères)">
        <input type="password" id="newPass2" class="input" placeholder="Confirmer le mot de passe">
        <div id="newPassMsg" class="hidden text-sm px-4 py-3 rounded-lg"></div>
        <button onclick="submitNewPass()" class="btn-primary w-full py-2.5">Changer le mot de passe</button>
      </div>
      <div class="mt-6 pt-6 border-t border-gray-100">
        <p class="text-xs text-gray-500 mb-3 font-medium">Comptes démo (mot de passe : Admin1234!)</p>
        <div class="grid grid-cols-2 gap-2">
          <?php foreach ([
            ['Directeur',      'directeur@fiscalite.ci'],
            ['Sous-Directeur', 'sousdirecteur@fiscalite.ci'],
            ['Chef Brigade A', 'chef.brigade.a@fiscalite.ci'],
            ['Agent',          'agent1@fiscalite.ci'],
          ] as [$l,$e]): ?>
          <button type="button" onclick="fillLogin('<?=$e?>')"
            class="text-left px-3 py-2 text-xs bg-gray-50 hover:bg-primary-50 border border-gray-200 hover:border-primary-300 rounded-lg">
            <span class="font-medium text-primary-800 block"><?=$l?></span>
            <span class="text-gray-400 block truncate"><?=$e?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <p class="text-center text-primary-300 text-xs mt-6">© <?=date('Y')?> Direction des Vérifications Fiscales Nationale</p>
  </div>
</div>

<?php else: ?>
<!-- ===== APP ===== -->
<div class="flex h-screen overflow-hidden">

  <!-- SIDEBAR -->
  <aside class="w-64 bg-primary-900 text-white flex flex-col flex-shrink-0">
    <div class="flex items-center gap-3 px-6 py-5 border-b border-primary-700">
      <svg class="h-8 w-8 text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
      </svg>
      <div>
        <h1 class="font-bold text-sm leading-tight">Contrôle Fiscal</h1>
        <p class="text-primary-300 text-xs">Direction des Vérifications</p>
      </div>
    </div>

    <!-- Recherche globale -->
    <div class="px-3 py-2 relative">
      <div class="relative">
        <svg class="absolute left-2.5 top-2.5 w-4 h-4 text-primary-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/></svg>
        <input id="gSearch" type="text" placeholder="Rechercher…" class="w-full bg-primary-800 text-white placeholder-primary-300 text-sm rounded-lg pl-8 pr-3 py-2 outline-none focus:ring-2 focus:ring-primary-500" oninput="onGSearch(this.value)" onfocus="document.getElementById('searchDrop').classList.remove('hidden')" autocomplete="off">
      </div>
      <div id="searchDrop" class="hidden absolute left-3 right-3 top-full mt-1 z-50 rounded-lg shadow-lg overflow-hidden"></div>
    </div>

    <nav class="flex-1 px-3 py-2 space-y-0.5 overflow-y-auto">
      <?php
      $navItems = [
        ['dashboard',    'Tableau de Bord', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['dossiers',     'Dossiers',        'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z'],
        ['contribuables','Contribuables',   'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
        ['calendrier',   'Calendrier',      'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['procedures',   'Procédures',      'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15'],
        ['alertes',      'Alertes',         'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
        ['statistiques', 'Statistiques',    'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ['export',       'Rapports & Export','M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4'],
      ];
      foreach ($navItems as [$p,$l,$icon]): ?>
      <a class="sidebar-link <?=$p==='dashboard'?'active':''?>" onclick="nav('<?=$p?>')" id="nav-<?=$p?>">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?=$icon?>"/>
        </svg>
        <span><?=$l?></span>
        <?php if($p==='alertes'):?><span id="alertBadge" class="ml-auto bg-red-500 text-white text-xs rounded-full min-w-[20px] h-5 flex items-center justify-center px-1 hidden"></span><?php endif;?>
      </a>
      <?php endforeach; ?>

      <?php if (in_array($user['role'],['directeur','sous_directeur'])): ?>
      <div class="pt-4 pb-2 px-3"><p class="text-primary-400 text-xs font-semibold uppercase tracking-wider">Administration</p></div>
      <a class="sidebar-link" onclick="nav('utilisateurs')" id="nav-utilisateurs">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        <span>Utilisateurs & Droits</span>
      </a>
      <a class="sidebar-link" onclick="nav('audit')" id="nav-audit">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <span>Journal d'audit</span>
      </a>
      <a class="sidebar-link" onclick="nav('sauvegarde')" id="nav-sauvegarde">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
        <span>Sauvegarde</span>
      </a>
      <?php endif; ?>
    </nav>

    <div class="border-t border-primary-700 p-4">
      <div class="relative">
        <button onclick="document.getElementById('uDrop').classList.toggle('hidden')"
          class="w-full flex items-center gap-3 hover:bg-primary-800 rounded-lg p-2 transition-colors">
          <div class="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center text-sm font-bold flex-shrink-0">
            <?=mb_substr($user['prenom'],0,1).mb_substr($user['nom'],0,1)?>
          </div>
          <div class="flex-1 text-left min-w-0">
            <p class="text-sm font-medium truncate"><?=htmlspecialchars($user['prenom'].' '.$user['nom'])?></p>
            <p class="text-primary-300 text-xs"><?=htmlspecialchars($user['role'])?></p>
          </div>
          <svg class="h-4 w-4 text-primary-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
        <div id="uDrop" class="hidden absolute bottom-full left-0 right-0 mb-1 bg-white rounded-lg shadow-lg border border-gray-200">
          <button onclick="showProfileModal();document.getElementById('uDrop').classList.add('hidden')" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 rounded-t-lg w-full text-left">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Mon profil &amp; 2FA
          </button>
          <button onclick="toggleDark();document.getElementById('uDrop').classList.add('hidden')" class="flex items-center gap-2 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 w-full text-left">
            <svg id="darkIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            Mode sombre
          </button>
          <a href="?logout=1" class="flex items-center gap-2 px-4 py-3 text-sm text-red-600 hover:bg-red-50 rounded-b-lg">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            Déconnexion
          </a>
        </div>
      </div>
    </div>
  </aside>

  <!-- MAIN -->
  <main class="flex-1 overflow-y-auto">
    <div id="pg" class="p-6">
      <div class="flex items-center justify-center h-64"><div class="spinner"></div></div>
    </div>
  </main>
</div>
<?php endif; ?>

<div id="modalContainer"></div>
<div id="toast" class="fixed bottom-4 right-4 z-50 hidden px-5 py-3 rounded-xl shadow-lg text-sm font-medium text-white"></div>

<script>
const APP = {
  user: <?=json_encode($isLoggedIn?['id'=>$user['id'],'nom'=>$user['nom'],'prenom'=>$user['prenom'],'role'=>$user['role'],'brigade_id'=>$user['brigade_id']]:null)?>,
  charts: {},
  currentPage: 'dashboard'
};

// ── Dark mode ─────────────────────────────────────────────────
if (localStorage.getItem('dark') === '1') document.body.classList.add('dark');
function toggleDark() {
  const on = document.body.classList.toggle('dark');
  localStorage.setItem('dark', on ? '1' : '0');
}

// ── Global search ─────────────────────────────────────────────
let _gst = null;
function onGSearch(q) {
  clearTimeout(_gst);
  const drop = document.getElementById('searchDrop');
  if (q.length < 2) { drop.innerHTML = ''; drop.classList.add('hidden'); return; }
  _gst = setTimeout(async () => {
    const r = await req('api/search.php?q=' + encodeURIComponent(q)).catch(() => null);
    if (!r) return;
    const sections = [
      ['Dossiers', r.dossiers, d => `<div class="px-4 py-2 hover:bg-gray-50 cursor-pointer text-sm" onclick="nav('dossierDetail','${d.id}');document.getElementById('searchDrop').classList.add('hidden')"><span class="font-mono font-bold text-primary-700">${d.numero_dossier}</span> <span class="text-gray-500">— ${d.contribuable_nom||''}</span></div>`],
      ['Contribuables', r.contribuables, c => `<div class="px-4 py-2 hover:bg-gray-50 cursor-pointer text-sm" onclick="nav('contribuables');document.getElementById('searchDrop').classList.add('hidden')"><span class="font-medium">${c.nom}</span> <span class="text-gray-400 text-xs">${c.nif}</span></div>`],
      ['Utilisateurs', r.users, u => `<div class="px-4 py-2 text-sm"><span class="font-medium">${u.nom_complet||u.prenom+' '+u.nom}</span> <span class="text-gray-400 text-xs">${u.email}</span></div>`],
    ];
    let html = '';
    sections.forEach(([title, items, fn]) => {
      if (!items?.length) return;
      html += `<div class="px-4 py-1.5 text-xs font-semibold text-gray-400 uppercase bg-gray-50 border-b">${title}</div>`;
      items.forEach(i => { html += fn(i); });
    });
    drop.innerHTML = html || '<div class="px-4 py-3 text-sm text-gray-400">Aucun résultat</div>';
    drop.classList.remove('hidden');
  }, 250);
}
document.addEventListener('click', e => {
  const drop = document.getElementById('searchDrop');
  if (drop && !drop.contains(e.target) && e.target.id !== 'gSearch') drop.classList.add('hidden');
});

const TYPE_ETAPE = {avis:'Avis de vérification',notification:'Notification',pv:'Procès-verbal',enquete:'Enquête',rapport:'Rapport',redressement:'Notification de redressement',reponse_contribuable:'Réponse du contribuable',decision_finale:'Décision finale'};
const TYPE_CONTROLE = {verification_comptabilite:'Vérification comptabilité',verification_ponctuelle:'Vérification ponctuelle',droit_communication:'Droit de communication',controle_sur_pieces:'Contrôle sur pièces'};
const STATUT_C = {ouvert:'bg-blue-100 text-blue-800',en_cours:'bg-amber-100 text-amber-800',suspendu:'bg-gray-100 text-gray-700',cloture:'bg-green-100 text-green-800',contentieux:'bg-red-100 text-red-800'};
const ETAPE_C  = {en_attente:'bg-gray-100 text-gray-600',en_cours:'bg-blue-100 text-blue-700',termine:'bg-green-100 text-green-700',retard:'bg-red-100 text-red-700'};
const ALERTE_C = {critique:'bg-red-100 text-red-700',avertissement:'bg-amber-100 text-amber-700',info:'bg-blue-100 text-blue-700'};
const ROLE_L   = {directeur:'Directeur',sous_directeur:'Sous-Directeur',chef_brigade:'Chef de Brigade',agent:'Agent'};
const ROLE_C   = {directeur:'bg-purple-100 text-purple-800',sous_directeur:'bg-blue-100 text-blue-800',chef_brigade:'bg-amber-100 text-amber-800',agent:'bg-gray-100 text-gray-700'};

// ── Helpers ──────────────────────────────────────────────────
async function req(url, opts={}) {
  const r = await fetch(url,{credentials:'same-origin',...opts,headers:{'Content-Type':'application/json',...(opts.headers||{})}});
  const d = await r.json().catch(()=>({}));
  if(!r.ok) throw new Error(d.error||`HTTP ${r.status}`);
  return d;
}
function pg(h)  { document.getElementById('pg').innerHTML=h; }
function bd(t,c){ return `<span class="badge ${c}">${t}</span>`; }
function sb(s)  { return bd(s,STATUT_C[s]||'bg-gray-100 text-gray-600'); }
function eb(s)  { return bd(s,ETAPE_C[s]||'bg-gray-100 text-gray-600'); }
function ab(t)  { return bd(t,ALERTE_C[t]||'bg-gray-100 text-gray-600'); }
function fd(d)  { if(!d||d==='null') return '—'; try{return new Date(d).toLocaleDateString('fr-FR');}catch{return d;} }
function ago(d) { if(!d) return ''; const diff=Math.floor((Date.now()-new Date(d))/86400000); if(diff<=0) return"aujourd'hui"; if(diff===1) return'hier'; if(diff<7) return`il y a ${diff}j`; return fd(d); }
function toast(m,t='ok') {
  const el=document.getElementById('toast');
  el.className=`fixed bottom-4 right-4 z-50 px-5 py-3 rounded-xl shadow-lg text-sm font-medium text-white ${t==='err'?'bg-red-600':'bg-green-600'}`;
  el.textContent=m; el.classList.remove('hidden');
  setTimeout(()=>el.classList.add('hidden'),3000);
}
function modal(h)  { document.getElementById('modalContainer').innerHTML=`<div class="modal-overlay" onclick="if(event.target===this)cModal()"><div class="modal-box">${h}</div></div>`; }
function cModal()  { document.getElementById('modalContainer').innerHTML=''; }
function spin()    { pg('<div class="flex items-center justify-center h-64"><div class="spinner"></div></div>'); }
function kpi(l,v,c){ return `<div class="card p-4"><p class="text-2xl font-bold" style="color:${c}">${v}</p><p class="text-xs text-gray-500 mt-1">${l}</p></div>`; }
function ir(l,v)   { return `<div class="flex justify-between gap-2 text-sm"><dt class="text-gray-500">${l}</dt><dd class="font-medium text-right">${v}</dd></div>`; }

// ── Nav ───────────────────────────────────────────────────────
function nav(page,param=null) {
  Object.values(APP.charts).forEach(c=>c.destroy()); APP.charts={};
  APP.currentPage = page;
  document.querySelectorAll('.sidebar-link').forEach(e=>e.classList.remove('active'));
  const el=document.getElementById('nav-'+page); if(el)el.classList.add('active');
  spin();
  ({dashboard,dossiers,contribuables,calendrier,procedures,alertes,statistiques,export:exportPage,utilisateurs,dossierDetail,audit,sauvegarde})[page]?.(param);
}

// ── Login ─────────────────────────────────────────────────────
<?php if(!$isLoggedIn): ?>
function fillLogin(e){ document.getElementById('loginEmail').value=e; document.getElementById('loginPass').value='Admin1234!'; }
function togglePass(){ const i=document.getElementById('loginPass'); i.type=i.type==='password'?'text':'password'; }
function showLoginForm(){ ['loginBox','twoFABox','resetBox','newPassBox'].forEach(id=>document.getElementById(id).classList.add('hidden')); document.getElementById('loginBox').classList.remove('hidden'); }
function showResetForm(){ ['loginBox','twoFABox','resetBox','newPassBox'].forEach(id=>document.getElementById(id).classList.add('hidden')); document.getElementById('resetBox').classList.remove('hidden'); }

// Vérifier si on a un token de reset dans l'URL
(function() {
  const params = new URLSearchParams(location.search);
  const token  = params.get('reset_token');
  if (token) {
    ['loginBox','twoFABox','resetBox'].forEach(id=>document.getElementById(id).classList.add('hidden'));
    document.getElementById('newPassBox').classList.remove('hidden');
    document.getElementById('newPassBox').dataset.token = token;
  }
})();

document.getElementById('loginForm').addEventListener('submit', async e=>{
  e.preventDefault();
  const btn=document.getElementById('loginBtn'), err=document.getElementById('loginErr');
  btn.textContent='Connexion…'; btn.disabled=true; err.classList.add('hidden');
  try {
    const r=await req('api/auth.php?action=login',{method:'POST',body:JSON.stringify({email:document.getElementById('loginEmail').value,password:document.getElementById('loginPass').value})});
    if (r.requires_2fa) {
      document.getElementById('loginBox').classList.add('hidden');
      document.getElementById('twoFABox').classList.remove('hidden');
      document.getElementById('totpCode').focus();
    } else { location.reload(); }
  } catch(ex){ err.textContent=ex.message; err.classList.remove('hidden'); btn.textContent='Se connecter'; btn.disabled=false; }
});

async function submit2FA() {
  const code=document.getElementById('totpCode').value.trim(), err=document.getElementById('twoFAErr');
  err.classList.add('hidden');
  try { await req('api/auth.php?action=verify_2fa',{method:'POST',body:JSON.stringify({code})}); location.reload(); }
  catch(ex){ err.textContent=ex.message; err.classList.remove('hidden'); }
}
document.getElementById('totpCode')?.addEventListener('keydown',e=>{ if(e.key==='Enter') submit2FA(); });

async function submitReset() {
  const email=document.getElementById('resetEmail').value.trim(), msg=document.getElementById('resetMsg');
  try {
    const r=await req('api/password_reset.php',{method:'POST',body:JSON.stringify({action:'request',email})});
    msg.className='text-sm px-4 py-3 rounded-lg bg-green-50 text-green-700'; msg.textContent=r.message; msg.classList.remove('hidden');
  } catch(ex){ msg.className='text-sm px-4 py-3 rounded-lg bg-red-50 text-red-700'; msg.textContent=ex.message; msg.classList.remove('hidden'); }
}

async function submitNewPass() {
  const p1=document.getElementById('newPass1').value, p2=document.getElementById('newPass2').value;
  const msg=document.getElementById('newPassMsg'), token=document.getElementById('newPassBox').dataset.token;
  msg.classList.add('hidden');
  if (p1.length < 8) { msg.className='text-sm px-4 py-3 rounded-lg bg-red-50 text-red-700'; msg.textContent='Minimum 8 caractères.'; msg.classList.remove('hidden'); return; }
  if (p1 !== p2)     { msg.className='text-sm px-4 py-3 rounded-lg bg-red-50 text-red-700'; msg.textContent='Les mots de passe ne correspondent pas.'; msg.classList.remove('hidden'); return; }
  try {
    await req('api/password_reset.php',{method:'POST',body:JSON.stringify({action:'reset',token,password:p1})});
    msg.className='text-sm px-4 py-3 rounded-lg bg-green-50 text-green-700'; msg.textContent='Mot de passe modifié ! Redirection…'; msg.classList.remove('hidden');
    setTimeout(()=>location.href='index.php',2000);
  } catch(ex){ msg.className='text-sm px-4 py-3 rounded-lg bg-red-50 text-red-700'; msg.textContent=ex.message; msg.classList.remove('hidden'); }
}
<?php endif; ?>

// ── DASHBOARD ─────────────────────────────────────────────────
async function dashboard() {
  const s=await req('api/stats.php'), t=s.totaux;
  pg(`<div class="space-y-6">
    <div><h1 class="text-2xl font-bold">Tableau de Bord</h1>
    <p class="text-gray-500 text-sm mt-1">Bonjour, ${APP.user.prenom} ${APP.user.nom} — ${new Date().toLocaleDateString('fr-FR',{weekday:'long',year:'numeric',month:'long',day:'numeric'})}</p></div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      ${kpi('Total dossiers',t.total,'#3B6EB3')}${kpi('En cours',t.enCours,'#D97706')}
      ${kpi('Clôturés',t.cloture,'#16A34A')}${kpi('Retards',t.retards,'#DC2626')}
      ${kpi('Alertes non lues',t.nonLues,'#EA580C')}${kpi('Suspendus',t.suspendu,'#6B7280')}
      ${kpi('Contentieux',t.contentieux,'#7C3AED')}${kpi('Ouverts',t.ouvert,'#2563EB')}
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="card lg:col-span-2"><h2 class="text-base font-semibold mb-4">Évolution mensuelle</h2><canvas id="cMois"></canvas></div>
      <div class="card"><h2 class="text-base font-semibold mb-4">Par type de contrôle</h2><canvas id="cType"></canvas></div>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="card"><h2 class="text-base font-semibold mb-4">Par agent</h2><canvas id="cAgent"></canvas></div>
      <div class="card"><h2 class="text-base font-semibold mb-4">Retards par étape</h2><canvas id="cRetard"></canvas></div>
    </div>
  </div>`);
  const ml=s.parMois.map(m=>{const[y,mo]=m.mois.split('-');return new Date(+y,+mo-1).toLocaleDateString('fr-FR',{month:'short',year:'2-digit'});});
  APP.charts.m=new Chart(document.getElementById('cMois'),{type:'line',data:{labels:ml,datasets:[{label:'Dossiers',data:s.parMois.map(m=>m.cnt),borderColor:'#1E3A5F',backgroundColor:'rgba(30,58,95,.08)',fill:true,tension:.4,pointBackgroundColor:'#1E3A5F'}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
  if(s.parType.length) APP.charts.t=new Chart(document.getElementById('cType'),{type:'doughnut',data:{labels:s.parType.map(t=>TYPE_CONTROLE[t.type_controle]||t.type_controle),datasets:[{data:s.parType.map(t=>t.cnt),backgroundColor:['#1E3A5F','#3B6EB3','#628BC2','#89A8D1'],borderWidth:2}]},options:{responsive:true,plugins:{legend:{position:'bottom',labels:{font:{size:11}}}}}});
  if(s.parAgent.length) APP.charts.a=new Chart(document.getElementById('cAgent'),{type:'bar',data:{labels:s.parAgent.map(a=>a.agent),datasets:[{label:'Dossiers',data:s.parAgent.map(a=>a.cnt),backgroundColor:'#3B6EB3',borderRadius:4}]},options:{responsive:true,indexAxis:'y',plugins:{legend:{display:false}},scales:{y:{ticks:{font:{size:11}}}}}});
  const rl={avis:'Avis',notification:'Notif.',pv:'PV',enquete:'Enquête',rapport:'Rapport',redressement:'Redress.',reponse_contribuable:'Réponse',decision_finale:'Décision'};
  if(s.retardsParEtape.length) APP.charts.r=new Chart(document.getElementById('cRetard'),{type:'bar',data:{labels:s.retardsParEtape.map(r=>rl[r.type_etape]||r.type_etape),datasets:[{label:'Retards',data:s.retardsParEtape.map(r=>r.cnt),backgroundColor:'#EF4444',borderRadius:4}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
}

// ── DOSSIERS ──────────────────────────────────────────────────
let _ds={search:'',statut:'',type:''};
window._contrib=[]; window._agents=[]; window._brigades=[];

async function dossiers() {
  const [contrib,agents,brig]=await Promise.all([req('api/contribuables.php'),req('api/users.php').catch(()=>[]),req('api/brigades.php')]);
  window._contrib=contrib; window._agents=agents; window._brigades=brig;
  await _renderDossiers();
}

function showDossierForm() {
  const contrib=window._contrib, agents=window._agents;
  const canPickAgent=['directeur','sous_directeur'].includes(APP.user.role);
  modal(`<div class="flex items-center justify-between p-6 border-b"><h2 class="text-lg font-semibold">Nouveau dossier</h2><button onclick="cModal()" class="text-gray-400">✕</button></div>
  <form onsubmit="submitDossier(event)" class="p-6 space-y-4">
    <div><label class="label">Type de contrôle *</label><select name="type_controle" class="input">${Object.entries(TYPE_CONTROLE).map(([k,v])=>`<option value="${k}">${v}</option>`).join('')}</select></div>
    <div><label class="label">Contribuable *</label><select name="contribuable_id" class="input" required><option value="">Sélectionner…</option>${contrib.map(c=>`<option value="${c.id}">${c.nom} — ${c.nif}</option>`).join('')}</select></div>
    ${canPickAgent?`<div><label class="label">Agent *</label><select name="agent_id" class="input">${agents.filter(u=>+u.actif).map(u=>`<option value="${u.id}">${u.prenom} ${u.nom} (${u.role})</option>`).join('')}</select></div>`:`<input type="hidden" name="agent_id" value="${APP.user.id}">`}
    <div><label class="label">Date ouverture *</label><input type="date" name="date_ouverture" class="input" value="${new Date().toISOString().split('T')[0]}" required></div>
    <div><label class="label">Observations</label><textarea name="observations" class="input" rows="2"></textarea></div>
    <div id="dErr" class="hidden bg-red-50 text-red-700 text-sm px-3 py-2 rounded border border-red-200"></div>
    <div class="flex gap-3"><button type="button" onclick="cModal()" class="btn-secondary flex-1">Annuler</button><button type="submit" class="btn-primary flex-1">Créer</button></div>
  </form>`);
}

async function submitDossier(e) {
  e.preventDefault();
  const fd=new FormData(e.target), err=document.getElementById('dErr');
  try {
    const r=await req('api/dossiers.php',{method:'POST',body:JSON.stringify(Object.fromEntries(fd))});
    cModal(); toast(`Dossier ${r.numero_dossier} créé`); nav('dossierDetail',r.id);
  } catch(ex){ err.textContent=ex.message; err.classList.remove('hidden'); }
}

function showContribuableForm(c=null) {
  const types=['SA','SARL','SAS','EURL','SNC','Entreprise individuelle'];
  modal(`<div class="flex items-center justify-between p-6 border-b"><h2 class="text-lg font-semibold">${c?'Modifier':'Nouveau'} contribuable</h2><button onclick="cModal()" class="text-gray-400">✕</button></div>
  <form onsubmit="submitContribuable(event,${c?c.id:'null'})" class="p-6 space-y-4">
    <div><label class="label">Nom / Raison sociale *</label><input name="nom" class="input" value="${c?c.nom:''}" required placeholder="Ex: SOCIÉTÉ IVOIRIENNE DE TRANSIT SA"></div>
    <div class="grid grid-cols-2 gap-3">
      <div><label class="label">NIF *</label><input name="nif" class="input" value="${c?c.nif:''}" required placeholder="CI-ABJ-2025-B-XXXXX" ${c?'readonly title="Le NIF ne peut pas être modifié"':''}></div>
      <div><label class="label">Type d'entreprise *</label><select name="type_entreprise" class="input">${types.map(t=>`<option value="${t}" ${c&&c.type_entreprise===t?'selected':''}>${t}</option>`).join('')}</select></div>
    </div>
    <div><label class="label">Secteur d'activité</label><input name="secteur_activite" class="input" value="${c?c.secteur_activite||'':''}" placeholder="Ex: Transport & Logistique"></div>
    <div><label class="label">Adresse</label><input name="adresse" class="input" value="${c?c.adresse||'':''}" placeholder="Ex: Abidjan, Plateau"></div>
    <div id="cErr" class="hidden bg-red-50 text-red-700 text-sm px-3 py-2 rounded border border-red-200"></div>
    <div class="flex gap-3"><button type="button" onclick="cModal()" class="btn-secondary flex-1">Annuler</button><button type="submit" class="btn-primary flex-1">${c?'Enregistrer':'Créer'}</button></div>
  </form>`);
}

async function submitContribuable(e,id) {
  e.preventDefault();
  const fd=new FormData(e.target),err=document.getElementById('cErr');
  try {
    if(id) await req(`api/contribuables.php?id=${id}`,{method:'PUT',body:JSON.stringify(Object.fromEntries(fd))});
    else   await req('api/contribuables.php',{method:'POST',body:JSON.stringify(Object.fromEntries(fd))});
    cModal(); toast(id?'Contribuable modifié':'Contribuable créé'); nav('contribuables');
  } catch(ex){ err.textContent=ex.message; err.classList.remove('hidden'); }
}

function showEditDossier(id) {
  modal(`<div class="flex items-center justify-between p-6 border-b"><h2 class="text-lg font-semibold">Modifier le dossier</h2><button onclick="cModal()" class="text-gray-400">✕</button></div>
  <form onsubmit="submitEditDossier(event,${id})" class="p-6 space-y-4">
    <div><label class="label">Statut</label><select name="statut" class="input">${['ouvert','en_cours','suspendu','cloture','contentieux'].map(s=>`<option value="${s}">${s}</option>`).join('')}</select></div>
    <div><label class="label">Observations</label><textarea name="observations" class="input" rows="3"></textarea></div>
    <div class="flex gap-3"><button type="button" onclick="cModal()" class="btn-secondary flex-1">Annuler</button><button type="submit" class="btn-primary flex-1">Sauvegarder</button></div>
  </form>`);
}
async function submitEditDossier(e,id){ e.preventDefault(); const fd=new FormData(e.target); await req(`api/dossiers.php?id=${id}`,{method:'PUT',body:JSON.stringify(Object.fromEntries(fd))}); cModal(); toast('Dossier mis à jour'); nav('dossierDetail',id); }

async function delDossier(id,num){ if(!confirm(`Supprimer ${num} ?`))return; await req(`api/dossiers.php?id=${id}`,{method:'DELETE'}); toast('Dossier supprimé'); nav('dossiers'); }

function showAddEtape(did) {
  modal(`<div class="flex items-center justify-between p-6 border-b"><h2 class="text-lg font-semibold">Ajouter une étape</h2><button onclick="cModal()" class="text-gray-400">✕</button></div>
  <form onsubmit="submitAddEtape(event,${did})" class="p-6 space-y-4">
    <div><label class="label">Type *</label><select name="type_etape" class="input">${Object.entries(TYPE_ETAPE).map(([k,v])=>`<option value="${k}">${v}</option>`).join('')}</select></div>
    <div><label class="label">Date prévue</label><input type="date" name="date_prevue" class="input"></div>
    <div><label class="label">Commentaire</label><textarea name="commentaire" class="input" rows="2"></textarea></div>
    <div id="eErr" class="hidden bg-red-50 text-red-700 text-sm px-3 py-2 rounded border border-red-200"></div>
    <div class="flex gap-3"><button type="button" onclick="cModal()" class="btn-secondary flex-1">Annuler</button><button type="submit" class="btn-primary flex-1">Ajouter</button></div>
  </form>`);
}
async function submitAddEtape(e,did){ e.preventDefault(); const fd=new FormData(e.target),err=document.getElementById('eErr'); try{ await req(`api/etapes.php?dossier_id=${did}`,{method:'POST',body:JSON.stringify(Object.fromEntries(fd))}); cModal(); toast('Étape ajoutée'); nav('dossierDetail',did); }catch(ex){ err.textContent=ex.message; err.classList.remove('hidden'); } }

function showEditEtape(e) {
  const sts=['en_attente','en_cours','termine','retard'];
  modal(`<div class="flex items-center justify-between p-6 border-b"><h2 class="text-lg font-semibold">Modifier l'étape</h2><button onclick="cModal()" class="text-gray-400">✕</button></div>
  <form onsubmit="submitEditEtape(event,${e.id},${e.dossier_id})" class="p-6 space-y-4">
    <div class="grid grid-cols-2 gap-3">
      <div><label class="label">Statut</label><select name="statut" class="input">${sts.map(s=>`<option value="${s}" ${e.statut===s?'selected':''}>${s}</option>`).join('')}</select></div>
      <div><label class="label">Date prévue</label><input type="date" name="date_prevue" class="input" value="${e.date_prevue||''}"></div>
      <div><label class="label">Date réalisation</label><input type="date" name="date_realisation" class="input" value="${e.date_realisation||''}"></div>
    </div>
    <div><label class="label">Commentaire</label><textarea name="commentaire" class="input" rows="2">${e.commentaire||''}</textarea></div>
    <div class="flex gap-3"><button type="button" onclick="cModal()" class="btn-secondary flex-1">Annuler</button><button type="submit" class="btn-primary flex-1">Sauvegarder</button></div>
  </form>`);
}
async function submitEditEtape(e,id,did){ e.preventDefault(); const fd=new FormData(e.target); await req(`api/etapes.php?id=${id}`,{method:'PUT',body:JSON.stringify(Object.fromEntries(fd))}); cModal(); toast('Étape mise à jour'); nav('dossierDetail',did); }
async function delEtape(id,did){ if(!confirm('Supprimer cette étape ?'))return; await req(`api/etapes.php?id=${id}`,{method:'DELETE'}); toast('Étape supprimée'); nav('dossierDetail',did); }

// ── PROCÉDURES ────────────────────────────────────────────────
async function procedures() {
  const data=await req('api/dossiers.php');
  const totalRetards=data.reduce((s,d)=>s+ +d.nb_retards,0);
  pg(`<div class="space-y-5">
    <div><h1 class="text-2xl font-bold">Suivi des Procédures</h1><p class="text-gray-500 text-sm">Progression par dossier</p></div>
    <div class="grid grid-cols-3 gap-4">
      ${kpi('En cours',data.filter(d=>d.statut==='en_cours').length,'#D97706')}
      ${kpi('Étapes en retard',totalRetards,'#DC2626')}
      ${kpi('Clôturés',data.filter(d=>d.statut==='cloture').length,'#16A34A')}
    </div>
    <div class="space-y-4">
      ${data.map(d=>{
        const hr=+d.nb_retards>0,bc=hr?'border-red-400':d.statut==='cloture'?'border-green-400':'border-primary-400';
        return `<div class="card border-l-4 ${bc} p-4">
          <div class="flex items-start justify-between gap-4 mb-3">
            <div>
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-mono font-bold text-primary-700 cursor-pointer hover:underline" onclick="nav('dossierDetail','${d.id}')">${d.numero_dossier}</span>
                ${sb(d.statut)}
                ${hr?`<span class="badge bg-red-50 text-red-600">⚠ ${d.nb_retards} retard(s)</span>`:''}
              </div>
              <p class="text-sm text-gray-600 mt-0.5">${d.contribuable_nom} — <span class="text-gray-400">${d.agent_nom}</span></p>
            </div>
            <span class="text-sm font-bold text-gray-700">${d.nb_etapes} étape(s)</span>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:0%"></div></div>
        </div>`;
      }).join('')}
    </div>
  </div>`);
}

// ── ALERTES ───────────────────────────────────────────────────
let _as={type:'',lu:''};
async function alertes() { await _renderAlertes(); }
async function _renderAlertes() {
  const p=new URLSearchParams(); if(_as.type)p.set('type',_as.type); if(_as.lu!=='')p.set('lu',_as.lu);
  const data=await req('api/alertes.php?'+p);
  const unread=data.filter(a=>!+a.lu).length;
  pg(`<div class="space-y-5">
    <div class="flex items-center justify-between">
      <div><h1 class="text-2xl font-bold">Alertes & Délais</h1><p class="text-gray-500 text-sm">${unread} non lue(s)</p></div>
      ${unread?`<button class="btn-secondary text-sm" onclick="markAllRead()">✓ Tout marquer lu</button>`:''}
    </div>
    <div class="card p-4 flex gap-3">
      <select class="input w-auto" onchange="_as.type=this.value;_renderAlertes()">
        <option value="">Tous types</option>${['critique','avertissement','info'].map(t=>`<option value="${t}" ${_as.type===t?'selected':''}>${t}</option>`).join('')}
      </select>
      <select class="input w-auto" onchange="_as.lu=this.value;_renderAlertes()">
        <option value="">Toutes</option><option value="false" ${_as.lu==='false'?'selected':''}>Non lues</option><option value="true" ${_as.lu==='true'?'selected':''}>Lues</option>
      </select>
    </div>
    <div class="space-y-2">
      ${!data.length?'<div class="card text-center text-gray-400 py-16">Aucune alerte</div>':
        data.map(a=>{const bc=a.type==='critique'?'border-red-400':a.type==='avertissement'?'border-amber-400':'border-blue-400';
          return `<div class="card p-4 flex items-start gap-4 border-l-4 ${bc} ${+a.lu?'opacity-60':''}">
            <div class="flex-1">
              <div class="flex items-center gap-2">${ab(a.type)} ${!+a.lu?'<span class="w-2 h-2 bg-primary-600 rounded-full"></span>':''}</div>
              <p class="text-sm text-gray-800 mt-1">${a.message}</p>
              <div class="text-xs text-gray-500 mt-1">
                <span class="text-primary-700 font-mono cursor-pointer hover:underline" onclick="nav('dossierDetail','${a.dossier_id}')">${a.numero_dossier||''}</span>
                ${a.contribuable_nom?`<span class="ml-2">${a.contribuable_nom}</span>`:''}
                <span class="ml-2">${ago(a.created_at)}</span>
              </div>
            </div>
            ${!+a.lu?`<button class="text-xs text-gray-400 hover:text-gray-600 px-2 py-1 rounded hover:bg-gray-100" onclick="markRead(${a.id})">✓</button>`:''}
          </div>`;}).join('')}
    </div>
  </div>`);
}
async function markRead(id){ await req(`api/alertes.php?id=${id}`,{method:'PUT'}); _renderAlertes(); updateBadge(); }
async function markAllRead(){ await req('api/alertes.php?action=mark_all_read',{method:'PUT'}); _renderAlertes(); updateBadge(); }

// ── STATISTIQUES ──────────────────────────────────────────────
async function statistiques() {
  const s=await req('api/stats.php'), t=s.totaux;
  const tc=t.total>0?Math.round(t.cloture/t.total*100):0, tr=t.total>0?Math.round(t.retards/t.total*100):0;
  const ml=s.parMois.map(m=>{const[y,mo]=m.mois.split('-');return new Date(+y,+mo-1).toLocaleDateString('fr-FR',{month:'short',year:'2-digit'});});
  pg(`<div class="space-y-6">
    <div><h1 class="text-2xl font-bold">Statistiques</h1><p class="text-gray-500 text-sm">Analyse complète</p></div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
      ${kpi('Total dossiers',t.total,'#1E3A5F')}${kpi('Taux clôture',tc+'%','#16A34A')}
      ${kpi('Taux retard',tr+'%','#DC2626')}${kpi('En cours',t.enCours,'#D97706')}
    </div>
    ${s.progression.length?`<div class="card"><h2 class="text-base font-semibold mb-4">Progression annuelle</h2><div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="bg-gray-50 border-b">${['Année','Total','Clôturés','En cours','Taux'].map(h=>`<th class="px-4 py-2 text-left text-xs text-gray-500 uppercase">${h}</th>`).join('')}</tr></thead><tbody>${s.progression.map(p=>{const tx=p.total>0?Math.round(p.clotures/p.total*100):0;return`<tr class="border-b border-gray-100"><td class="px-4 py-2 font-bold">${p.annee}</td><td class="px-4 py-2">${p.total}</td><td class="px-4 py-2 text-green-700">${p.clotures}</td><td class="px-4 py-2 text-amber-700">${p.en_cours}</td><td class="px-4 py-2"><div class="flex items-center gap-2"><div class="progress-bar flex-1"><div class="progress-fill bg-green-500" style="width:${tx}%"></div></div><span class="text-xs">${tx}%</span></div></td></tr>`;}).join('')}</tbody></table></div></div>`:''}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="card"><h2 class="text-base font-semibold mb-4">Évolution mensuelle</h2><canvas id="sm"></canvas></div>
      <div class="card"><h2 class="text-base font-semibold mb-4">Par type</h2><canvas id="st"></canvas></div>
      <div class="card"><h2 class="text-base font-semibold mb-4">Par agent</h2><canvas id="sa"></canvas></div>
      <div class="card"><h2 class="text-base font-semibold mb-4">Retards par étape</h2><canvas id="sr"></canvas></div>
    </div>
  </div>`);
  APP.charts.m=new Chart(document.getElementById('sm'),{type:'line',data:{labels:ml,datasets:[{label:'Dossiers',data:s.parMois.map(m=>m.cnt),borderColor:'#1E3A5F',backgroundColor:'rgba(30,58,95,.1)',fill:true,tension:.4,pointBackgroundColor:'#1E3A5F',pointRadius:5}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
  if(s.parType.length) APP.charts.t=new Chart(document.getElementById('st'),{type:'doughnut',data:{labels:s.parType.map(t=>TYPE_CONTROLE[t.type_controle]||t.type_controle),datasets:[{data:s.parType.map(t=>t.cnt),backgroundColor:['#1E3A5F','#3B6EB3','#628BC2','#89A8D1'],borderWidth:3,borderColor:'#fff'}]},options:{responsive:true,plugins:{legend:{position:'bottom'}}}});
  if(s.parAgent.length) APP.charts.a=new Chart(document.getElementById('sa'),{type:'bar',data:{labels:s.parAgent.map(a=>a.agent.split(' ').slice(0,2).join(' ')),datasets:[{label:'Dossiers',data:s.parAgent.map(a=>a.cnt),backgroundColor:'#3B6EB3',borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
  const rl={avis:'Avis',notification:'Notif.',pv:'PV',enquete:'Enquête',rapport:'Rapport',redressement:'Redress.',reponse_contribuable:'Réponse',decision_finale:'Décision'};
  if(s.retardsParEtape.length) APP.charts.r=new Chart(document.getElementById('sr'),{type:'bar',data:{labels:s.retardsParEtape.map(r=>rl[r.type_etape]||r.type_etape),datasets:[{label:'Retards',data:s.retardsParEtape.map(r=>r.cnt),backgroundColor:'#EF4444',borderRadius:6}]},options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});
}

// ── EXPORT ────────────────────────────────────────────────────
let _ep='annuelle';
function exportPage() {
  const years=Array.from({length:6},(_,i)=>new Date().getFullYear()-i);
  pg(`<div class="space-y-6">
    <div><h1 class="text-2xl font-bold">Rapports & Export</h1><p class="text-gray-500 text-sm">Générez et téléchargez vos rapports</p></div>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="card space-y-5">
        <h2 class="font-semibold">Paramètres</h2>
        <div><label class="label">Type</label><div class="flex gap-2">
          ${[['annuelle','Annuel'],['mensuelle','Mensuel'],['trimestrielle','Trimestriel']].map(([v,l])=>`<button class="tab-btn flex-1 ${v==='annuelle'?'active':''}" id="ep-${v}" onclick="setEP('${v}')">${l}</button>`).join('')}
        </div></div>
        <div><label class="label">Année</label><select id="epA" class="input">${years.map(y=>`<option>${y}</option>`).join('')}</select></div>
        <div id="epMR" class="hidden"><label class="label">Mois</label><select id="epM" class="input"><option value="">—</option>${['01','02','03','04','05','06','07','08','09','10','11','12'].map((m,i)=>`<option value="${m}">${new Date(2024,i,1).toLocaleDateString('fr-FR',{month:'long'})}</option>`).join('')}</select></div>
        <div id="epTR" class="hidden"><label class="label">Trimestre</label><select id="epT" class="input"><option value="">—</option>${[1,2,3,4].map(t=>`<option value="${t}">T${t}</option>`).join('')}</select></div>
      </div>
      <div class="space-y-4">
        <div class="card border-2 border-transparent hover:border-green-300 transition-all">
          <h3 class="font-semibold">Export Excel / CSV</h3>
          <p class="text-sm text-gray-500 mt-1">Fichier .xlsx (PhpSpreadsheet) ou .csv selon l'installation.</p>
          <button class="mt-3 btn-primary text-sm py-1.5 px-3" style="background:#16A34A" onclick="doExport('excel')">↓ Télécharger</button>
        </div>
        <div class="card border-2 border-transparent hover:border-blue-300 transition-all">
          <h3 class="font-semibold">Export JSON</h3>
          <p class="text-sm text-gray-500 mt-1">Données brutes pour intégration avec d'autres systèmes.</p>
          <button class="mt-3 btn-primary text-sm py-1.5 px-3" onclick="doExport('json')">↓ Télécharger (.json)</button>
        </div>
      </div>
    </div>
  </div>`);
}
function setEP(v){ _ep=v; ['annuelle','mensuelle','trimestrielle'].forEach(p=>{document.getElementById('ep-'+p)?.classList.toggle('active',p===v);}); document.getElementById('epMR').classList.toggle('hidden',v!=='mensuelle'); document.getElementById('epTR').classList.toggle('hidden',v!=='trimestrielle'); }
function doExport(fmt){ const a=document.getElementById('epA').value,m=_ep==='mensuelle'?document.getElementById('epM').value:'',t=_ep==='trimestrielle'?document.getElementById('epT').value:''; window.location.href=`api/export.php?format=${fmt}&annee=${a}${m?'&mois='+m:''}${t?'&trimestre='+t:''}`; }

// ── UTILISATEURS ──────────────────────────────────────────────
async function utilisateurs() {
  const [users,brig]=await Promise.all([req('api/users.php'),req('api/brigades.php')]);
  window._brigades=brig;
  pg(`<div class="space-y-6">
    <div class="flex items-center justify-between">
      <div><h1 class="text-2xl font-bold">Utilisateurs & Droits</h1><p class="text-gray-500 text-sm">${users.length} utilisateur(s)</p></div>
      <button class="btn-primary flex items-center gap-2" onclick="showUserModal()">+ Nouvel utilisateur</button>
    </div>
    <div class="card"><h2 class="font-semibold mb-4">Brigades</h2><div class="grid grid-cols-3 gap-3">${brig.map(b=>`<div class="bg-gray-50 rounded-lg p-3 border border-gray-200"><p class="font-medium">${b.nom}</p><p class="text-sm text-gray-500">Chef: ${b.chef_nom||'—'}</p></div>`).join('')}</div></div>
    ${['directeur','sous_directeur','chef_brigade','agent'].map(role=>{
      const ru=users.filter(u=>u.role===role); if(!ru.length)return'';
      return `<div><h2 class="font-semibold text-gray-700 mb-3">${ROLE_L[role]}s (${ru.length})</h2>
      <div class="card p-0 overflow-hidden"><table class="w-full text-sm">
        <thead class="bg-gray-50 border-b"><tr>${['Nom','Email','Rôle','Brigade','Statut',''].map(h=>`<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">${h}</th>`).join('')}</tr></thead>
        <tbody class="divide-y divide-gray-100">
        ${ru.map(u=>`<tr class="${!+u.actif?'opacity-50':''}">
          <td class="px-4 py-3"><div class="flex items-center gap-2"><div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-xs font-bold text-primary-800">${(u.prenom[0]||'')+(u.nom[0]||'')}</div><span class="font-medium">${u.prenom} ${u.nom}</span></div></td>
          <td class="px-4 py-3 text-gray-600">${u.email}</td>
          <td class="px-4 py-3">${bd(ROLE_L[u.role]||u.role, ROLE_C[u.role]||'bg-gray-100 text-gray-700')}</td>
          <td class="px-4 py-3 text-gray-600">${u.brigade_nom||'—'}</td>
          <td class="px-4 py-3">${bd(+u.actif?'Actif':'Inactif',+u.actif?'bg-green-100 text-green-700':'bg-gray-100 text-gray-500')}</td>
          <td class="px-4 py-3"><div class="flex gap-1">
            <button class="p-1.5 rounded hover:bg-gray-100 text-gray-500" onclick='showUserModal(${JSON.stringify(u)})'>✎</button>
            <button class="p-1.5 rounded ${+u.actif?'hover:bg-red-50 text-red-500':'hover:bg-green-50 text-green-500'}" onclick="toggleUser(${u.id},${u.actif})">${+u.actif?'⏻':'↺'}</button>
          </div></td>
        </tr>`).join('')}
        </tbody>
      </table></div></div>`;
    }).join('')}
  </div>`);
}

function showUserModal(u=null) {
  const brig=window._brigades||[];
  modal(`<div class="flex items-center justify-between p-6 border-b"><h2 class="text-lg font-semibold">${u?'Modifier':'Nouvel'} utilisateur</h2><button onclick="cModal()" class="text-gray-400">✕</button></div>
  <form onsubmit="submitUser(event,${u?u.id:'null'})" class="p-6 space-y-4">
    <div class="grid grid-cols-2 gap-3">
      <div><label class="label">Prénom *</label><input name="prenom" class="input" value="${u?u.prenom:''}" required></div>
      <div><label class="label">Nom *</label><input name="nom" class="input" value="${u?u.nom:''}" required></div>
    </div>
    <div><label class="label">Email *</label><input type="email" name="email" class="input" value="${u?u.email:''}" required></div>
    <div><label class="label">${u?'Nouveau mot de passe (vide = inchangé)':'Mot de passe *'}</label><input type="password" name="password" class="input" ${u?'':'required'} minlength="8" placeholder="••••••••"></div>
    <div><label class="label">Rôle *</label><select name="role" class="input">${Object.entries(ROLE_L).map(([k,v])=>`<option value="${k}" ${u&&u.role===k?'selected':''}>${v}</option>`).join('')}</select></div>
    <div><label class="label">Brigade</label><select name="brigade_id" class="input"><option value="">—</option>${brig.map(b=>`<option value="${b.id}" ${u&&+u.brigade_id===b.id?'selected':''}>${b.nom}</option>`).join('')}</select></div>
    <div id="uErr" class="hidden bg-red-50 text-red-700 text-sm px-3 py-2 rounded border border-red-200"></div>
    <div class="flex gap-3"><button type="button" onclick="cModal()" class="btn-secondary flex-1">Annuler</button><button type="submit" class="btn-primary flex-1">${u?'Modifier':'Créer'}</button></div>
  </form>`);
}

async function submitUser(e,id) {
  e.preventDefault(); const fd=new FormData(e.target),err=document.getElementById('uErr');
  const body=Object.fromEntries(fd); if(!body.password)delete body.password; if(!body.brigade_id)body.brigade_id=null;
  try {
    if(id) await req(`api/users.php?id=${id}`,{method:'PUT',body:JSON.stringify(body)});
    else    await req('api/users.php',{method:'POST',body:JSON.stringify(body)});
    cModal(); toast(id?'Utilisateur modifié':'Utilisateur créé'); nav('utilisateurs');
  } catch(ex){ err.textContent=ex.message; err.classList.remove('hidden'); }
}

async function toggleUser(id,actif) { await req(`api/users.php?id=${id}`,{method:'PUT',body:JSON.stringify({actif:+actif?0:1})}); toast(+actif?'Désactivé':'Réactivé'); nav('utilisateurs'); }

// ── CALENDRIER ────────────────────────────────────────────────
let _calYear=new Date().getFullYear(), _calMonth=new Date().getMonth();
async function calendrier() {
  const [dossiers,etapesRaw]=await Promise.all([req('api/dossiers.php'),req('api/etapes.php?view=all').catch(()=>[])]);
  renderCalendrier(dossiers);
}
function renderCalendrier(dossiers) {
  const y=_calYear,m=_calMonth;
  const firstDay=new Date(y,m,1).getDay()||7; // Lundi=1
  const daysInMonth=new Date(y,m+1,0).getDate();
  const monthName=new Date(y,m,1).toLocaleDateString('fr-FR',{month:'long',year:'numeric'});
  // Grouper dossiers par jour d'ouverture
  const byDay={};
  dossiers.forEach(d=>{
    const dt=d.date_ouverture?.substring(0,10);
    if(dt){const[dy,dm,dd]=dt.split('-');if(+dy===y&&+dm-1===m){byDay[+dd]||(byDay[+dd]=[]);byDay[+dd].push(d);}}
  });
  // Dossiers actifs (non clôturés)
  const actifs=dossiers.filter(d=>d.statut!=='cloture'&&d.statut!=='contentieux');
  const retards=dossiers.filter(d=>+d.nb_retards>0);

  let cells='';
  for(let i=1;i<(firstDay===7?1:firstDay);i++) cells+='<div></div>';
  for(let d=1;d<=daysInMonth;d++){
    const items=byDay[d]||[];
    const isToday=y===new Date().getFullYear()&&m===new Date().getMonth()&&d===new Date().getDate();
    cells+=`<div class="min-h-[64px] border border-gray-100 rounded-lg p-1 ${isToday?'bg-primary-50 border-primary-300':''}">
      <div class="text-xs font-medium ${isToday?'text-primary-700':'text-gray-500'} mb-1">${d}</div>
      ${items.slice(0,2).map(dos=>`<div class="text-xs bg-primary-100 text-primary-800 rounded px-1 mb-0.5 truncate cursor-pointer" onclick="nav('dossierDetail','${dos.id}')" title="${dos.numero_dossier}">${dos.numero_dossier}</div>`).join('')}
      ${items.length>2?`<div class="text-xs text-gray-400">+${items.length-2}</div>`:''}
    </div>`;
  }
  pg(`<div class="space-y-5">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">Calendrier des échéances</h1>
      <div class="flex items-center gap-2">
        <button class="btn-secondary text-sm px-3 py-1.5" onclick="_calMonth--;if(_calMonth<0){_calMonth=11;_calYear--;}renderCalendrier(window._calDossiers||[])">‹ Préc.</button>
        <span class="font-semibold capitalize text-sm">${monthName}</span>
        <button class="btn-secondary text-sm px-3 py-1.5" onclick="_calMonth++;if(_calMonth>11){_calMonth=0;_calYear++;}renderCalendrier(window._calDossiers||[])">Suiv. ›</button>
      </div>
    </div>
    <div class="grid grid-cols-3 gap-4">
      ${kpi('Dossiers actifs',actifs.length,'#D97706')}
      ${kpi('Retards',retards.length,'#DC2626')}
      ${kpi('Ouverts ce mois',Object.values(byDay).flat().length,'#3B6EB3')}
    </div>
    <div class="card p-4">
      <div class="grid grid-cols-7 gap-1 mb-2 text-center text-xs font-semibold text-gray-500">
        ${['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'].map(j=>`<div>${j}</div>`).join('')}
      </div>
      <div class="grid grid-cols-7 gap-1">${cells}</div>
    </div>
    ${retards.length?`<div class="card"><h2 class="font-semibold mb-3 text-red-600">⚠ Dossiers en retard</h2><div class="space-y-2">${retards.map(d=>`<div class="flex items-center justify-between text-sm p-2 bg-red-50 rounded-lg"><span class="font-mono font-bold cursor-pointer text-primary-700" onclick="nav('dossierDetail','${d.id}')">${d.numero_dossier}</span><span class="text-gray-600">${d.contribuable_nom}</span><span class="text-red-600">${d.nb_retards} retard(s)</span></div>`).join('')}</div></div>`:''}`);
  window._calDossiers = dossiers;
}

// ── DOSSIERS : Kanban + Pagination ────────────────────────────
let _dsView='table', _dsPage=1;
const _dsPerPage=20;

async function _renderDossiers() {
  const p=new URLSearchParams();
  if(_ds.search)p.set('search',_ds.search);
  if(_ds.statut)p.set('statut',_ds.statut);
  if(_ds.type)  p.set('type_controle',_ds.type);
  const data=await req('api/dossiers.php?'+p);
  const canCreate=['directeur','sous_directeur','chef_brigade'].includes(APP.user.role);
  const statusLabels={ouvert:'Ouvert',en_cours:'En cours',suspendu:'Suspendu',cloture:'Clôturé',contentieux:'Contentieux'};

  const tableToggle=`<div class="flex gap-1 border border-gray-200 rounded-lg p-0.5">
    <button onclick="_dsView='table';_renderDossiers()" class="px-3 py-1.5 text-xs rounded font-medium ${_dsView==='table'?'bg-primary-800 text-white':'text-gray-500'}">☰ Tableau</button>
    <button onclick="_dsView='kanban';_renderDossiers()" class="px-3 py-1.5 text-xs rounded font-medium ${_dsView==='kanban'?'bg-primary-800 text-white':'text-gray-500'}">⊞ Kanban</button>
  </div>`;

  if (_dsView==='kanban') {
    const cols=['ouvert','en_cours','suspendu','cloture','contentieux'];
    const colColors={ouvert:'bg-blue-500',en_cours:'bg-amber-500',suspendu:'bg-gray-400',cloture:'bg-green-500',contentieux:'bg-red-500'};
    pg(`<div class="space-y-4">
      <div class="flex items-center justify-between">
        <div><h1 class="text-2xl font-bold">Dossiers</h1><p class="text-gray-500 text-sm">${data.length} dossier(s)</p></div>
        <div class="flex gap-2">${tableToggle}${canCreate?`<button class="btn-primary flex items-center gap-2 text-sm" onclick="showDossierForm()">+ Nouveau</button>`:''}</div>
      </div>
      <div class="flex gap-4 overflow-x-auto pb-2">
        ${cols.map(col=>{
          const items=data.filter(d=>d.statut===col);
          return `<div class="kanban-col flex-shrink-0 w-56">
            <div class="flex items-center gap-2 mb-3"><div class="w-3 h-3 rounded-full ${colColors[col]}"></div><span class="text-sm font-semibold">${statusLabels[col]}</span><span class="ml-auto text-xs text-gray-400">${items.length}</span></div>
            ${items.map(d=>`<div class="kanban-card" onclick="nav('dossierDetail','${d.id}')">
              <div class="font-mono text-xs font-bold text-primary-700">${d.numero_dossier}</div>
              <div class="text-xs text-gray-600 mt-1 truncate">${d.contribuable_nom}</div>
              <div class="text-xs text-gray-400 mt-1">${d.agent_nom}</div>
              ${+d.nb_retards>0?`<div class="text-xs text-red-500 mt-1">⚠ ${d.nb_retards} retard(s)</div>`:''}
            </div>`).join('')}
          </div>`;
        }).join('')}
      </div>
    </div>`);
    return;
  }

  // Vue tableau avec pagination
  const total=data.length, totalPages=Math.max(1,Math.ceil(total/_dsPerPage));
  _dsPage=Math.min(_dsPage,totalPages);
  const slice=data.slice((_dsPage-1)*_dsPerPage, _dsPage*_dsPerPage);
  const pager=totalPages>1?`<div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 text-sm">
    <button class="btn-secondary text-xs py-1 px-3" onclick="_dsPage=Math.max(1,_dsPage-1);_renderDossiers()" ${_dsPage===1?'disabled':''}>← Préc.</button>
    <span class="text-gray-500">Page ${_dsPage} / ${totalPages} (${total} dossiers)</span>
    <button class="btn-secondary text-xs py-1 px-3" onclick="_dsPage=Math.min(${totalPages},_dsPage+1);_renderDossiers()" ${_dsPage===totalPages?'disabled':''}>Suiv. →</button>
  </div>`:'';

  pg(`<div class="space-y-5">
    <div class="flex items-center justify-between">
      <div><h1 class="text-2xl font-bold">Dossiers de Contrôle</h1><p class="text-gray-500 text-sm">${total} dossier(s)</p></div>
      <div class="flex gap-2">${tableToggle}${canCreate?`<button class="btn-primary flex items-center gap-2" onclick="showDossierForm()"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>Nouveau dossier</button>`:''}</div>
    </div>
    <div class="card p-4 flex flex-wrap gap-3">
      <input class="input flex-1 min-w-[180px]" placeholder="Rechercher…" value="${_ds.search}" oninput="_ds.search=this.value;_dsPage=1;_renderDossiers()">
      <select class="input w-auto" onchange="_ds.statut=this.value;_dsPage=1;_renderDossiers()">
        <option value="">Tous statuts</option>
        ${['ouvert','en_cours','suspendu','cloture','contentieux'].map(s=>`<option value="${s}" ${_ds.statut===s?'selected':''}>${s}</option>`).join('')}
      </select>
      <select class="input w-auto" onchange="_ds.type=this.value;_dsPage=1;_renderDossiers()">
        <option value="">Tous types</option>
        ${Object.entries(TYPE_CONTROLE).map(([k,v])=>`<option value="${k}" ${_ds.type===k?'selected':''}>${v}</option>`).join('')}
      </select>
    </div>
    <div class="card p-0 overflow-hidden">
      ${!slice.length?'<p class="text-center text-gray-400 py-16">Aucun dossier trouvé</p>':`
      <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead class="bg-gray-50 border-b"><tr>
          ${['N° Dossier','Contribuable','Type','Agent','Ouverture','Statut','Ret.',''].map(h=>`<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">${h}</th>`).join('')}
        </tr></thead>
        <tbody class="divide-y divide-gray-100">
        ${slice.map(d=>`<tr class="hover:bg-gray-50 cursor-pointer" onclick="nav('dossierDetail','${d.id}')">
          <td class="px-4 py-3 font-mono font-bold text-primary-700">${d.numero_dossier}</td>
          <td class="px-4 py-3"><div class="font-medium max-w-[170px] truncate">${d.contribuable_nom}</div><div class="text-xs text-gray-400">${d.nif}</div></td>
          <td class="px-4 py-3 text-gray-600 text-xs">${TYPE_CONTROLE[d.type_controle]||d.type_controle}</td>
          <td class="px-4 py-3 text-gray-700">${d.agent_nom}</td>
          <td class="px-4 py-3 text-gray-600">${fd(d.date_ouverture)}</td>
          <td class="px-4 py-3">${sb(d.statut)}</td>
          <td class="px-4 py-3">${+d.nb_retards>0?`<span class="text-red-600 text-xs font-medium">⚠ ${d.nb_retards}</span>`:''}</td>
          <td class="px-4 py-3 text-primary-700 text-xs">→</td>
        </tr>`).join('')}
        </tbody>
      </table></div>${pager}`}
    </div>
  </div>`);
}

// ── CONTRIBUABLES (avec import CSV) ──────────────────────────
async function contribuables() {
  const data = await req('api/contribuables.php');
  const canEdit = ['directeur','sous_directeur','chef_brigade'].includes(APP.user.role);
  pg(`<div class="space-y-5">
    <div class="flex items-center justify-between">
      <div><h1 class="text-2xl font-bold">Contribuables</h1><p class="text-gray-500 text-sm">${data.length} contribuable(s)</p></div>
      <div class="flex gap-2">
        ${canEdit?`<button class="btn-secondary text-sm" onclick="showImportCSV()">↑ Importer CSV</button><button class="btn-primary flex items-center gap-2" onclick="showContribuableForm()"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>Nouveau</button>`:''}
      </div>
    </div>
    <div class="card p-0 overflow-hidden">
      ${!data.length?'<p class="text-center text-gray-400 py-16">Aucun contribuable</p>':`
      <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead class="bg-gray-50 border-b"><tr>${['Nom / Raison sociale','NIF','Type','Secteur','Adresse',''].map(h=>`<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">${h}</th>`).join('')}</tr></thead>
        <tbody class="divide-y divide-gray-100">
        ${data.map(c=>`<tr class="hover:bg-gray-50">
          <td class="px-4 py-3 font-medium">${c.nom}</td>
          <td class="px-4 py-3 font-mono text-gray-600 text-xs">${c.nif}</td>
          <td class="px-4 py-3"><span class="badge bg-blue-50 text-blue-700">${c.type_entreprise}</span></td>
          <td class="px-4 py-3 text-gray-500">${c.secteur_activite||'—'}</td>
          <td class="px-4 py-3 text-gray-500 max-w-[200px] truncate">${c.adresse||'—'}</td>
          <td class="px-4 py-3">${canEdit?`<button class="p-1.5 rounded hover:bg-gray-100 text-gray-500" onclick='showContribuableForm(${JSON.stringify(c)})'>✎</button>`:''}</td>
        </tr>`).join('')}
        </tbody>
      </table></div>`}
    </div>
  </div>`);
}

function showImportCSV() {
  modal(`<div class="flex items-center justify-between p-6 border-b"><h2 class="text-lg font-semibold">Importer des contribuables (CSV)</h2><button onclick="cModal()" class="text-gray-400">✕</button></div>
  <div class="p-6 space-y-4">
    <p class="text-sm text-gray-500">Format attendu (séparateur <strong>;</strong>, première ligne ignorée) :</p>
    <code class="block text-xs bg-gray-100 rounded p-2">nom;nif;type_entreprise;adresse;secteur_activite</code>
    <input type="file" id="csvFile" accept=".csv" class="input">
    <div id="csvResult" class="hidden text-sm p-3 rounded-lg"></div>
    <div class="flex gap-3"><button type="button" onclick="cModal()" class="btn-secondary flex-1">Annuler</button><button onclick="submitCSV()" class="btn-primary flex-1">Importer</button></div>
  </div>`);
}
async function submitCSV() {
  const f=document.getElementById('csvFile').files[0], res=document.getElementById('csvResult');
  if(!f){res.className='text-sm p-3 rounded-lg bg-red-50 text-red-700';res.textContent='Sélectionnez un fichier.';res.classList.remove('hidden');return;}
  const fd=new FormData(); fd.append('csv_file',f);
  try {
    const r=await fetch('api/import_csv.php',{method:'POST',credentials:'same-origin',body:fd}).then(x=>x.json());
    res.className='text-sm p-3 rounded-lg bg-green-50 text-green-700';
    res.innerHTML=`✓ ${r.importes} importé(s), ${r.ignores} ignoré(s).${r.erreurs?.length?`<br>Erreurs : ${r.erreurs.join(', ')}`:''}`;
    res.classList.remove('hidden');
  } catch(ex){res.className='text-sm p-3 rounded-lg bg-red-50 text-red-700';res.textContent=ex.message;res.classList.remove('hidden');}
}

// ── DOSSIER DETAIL avec onglets ───────────────────────────────
let _detailTab='infos', _detailId=0;
async function dossierDetail(id) {
  _detailId=+id;
  document.querySelectorAll('.sidebar-link').forEach(e=>e.classList.remove('active'));
  document.getElementById('nav-dossiers')?.classList.add('active');
  const {dossier:d,etapes}=await req(`api/dossiers.php?id=${id}`);
  const done=etapes.filter(e=>e.statut==='termine').length;
  const pct=etapes.length?Math.round(done/etapes.length*100):0;
  const canEdit=APP.user.role!=='agent'||+d.agent_id===APP.user.id;
  const canDel=['directeur','sous_directeur'].includes(APP.user.role);

  const tabs=['infos','etapes','commentaires','pieces','historique'];
  const tabLabels={infos:'Informations',etapes:'Étapes','commentaires':'Commentaires',pieces:'Pièces jointes',historique:'Historique'};

  pg(`<div class="space-y-6">
    <div class="flex items-center gap-3">
      <button onclick="nav('dossiers')" class="p-2 rounded-lg hover:bg-gray-100 text-gray-500">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <div class="flex-1">
        <div class="flex items-center gap-3"><h1 class="text-2xl font-bold font-mono">${d.numero_dossier}</h1>${sb(d.statut)}</div>
        <p class="text-gray-500 text-sm">${d.contribuable_nom} — ${d.nif}</p>
      </div>
      <a href="api/pdf.php?id=${id}" target="_blank" class="btn-secondary text-sm flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>PDF</a>
      ${canDel?`<button class="btn-danger text-sm" onclick="delDossier(${id},'${d.numero_dossier}')">Supprimer</button>`:''}
    </div>

    <!-- Onglets -->
    <div class="detail-tab-bar flex gap-1 p-1">
      ${tabs.map(t=>`<button class="tab-btn ${_detailTab===t?'active':''}" onclick="_detailTab='${t}';renderDetailTab(${id},${JSON.stringify(d)},${JSON.stringify(etapes)},${done},${pct},${canEdit})">${tabLabels[t]}</button>`).join('')}
    </div>
    <div id="detailContent"></div>
  </div>`);

  renderDetailTab(id,d,etapes,done,pct,canEdit);
}

function renderDetailTab(id,d,etapes,done,pct,canEdit) {
  const ct=document.getElementById('detailContent');
  if(!ct) return;
  if (_detailTab==='infos') {
    ct.innerHTML=`<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="card space-y-3">
        <h2 class="font-semibold">Informations</h2>
        <dl class="space-y-2">${[['Contribuable',d.contribuable_nom],['NIF',d.nif],['Type entreprise',d.type_entreprise],['Secteur',d.secteur_activite],['Type contrôle',TYPE_CONTROLE[d.type_controle]||d.type_controle],['Agent',d.agent_nom],['Chef brigade',d.chef_brigade_nom||'—'],['Brigade',d.brigade_nom||'—'],['Date ouverture',fd(d.date_ouverture)],['Date clôture',fd(d.date_cloture)]].map(([l,v])=>ir(l,v)).join('')}</dl>
        ${d.observations?`<div class="pt-2 border-t text-sm"><p class="text-gray-500 text-xs mb-1">Observations</p><p class="text-gray-700">${d.observations}</p></div>`:''}
        <div class="pt-2 border-t">
          <div class="flex justify-between text-xs text-gray-500 mb-1"><span>Avancement</span><span>${pct}%</span></div>
          <div class="progress-bar"><div class="progress-fill" style="width:${pct}%"></div></div>
          <p class="text-xs text-gray-500 mt-1">${done}/${etapes.length} étapes terminées</p>
        </div>
        ${canEdit?`<button class="btn-secondary w-full text-sm" onclick="showEditDossier(${id})">Modifier le statut</button>`:''}
      </div>
      <div class="lg:col-span-2">
        <div class="card text-center text-gray-400 py-8">Sélectionnez l'onglet <strong>Étapes</strong> pour voir la procédure.</div>
      </div>
    </div>`;
  } else if (_detailTab==='etapes') {
    ct.innerHTML=`<div class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="font-semibold">Procédure de contrôle</h2>
        ${canEdit?`<button class="btn-primary text-sm py-1.5" onclick="showAddEtape(${id})">+ Ajouter étape</button>`:''}
      </div>
      ${!etapes.length?'<div class="card text-center text-gray-400 py-10">Aucune étape</div>':
        etapes.map(e=>`<div class="card p-4 tl-border tl-${e.statut}">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="flex items-center gap-2 flex-wrap"><span class="font-medium">${TYPE_ETAPE[e.type_etape]||e.type_etape}</span>${eb(e.statut)}</div>
              <div class="text-xs text-gray-500 mt-1">${e.date_prevue?`Prévue: ${fd(e.date_prevue)} `:''} ${e.date_realisation?`<span class="text-green-600">Réalisée: ${fd(e.date_realisation)}</span>`:''}</div>
              ${e.commentaire?`<p class="text-sm text-gray-600 mt-1">${e.commentaire}</p>`:''}
            </div>
            ${canEdit?`<div class="flex gap-1 flex-shrink-0">
              <button class="p-1.5 rounded hover:bg-gray-100 text-gray-500" onclick='showEditEtape(${JSON.stringify(e)})'>✎</button>
              <button class="p-1.5 rounded hover:bg-red-50 text-red-500" onclick="delEtape(${e.id},${id})">✕</button>
            </div>`:''}
          </div>
        </div>`).join('')}
    </div>`;
  } else if (_detailTab==='commentaires') {
    ct.innerHTML='<div class="flex justify-center py-8"><div class="spinner"></div></div>';
    loadCommentaires(id);
  } else if (_detailTab==='pieces') {
    ct.innerHTML='<div class="flex justify-center py-8"><div class="spinner"></div></div>';
    loadPieces(id);
  } else if (_detailTab==='historique') {
    ct.innerHTML='<div class="flex justify-center py-8"><div class="spinner"></div></div>';
    loadHistorique(id);
  }
}

// ── COMMENTAIRES ──────────────────────────────────────────────
async function loadCommentaires(id) {
  const data=await req(`api/commentaires.php?dossier_id=${id}`);
  const ct=document.getElementById('detailContent');
  if(!ct) return;
  ct.innerHTML=`<div class="space-y-4">
    <h2 class="font-semibold">Commentaires internes</h2>
    <div id="commList" class="space-y-3">
      ${!data.length?'<div class="card text-center text-gray-400 py-8">Aucun commentaire</div>':
        data.map(c=>`<div class="card p-4 flex gap-3">
          <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-xs font-bold text-primary-800 flex-shrink-0">${c.auteur?.split(' ').map(n=>n[0]).join('').substring(0,2)||'?'}</div>
          <div class="flex-1">
            <div class="flex items-center justify-between gap-2">
              <span class="text-sm font-medium">${c.auteur||'Inconnu'}</span>
              <span class="text-xs text-gray-400">${ago(c.created_at)}</span>
            </div>
            <p class="text-sm text-gray-700 mt-1">${c.message}</p>
          </div>
          ${c.user_id===APP.user.id||['directeur','sous_directeur'].includes(APP.user.role)?`<button class="text-xs text-gray-400 hover:text-red-500" onclick="deleteComm(${c.id},${id})">✕</button>`:''}
        </div>`).join('')}
    </div>
    <div class="card p-4 space-y-3">
      <textarea id="newComm" class="input" rows="3" placeholder="Ajouter un commentaire…"></textarea>
      <button class="btn-primary text-sm" onclick="submitComm(${id})">Envoyer</button>
    </div>
  </div>`;
}
async function submitComm(id) {
  const msg=document.getElementById('newComm')?.value?.trim();
  if(!msg) return;
  await req(`api/commentaires.php?dossier_id=${id}`,{method:'POST',body:JSON.stringify({message:msg})});
  toast('Commentaire ajouté'); loadCommentaires(id);
}
async function deleteComm(cid,did) {
  if(!confirm('Supprimer ce commentaire ?')) return;
  await req(`api/commentaires.php?id=${cid}`,{method:'DELETE'});
  toast('Supprimé'); loadCommentaires(did);
}

// ── PIÈCES JOINTES ─────────────────────────────────────────────
function fmtSize(b){if(b<1024)return b+'o';if(b<1048576)return(b/1024).toFixed(1)+'Ko';return(b/1048576).toFixed(1)+'Mo';}
async function loadPieces(id) {
  const data=await req(`api/pieces_jointes.php?dossier_id=${id}`);
  const canEdit=APP.user.role!=='agent'||true; // tous peuvent uploader
  const ct=document.getElementById('detailContent');
  if(!ct) return;
  ct.innerHTML=`<div class="space-y-4">
    <h2 class="font-semibold">Pièces jointes</h2>
    <div class="space-y-2">
      ${!data.length?'<div class="card text-center text-gray-400 py-8">Aucun fichier joint</div>':
        data.map(f=>`<div class="card p-4 flex items-center gap-4">
          <svg class="w-8 h-8 text-primary-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-medium truncate">${f.nom_original}</p>
            <p class="text-xs text-gray-400">${fmtSize(f.taille)} — ${ago(f.created_at)} — ${f.auteur||''}</p>
          </div>
          <button class="btn-secondary text-xs py-1 px-3" onclick="window.open('api/pieces_jointes.php?id=${f.id}&download=1')">↓ Télécharger</button>
          <button class="p-1.5 rounded hover:bg-red-50 text-red-400" onclick="deleteFichier(${f.id},${id})">✕</button>
        </div>`).join('')}
    </div>
    <div class="card p-4 space-y-3">
      <label class="label">Ajouter un fichier (max 10 Mo)</label>
      <input type="file" id="pjFile" class="input">
      <button class="btn-primary text-sm" onclick="uploadFichier(${id})">↑ Envoyer</button>
      <div id="pjMsg" class="hidden text-sm"></div>
    </div>
  </div>`;
}
async function uploadFichier(id) {
  const f=document.getElementById('pjFile')?.files[0], msg=document.getElementById('pjMsg');
  if(!f){msg.className='text-sm text-red-600';msg.textContent='Sélectionnez un fichier.';msg.classList.remove('hidden');return;}
  const fd=new FormData(); fd.append('fichier',f);
  try {
    await fetch(`api/pieces_jointes.php?dossier_id=${id}`,{method:'POST',credentials:'same-origin',body:fd}).then(r=>{if(!r.ok)return r.json().then(e=>{throw new Error(e.error||'Erreur upload')});return r.json();});
    toast('Fichier envoyé'); loadPieces(id);
  } catch(ex){msg.className='text-sm text-red-600';msg.textContent=ex.message;msg.classList.remove('hidden');}
}
async function deleteFichier(fid,did) {
  if(!confirm('Supprimer ce fichier ?')) return;
  await req(`api/pieces_jointes.php?id=${fid}`,{method:'DELETE'});
  toast('Fichier supprimé'); loadPieces(did);
}

// ── HISTORIQUE ─────────────────────────────────────────────────
async function loadHistorique(id) {
  const data=await req(`api/historique.php?dossier_id=${id}`);
  const ct=document.getElementById('detailContent');
  if(!ct) return;
  ct.innerHTML=`<div class="space-y-4">
    <h2 class="font-semibold">Historique des modifications</h2>
    ${!data.length?'<div class="card text-center text-gray-400 py-8">Aucun historique</div>':
      `<div class="space-y-2">${data.map(h=>`<div class="flex gap-4 items-start text-sm">
        <div class="w-2 h-2 rounded-full bg-primary-400 mt-2 flex-shrink-0"></div>
        <div class="flex-1 card p-3">
          <div class="flex justify-between gap-2"><span class="font-medium">${h.action}</span><span class="text-xs text-gray-400">${fd(h.created_at)}</span></div>
          ${h.details?`<p class="text-xs text-gray-500 mt-0.5">${h.details}</p>`:''}
          <p class="text-xs text-gray-400 mt-0.5">Par ${h.utilisateur||'Système'}</p>
        </div>
      </div>`).join('')}</div>`}
  </div>`;
}

// ── AUDIT ─────────────────────────────────────────────────────
async function audit() {
  const data=await req('api/stats.php?action=audit');
  pg(`<div class="space-y-5">
    <div><h1 class="text-2xl font-bold">Journal d'audit</h1><p class="text-gray-500 text-sm">${data.length} entrée(s)</p></div>
    <div class="card p-0 overflow-hidden">
      <div class="overflow-x-auto"><table class="w-full text-sm">
        <thead class="bg-gray-50 border-b"><tr>${['Date','Utilisateur','Action','Table','Détails'].map(h=>`<th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">${h}</th>`).join('')}</tr></thead>
        <tbody class="divide-y divide-gray-100">
        ${data.map(l=>`<tr class="hover:bg-gray-50">
          <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">${fd(l.created_at)}</td>
          <td class="px-4 py-3">${l.utilisateur||'Système'}</td>
          <td class="px-4 py-3"><span class="badge ${l.action==='DELETE'||l.action==='DEACTIVATE'?'bg-red-100 text-red-700':l.action==='CREATE'?'bg-green-100 text-green-700':'bg-gray-100 text-gray-700'}">${l.action}</span></td>
          <td class="px-4 py-3 text-gray-500">${l.table_name||'—'}</td>
          <td class="px-4 py-3 text-gray-500 max-w-[250px] truncate">${l.details||'—'}</td>
        </tr>`).join('')}
        </tbody>
      </table></div>
    </div>
  </div>`);
}

// ── SAUVEGARDE ────────────────────────────────────────────────
function sauvegarde() {
  pg(`<div class="space-y-6">
    <div><h1 class="text-2xl font-bold">Sauvegarde</h1><p class="text-gray-500 text-sm">Téléchargez une copie complète de la base de données</p></div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div class="card space-y-4">
        <h2 class="font-semibold">Export SQL / DB</h2>
        <p class="text-sm text-gray-500">Génère un fichier de sauvegarde complet de toutes les données.</p>
        <button class="btn-primary" onclick="window.location.href='api/backup.php'">↓ Télécharger la sauvegarde</button>
      </div>
      <div class="card space-y-4 border-amber-200 bg-amber-50">
        <h2 class="font-semibold text-amber-800">⚠ Informations</h2>
        <ul class="text-sm text-amber-700 space-y-1 list-disc list-inside">
          <li>Les mots de passe ne sont pas inclus en clair</li>
          <li>Conservez ce fichier en lieu sûr</li>
          <li>Restauration via phpMyAdmin (MySQL) ou DB Browser (SQLite)</li>
        </ul>
      </div>
    </div>
  </div>`);
}

// ── PROFIL / 2FA ──────────────────────────────────────────────
async function showProfileModal() {
  const st=await req('api/totp.php?action=status');
  modal(`<div class="flex items-center justify-between p-6 border-b"><h2 class="text-lg font-semibold">Mon profil</h2><button onclick="cModal()" class="text-gray-400">✕</button></div>
  <div class="p-6 space-y-6">
    <div><p class="text-sm font-medium">Authentification à deux facteurs (2FA)</p>
      <p class="text-xs text-gray-500 mt-1">${st.enabled?'✅ Activée — votre compte est protégé.':'⚠ Non activée — activez-la pour plus de sécurité.'}</p>
    </div>
    <div id="totpSetup">
      ${st.enabled
        ? `<button class="btn-danger text-sm w-full" onclick="setup2FA('disable')">Désactiver le 2FA</button>`
        : `<button class="btn-primary text-sm w-full" onclick="setup2FA('enable')">Activer le 2FA</button>`}
    </div>
  </div>`);
}
async function setup2FA(mode) {
  const box=document.getElementById('totpSetup');
  if (mode==='enable') {
    const r=await req('api/totp.php?action=setup');
    box.innerHTML=`<div class="space-y-3">
      <p class="text-sm">Scannez ce QR code avec Google Authenticator, Authy ou similaire.</p>
      <canvas id="qrCanvas" class="mx-auto block"></canvas>
      <p class="text-xs text-gray-500 text-center">Ou entrez la clé : <code class="bg-gray-100 px-1 rounded">${r.secret}</code></p>
      <input id="totpActivate" type="text" class="input text-center tracking-widest text-lg" maxlength="6" placeholder="000000" inputmode="numeric">
      <button class="btn-primary w-full" onclick="confirmActivate()">Confirmer l'activation</button>
    </div>`;
    try { QRCode.toCanvas(document.getElementById('qrCanvas'), r.otpauth_url, {width:200}); } catch(e){}
    window._totpSecret = r.secret;
  } else {
    box.innerHTML=`<div class="space-y-3">
      <p class="text-sm">Entrez votre code actuel pour désactiver le 2FA.</p>
      <input id="totpDisable" type="text" class="input text-center tracking-widest text-lg" maxlength="6" placeholder="000000" inputmode="numeric">
      <button class="btn-danger w-full" onclick="confirmDisable()">Désactiver le 2FA</button>
    </div>`;
  }
}
async function confirmActivate() {
  const code=document.getElementById('totpActivate')?.value?.trim();
  try { await req('api/totp.php',{method:'POST',body:JSON.stringify({action:'activate',code})}); cModal(); toast('2FA activé !'); }
  catch(ex){ toast(ex.message,'err'); }
}
async function confirmDisable() {
  const code=document.getElementById('totpDisable')?.value?.trim();
  try { await req('api/totp.php',{method:'POST',body:JSON.stringify({action:'disable',code})}); cModal(); toast('2FA désactivé.'); }
  catch(ex){ toast(ex.message,'err'); }
}

// ── Badge alertes ─────────────────────────────────────────────
async function updateBadge() {
  try { const r=await req('api/alertes.php?action=count'),el=document.getElementById('alertBadge'); if(!el)return; if(r.count>0){el.textContent=r.count>99?'99+':r.count;el.classList.remove('hidden');}else el.classList.add('hidden'); } catch {}
}

<?php if($isLoggedIn): ?>
nav('dashboard');
updateBadge();
setInterval(updateBadge,30000);
setInterval(()=>{ if(APP.currentPage==='dashboard') dashboard(); }, 60000);
<?php endif; ?>
</script>
</body>
</html>
