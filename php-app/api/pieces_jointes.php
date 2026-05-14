<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::check();

header('Content-Type: application/json; charset=utf-8');

$db     = DB::get();
$method = method();

// ─── GET : liste ou téléchargement ───────────────────────────────────────────

if ($method === 'GET') {

    // Téléchargement d'un fichier
    $fileId   = (int)($_GET['id'] ?? 0);
    $download = isset($_GET['download']) && $_GET['download'] === '1';

    if ($fileId && $download) {
        $stmt = $db->prepare('SELECT * FROM pieces_jointes WHERE id = ?');
        $stmt->execute([$fileId]);
        $file = $stmt->fetch();

        if (!$file) {
            http_response_code(404);
            echo json_encode(['error' => 'Fichier non trouvé']);
            exit;
        }

        $fullPath = UPLOAD_DIR . $file['dossier_id'] . '/' . $file['nom_stockage'];

        if (!file_exists($fullPath)) {
            http_response_code(404);
            echo json_encode(['error' => 'Fichier physique introuvable']);
            exit;
        }

        // Retirer le header JSON mis en tête
        header_remove('Content-Type');
        header('Content-Type: ' . ($file['mime_type'] ?: 'application/octet-stream'));
        header('Content-Disposition: attachment; filename="' . addslashes($file['nom_original']) . '"');
        header('Content-Length: ' . $file['taille']);
        header('Cache-Control: private, no-cache');

        readfile($fullPath);
        exit;
    }

    // Liste des fichiers d'un dossier
    $dossierId = (int)($_GET['dossier_id'] ?? 0);
    if (!$dossierId) {
        json_error('Paramètre dossier_id requis');
    }

    $userConcat = sql_concat('u.nom', 'u.prenom');
    $stmt = $db->prepare("
        SELECT pj.id, pj.nom_original, pj.mime_type, pj.taille, pj.created_at,
               $userConcat AS user_nom
        FROM pieces_jointes pj
        JOIN users u ON pj.user_id = u.id
        WHERE pj.dossier_id = ?
        ORDER BY pj.created_at DESC
    ");
    $stmt->execute([$dossierId]);
    json_response($stmt->fetchAll());
}

// ─── POST : upload ────────────────────────────────────────────────────────────

if ($method === 'POST') {
    $dossierId = (int)($_GET['dossier_id'] ?? $_POST['dossier_id'] ?? 0);
    if (!$dossierId) {
        json_error('Paramètre dossier_id requis');
    }

    if (empty($_FILES['fichier']) || $_FILES['fichier']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['fichier']['error'] ?? -1;
        $errMsg  = match ($errCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux.',
            UPLOAD_ERR_NO_FILE                        => 'Aucun fichier envoyé.',
            default                                   => 'Erreur lors de l\'upload (code ' . $errCode . ').',
        };
        json_error($errMsg);
    }

    $file        = $_FILES['fichier'];
    $nomOriginal = $file['name'];
    $taille      = $file['size'];
    $tmpPath     = $file['tmp_name'];
    $mimeType    = mime_content_type($tmpPath) ?: 'application/octet-stream';

    // Vérification taille
    if ($taille > MAX_FILE_SIZE) {
        json_error('Fichier trop volumineux. Maximum autorisé : ' . (MAX_FILE_SIZE / 1024 / 1024) . ' Mo.');
    }

    // Vérification extension
    $ext          = strtolower(pathinfo($nomOriginal, PATHINFO_EXTENSION));
    $allowedExts  = array_map('trim', explode(',', ALLOWED_EXTS));
    if (!in_array($ext, $allowedExts, true)) {
        json_error('Extension non autorisée. Extensions acceptées : ' . ALLOWED_EXTS . '.');
    }

    // Répertoire de destination
    $destDir = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . $dossierId . DIRECTORY_SEPARATOR;
    if (!is_dir($destDir)) {
        if (!mkdir($destDir, 0755, true)) {
            json_error('Impossible de créer le répertoire de stockage.', 500);
        }
    }

    // Nom de stockage unique
    $nomStockage = uniqid('pj_', true) . '.' . $ext;
    $destPath    = $destDir . $nomStockage;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        json_error('Échec du déplacement du fichier.', 500);
    }

    // Insertion en base
    $stmt = $db->prepare("
        INSERT INTO pieces_jointes (dossier_id, user_id, nom_original, nom_stockage, mime_type, taille)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$dossierId, Auth::userId(), $nomOriginal, $nomStockage, $mimeType, $taille]);
    $newId = (int)$db->lastInsertId();

    Auth::auditLog(Auth::userId(), 'UPLOAD', 'pieces_jointes', $newId, "Fichier : $nomOriginal");
    json_response(['id' => $newId, 'nom_original' => $nomOriginal, 'taille' => $taille], 201);
}

// ─── DELETE : suppression ─────────────────────────────────────────────────────

if ($method === 'DELETE') {
    $fileId = (int)($_GET['id'] ?? 0);
    if (!$fileId) {
        json_error('Paramètre id requis');
    }

    $stmt = $db->prepare('SELECT * FROM pieces_jointes WHERE id = ?');
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();

    if (!$file) {
        json_error('Fichier non trouvé', 404);
    }

    $role   = Auth::role();
    $userId = Auth::userId();

    // Autorisation : auteur OU directeur / sous_directeur
    if ((int)$file['user_id'] !== $userId && !in_array($role, ['directeur', 'sous_directeur'], true)) {
        json_error('Accès refusé : vous n\'êtes pas l\'auteur de ce fichier.', 403);
    }

    // Suppression physique
    $fullPath = UPLOAD_DIR . $file['dossier_id'] . DIRECTORY_SEPARATOR . $file['nom_stockage'];
    if (file_exists($fullPath)) {
        @unlink($fullPath);
    }

    // Suppression en base
    $db->prepare('DELETE FROM pieces_jointes WHERE id = ?')->execute([$fileId]);

    Auth::auditLog($userId, 'DELETE', 'pieces_jointes', $fileId, 'Suppression fichier : ' . $file['nom_original']);
    json_response(['ok' => true]);
}

json_error('Méthode non autorisée', 405);
