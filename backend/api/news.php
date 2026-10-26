<?php
defined('API') or exit;
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') { // public
    $rows = db()->query('SELECT id, title, category, body, image_data, created_at FROM news ORDER BY created_at DESC, id DESC')->fetchAll();
    json_out(['news' => $rows]);
}

$user = require_staff(); // everything below needs a staff login
$d = body();
$title = trim($d['title'] ?? ''); $text = trim($d['body'] ?? ''); $cat = trim($d['category'] ?? '');

if ($method === 'POST' || $method === 'PUT') {
    if ($title === '' || $text === '') fail('Headline and story are required.');
    $image = $d['image'] ?? null;
    if ($image !== null) {
        if (!is_string($image) || strlen($image) > 2796300 ||
            !preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/]*={0,2})$#', $image, $matches)) {
            fail('Choose a PNG, JPEG, or WebP image no larger than 2 MB.');
        }
        $binary = base64_decode($matches[2], true);
        $imageInfo = $binary === false ? false : getimagesizefromstring($binary);
        if ($binary === false || strlen($binary) > 2097152 || $imageInfo === false ||
            !in_array($imageInfo['mime'] ?? '', ['image/png', 'image/jpeg', 'image/webp'], true) ||
            $imageInfo[0] > 6000 || $imageInfo[1] > 6000) {
            fail('The image is invalid or exceeds the 2 MB or 6000 × 6000 pixel limit.');
        }
    }
    if ($method === 'POST') {
        db()->prepare('INSERT INTO news (title, category, body, author, image_data) VALUES (?,?,?,?,?)')->execute([$title, $cat, $text, $user, $image]);
        json_out(['id' => (int)db()->lastInsertId()], 201);
    }
    if (!$id) fail('News id required.');
    if (array_key_exists('image', $d)) {
        db()->prepare('UPDATE news SET title=?, category=?, body=?, image_data=? WHERE id=?')->execute([$title, $cat, $text, $image, $id]);
    } else {
        db()->prepare('UPDATE news SET title=?, category=?, body=? WHERE id=?')->execute([$title, $cat, $text, $id]);
    }
    json_out(['ok' => true]);
}
if ($method === 'DELETE') {
    if (!$id) fail('News id required.');
    db()->prepare('DELETE FROM news WHERE id=?')->execute([$id]);
    json_out(['ok' => true]);
}
fail('Method not allowed', 405);
