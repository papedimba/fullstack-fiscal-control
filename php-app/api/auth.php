<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/totp.php';

Auth::start();
header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'login':
        if (method() !== 'POST') json_error('Méthode invalide', 405);
        $body     = request_body();
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        if (!$email || !$password) json_error('Email et mot de passe requis');

        $user = Auth::login($email, $password);
        if (!$user) json_error('Identifiants invalides', 401);

        // Vérifier si le 2FA est activé
        $db   = DB::get();
        $totp = $db->prepare('SELECT secret FROM totp_secrets WHERE user_id=? AND actif=1');
        $totp->execute([$user['id']]);
        $totpRow = $totp->fetch();

        if ($totpRow) {
            // 2FA requis : on stocke un état intermédiaire et on détruit la session complète
            $pendingId = $user['id'];
            Auth::logout();
            Auth::start();
            $_SESSION['pending_2fa_user_id'] = $pendingId;
            json_response(['requires_2fa' => true]);
        }

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

    case 'verify_2fa':
        if (method() !== 'POST') json_error('Méthode invalide', 405);
        $pendingId = $_SESSION['pending_2fa_user_id'] ?? null;
        if (!$pendingId) json_error('Aucune session 2FA en attente', 400);

        $body = request_body();
        $code = trim($body['code'] ?? '');
        if (!$code) json_error('Code requis');

        $db   = DB::get();
        $totp = $db->prepare('SELECT secret FROM totp_secrets WHERE user_id=? AND actif=1');
        $totp->execute([$pendingId]);
        $row  = $totp->fetch();
        if (!$row || !TOTP::verify($row['secret'], $code)) json_error('Code incorrect', 401);

        // Promouvoir la session en session complète
        $stmt = $db->prepare('SELECT * FROM users WHERE id=? AND actif=1');
        $stmt->execute([$pendingId]);
        $user = $stmt->fetch();
        if (!$user) json_error('Utilisateur introuvable', 401);

        unset($_SESSION['pending_2fa_user_id']);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['brigade_id'] = $user['brigade_id'];
        Auth::auditLog($user['id'], 'LOGIN_2FA', 'users', $user['id'], 'Connexion avec 2FA');

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
