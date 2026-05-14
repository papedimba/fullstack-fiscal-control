<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::check();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo '<p>Paramètre id manquant.</p>';
    exit;
}

$db = DB::get();

$agentConcat = sql_concat('u.nom', 'u.prenom');
$chefConcat  = sql_concat('cb.nom', 'cb.prenom');

$stmt = $db->prepare("
    SELECT d.*,
           c.nom AS contribuable_nom, c.nif, c.type_entreprise, c.adresse, c.secteur_activite,
           $agentConcat AS agent_nom,
           $chefConcat  AS chef_brigade_nom,
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

if (!$dossier) {
    http_response_code(404);
    echo '<p>Dossier non trouvé.</p>';
    exit;
}

$stmtEtapes = $db->prepare('SELECT * FROM etapes WHERE dossier_id = ? ORDER BY created_at ASC');
$stmtEtapes->execute([$id]);
$etapes = $stmtEtapes->fetchAll();

$stmtAlertes = $db->prepare("SELECT * FROM alertes WHERE dossier_id = ? AND lu = 0 ORDER BY created_at DESC");
$stmtAlertes->execute([$id]);
$alertes = $stmtAlertes->fetchAll();

$dateGen = date('d/m/Y à H:i');

function h(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function fmtDate(?string $d): string {
    if (!$d) return '—';
    $ts = strtotime($d);
    return $ts ? date('d/m/Y', $ts) : h($d);
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Fiche Dossier <?= h($dossier['numero_dossier']) ?></title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: Arial, Helvetica, sans-serif;
        font-size: 13px;
        color: #222;
        background: #f4f6f9;
        padding: 20px;
    }
    .page {
        background: #fff;
        max-width: 900px;
        margin: 0 auto;
        padding: 30px 40px;
        box-shadow: 0 2px 12px rgba(0,0,0,.15);
    }
    /* En-tête */
    .header {
        border-bottom: 3px solid #1E3A5F;
        padding-bottom: 16px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .header-logo {
        font-size: 15px;
        font-weight: bold;
        color: #1E3A5F;
        text-transform: uppercase;
        line-height: 1.4;
        max-width: 420px;
    }
    .header-logo span {
        display: block;
        font-size: 11px;
        font-weight: normal;
        color: #555;
        text-transform: none;
        margin-top: 4px;
    }
    .header-meta {
        text-align: right;
        font-size: 11px;
        color: #555;
    }
    .header-meta strong {
        display: block;
        font-size: 18px;
        color: #1E3A5F;
        font-weight: bold;
        text-transform: uppercase;
        margin-bottom: 6px;
    }
    /* Sections */
    .section {
        margin-bottom: 28px;
    }
    .section-title {
        background: #1E3A5F;
        color: #fff;
        font-size: 12px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: .5px;
        padding: 6px 12px;
        margin-bottom: 12px;
        border-radius: 3px;
    }
    /* Grille infos */
    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 20px;
    }
    .info-row {
        display: flex;
        gap: 8px;
        padding: 5px 0;
        border-bottom: 1px solid #eee;
    }
    .info-label {
        font-weight: bold;
        color: #1E3A5F;
        min-width: 140px;
        flex-shrink: 0;
        font-size: 11px;
        text-transform: uppercase;
    }
    .info-value {
        color: #333;
    }
    .info-row.full {
        grid-column: 1 / -1;
    }
    /* Badge statut */
    .badge {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
        text-transform: uppercase;
    }
    .badge-en_cours       { background: #DBEAFE; color: #1D4ED8; }
    .badge-cloture        { background: #D1FAE5; color: #065F46; }
    .badge-ouvert         { background: #FEF9C3; color: #854D0E; }
    .badge-suspendu       { background: #FEE2E2; color: #991B1B; }
    /* Tableau étapes */
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }
    thead th {
        background: #1E3A5F;
        color: #fff;
        padding: 8px 10px;
        text-align: left;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .4px;
    }
    tbody tr:nth-child(even) { background: #F8FAFC; }
    tbody tr:hover           { background: #EFF6FF; }
    tbody td {
        padding: 7px 10px;
        border-bottom: 1px solid #E5E7EB;
        vertical-align: top;
    }
    /* Alertes */
    .alerte-item {
        background: #FEF2F2;
        border-left: 4px solid #DC2626;
        padding: 8px 12px;
        margin-bottom: 8px;
        border-radius: 0 4px 4px 0;
        font-size: 12px;
    }
    .alerte-item .alerte-date {
        font-size: 10px;
        color: #888;
        margin-top: 3px;
    }
    .no-data {
        color: #888;
        font-style: italic;
        font-size: 12px;
        padding: 10px 0;
    }
    /* Pied de page */
    .footer {
        border-top: 1px solid #ddd;
        margin-top: 30px;
        padding-top: 10px;
        font-size: 10px;
        color: #aaa;
        text-align: center;
    }
    /* Bouton imprimer */
    .btn-print {
        display: inline-block;
        background: #1E3A5F;
        color: #fff;
        border: none;
        padding: 10px 22px;
        border-radius: 5px;
        font-size: 14px;
        cursor: pointer;
        margin-bottom: 20px;
        font-family: inherit;
    }
    .btn-print:hover { background: #162d4a; }
    @media print {
        body { background: #fff; padding: 0; }
        .page { box-shadow: none; padding: 15px 20px; }
        .btn-print { display: none !important; }
        .section { page-break-inside: avoid; }
    }
</style>
</head>
<body>

<button class="btn-print" onclick="window.print()">&#128438; Imprimer / Exporter PDF</button>

<div class="page">

    <!-- En-tête -->
    <div class="header">
        <div class="header-logo">
            Direction des Vérifications Fiscales Nationale
            <span>République — Administration Fiscale</span>
        </div>
        <div class="header-meta">
            <strong>Fiche de Dossier de Contrôle</strong>
            Généré le : <?= h($dateGen) ?><br>
            N° : <strong><?= h($dossier['numero_dossier']) ?></strong>
        </div>
    </div>

    <!-- Informations générales -->
    <div class="section">
        <div class="section-title">Informations générales</div>
        <div class="info-grid">
            <div class="info-row">
                <span class="info-label">N° Dossier</span>
                <span class="info-value"><strong><?= h($dossier['numero_dossier']) ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Statut</span>
                <span class="info-value">
                    <span class="badge badge-<?= h($dossier['statut']) ?>"><?= h($dossier['statut']) ?></span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Contribuable</span>
                <span class="info-value"><?= h($dossier['contribuable_nom']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">NIF</span>
                <span class="info-value"><?= h($dossier['nif']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Type d'entreprise</span>
                <span class="info-value"><?= h($dossier['type_entreprise']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Type de contrôle</span>
                <span class="info-value"><?= h($dossier['type_controle']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Agent vérificateur</span>
                <span class="info-value"><?= h($dossier['agent_nom']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Chef de brigade</span>
                <span class="info-value"><?= h($dossier['chef_brigade_nom'] ?: '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Brigade</span>
                <span class="info-value"><?= h($dossier['brigade_nom'] ?: '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date d'ouverture</span>
                <span class="info-value"><?= fmtDate($dossier['date_ouverture']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date de clôture</span>
                <span class="info-value"><?= fmtDate($dossier['date_cloture']) ?></span>
            </div>
            <div class="info-row full">
                <span class="info-label">Observations</span>
                <span class="info-value"><?= h($dossier['observations'] ?: '—') ?></span>
            </div>
        </div>
    </div>

    <!-- Étapes -->
    <div class="section">
        <div class="section-title">Étapes du dossier (<?= count($etapes) ?>)</div>
        <?php if (empty($etapes)): ?>
            <p class="no-data">Aucune étape enregistrée.</p>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Type d'étape</th>
                    <th>Statut</th>
                    <th>Date prévue</th>
                    <th>Date réalisation</th>
                    <th>Commentaire</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($etapes as $i => $e): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= h($e['type_etape']) ?></td>
                    <td><?= h($e['statut']) ?></td>
                    <td><?= fmtDate($e['date_prevue'] ?? null) ?></td>
                    <td><?= fmtDate($e['date_realisation'] ?? null) ?></td>
                    <td><?= h($e['commentaire'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- Alertes actives -->
    <div class="section">
        <div class="section-title">Alertes actives (non lues)</div>
        <?php if (empty($alertes)): ?>
            <p class="no-data">Aucune alerte active.</p>
        <?php else: ?>
            <?php foreach ($alertes as $a): ?>
            <div class="alerte-item">
                <strong><?= h($a['type_alerte'] ?? $a['titre'] ?? 'Alerte') ?></strong>
                — <?= h($a['message'] ?? '') ?>
                <div class="alerte-date"><?= fmtDate($a['created_at'] ?? null) ?></div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="footer">
        Document généré automatiquement le <?= h($dateGen) ?> — Direction des Vérifications Fiscales Nationale
    </div>
</div>

</body>
</html>
