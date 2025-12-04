<?php
declare(strict_types=1);

$env = parse_ini_file(__DIR__ . '/../../.env', false, INI_SCANNER_TYPED);
date_default_timezone_set($env['TIMEZONE'] ?? 'Europe/Athens');

// Base URL για static αρχεία (CSS/JS εικόνες)
$assetBase = $env['ASSETS_BASE_URL'] ?? '/assets';
$assetBase = '/' . ltrim($assetBase, '/');  // να ξεκινάει πάντα με /
$assetBase = rtrim($assetBase, '/');        // χωρίς τελικό /

if (!defined('APP_ASSET_BASE')) {
  define('APP_ASSET_BASE', $assetBase);
}



$dsn = sprintf(
  'mysql:host=%s;port=%s;dbname=%s;charset=%s',
  $env['DB_HOST'], $env['DB_PORT'], $env['DB_NAME'], $env['DB_CHARSET']
);
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
  PDO::ATTR_TIMEOUT            => 5,
];

try {
  $pdo = new PDO($dsn, $env['DB_USER'], (string)($env['DB_PASS'] ?? ''), $options);
  $pdo->exec("SET NAMES {$env['DB_CHARSET']} COLLATE {$env['DB_COLLATION']};");
  $pdo->exec("SET time_zone = '+03:00';");
} catch (Throwable $e) {
  http_response_code(500);
  echo 'DB connection failed.';
  exit;
}
