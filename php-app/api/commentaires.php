<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::check();

header('Content-Type: application/json; charset=utf-8');

$db         = DB::get();
$method     = method();
$dossierId  = (int)($_GET['dossier_id'] ?? 0);
$id         = (int)($_GET['id'] ?? 0);

// GET ?dossier_id=X → liste des commentaires avec nom de l'auteur
if ($method === 'GET') {
    if (!$dossierId) json_error('dossier_id requis');

    $auteurConcat = sql_concat('u.nom', 'u.prenom');
    $stmt = $db->prepare("
        SELECT c.id, c.dossier_id, c.user_id, c.message, c.created_at,
               $auteurConcat AS auteur_nom
        FROM commentaires c
        JOIN users u ON c.user_id = u.id
        WHERE c.dossier_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$dossierId]);
    json_response($stmt->fetchAll());
}

// POST ?dossier_id=X body={message} → créer un commentaire
if ($method === 'POST') {
    if (!$dossierId) json_error('dossier_id requis');

    $body = request_body();
    require_fields($body, 'message');

    $message = trim($body['message']);
    if ($message === '') json_error('Le message ne peut pas être vide');

    $userId = Auth::userId();

    // Vérifier que le dossier existe
    $check = $db->prepare('SELECT id FROM dossiers WHERE id = ?');
    $check->execute([$dossierId]);
    if (!$check->fetch()) json_error('Dossier non trouvé', 404);

    $stmt = $db->prepare('INSERT INTO commentaires (dossier_id, user_id, message) VALUES (?, ?, ?)');
    $stmt->execute([$dossierId, $userId, $message]);
    $newId = (int)$db->lastInsertId();

    Auth::auditLog($userId, 'CREATE', 'commentaires', $newId, "Commentaire sur dossier $dossierId");
    json_response(['id' => $newId], 201);
}

// DELETE ?id=X → supprimer (son propre commentaire ou directeur/sous_directeur)
if ($method === 'DELETE') {
    if (!$id) json_error('id requis');

    $stmt = $db->prepare('SELECT * FROM commentaires WHERE id = ?');
    $stmt->execute([$id]);
    $commentaire = $stmt->fetch();
    if (!$commentaire) json_error('Commentaire non trouvé', 404);

    $userId = Auth::userId();
    $role   = Auth::role();

    // Autoriser : propriétaire du commentaire ou directeur/sous_directeur
    if ($commentaire['user_id'] !== $userId && !in_array($role, ['directeur', 'sous_directeur'], true)) {
        json_error('Accès refusé : vous ne pouvez supprimer que vos propres commentaires', 403);
    }

    $db->prepare('DELETE FROM commentaires WHERE id = ?')->execute([$id]);
    Auth::auditLog($userId, 'DELETE', 'commentaires', $id, 'Suppression commentaire');
    json_response(['ok' => true]);
}

json_error('Méthode non autorisée', 405);
