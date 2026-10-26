<?php
// Router. Run from the project folder:  php -S 127.0.0.1:8000 backend\index.php
// then open http://127.0.0.1:8000/admission_portal.html
define('API', true);
require_once __DIR__ . '/helpers.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Health check for Group 3's Admin Hub: GET /api/db-check (no login needed)
if (preg_match('#(^|/backend)/api/db-check/?$#', $path)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail('Method not allowed', 405);
    try {
        db()->query('SELECT 1');
        json_out(['ok' => true]);
    } catch (PDOException $e) {
        fail('Database not reachable.', 503);
    }
}

if (!preg_match('#/backend/(.*)$#', $path, $m)) {
    if (php_sapi_name() === 'cli-server') return false; // serve admission_portal.html and other static files
    fail('Not found', 404);
}
$parts = explode('/', trim($m[1], '/'));
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($parts[0] === 'auth' && ($parts[1] ?? '') === 'login' && $method === 'POST') {
        require __DIR__ . '/api/auth.php';
    } elseif ($parts[0] === 'student-login' && $method === 'POST') {
        require __DIR__ . '/api/student-login.php';
    } elseif ($parts[0] === 'students') {
        require __DIR__ . '/api/students.php';
    } elseif ($parts[0] === 'news') {
        $id = isset($parts[1]) && $parts[1] !== '' ? (int)$parts[1] : null;
        require __DIR__ . '/api/news.php';
    } else {
        fail('Not found', 404);
    }
} catch (PDOException $e) {
    error_log('Database request failed: ' . $e->getMessage());
    fail('Database connection failed. Check DB_DRIVER and the database path or MySQL settings, confirm the matching schema was imported, and verify that PHP has the required PDO driver.', 500);
}