<?php
defined('API') or exit;

$action = $parts[1] ?? '';

function student_text(array $d, string $key): string {
    $value = $d[$key] ?? '';
    if (!is_string($value) && !is_int($value) && !is_float($value)) fail(ucfirst(str_replace('_', ' ', $key)) . ' must be text.');
    return trim((string)$value);
}

function student_fields(array $d): array {
    $fields = [
        'full_name' => student_text($d, 'full_name'),
        'contact_number' => student_text($d, 'contact_number'),
        'email' => student_text($d, 'email'),
        'address' => student_text($d, 'address'),
        'year_level' => false,
        'section' => student_text($d, 'section'),
        'enrollment_status' => student_text($d, 'enrollment_status'),
    ];
    $yearLevel = $d['year_level'] ?? null;
    if (!is_int($yearLevel) && !is_string($yearLevel)) fail('Year level must be a number from 1 to 6.');
    $fields['year_level'] = filter_var($yearLevel, FILTER_VALIDATE_INT);
    if ($fields['full_name'] === '' || strlen($fields['full_name']) > 120) fail('Enter a full name (up to 120 characters).');
    if ($fields['year_level'] === false || $fields['year_level'] < 1 || $fields['year_level'] > 6) fail('Year level must be a number from 1 to 6.');
    if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) fail('Enter a valid email address.');
    if (strlen($fields['contact_number']) > 40 || strlen($fields['email']) > 254 || strlen($fields['address']) > 500 || strlen($fields['section']) > 40) {
        fail('One or more student details exceed the allowed length.');
    }
    if (!in_array($fields['enrollment_status'], ['enrolled', 'not-enrolled', 'irregular'], true)) {
        fail('Enrollment status must be enrolled, not-enrolled, or irregular.');
    }
    return $fields;
}

function student_record(array $data): ?array {
    if (isset($data['student_id'], $data['full_name']) && is_string($data['student_id']) && is_string($data['full_name'])) return $data;
    foreach ($data as $value) {
        if (is_array($value)) {
            $record = student_record($value);
            if ($record !== null) return $record;
        }
    }
    return null;
}

function current_student(string $studentId): array {
    if (!preg_match('/^\d{4}-\d{5}$/', $studentId)) fail('Invalid student number.');
    $token = bearer_token();
    if ($token === '') fail('Student sign-in required.', 401);
    $response = external_json_request(
        ADMIN_HUB_API_URL,
        '/hub/students/' . rawurlencode($studentId),
        'GET',
        ['Authorization: Bearer ' . $token]
    );
    $data = external_response_data($response, 'Student sign-in');
    $record = student_record($data);
    if ($record === null || $record['student_id'] !== $studentId) fail('The student hub returned an invalid profile.', 502);
    return $record;
}

function student_search_results(array $data): ?array {
    if (isset($data['student_id'], $data['full_name'])) return [$data];
    if (array_key_exists('students', $data) && is_array($data['students'])) return $data['students'];
    if (array_key_exists('results', $data) && is_array($data['results'])) return $data['results'];
    if ($data === []) return [];
    if (isset($data[0]) && is_array($data[0]) && isset($data[0]['student_id'], $data[0]['full_name'])) return $data;
    foreach ($data as $value) {
        if (is_array($value)) {
            $results = student_search_results($value);
            if ($results !== null) return $results;
        }
    }
    return null;
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_staff();
    if (STUDENT_RECORDS_API_KEY === '') fail('Student registration is not configured. Ask the site administrator to set STUDENT_RECORDS_API_KEY.', 503);
    $d = body();
    $fields = student_fields($d);
    $password = $d['password'] ?? null;
    if (!is_string($password) || strlen($password) < 6) fail('Password must be at least 6 characters.');

    $created = null;
    $studentId = '';
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $studentId = date('Y') . '-' . str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $created = external_json_request(
            STUDENT_RECORDS_API_URL,
            '/api/students',
            'POST',
            ['x-api-key: ' . STUDENT_RECORDS_API_KEY],
            ['student_id' => $studentId] + $fields
        );
        if ($created['status'] !== 409) break;
    }
    if ($created === null || $created['status'] === 409) fail('Unable to generate a unique student number. Please try again.', 503);
    $saved = external_response_data($created, 'Student record');
    $record = student_record($saved);
    if ($record === null || $record['student_id'] !== $studentId) fail('The student service returned an invalid registration response.', 502);

    $passwordResponse = external_json_request(
        STUDENT_RECORDS_API_URL,
        '/api/auth/set-password',
        'POST',
        ['x-api-key: ' . STUDENT_RECORDS_API_KEY],
        ['student_id' => $studentId, 'password' => $password]
    );
    if ($passwordResponse['status'] < 200 || $passwordResponse['status'] >= 300 || ($passwordResponse['data']['ok'] ?? false) !== true) {
        $message = $passwordResponse['data']['error'] ?? $passwordResponse['data']['message'] ?? 'The password could not be saved.';
        if (!is_string($message) || trim($message) === '') $message = 'The password could not be saved.';
        json_out(['error' => 'Student record ' . $studentId . ' was created, but password setup failed: ' . $message, 'student_id' => $studentId], 502);
    }
    json_out(['student_id' => $studentId], 201);
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = body();
    $studentId = student_text($d, 'student_id');
    $password = $d['password'] ?? null;
    if (!is_string($password)) fail('Enter your student number and password.');
    if (!preg_match('/^\d{4}-\d{5}$/', $studentId) || $password === '') fail('Enter your student number and password.');
    $response = external_json_request(
        ADMIN_HUB_API_URL,
        '/hub/login',
        'POST',
        [],
        ['student_id' => $studentId, 'password' => $password]
    );
    $data = external_response_data($response, 'Sign-in');
    if (!isset($data['token'], $data['student_id']) || !is_string($data['token']) || $data['student_id'] !== $studentId) {
        fail('The student hub returned an invalid sign-in response.', 502);
    }
    json_out(['token' => $data['token'], 'student_id' => $studentId]);
}

if ($action === 'search' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    require_staff();
    $query = trim((string)($_GET['q'] ?? ''));
    if (strlen($query) < 2 || strlen($query) > 100) fail('Search text must be between 2 and 100 characters.');
    $response = external_json_request(ADMIN_HUB_API_URL, '/hub/students/search?q=' . rawurlencode($query), 'GET');
    $data = external_response_data($response, 'Student search');
    $results = student_search_results($data);
    if ($results === null) fail('The student hub returned an invalid search response.', 502);
    $students = [];
    foreach ($results as $result) {
        if (!is_array($result) || !isset($result['student_id'], $result['full_name'])) continue;
        $students[] = array_intersect_key($result, array_flip(['student_id', 'full_name', 'year_level', 'section', 'enrollment_status']));
    }
    json_out(['students' => $students]);
}

if (preg_match('/^\d{4}-\d{5}$/', $action) && ($parts[2] ?? '') === 'full' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $record = current_student($action);
    json_out(['student' => array_intersect_key($record, array_flip([
        'student_id', 'full_name', 'contact_number', 'email', 'address', 'year_level', 'section', 'enrollment_status',
    ]))]);
}

if (preg_match('/^\d{4}-\d{5}$/', $action) && !isset($parts[2]) && $_SERVER['REQUEST_METHOD'] === 'PUT') {
    if (STUDENT_RECORDS_API_KEY === '') fail('Student profile editing is not configured. Ask the site administrator to set STUDENT_RECORDS_API_KEY.', 503);
    $record = current_student($action);
    $fields = student_fields(body());
    $response = external_json_request(
        STUDENT_RECORDS_API_URL,
        '/api/students/' . rawurlencode($action),
        'PUT',
        [
            'x-api-key: ' . STUDENT_RECORDS_API_KEY,
            'x-changed-by: ' . preg_replace('/[\r\n]+/', ' ', $record['full_name']) . ' (' . $action . ')',
        ],
        $fields
    );
    $data = external_response_data($response, 'Student profile update');
    json_out($data);
}

fail('Not found', 404);
