<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/email.php';

Auth::start();
// Pas d'Auth::check() — endpoint public (utilisateur non connecté)

header('Content-Type: application/json; charset=utf-8');

$db     = DB::get();
$method = method();

// ─── GET : vérification de token ─────────────────────────────────────────────

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action !== 'verify') {
        json_error('Action inconnue', 400);
    }

    $token = trim($_GET['token'] ?? '');
    if ($token === '') {
        json_response(['valid' => false]);
    }

    $nowExpr = USE_SQLITE ? "datetime('now')" : 'NOW()';

    $stmt = $db->prepare("
        SELECT email FROM password_resets
        WHERE token = ? AND used = 0 AND expires_at > $nowExpr
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        json_response(['valid' => false]);
    }

    json_response(['valid' => true, 'email' => $row['email']]);
}

// ─── POST : demande ou réinitialisation ───────────────────────────────────────

if ($method === 'POST') {
    $body   = request_body();
    $action = trim($body['action'] ?? '');

    // ── Action : request ──────────────────────────────────────────────────────
    if ($action === 'request') {
        $email = trim($body['email'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_error('Adresse email invalide.');
        }

        // Vérifier existence de l'utilisateur (sans révéler si l'email existe)
        $stmt = $db->prepare('SELECT id, nom, prenom FROM users WHERE email = ? AND actif = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token     = bin2hex(random_bytes(32));
            $nom       = trim(($user['nom'] ?? '') . ' ' . ($user['prenom'] ?? ''));
            $expiresAt = USE_SQLITE
                ? date('Y-m-d H:i:s', strtotime('+1 hour'))
                : date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Invalider les anciens tokens non utilisés pour cet email
            $db->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0")
               ->execute([$email]);

            // Insérer le nouveau token
            $db->prepare("
                INSERT INTO password_resets (email, token, expires_at, used)
                VALUES (?, ?, ?, 0)
            ")->execute([$email, $token, $expiresAt]);

            // Envoyer l'email (silencieux en cas d'échec)
            Mailer::sendResetLink($email, $nom, $token);
        }

        // Réponse identique qu'il y ait ou non un compte (anti-énumération)
        json_response(['ok' => true, 'message' => 'Si cet email existe, un lien a été envoyé.']);
    }

    // ── Action : reset ────────────────────────────────────────────────────────
    if ($action === 'reset') {
        $token    = trim($body['token']    ?? '');
        $password = trim($body['password'] ?? '');

        if ($token === '') {
            json_error('Token manquant.');
        }

        if (strlen($password) < 8) {
            json_error('Le mot de passe doit contenir au moins 8 caractères.');
        }

        $nowExpr = USE_SQLITE ? "datetime('now')" : 'NOW()';

        // Vérifier validité du token
        $stmt = $db->prepare("
            SELECT email FROM password_resets
            WHERE token = ? AND used = 0 AND expires_at > $nowExpr
        ");
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        if (!$row) {
            json_error('Token invalide ou expiré.', 400);
        }

        $emailReset = $row['email'];
        $hash       = password_hash($password, PASSWORD_BCRYPT);

        // Mettre à jour le mot de passe de l'utilisateur
        $db->prepare('UPDATE users SET password_hash = ? WHERE email = ?')
           ->execute([$hash, $emailReset]);

        // Marquer le token comme utilisé
        $db->prepare('UPDATE password_resets SET used = 1 WHERE token = ?')
           ->execute([$token]);

        json_response(['ok' => true]);
    }

    json_error('Action inconnue. Valeurs acceptées : request, reset.', 400);
}

json_error('Méthode non autorisée', 405);
