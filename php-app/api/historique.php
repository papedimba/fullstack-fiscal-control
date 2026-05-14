<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::check();

header('Content-Type: application/json; charset=utf-8');

$db        = DB::get();
$method    = method();
$dossierId = (int)($_GET['dossier_id'] ?? 0);

// Seule la méthode GET est supportée
if ($method !== 'GET') json_error('Méthode non autorisée', 405);

if (!$dossierId) json_error('dossier_id requis');

// Vérifier que le dossier existe
$check = $db->prepare('SELECT id FROM dossiers WHERE id = ?');
$check->execute([$dossierId]);
if (!$check->fetch()) json_error('Dossier non trouvé', 404);

$userConcat = sql_concat('u.nom', 'u.prenom');
$stmt = $db->prepare("
    SELECT h.id, h.dossier_id, h.user_id, h.action, h.details, h.created_at,
           $userConcat AS utilisateur_nom
    FROM dossier_historique h
    LEFT JOIN users u ON h.user_id = u.id
    WHERE h.dossier_id = ?
    ORDER BY h.created_at DESC
");
$stmt->execute([$dossierId]);
json_response($stmt->fetchAll());
