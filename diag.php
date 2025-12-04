<?php
// diag.php – αυτόνομο διαγνωστικό για Aesthetics CRM / zapnew

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Διαβάζουμε το .env
function loadEnv(string $path): array {
    $env = [];
    if (!is_readable($path)) {
        return $env;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        $val = trim($val, " \t\n\r\0\x0B\"'");
        if ($key !== '') {
            $env[$key] = $val;
        }
    }
    return $env;
}

$info = [
    'php_version'   => PHP_VERSION,
    'php_sapi'      => PHP_SAPI,
    'php_timezone'  => date_default_timezone_get(),
    'php_datetime'  => date('Y-m-d H:i:s'),
];

$env = loadEnv(__DIR__ . '/.env');

$dbHost = $env['DB_HOST']     ?? 'localhost';
$dbPort = $env['DB_PORT']     ?? '3306';
$dbName = $env['DB_DATABASE'] ?? ($env['DB_NAME'] ?? 'zapdb');
$dbUser = $env['DB_USERNAME'] ?? ($env['DB_USER'] ?? 'root');
$dbPass = $env['DB_PASSWORD'] ?? ($env['DB_PASS'] ?? '');

$pdo = null;
$dbInfo = [];
$tableCounts = [];
$dbError = null;

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $stmt = $pdo->query('SELECT DATABASE() AS db, @@hostname AS host, @@version AS version, @@sql_mode AS sql_mode');
    $row = $stmt->fetch() ?: [];
    $dbInfo = [
        'db_name'   => $row['db']       ?? null,
        'db_host'   => $row['host']     ?? null,
        'db_version'=> $row['version']  ?? null,
        'sql_mode'  => $row['sql_mode'] ?? null,
    ];

    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $interesting = [
        'patient',
        'payment',
        'patient_deposit',
        'consent',
        'doctor',
        'receipt',
        'receipt_allocation',
        'history_payment',
        'history_patient_deposit',
        'payment_category',
        'repeat_treatments',
        'rrr',
        'stat_zap2',
        'users',
    ];

    foreach ($interesting as $t) {
        if (!in_array($t, $tables, true)) {
            continue;
        }
        try {
            $c = $pdo->query("SELECT COUNT(*) AS c FROM `$t`")->fetch();
            $tableCounts[$t] = (int)($c['c'] ?? 0);
        } catch (Throwable $e) {
            $tableCounts[$t] = -1;
        }
    }

} catch (Throwable $e) {
    $dbError = $e->getMessage();
}
?>
<!doctype html>
<html lang="el" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <title>zapnew diag.php</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:#111;
            color:#eee;
            padding:1.5rem;
        }
        h1,h2 { margin-top:0; }
        .card {
            border:1px solid #333;
            border-radius:8px;
            padding:1rem 1.25rem;
            margin-bottom:1rem;
            background:#181818;
        }
        table {
            width:100%;
            border-collapse:collapse;
            margin-top:0.5rem;
        }
        th, td {
            border:1px solid #333;
            padding:0.25rem 0.5rem;
            text-align:left;
            font-size:0.9rem;
        }
        th { background:#222; }
        code { background:#222; padding:0.1rem 0.25rem; border-radius:4px; }
    </style>
</head>
<body>
<h1>zapnew / Aesthetics CRM – diag.php</h1>

<div class="card">
    <h2>PHP / Server</h2>
    <p>PHP version: <code><?= h($info['php_version']) ?></code></p>
    <p>SAPI: <code><?= h($info['php_sapi']) ?></code></p>
    <p>Timezone: <code><?= h($info['php_timezone']) ?></code></p>
    <p>Datetime: <code><?= h($info['php_datetime']) ?></code></p>
</div>

<div class="card">
    <h2>.env (DB τιμές)</h2>
    <p>DB_HOST: <code><?= h($dbHost) ?></code></p>
    <p>DB_PORT: <code><?= h($dbPort) ?></code></p>
    <p>DB_NAME/DB_DATABASE: <code><?= h($dbName) ?></code></p>
    <p>DB_USER/DB_USERNAME: <code><?= h($dbUser) ?></code></p>
</div>

<div class="card">
    <h2>Database connection</h2>
    <?php if ($dbError): ?>
        <p style="color:#f66;">Σφάλμα σύνδεσης PDO: <?= h($dbError) ?></p>
    <?php else: ?>
        <p>Database (DATABASE()): <code><?= h($dbInfo['db_name'] ?? '') ?></code></p>
        <p>Host (@@hostname): <code><?= h($dbInfo['db_host'] ?? '') ?></code></p>
        <p>MySQL version: <code><?= h($dbInfo['db_version'] ?? '') ?></code></p>
        <p>sql_mode: <code><?= h($dbInfo['sql_mode'] ?? '') ?></code></p>
    <?php endif; ?>
</div>

<?php if ($pdo && !$dbError): ?>
    <div class="card">
        <h2>Tables (σύνοψη)</h2>
        <?php if (empty($tableCounts)): ?>
            <p>Δεν βρέθηκαν γνωστοί πίνακες.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>Πίνακας</th>
                    <th>Πλήθος γραμμών</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($tableCounts as $t => $c): ?>
                    <tr>
                        <td><?= h($t) ?></td>
                        <td><?= $c >= 0 ? h((string)$c) : 'σφάλμα μέτρησης' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

</body>
</html>
