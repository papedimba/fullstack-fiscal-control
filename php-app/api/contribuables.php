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
    Auth::auditLog(Auth::userId(), 'CREATE', 'contribuables', (int)$db->lastInsertId(), "Création {$body['nom']}");
    json_response(['id' => $db->lastInsertId()], 201);
}

json_error('Méthode non autorisée', 405);
