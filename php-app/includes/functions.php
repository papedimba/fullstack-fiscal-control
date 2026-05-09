<?php
function json_response(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_error(string $msg, int $code = 400): never {
    json_response(['error' => $msg], $code);
}

function request_body(): array {
    $body = file_get_contents('php://input');
    return json_decode($body, true) ?? [];
}

function method(): string {
    return $_SERVER['REQUEST_METHOD'];
}

function get(string $key, mixed $default = null): mixed {
    return $_GET[$key] ?? $default;
}

function post(string $key, mixed $default = null): mixed {
    $body = request_body();
    return $body[$key] ?? $default;
}

function require_fields(array $body, string ...$fields): void {
    foreach ($fields as $f) {
        if (!isset($body[$f]) || $body[$f] === '') {
            json_error("Champ requis : $f");
        }
    }
}

function build_dossier_where(): array {
    $role       = Auth::role();
    $userId     = Auth::userId();
    $brigadeId  = Auth::brigadeId();
    $where  = '1=1';
    $params = [];
    if ($role === 'agent') {
        $where  .= ' AND d.agent_id = ?';
        $params[] = $userId;
    } elseif ($role === 'chef_brigade') {
        $where  .= ' AND d.brigade_id = ?';
        $params[] = $brigadeId;
    }
    return [$where, $params];
}

function next_numero_dossier(PDO $db, string $date): string {
    $year = date('Y', strtotime($date));
    $stmt = $db->prepare("SELECT numero_dossier FROM dossiers WHERE numero_dossier LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(["DCF-{$year}-%"]);
    $last = $stmt->fetchColumn();
    $seq  = $last ? (int)substr($last, strrpos($last, '-') + 1) + 1 : 1;
    return sprintf('DCF-%s-%03d', $year, $seq);
}
