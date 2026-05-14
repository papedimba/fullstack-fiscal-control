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

// --- Email (notifications & réinitialisation de mot de passe) ---
define('MAIL_ENABLED',   false);             // true pour activer l'envoi
define('MAIL_HOST',      'smtp.example.com');
define('MAIL_PORT',      587);
define('MAIL_USER',      'noreply@fiscalite.ci');
define('MAIL_PASS',      '');
define('MAIL_FROM',      'noreply@fiscalite.ci');
define('MAIL_FROM_NAME', 'Contrôle Fiscal');
define('APP_URL',        'http://localhost/fiscal-control'); // URL de base

// --- Pièces jointes ---
define('UPLOAD_DIR',     __DIR__ . '/../data/uploads/');
define('MAX_FILE_SIZE',  10 * 1024 * 1024); // 10 Mo
define('ALLOWED_EXTS',   'pdf,doc,docx,xls,xlsx,jpg,jpeg,png,txt,zip');
