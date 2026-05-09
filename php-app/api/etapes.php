<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::check();

header('Content-Type: application/json; charset=utf-8');

$db         = DB::get();
$method     = method();
$id         = (int)($_GET['id']         ?? 0); // étape id
$dossierId  = (int)($_GET['dossier_id'] ?? 0);
$action     = $_GET['action'] ?? '';

// Refresh retards
if ($action === 'refresh') {
    Auth::requireRoles('directeur', 'sous_directeur');
    $today   = date('Y-m-d');
    $retards = $db->prepare("
        SELECT e.*, d.numero_dossier FROM etapes e
        JOIN dossiers d ON e.dossier_id = d.id
        WHERE e.date_prevue IS NOT NULL AND e.date_prevue < ? AND e.statut NOT IN ('termine','retard')
          AND d.statut NOT IN ('cloture')
    ");
    $retards->execute([$today]);
    $rows  = $retards->fetchAll();
    $count = 0;
    foreach ($rows as $e) {
        $db->prepare('UPDATE etapes SET statut = ? WHERE id = ?')->execute(['retard', $e['id']]);
        $exists = $db->prepare('SELECT id FROM alertes WHERE etape_id = ? AND type = ?');
        $exists->execute([$e['id'], 'critique']);
        if (!$exists->fetch()) {
            $db->prepare('INSERT INTO alertes (dossier_id,etape_id,message,type) VALUES (?,?,?,?)')
               ->execute([$e['dossier_id'], $e['id'], "Retard sur l'étape du dossier {$e['numero_dossier']}", 'critique']);
        }
        $count++;
    }
    json_response(['updated' => $count]);
}

// LIST by dossier
if ($method === 'GET' && $dossierId) {
    $stmt = $db->prepare('SELECT * FROM etapes WHERE dossier_id = ? ORDER BY created_at ASC');
    $stmt->execute([$dossierId]);
    json_response($stmt->fetchAll());
}

// CREATE
if ($method === 'POST' && $dossierId) {
    $body = request_body();
    require_fields($body, 'type_etape');

    $stmt = $db->prepare('INSERT INTO etapes (dossier_id,type_etape,date_prevue,commentaire) VALUES (?,?,?,?)');
    $stmt->execute([$dossierId, $body['type_etape'], $body['date_prevue'] ?? null, $body['commentaire'] ?? '']);

    // Passer le dossier en 'en_cours' s'il était 'ouvert'
    $db->prepare("UPDATE dossiers SET statut='en_cours' WHERE id=? AND statut='ouvert'")->execute([$dossierId]);
    Auth::auditLog(Auth::userId(), 'CREATE', 'etapes', (int)$db->lastInsertId(), "Étape {$body['type_etape']}");
    json_response(['id' => $db->lastInsertId()], 201);
}

// UPDATE
if ($method === 'PUT' && $id) {
    $body = request_body();
    $row  = $db->prepare('SELECT * FROM etapes WHERE id = ?');
    $row->execute([$id]);
    $cur  = $row->fetch();
    if (!$cur) json_error('Étape non trouvée', 404);

    $statut = $body['statut'] ?? $cur['statut'];
    // Auto-retard
    $datePrevue = $body['date_prevue'] ?? $cur['date_prevue'];
    $dateReal   = $body['date_realisation'] ?? $cur['date_realisation'];
    if ($datePrevue && !$dateReal && $statut !== 'termine' && $datePrevue < date('Y-m-d')) {
        $statut = 'retard';
    }

    $db->prepare('UPDATE etapes SET statut=?,date_prevue=?,date_realisation=?,commentaire=? WHERE id=?')->execute([
        $statut, $datePrevue, $dateReal, $body['commentaire'] ?? $cur['commentaire'], $id,
    ]);

    // Créer une alerte si passage en retard
    if ($statut === 'retard' && $cur['statut'] !== 'retard') {
        $d = $db->prepare('SELECT numero_dossier FROM dossiers WHERE id=?');
        $d->execute([$cur['dossier_id']]);
        $num = $d->fetchColumn();
        $db->prepare('INSERT INTO alertes (dossier_id,etape_id,message,type) VALUES (?,?,?,?)')
           ->execute([$cur['dossier_id'], $id, "Retard sur l'étape \"{$cur['type_etape']}\" du dossier $num", 'critique']);
    }

    Auth::auditLog(Auth::userId(), 'UPDATE', 'etapes', $id, 'Mise à jour étape');
    json_response(['ok' => true]);
}

// DELETE
if ($method === 'DELETE' && $id) {
    Auth::requireRoles('directeur', 'sous_directeur', 'chef_brigade');
    $db->prepare('DELETE FROM etapes WHERE id = ?')->execute([$id]);
    json_response(['ok' => true]);
}

json_error('Méthode non autorisée', 405);
