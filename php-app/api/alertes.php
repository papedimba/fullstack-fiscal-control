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
$action = $_GET['action'] ?? '';

[$where, $params] = build_dossier_where();

// Count non lues
if ($action === 'count') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM alertes a JOIN dossiers d ON a.dossier_id=d.id WHERE a.lu=0 AND $where");
    $stmt->execute($params);
    json_response(['count' => (int)$stmt->fetchColumn()]);
}

// Mark all read
if ($action === 'mark_all_read' && $method === 'PUT') {
    $role = Auth::role();
    if ($role === 'agent') {
        $db->prepare('UPDATE alertes SET lu=1 WHERE dossier_id IN (SELECT id FROM dossiers WHERE agent_id=?)')->execute([Auth::userId()]);
    } elseif ($role === 'chef_brigade') {
        $db->prepare('UPDATE alertes SET lu=1 WHERE dossier_id IN (SELECT id FROM dossiers WHERE brigade_id=?)')->execute([Auth::brigadeId()]);
    } else {
        $db->exec('UPDATE alertes SET lu=1');
    }
    json_response(['ok' => true]);
}

// Mark one read
if ($id && $method === 'PUT') {
    $db->prepare('UPDATE alertes SET lu=1 WHERE id=?')->execute([$id]);
    json_response(['ok' => true]);
}

// LIST
if ($method === 'GET') {
    $type = get('type');
    $lu   = get('lu');
    $w    = $where;
    $p    = $params;
    if ($type)         { $w .= ' AND a.type=?'; $p[] = $type; }
    if ($lu !== null)  { $w .= ' AND a.lu=?';   $p[] = ($lu === 'true') ? 1 : 0; }

    $stmt = $db->prepare("
        SELECT a.*, d.numero_dossier, c.nom AS contribuable_nom
        FROM alertes a
        JOIN dossiers d ON a.dossier_id=d.id
        JOIN contribuables c ON d.contribuable_id=c.id
        WHERE $w
        ORDER BY a.created_at DESC
        LIMIT 100
    ");
    $stmt->execute($p);
    json_response($stmt->fetchAll());
}

json_error('Méthode non autorisée', 405);
