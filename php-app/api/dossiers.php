<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::check();

header('Content-Type: application/json; charset=utf-8');

$db     = DB::get();
$method = method();
$id     = (int)($_GET['id'] ?? 0);

// LIST
if ($method === 'GET' && !$id) {
    [$where, $params] = build_dossier_where();

    $search      = get('search');
    $statut      = get('statut');
    $type        = get('type_controle');
    $agentId     = get('agent_id');

    if ($search) {
        $where .= ' AND (c.nom LIKE ? OR d.numero_dossier LIKE ? OR c.nif LIKE ?)';
        $s = "%$search%";
        array_push($params, $s, $s, $s);
    }
    if ($statut) { $where .= ' AND d.statut = ?'; $params[] = $statut; }
    if ($type)   { $where .= ' AND d.type_controle = ?'; $params[] = $type; }
    if ($agentId){ $where .= ' AND d.agent_id = ?'; $params[] = $agentId; }

    $agentConcat = sql_concat('u.nom', 'u.prenom');
    $chefConcat  = sql_concat('cb.nom', 'cb.prenom');
    $stmt = $db->prepare("
        SELECT d.*,
               c.nom AS contribuable_nom, c.nif, c.type_entreprise, c.secteur_activite,
               $agentConcat AS agent_nom,
               $chefConcat AS chef_brigade_nom,
               b.nom AS brigade_nom,
               (SELECT COUNT(*) FROM etapes e WHERE e.dossier_id = d.id) AS nb_etapes,
               (SELECT COUNT(*) FROM etapes e WHERE e.dossier_id = d.id AND e.statut = 'retard') AS nb_retards
        FROM dossiers d
        JOIN contribuables c ON d.contribuable_id = c.id
        JOIN users u ON d.agent_id = u.id
        LEFT JOIN users cb ON d.chef_brigade_id = cb.id
        LEFT JOIN brigades b ON d.brigade_id = b.id
        WHERE $where
        ORDER BY d.created_at DESC
    ");
    $stmt->execute($params);
    json_response($stmt->fetchAll());
}

// GET ONE
if ($method === 'GET' && $id) {
    $agentConcat = sql_concat('u.nom', 'u.prenom');
    $chefConcat  = sql_concat('cb.nom', 'cb.prenom');
    $stmt = $db->prepare("
        SELECT d.*,
               c.nom AS contribuable_nom, c.nif, c.type_entreprise, c.adresse, c.secteur_activite,
               $agentConcat AS agent_nom, u.email AS agent_email,
               $chefConcat AS chef_brigade_nom,
               b.nom AS brigade_nom
        FROM dossiers d
        JOIN contribuables c ON d.contribuable_id = c.id
        JOIN users u ON d.agent_id = u.id
        LEFT JOIN users cb ON d.chef_brigade_id = cb.id
        LEFT JOIN brigades b ON d.brigade_id = b.id
        WHERE d.id = ?
    ");
    $stmt->execute([$id]);
    $dossier = $stmt->fetch();
    if (!$dossier) json_error('Dossier non trouvé', 404);

    $etapes  = $db->prepare('SELECT * FROM etapes WHERE dossier_id = ? ORDER BY created_at ASC');
    $etapes->execute([$id]);
    $alertes = $db->prepare('SELECT * FROM alertes WHERE dossier_id = ? ORDER BY created_at DESC');
    $alertes->execute([$id]);

    json_response(['dossier' => $dossier, 'etapes' => $etapes->fetchAll(), 'alertes' => $alertes->fetchAll()]);
}

// CREATE
if ($method === 'POST' && !$id) {
    Auth::requireRoles('directeur', 'sous_directeur', 'chef_brigade');
    $body = request_body();
    require_fields($body, 'contribuable_id', 'agent_id', 'date_ouverture', 'type_controle');

    $numero = next_numero_dossier($db, $body['date_ouverture']);
    $stmt   = $db->prepare("
        INSERT INTO dossiers (numero_dossier,contribuable_id,agent_id,chef_brigade_id,brigade_id,date_ouverture,type_controle,observations)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $numero,
        $body['contribuable_id'],
        $body['agent_id'],
        $body['chef_brigade_id'] ?? null,
        $body['brigade_id']      ?? null,
        $body['date_ouverture'],
        $body['type_controle'],
        $body['observations']    ?? '',
    ]);
    $newId = $db->lastInsertId();
    Auth::auditLog(Auth::userId(), 'CREATE', 'dossiers', (int)$newId, "Création $numero");
    json_response(['id' => $newId, 'numero_dossier' => $numero], 201);
}

// UPDATE
if ($method === 'PUT' && $id) {
    $body = request_body();
    $row  = $db->prepare('SELECT * FROM dossiers WHERE id = ?');
    $row->execute([$id]);
    $cur  = $row->fetch();
    if (!$cur) json_error('Dossier non trouvé', 404);

    $db->prepare("
        UPDATE dossiers SET statut=?,observations=?,agent_id=?,chef_brigade_id=?,brigade_id=?,date_cloture=? WHERE id=?
    ")->execute([
        $body['statut']          ?? $cur['statut'],
        $body['observations']    ?? $cur['observations'],
        $body['agent_id']        ?? $cur['agent_id'],
        array_key_exists('chef_brigade_id',$body) ? $body['chef_brigade_id'] : $cur['chef_brigade_id'],
        array_key_exists('brigade_id',$body)      ? $body['brigade_id']      : $cur['brigade_id'],
        array_key_exists('date_cloture',$body)    ? $body['date_cloture']    : $cur['date_cloture'],
        $id,
    ]);
    Auth::auditLog(Auth::userId(), 'UPDATE', 'dossiers', $id, 'Mise à jour');
    json_response(['ok' => true]);
}

// DELETE
if ($method === 'DELETE' && $id) {
    Auth::requireRoles('directeur', 'sous_directeur');
    $db->prepare('DELETE FROM dossiers WHERE id = ?')->execute([$id]);
    Auth::auditLog(Auth::userId(), 'DELETE', 'dossiers', $id, 'Suppression');
    json_response(['ok' => true]);
}

json_error('Méthode non autorisée', 405);
