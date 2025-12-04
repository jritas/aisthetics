<?php
// patients.php
// Επιστρέφει JSON από τον πίνακα patient της zapdb.
//  - ?id=123  -> επιστροφή ενός μόνο ασθενή (για prefill από καρτέλα)
//  - ?q=...   -> αναζήτηση σε name/phone (live search στη φόρμα GDPR)

header('Content-Type: application/json; charset=utf-8');

try {
    // ----- Διαβάζουμε το .env του zapnew από το /httpdocs/.env -----
    $root   = dirname(__DIR__); // /httpdocs
    $envFile = $root . '/.env';
    $env    = [];

    if (!is_file($envFile)) {
        throw new RuntimeException("Missing .env file at {$envFile}");
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        list($k, $v) = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        $v = trim($v, " \t\n\r\0\x0B\"'");
        if ($k !== '') {
            $env[$k] = $v;
        }
    }

    $host     = $env['DB_HOST']      ?? '127.0.0.1';
    $port     = $env['DB_PORT']      ?? '3306';
    $dbName   = $env['DB_NAME']      ?? 'zapdb';
    $user     = $env['DB_USER']      ?? '';
    $pass     = $env['DB_PASS']      ?? '';
    $charset  = $env['DB_CHARSET']   ?? 'utf8mb4';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset={$charset}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // ----- Αν υπάρχει συγκεκριμένο id → φέρνουμε μόνο αυτόν τον ασθενή -----
    if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
        $id = (int) $_GET['id'];

        $stmt = $pdo->prepare("
            SELECT id, name, phone, email
            FROM patient
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode($row ?: null, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ----- Διαφορετικά: αναζήτηση με q ή λίστα -----
    $sql    = "SELECT id, name, phone, email FROM patient";
    $params = [];

    if (isset($_GET['q']) && trim($_GET['q']) !== '') {
        $q = '%' . trim($_GET['q']) . '%';
        $sql .= " WHERE name LIKE :q OR phone LIKE :q";
        $params[':q'] = $q;
    }

    $sql .= " ORDER BY name LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
