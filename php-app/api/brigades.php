<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
Auth::check();
header('Content-Type: application/json; charset=utf-8');

$rows = DB::get()->query("
    SELECT b.*, u.nom || ' ' || u.prenom AS chef_nom
    FROM brigades b LEFT JOIN users u ON b.chef_id=u.id
")->fetchAll();

json_response($rows);
