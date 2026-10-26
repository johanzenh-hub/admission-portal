<?php
$dbHost = getenv('DB_HOST');
$dbName = getenv('DB_NAME');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbPort = getenv('DB_PORT');
$dbDriver = getenv('DB_DRIVER');
$dbPath = getenv('DB_PATH');
$appSecret = getenv('APP_SECRET');
$studentRecordsApiUrl = getenv('STUDENT_RECORDS_API_URL');
$adminHubApiUrl = getenv('ADMIN_HUB_API_URL');
$studentRecordsApiKey = getenv('STUDENT_RECORDS_API_KEY');

define('DB_HOST', $dbHost === false ? '' : trim($dbHost));
define('DB_NAME', $dbName === false ? '' : trim($dbName));
define('DB_USER', $dbUser === false ? '' : trim($dbUser));
define('DB_PASS', $dbPass === false ? '' : $dbPass);
define('DB_PORT', $dbPort === false || trim($dbPort) === '' ? '3306' : trim($dbPort));
define('DB_DRIVER', $dbDriver === false || trim($dbDriver) === ''
    ? ($dbHost === false || trim($dbHost) === '' ? 'sqlite' : 'mysql')
    : strtolower(trim($dbDriver)));
define('DB_PATH', $dbPath === false || trim($dbPath) === ''
    ? __DIR__ . '/admission_portal.sqlite'
    : trim($dbPath));
define('APP_SECRET', $appSecret === false ? '' : $appSecret);
define('STUDENT_RECORDS_API_URL', $studentRecordsApiUrl === false || trim($studentRecordsApiUrl) === ''
    ? 'https://student-records-api-6qqj.onrender.com'
    : rtrim(trim($studentRecordsApiUrl), '/'));
define('ADMIN_HUB_API_URL', $adminHubApiUrl === false || trim($adminHubApiUrl) === ''
    ? 'https://it-department-hub.onrender.com'
    : rtrim(trim($adminHubApiUrl), '/'));
define('STUDENT_RECORDS_API_KEY', $studentRecordsApiKey === false ? '' : trim($studentRecordsApiKey));
define('TOKEN_TTL', 60 * 60 * 8); // staff stays logged in for 8 hours