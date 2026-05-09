<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::start();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        if (method() !== 'POST') json_error('Méthode invalide', 405);
        $body = request_body();
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        if (!$email || !$password) json_error('Email et mot de passe requis');
        $user = Auth::login($email, $password);
        if (!$user) json_error('Identifiants invalides', 401);
        json_response([
            'user' => [
                'id'         => $user['id'],
                'nom'        => $user['nom'],
                'prenom'     => $user['prenom'],
                'email'      => $user['email'],
                'role'       => $user['role'],
                'brigade_id' => $user['brigade_id'],
            ]
        ]);

    case 'logout':
        Auth::logout();
        json_response(['ok' => true]);

    case 'me':
        Auth::check();
        $user = Auth::user();
        if (!$user) json_error('Session expirée', 401);
        json_response($user);

    default:
        json_error('Action inconnue', 404);
}
