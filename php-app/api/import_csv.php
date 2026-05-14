<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::requireRoles('directeur', 'sous_directeur', 'chef_brigade');

header('Content-Type: application/json; charset=utf-8');

$method = method();
if ($method !== 'POST') json_error('Méthode non autorisée', 405);

// Vérifier qu'un fichier a bien été envoyé
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    $uploadError = $_FILES['csv_file']['error'] ?? -1;
    json_error("Fichier CSV manquant ou erreur d'envoi (code: $uploadError)");
}

$tmpPath = $_FILES['csv_file']['tmp_name'];
if (!is_readable($tmpPath)) json_error('Impossible de lire le fichier uploadé');

// Lire le contenu et forcer l'encodage UTF-8
$content = file_get_contents($tmpPath);
if ($content === false) json_error('Impossible de lire le contenu du fichier');

// Détecter et convertir l'encodage si nécessaire
if (!mb_check_encoding($content, 'UTF-8')) {
    $content = mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
}

// Normaliser les fins de ligne
$content = str_replace(["\r\n", "\r"], "\n", $content);
$lines   = explode("\n", $content);

// Supprimer le BOM UTF-8 éventuel sur la première ligne
if (str_starts_with($lines[0] ?? '', "\xEF\xBB\xBF")) {
    $lines[0] = substr($lines[0], 3);
}

// Ignorer la première ligne (en-têtes)
array_shift($lines);

$MAX_LINES = 500;
$importes  = 0;
$ignores   = 0;
$erreurs   = [];

$db   = DB::get();
$stmt = $db->prepare(
    'INSERT INTO contribuables (nom, nif, type_entreprise, adresse, secteur_activite) VALUES (?, ?, ?, ?, ?)'
);
$checkNif = $db->prepare('SELECT id FROM contribuables WHERE nif = ?');

$lineNum = 1; // ligne 1 = première ligne de données (après en-têtes)

foreach ($lines as $rawLine) {
    if ($lineNum > $MAX_LINES) {
        $erreurs[] = "Limite de $MAX_LINES lignes atteinte, les lignes suivantes ont été ignorées.";
        break;
    }

    $line = trim($rawLine);

    // Ignorer les lignes vides
    if ($line === '') {
        $lineNum++;
        continue;
    }

    // Découper avec le séparateur point-virgule
    $cols = str_getcsv($line, ';');

    // Nettoyer chaque colonne
    $cols = array_map('trim', $cols);

    // Vérifier qu'on a au moins nom et nif (colonnes 0 et 1)
    if (count($cols) < 2) {
        $erreurs[] = "Ligne $lineNum : format invalide (moins de 2 colonnes)";
        $lineNum++;
        continue;
    }

    $nom              = $cols[0] ?? '';
    $nif              = $cols[1] ?? '';
    $type_entreprise  = $cols[2] ?? '';
    $adresse          = $cols[3] ?? '';
    $secteur_activite = $cols[4] ?? '';

    // Valider les champs obligatoires
    if ($nom === '') {
        $erreurs[] = "Ligne $lineNum : le nom est vide";
        $lineNum++;
        continue;
    }
    if ($nif === '') {
        $erreurs[] = "Ligne $lineNum : le NIF est vide";
        $lineNum++;
        continue;
    }

    // Vérifier si le NIF existe déjà
    $checkNif->execute([$nif]);
    if ($checkNif->fetch()) {
        $ignores++;
        $lineNum++;
        continue;
    }

    // Insérer le contribuable
    try {
        $stmt->execute([$nom, $nif, $type_entreprise, $adresse, $secteur_activite]);
        $importes++;
    } catch (PDOException $e) {
        // Contrainte d'unicité sur le NIF (race condition possible)
        if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE')) {
            $ignores++;
        } else {
            $erreurs[] = "Ligne $lineNum : erreur base de données - " . $e->getMessage();
        }
    }

    $lineNum++;
}

Auth::auditLog(
    Auth::userId(),
    'IMPORT_CSV',
    'contribuables',
    null,
    "Import CSV : $importes importés, $ignores ignorés"
);

json_response([
    'importes' => $importes,
    'ignores'  => $ignores,
    'erreurs'  => $erreurs,
]);
