<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

mysqli_report(MYSQLI_REPORT_OFF);

$env = parse_ini_file(__DIR__ . '/../.env', false);

$host = $env['DB_HOST'] ?? '10.2.49.46';
$port = (int)($env['DB_PORT'] ?? 3306);
$user = $env['DB_USER'] ?? '';
$pass = $env['DB_PASS'] ?? '';
$db   = $env['DB_NAME'] ?? '';

echo "<pre>";
echo "Trying connection to {$host}:{$port} as user {$user} on db {$db}\n\n";

$link = @mysqli_connect($host, $user, $pass, $db, $port);

if (!$link) {
    echo "FAILED\n";
    echo "mysqli_connect_errno: " . mysqli_connect_errno() . "\n";
    echo "mysqli_connect_error: " . mysqli_connect_error() . "\n";
} else {
    echo "OK! Connected successfully.\n";
    mysqli_close($link);
}
