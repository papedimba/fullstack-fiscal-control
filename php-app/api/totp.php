<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/totp.php';

Auth::start();
Auth::check();

header('Content-Type: application/json; charset=utf-8');

$db     = DB::get();
$userId = Auth::userId();
$method = method();

// ─── GET : status ou setup ────────────────────────────────────────────────────

if ($method === 'GET') {
    $action = trim($_GET['action'] ?? '');

    // ── Action : status ───────────────────────────────────────────────────────
    if ($action === 'status') {
        $stmt = $db->prepare('SELECT id FROM totp_secrets WHERE user_id = ? AND actif = 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        json_response(['enabled' => (bool)$row]);
    }

    // ── Action : setup ────────────────────────────────────────────────────────
    if ($action === 'setup') {
        // Récupérer un secret non activé existant ou en générer un nouveau
        $stmt = $db->prepare('SELECT secret FROM totp_secrets WHERE user_id = ? AND actif = 0');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if ($row) {
            $secret = $row['secret'];
        } else {
            $secret = TOTP::generateSecret();

            // Supprimer tout secret existant (actif ou non) avant d'insérer
            $db->prepare('DELETE FROM totp_secrets WHERE user_id = ?')->execute([$userId]);

            $db->prepare('INSERT INTO totp_secrets (user_id, secret, actif) VALUES (?, ?, 0)')
               ->execute([$userId, $secret]);
        }

        // Récupérer l'email de l'utilisateur pour construire l'URL otpauth
        $stmtUser = $db->prepare('SELECT email FROM users WHERE id = ?');
        $stmtUser->execute([$userId]);
        $userRow = $stmtUser->fetch();
        $email   = $userRow['email'] ?? '';

        json_response([
            'secret'      => $secret,
            'otpauth_url' => TOTP::getOtpauthUrl($secret, $email),
        ]);
    }

    json_error('Action inconnue. Valeurs acceptées : status, setup.', 400);
}

// ─── POST : activer ou désactiver ────────────────────────────────────────────

if ($method === 'POST') {
    $body   = request_body();
    $action = trim($body['action'] ?? '');
    $code   = trim($body['code']   ?? '');

    if ($code === '') {
        json_error('Le champ code est requis.');
    }

    // ── Action : activate ─────────────────────────────────────────────────────
    if ($action === 'activate') {
        $stmt = $db->prepare('SELECT secret FROM totp_secrets WHERE user_id = ? AND actif = 0');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row) {
            json_error('Aucun secret TOTP en attente d\'activation. Appelez d\'abord GET ?action=setup.', 400);
        }

        if (!TOTP::verify($row['secret'], $code)) {
            json_error('Code invalide', 400);
        }

        $db->prepare('UPDATE totp_secrets SET actif = 1 WHERE user_id = ? AND actif = 0')
           ->execute([$userId]);

        Auth::auditLog($userId, 'TOTP_ACTIVATE', 'totp_secrets', $userId, 'Activation 2FA TOTP');
        json_response(['ok' => true]);
    }

    // ── Action : disable ──────────────────────────────────────────────────────
    if ($action === 'disable') {
        $stmt = $db->prepare('SELECT secret FROM totp_secrets WHERE user_id = ? AND actif = 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if (!$row) {
            json_error('Le 2FA n\'est pas activé pour cet utilisateur.', 400);
        }

        if (!TOTP::verify($row['secret'], $code)) {
            json_error('Code invalide', 400);
        }

        $db->prepare('DELETE FROM totp_secrets WHERE user_id = ?')->execute([$userId]);

        Auth::auditLog($userId, 'TOTP_DISABLE', 'totp_secrets', $userId, 'Désactivation 2FA TOTP');
        json_response(['ok' => true]);
    }

    json_error('Action inconnue. Valeurs acceptées : activate, disable.', 400);
}

json_error('Méthode non autorisée', 405);
