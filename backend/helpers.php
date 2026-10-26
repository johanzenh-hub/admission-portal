<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if (DB_DRIVER === 'sqlite') {
            if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                fail('SQLite is not enabled in PHP. Enable the PDO SQLite extension (pdo_sqlite) and restart PHP.', 500);
            }
            $pdo = new PDO('sqlite:' . DB_PATH, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');
        } elseif (DB_DRIVER === 'mysql') {
            if (DB_HOST === '' || DB_NAME === '' || DB_USER === '' || !ctype_digit(DB_PORT)) {
                fail('MySQL database configuration is incomplete. Set DB_HOST, DB_NAME, DB_USER, and a numeric DB_PORT in the hosting environment.', 500);
            }
            $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $options);
        } else {
            fail('Unsupported DB_DRIVER. Use sqlite or mysql.', 500);
        }
        if (DB_DRIVER === 'sqlite') {
            $columns = $pdo->query('PRAGMA table_info(news)')->fetchAll();
            $hasImage = count(array_filter($columns, static fn($column) => $column['name'] === 'image_data')) > 0;
            if (!$hasImage) $pdo->exec('ALTER TABLE news ADD COLUMN image_data TEXT');
        } else {
            $column = $pdo->query("SHOW COLUMNS FROM news LIKE 'image_data'")->fetch();
            if (!$column) $pdo->exec('ALTER TABLE news ADD COLUMN image_data MEDIUMTEXT NULL');
        }
    }
    return $pdo;
}
function json_out($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
function fail(string $msg, int $code = 400): void { json_out(['error' => $msg], $code); }
function body(): array {
    $d = json_decode(file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}
function bearer_token(): string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$h && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) if (strtolower($k) === 'authorization') $h = $v;
    }
    return preg_match('/^Bearer\s+(.+)$/', $h, $m) ? trim($m[1]) : '';
}
function external_json_request(string $baseUrl, string $path, string $method, array $headers = [], ?array $payload = null): array {
    if (function_exists('set_time_limit')) set_time_limit(80);
    $requestHeaders = array_merge(['Accept: application/json'], $headers);
    $content = '';
    if ($payload !== null) {
        $json = json_encode($payload);
        if ($json === false) fail('Unable to encode the student service request.', 500);
        $requestHeaders[] = 'Content-Type: application/json';
        $content = $json;
    }
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $requestHeaders),
            'content' => $content,
            'timeout' => 70,
            'ignore_errors' => true,
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $warning = '';
    set_error_handler(static function (int $severity, string $message) use (&$warning): bool {
        $warning = $message;
        return true;
    }, E_WARNING);
    try {
        $response = file_get_contents(rtrim($baseUrl, '/') . $path, false, $context);
    } finally {
        restore_error_handler();
    }
    if ($response === false) fail('Unable to contact the student service: ' . ($warning ?: 'request failed.'), 502);

    $data = json_decode($response, true);
    if (!is_array($data)) fail('The student service returned an invalid response.', 502);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $match)) $status = (int)$match[1];
    }
    return ['status' => $status, 'data' => $data];
}
function external_response_data(array $response, string $service): array {
    if ($response['status'] < 200 || $response['status'] >= 300) {
        $message = $response['data']['error'] ?? $response['data']['message'] ?? '';
        if (!is_string($message) || trim($message) === '') $message = 'The service could not complete the request.';
        $status = $response['status'] >= 400 && $response['status'] < 500 ? $response['status'] : 502;
        fail($service . ': ' . $message, $status);
    }
    return $response['data'];
}
function make_token(string $user): string {
    if (strlen(APP_SECRET) < 32) fail('Server configuration error: APP_SECRET must be at least 32 characters.', 500);
    $p = base64_encode(json_encode(['u' => $user, 'exp' => time() + TOKEN_TTL]));
    return $p . '.' . hash_hmac('sha256', $p, APP_SECRET);
}
function require_staff(): string {
    if (strlen(APP_SECRET) < 32) fail('Server configuration error: APP_SECRET must be at least 32 characters.', 500);
    $token = bearer_token();
    if ($token === '') fail('Staff login required.', 401);
    $parts = explode('.', $token);
    if (count($parts) !== 2 || !hash_equals(hash_hmac('sha256', $parts[0], APP_SECRET), $parts[1])) fail('Invalid session.', 401);
    $d = json_decode(base64_decode($parts[0]), true);
    if (!$d || ($d['exp'] ?? 0) < time()) fail('Session expired. Please log in again.', 401);
    return $d['u'];
}
// Create the first staff account from private hosting environment variables.
function ensure_default_admin(): void {
    $n = db()->query('SELECT COUNT(*) c FROM admin_accounts')->fetch()['c'];
    if ((int)$n === 0) {
        $username = getenv('INITIAL_ADMIN_USERNAME');
        $password = getenv('INITIAL_ADMIN_PASSWORD');
        if ($username === false || trim($username) === '' || $password === false || $password === '') {
            fail('No staff account exists. Set INITIAL_ADMIN_USERNAME and INITIAL_ADMIN_PASSWORD in the hosting environment, then try again.', 503);
        }
        db()->prepare('INSERT INTO admin_accounts (username, password_hash) VALUES (?, ?)')
            ->execute([trim($username), password_hash($password, PASSWORD_DEFAULT)]);
    }
}
