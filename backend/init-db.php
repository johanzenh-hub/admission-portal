<?php
require_once __DIR__ . '/config.php';

if (DB_DRIVER !== 'sqlite') {
    exit(0);
}

$directory = dirname(DB_PATH);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create the SQLite database directory.');
}

$pdo = new PDO('sqlite:' . DB_PATH, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$tableCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'news'"
)->fetchColumn();

if ($tableCount === 0) {
    $schema = file_get_contents(__DIR__ . '/schema.sqlite.sql');
    if ($schema === false) {
        throw new RuntimeException('Unable to read the SQLite schema.');
    }
    $pdo->exec($schema);
}
