<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::check();

header('Content-Type: application/json; charset=utf-8');

$db     = DB::get();
$method = method();

if ($method !== 'GET') json_error('Méthode non autorisée', 405);

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) json_error('La recherche doit contenir au moins 2 caractères');

$like = "%$q%";

// --- Recherche dans dossiers (avec JOIN contribuables) ---
$stmt = $db->prepare("
    SELECT d.id, d.numero_dossier, d.statut, d.type_controle, d.date_ouverture,
           c.nom AS contribuable_nom, c.nif
    FROM dossiers d
    JOIN contribuables c ON d.contribuable_id = c.id
    WHERE d.numero_dossier LIKE ? OR d.observations LIKE ?
    ORDER BY d.created_at DESC
    LIMIT 10
");
$stmt->execute([$like, $like]);
$dossiers = $stmt->fetchAll();

// --- Recherche dans contribuables ---
$stmt = $db->prepare("
    SELECT id, nom, nif, type_entreprise, adresse, secteur_activite
    FROM contribuables
    WHERE nom LIKE ? OR nif LIKE ?
    ORDER BY nom ASC
    LIMIT 10
");
$stmt->execute([$like, $like]);
$contribuables = $stmt->fetchAll();

// --- Recherche dans users ---
$agentConcat = sql_concat('u.nom', 'u.prenom');
$stmt = $db->prepare("
    SELECT u.id, u.nom, u.prenom, u.email, u.role,
           $agentConcat AS nom_complet
    FROM users u
    WHERE u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?
    ORDER BY u.nom ASC
    LIMIT 10
");
$stmt->execute([$like, $like, $like]);
$users = $stmt->fetchAll();

json_response([
    'dossiers'      => $dossiers,
    'contribuables' => $contribuables,
    'users'         => $users,
]);
