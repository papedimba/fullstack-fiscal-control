<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::check();

header('Content-Type: application/json; charset=utf-8');

$db = DB::get();
[$where, $params] = build_dossier_where();

$action = $_GET['action'] ?? 'dashboard';

if ($action === 'audit') {
    Auth::requireRoles('directeur', 'sous_directeur');
    $concat = sql_concat('u.nom', 'u.prenom');
    $logs = $db->query("
        SELECT al.*, $concat AS utilisateur
        FROM audit_logs al LEFT JOIN users u ON al.user_id=u.id
        ORDER BY al.created_at DESC LIMIT 200
    ")->fetchAll();
    json_response($logs);
}

// Dashboard stats
$q = fn(string $sql, array $p = []) => (function() use ($db, $sql, $p) {
    $s = $db->prepare($sql); $s->execute($p); return $s->fetchColumn();
})();

$total      = $q("SELECT COUNT(*) FROM dossiers d WHERE $where", $params);
$enCours    = $q("SELECT COUNT(*) FROM dossiers d WHERE $where AND d.statut='en_cours'", $params);
$ouvert     = $q("SELECT COUNT(*) FROM dossiers d WHERE $where AND d.statut='ouvert'", $params);
$cloture    = $q("SELECT COUNT(*) FROM dossiers d WHERE $where AND d.statut='cloture'", $params);
$suspendu   = $q("SELECT COUNT(*) FROM dossiers d WHERE $where AND d.statut='suspendu'", $params);
$contentieux= $q("SELECT COUNT(*) FROM dossiers d WHERE $where AND d.statut='contentieux'", $params);
$nonLues    = $q("SELECT COUNT(*) FROM alertes a JOIN dossiers d ON a.dossier_id=d.id WHERE a.lu=0 AND $where", $params);
$retards    = $q("SELECT COUNT(*) FROM etapes e JOIN dossiers d ON e.dossier_id=d.id WHERE e.statut='retard' AND $where", $params);

$fetch = function(string $sql, array $p = []) use ($db) {
    $s = $db->prepare($sql); $s->execute($p); return $s->fetchAll();
};

$parType = $fetch("SELECT type_controle, COUNT(*) AS cnt FROM dossiers d WHERE $where GROUP BY type_controle", $params);

$agentConcat = sql_concat('u.nom', 'u.prenom');
$parAgent = $fetch("
    SELECT $agentConcat AS agent, COUNT(d.id) AS cnt
    FROM dossiers d JOIN users u ON d.agent_id=u.id
    WHERE $where GROUP BY d.agent_id ORDER BY cnt DESC LIMIT 10
", $params);

$ym      = sql_yearmonth('d.date_ouverture');
$dateSub = sql_date_sub_months(12);
$parMois = $fetch("
    SELECT $ym AS mois, COUNT(*) AS cnt
    FROM dossiers d WHERE $where AND d.date_ouverture >= $dateSub
    GROUP BY mois ORDER BY mois ASC
", $params);

$retardsParEtape = $fetch("
    SELECT e.type_etape, COUNT(*) AS cnt
    FROM etapes e JOIN dossiers d ON e.dossier_id=d.id
    WHERE e.statut='retard' AND $where
    GROUP BY e.type_etape ORDER BY cnt DESC
", $params);

$yr = sql_year('d.date_ouverture');
$progression = $fetch("
    SELECT $yr AS annee,
           SUM(CASE WHEN d.statut='cloture' THEN 1 ELSE 0 END) AS clotures,
           SUM(CASE WHEN d.statut!='cloture' THEN 1 ELSE 0 END) AS en_cours,
           COUNT(*) AS total
    FROM dossiers d WHERE $where
    GROUP BY annee ORDER BY annee DESC LIMIT 5
", $params);

json_response([
    'totaux' => compact('total','enCours','ouvert','cloture','suspendu','contentieux','nonLues','retards'),
    'parType'         => $parType,
    'parAgent'        => $parAgent,
    'parMois'         => $parMois,
    'retardsParEtape' => $retardsParEtape,
    'progression'     => $progression,
]);
