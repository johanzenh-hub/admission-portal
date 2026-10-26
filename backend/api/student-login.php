<?php
defined('API') or exit;
$d = body();

$r = external_json_request(HUB_URL, '/hub/login', 'POST', [], [
    'student_id' => trim($d['student_id'] ?? ''),
    'password' => $d['password'] ?? '',
]);

json_out($r['data'], $r['status'] ?: 502); // good: {token, student_id}. Wrong password: 401