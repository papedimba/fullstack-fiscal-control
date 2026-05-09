<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::requireRoles('directeur', 'sous_directeur');

header('Content-Type: application/json; charset=utf-8');

$db     = DB::get();
$method = method();
$id     = (int)($_GET['id'] ?? 0);

if ($method === 'GET') {
    $rows = $db->query("
        SELECT u.id,u.nom,u.prenom,u.email,u.role,u.brigade_id,u.actif,u.created_at,
               b.nom AS brigade_nom
        FROM users u LEFT JOIN brigades b ON u.brigade_id=b.id
        ORDER BY u.created_at DESC
    ")->fetchAll();
    json_response($rows);
}

if ($method === 'POST') {
    Auth::requireRoles('directeur');
    $body = request_body();
    require_fields($body, 'nom', 'prenom', 'email', 'password', 'role');

    $exists = $db->prepare('SELECT id FROM users WHERE email=?');
    $exists->execute([$body['email']]);
    if ($exists->fetch()) json_error('Email déjà utilisé', 409);

    $hash = password_hash($body['password'], PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO users (nom,prenom,email,password_hash,role,brigade_id) VALUES (?,?,?,?,?,?)');
    $stmt->execute([$body['nom'], $body['prenom'], $body['email'], $hash, $body['role'], $body['brigade_id'] ?? null]);
    $newId = $db->lastInsertId();
    Auth::auditLog(Auth::userId(), 'CREATE', 'users', (int)$newId, "Création {$body['email']}");
    json_response(['id' => $newId], 201);
}

if ($method === 'PUT' && $id) {
    Auth::requireRoles('directeur');
    $body = request_body();
    $cur  = $db->prepare('SELECT * FROM users WHERE id=?');
    $cur->execute([$id]);
    $user = $cur->fetch();
    if (!$user) json_error('Utilisateur non trouvé', 404);

    $hash = $user['password_hash'];
    if (!empty($body['password'])) $hash = password_hash($body['password'], PASSWORD_BCRYPT);

    $db->prepare('UPDATE users SET nom=?,prenom=?,email=?,password_hash=?,role=?,brigade_id=?,actif=? WHERE id=?')->execute([
        $body['nom']        ?? $user['nom'],
        $body['prenom']     ?? $user['prenom'],
        $body['email']      ?? $user['email'],
        $hash,
        $body['role']       ?? $user['role'],
        array_key_exists('brigade_id',$body) ? $body['brigade_id'] : $user['brigade_id'],
        array_key_exists('actif',$body)      ? (int)$body['actif'] : $user['actif'],
        $id,
    ]);
    Auth::auditLog(Auth::userId(), 'UPDATE', 'users', $id, 'Mise à jour');
    json_response(['ok' => true]);
}

if ($method === 'DELETE' && $id) {
    Auth::requireRoles('directeur');
    $db->prepare('UPDATE users SET actif=0 WHERE id=?')->execute([$id]);
    Auth::auditLog(Auth::userId(), 'DEACTIVATE', 'users', $id, 'Désactivation');
    json_response(['ok' => true]);
}

json_error('Méthode non autorisée', 405);
