<?php
defined('API') or exit;
ensure_default_admin();
$d = body();
$st = db()->prepare('SELECT username, password_hash FROM admin_accounts WHERE username = ?');
$st->execute([trim($d['username'] ?? '')]);
$u = $st->fetch();
if (!$u || !password_verify($d['password'] ?? '', $u['password_hash'])) fail('Incorrect username or password.', 401);
json_out(['token' => make_token($u['username']), 'username' => $u['username']]);
