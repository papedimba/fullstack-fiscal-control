<?php
// ============================================================
//  Configuration — modifier selon votre environnement
// ============================================================

// --- Base de données ---
// Pour MySQL : définir USE_SQLITE = false et renseigner les paramètres MySQL
define('USE_SQLITE', true);          // true = SQLite (dev/démo), false = MySQL (prod)

define('DB_HOST',     '127.0.0.1');
define('DB_PORT',     '3306');
define('DB_NAME',     'fiscal_control');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

// SQLite (utilisé si USE_SQLITE = true)
define('SQLITE_PATH', __DIR__ . '/../data/fiscal_control.db');

// --- Application ---
define('APP_NAME',    'Tableau de Bord – Contrôle Fiscal');
define('APP_VERSION', '1.0.0');

// Session
define('SESSION_LIFETIME', 28800); // 8 heures en secondes

// Timezone
date_default_timezone_set('Africa/Abidjan');
