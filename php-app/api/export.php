<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$vendorPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorPath)) require_once $vendorPath;

Auth::start();
Auth::check();

$db     = DB::get();
$format = $_GET['format'] ?? 'excel';
$annee  = $_GET['annee']  ?? date('Y');
$mois   = $_GET['mois']   ?? '';
$trim   = $_GET['trimestre'] ?? '';

[$where, $params] = build_dossier_where();
$where .= " AND strftime('%Y', d.date_ouverture) = ?";
$params[] = $annee;

if ($mois) {
    $where .= " AND strftime('%m', d.date_ouverture) = ?";
    $params[] = str_pad($mois, 2, '0', STR_PAD_LEFT);
}
if ($trim) {
    $debut = ((int)$trim - 1) * 3 + 1;
    $fin   = (int)$trim * 3;
    $where .= " AND CAST(strftime('%m', d.date_ouverture) AS INTEGER) BETWEEN ? AND ?";
    $params[] = $debut;
    $params[] = $fin;
}

$stmt = $db->prepare("
    SELECT d.numero_dossier, c.nom AS contribuable, c.nif, c.type_entreprise, c.secteur_activite,
           d.type_controle, d.statut, d.date_ouverture, d.date_cloture,
           u.nom || ' ' || u.prenom AS agent,
           cb.nom || ' ' || cb.prenom AS chef_brigade,
           b.nom AS brigade,
           (SELECT COUNT(*) FROM etapes e WHERE e.dossier_id=d.id AND e.statut='retard') AS retards
    FROM dossiers d
    JOIN contribuables c ON d.contribuable_id=c.id
    JOIN users u ON d.agent_id=u.id
    LEFT JOIN users cb ON d.chef_brigade_id=cb.id
    LEFT JOIN brigades b ON d.brigade_id=b.id
    WHERE $where
    ORDER BY d.date_ouverture DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ---- JSON ----
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="rapport_fiscal_' . $annee . '.json"');
    echo json_encode([
        'generated_at' => date('c'),
        'total'        => count($rows),
        'dossiers'     => $rows,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ---- Excel via PhpSpreadsheet ----
if ($format === 'excel' && file_exists($vendorPath) && class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Dossiers');

    $headers = ['N° Dossier','Contribuable','NIF','Type entreprise','Secteur','Type contrôle','Statut','Date ouverture','Date clôture','Agent','Chef brigade','Brigade','Retards'];
    $letters = range('A', 'M');

    foreach ($headers as $i => $h) {
        $cell = $letters[$i] . '1';
        $sheet->setCellValue($cell, $h);
        $sheet->getStyle($cell)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E3A5F']],
            'alignment' => ['horizontal' => 'center'],
        ]);
        $sheet->getColumnDimension($letters[$i])->setAutoSize(true);
    }

    $rowNum = 2;
    foreach ($rows as $r) {
        $sheet->fromArray(array_values($r), null, 'A' . $rowNum);
        if ($r['statut'] === 'cloture') {
            $sheet->getStyle("A{$rowNum}:M{$rowNum}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFD4EDDA');
        } elseif ((int)$r['retards'] > 0) {
            $sheet->getStyle("A{$rowNum}:M{$rowNum}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFEEBA');
        }
        $rowNum++;
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="rapport_fiscal_' . $annee . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
    exit;
}

// ---- CSV fallback ----
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="rapport_fiscal_' . $annee . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, ['N° Dossier','Contribuable','NIF','Type entreprise','Secteur','Type contrôle','Statut','Date ouverture','Date clôture','Agent','Chef brigade','Brigade','Retards'], ';');
foreach ($rows as $r) fputcsv($out, array_values($r), ';');
fclose($out);
