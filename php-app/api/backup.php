<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::requireRoles('directeur', 'sous_directeur');

$db       = DB::get();
$date     = date('Y-m-d');
$filename = "backup_fiscal_$date";

// ============================================================
//  SQLite : retourner le fichier SQLite brut en téléchargement
// ============================================================
if (USE_SQLITE) {
    $filePath = SQLITE_PATH;
    if (!file_exists($filePath)) {
        http_response_code(500);
        echo json_encode(['error' => 'Fichier SQLite introuvable']);
        exit;
    }

    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"{$filename}.db\"");
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, no-store, must-revalidate');

    Auth::auditLog(Auth::userId(), 'BACKUP', 'database', null, 'Téléchargement sauvegarde SQLite');
    readfile($filePath);
    exit;
}

// ============================================================
//  MySQL : générer un dump SQL via PDO
// ============================================================

// Tables à inclure dans le dump (users sans password_hash pour sécurité)
$tables = [
    'brigades',
    'users',
    'contribuables',
    'dossiers',
    'etapes',
    'alertes',
    'audit_logs',
    'commentaires',
    'pieces_jointes',
    'dossier_historique',
];

// Colonnes à masquer pour des raisons de sécurité : users.password_hash
$maskedColumns = [
    'users' => ['password_hash'],
];

$dump  = "-- ============================================================\n";
$dump .= "--  Sauvegarde base de données : fiscal_control\n";
$dump .= "--  Date : $date\n";
$dump .= "--  Généré par : Tableau de Bord Contrôle Fiscal\n";
$dump .= "-- ============================================================\n\n";
$dump .= "SET NAMES utf8mb4;\n";
$dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($tables as $table) {
    // Structure : récupérer les colonnes
    $colStmt = $db->query("SHOW COLUMNS FROM `$table`");
    $columns = $colStmt->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($columns, 'Field');

    // Données
    $rowStmt = $db->query("SELECT * FROM `$table`");
    $rows    = $rowStmt->fetchAll(PDO::FETCH_ASSOC);

    $dump .= "-- -----------------------------------------------------------\n";
    $dump .= "--  Table : $table\n";
    $dump .= "-- -----------------------------------------------------------\n";
    $dump .= "TRUNCATE TABLE `$table`;\n";

    if (!empty($rows)) {
        $masked = $maskedColumns[$table] ?? [];

        // Construire les colonnes d'entête pour INSERT
        $colList = implode(', ', array_map(fn($c) => "`$c`", $colNames));

        foreach ($rows as $row) {
            $values = [];
            foreach ($colNames as $col) {
                if (in_array($col, $masked, true)) {
                    // Remplacer par chaîne vide pour sécurité
                    $values[] = "''";
                } elseif ($row[$col] === null) {
                    $values[] = 'NULL';
                } else {
                    $escaped  = str_replace(
                        ["\\", "'", "\n", "\r", "\x1a"],
                        ["\\\\", "\\'", "\\n", "\\r", "\\Z"],
                        (string)$row[$col]
                    );
                    $values[] = "'$escaped'";
                }
            }
            $valueList = implode(', ', $values);
            $dump .= "INSERT INTO `$table` ($colList) VALUES ($valueList);\n";
        }
    }

    $dump .= "\n";
}

$dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
$dump .= "-- Fin du dump\n";

Auth::auditLog(Auth::userId(), 'BACKUP', 'database', null, 'Téléchargement sauvegarde MySQL');

header('Content-Type: application/sql; charset=utf-8');
header("Content-Disposition: attachment; filename=\"{$filename}.sql\"");
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Content-Length: ' . strlen($dump));

echo $dump;
exit;
