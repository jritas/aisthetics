<?php
// submit.php — καταχώριση συναίνεσης GDPR
header('Content-Type: application/json; charset=utf-8');

try {
    // ----- Σύνδεση στη zapdb μέσω .env (ίδια λογική με patients.php) -----
    $root    = dirname(__DIR__); // /httpdocs
    $envFile = $root . '/.env';
    $env     = [];

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

    // ----- Παίρνουμε το JSON από το front-end -----
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Invalid JSON payload');
    }

    $pid      = isset($data['patient_id']) ? (int)$data['patient_id'] : 0;
    $city     = trim($data['city']             ?? '');
    $addr     = trim($data['customer_address'] ?? '');
    $consent  = isset($data['consent']) ? (int)$data['consent'] : 0;
    $signature= $data['signature'] ?? '';

    if ($pid <= 0)          throw new RuntimeException('Missing patient_id');
    if ($city === '')       throw new RuntimeException('Χρειάζεται πόλη');
    if ($addr === '')       throw new RuntimeException('Χρειάζεται διεύθυνση');
    if ($signature === '')  throw new RuntimeException('Missing signature');

    // ----- Φέρνουμε στοιχεία ασθενή από τον πίνακα patient -----
    $st = $pdo->prepare("
        SELECT id, name, phone, email
        FROM patient
        WHERE id = ?
    ");
    $st->execute([$pid]);
    $pat = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pat) {
        throw new RuntimeException('Patient not found');
    }

    $pdo->beginTransaction();

    // ----- Εισαγωγή στη consent -----
    // Υποθέτουμε σχήμα:
    // consent(patient_id, customer_name, customer_phone, email,
    //         customer_address, city, consent, signature, timestamp)
    $ins = $pdo->prepare("
        INSERT INTO consent
          (patient_id, customer_name, customer_phone, email,
           customer_address, city, consent, signature, timestamp)
        VALUES
          (:pid, :name, :phone, :email,
           :addr, :city, :consent, :signature, :ts)
    ");

    $now = date('Y-m-d H:i:s');

    $ins->execute([
        ':pid'       => $pid,
        ':name'      => $pat['name']  ?? '',
        ':phone'     => $pat['phone'] ?? '',
        ':email'     => $pat['email'] ?? '',
        ':addr'      => $addr,
        ':city'      => $city,
        ':consent'   => $consent,
        ':signature' => $signature,
        ':ts'        => $now,
    ]);

    // ----- Update σημαίας gdpr στον πίνακα patient -----
    $upd = $pdo->prepare("UPDATE patient SET gdpr = 'ΝΑΙ' WHERE id = ?");
    $upd->execute([$pid]);

    $pdo->commit();

    echo json_encode(['status' => 'success']);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
