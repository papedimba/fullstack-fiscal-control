<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::check();

header('Content-Type: application/json; charset=utf-8');

$db     = DB::get();
$method = method();

if ($method === 'GET') {
    $rows = $db->query('SELECT * FROM contribuables ORDER BY nom ASC')->fetchAll();
    json_response($rows);
}

if ($method === 'POST') {
    Auth::requireRoles('directeur', 'sous_directeur', 'chef_brigade');
    $body = request_body();
    require_fields($body, 'nom', 'nif', 'type_entreprise');

    $exists = $db->prepare('SELECT id FROM contribuables WHERE nif = ?');
    $exists->execute([$body['nif']]);
    if ($exists->fetch()) json_error('NIF déjà enregistré', 409);

    $stmt = $db->prepare('INSERT INTO contribuables (nom,nif,type_entreprise,adresse,secteur_activite) VALUES (?,?,?,?,?)');
    $stmt->execute([$body['nom'], $body['nif'], $body['type_entreprise'], $body['adresse'] ?? '', $body['secteur_activite'] ?? '']);
    $newId = (int)$db->lastInsertId();
    Auth::auditLog(Auth::userId(), 'CREATE', 'contribuables', $newId, "Création {$body['nom']}");
    json_response(['id' => $newId], 201);
}

if ($method === 'PUT') {
    Auth::requireRoles('directeur', 'sous_directeur', 'chef_brigade');
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_error('ID requis');
    $body = request_body();
    require_fields($body, 'nom', 'type_entreprise');

    $db->prepare('UPDATE contribuables SET nom=?,type_entreprise=?,adresse=?,secteur_activite=? WHERE id=?')
       ->execute([$body['nom'], $body['type_entreprise'], $body['adresse'] ?? '', $body['secteur_activite'] ?? '', $id]);
    Auth::auditLog(Auth::userId(), 'UPDATE', 'contribuables', $id, "Modification {$body['nom']}");
    json_response(['ok' => true]);
}

json_error('Méthode non autorisée', 405);
