<?php
require_once __DIR__ . '/db.php';

class Auth {
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => SESSION_LIFETIME,
                'path'     => '/',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function login(string $email, string $password): array|false {
        $db   = DB::get();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND actif = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) return false;
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role']  = $user['role'];
        $_SESSION['brigade_id'] = $user['brigade_id'];
        self::auditLog($user['id'], 'LOGIN', 'users', $user['id'], 'Connexion');
        return $user;
    }

    public static function logout(): void {
        session_destroy();
    }

    public static function user(): ?array {
        if (empty($_SESSION['user_id'])) return null;
        $db   = DB::get();
        $stmt = $db->prepare('SELECT id,nom,prenom,email,role,brigade_id FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    public static function check(): void {
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }
    }

    public static function requireRoles(string ...$roles): void {
        self::check();
        if (!in_array($_SESSION['user_role'], $roles, true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès refusé']);
            exit;
        }
    }

    public static function role(): string {
        return $_SESSION['user_role'] ?? '';
    }

    public static function userId(): int {
        return (int)($_SESSION['user_id'] ?? 0);
    }

    public static function brigadeId(): ?int {
        return isset($_SESSION['brigade_id']) ? (int)$_SESSION['brigade_id'] : null;
    }

    public static function auditLog(int $userId, string $action, string $table, ?int $recordId, string $details = ''): void {
        try {
            DB::get()->prepare(
                'INSERT INTO audit_logs (user_id, action, table_name, record_id, details) VALUES (?,?,?,?,?)'
            )->execute([$userId, $action, $table, $recordId, $details]);
        } catch (Exception) { /* non-bloquant */ }
    }
}
