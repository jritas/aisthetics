<?php
// sync_from_local_dump.php
// ----------------------------------------------
// 1. Παίρνει το dump local_zapdb.sql από _uploads
// 2. Καθαρίζει τη βάση zapdb_local (staging)
// 3. Κάνει import το dump στη zapdb_local (γραμμή-γραμμή),
//    αγνοώντας CREATE DATABASE / USE
// 4. Δείχνει counts για zapdb_local
// 5. Κάνει full refresh zapdb από zapdb_local
//    (TRUNCATE + INSERT/INSERT IGNORE) με progress
// 6. Δείχνει counts για zapdb και την τρέχουσα βάση με SELECT DATABASE()
// ----------------------------------------------

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 0);
set_time_limit(0);

// Προσπαθούμε να μην κάνει buffering, για να βλέπεις αμέσως τα μηνύματα
@ini_set('output_buffering','off');
@ini_set('zlib.output_compression', 0);
while (ob_get_level()) { ob_end_flush(); }
ob_implicit_flush(true);

// ΡΥΘΜΙΣΕΙΣ ΣΥΝΔΕΣΗΣ
$DB_HOST      = '10.2.49.46';
$DB_USER      = 'zapdbuser';
$DB_PASS      = 'zaq==ZAQ1!';
$DB_NAME_WEB  = 'zapdb';        // web βάση (νέα δομή)
$DB_NAME_LOCAL= 'zapdb_local';  // staging βάση για το dump

// Ρύθμιση διαδρομής dump
$dumpFile = __DIR__ . '/_uploads/local_zapdb.sql';

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title>Sync από local_zapdb.sql στη web βάση</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:#111; color:#eee; padding:20px; }
        code { background:#222; padding:2px 4px; border-radius:3px; }
        .ok { color:#8f8; }
        .err { color:#f88; }
        pre { background:#222; padding:10px; white-space:pre-wrap; border-radius:4px; }
    </style>
    <script>
        let start = Date.now();
        let timerId;
        function tick() {
            let s = Math.floor((Date.now() - start) / 1000);
            let el = document.getElementById('elapsed');
            if (el) el.textContent = s + ' sec';
        }
        window.addEventListener('load', () => {
            timerId = setInterval(tick, 1000);
            tick();
        });
    </script>
</head>
<body>
<h1>Sync από τοπικό dump στη web βάση</h1>
<p>Χρόνος εκτέλεσης: <span id="elapsed">0 sec</span></p>
<?php
flush();

if (!is_readable($dumpFile)) {
    echo '<p class="err">Δεν βρέθηκε ή δεν είναι αναγνώσιμο το αρχείο <code>' . htmlspecialchars($dumpFile) . '</code>.</p>';
    echo '<p>Βάλε το weekly dump σου εκεί με όνομα <code>local_zapdb.sql</code> και ξαναφόρτωσε τη σελίδα.</p>';
    exit;
}

echo '<p>Χρήση dump: <code>' . htmlspecialchars($dumpFile) . '</code></p>';
flush();

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Βοηθητική για εκτέλεση query με μήνυμα προόδου.
 */
function runQuery(mysqli $mysqli, string $sql, string $label, bool $showRows = false) {
    echo '<p>Εκτέλεση: ' . htmlspecialchars($label) . ' …</p>';
    flush();
    $start = microtime(true);
    $mysqli->query($sql);
    $elapsed = microtime(true) - $start;
    if ($showRows) {
        $rows = $mysqli->affected_rows;
        echo '<p class="ok">' . htmlspecialchars($label) . ' – ' . (int)$rows . ' εγγραφές (' . number_format($elapsed, 2) . " sec)</p>";
    } else {
        echo '<p class="ok">' . htmlspecialchars($label) . ' – ΟΚ (' . number_format($elapsed, 2) . " sec)</p>";
    }
    flush();
}

try {

    // Σύνδεση χωρίς συγκεκριμένη DB
    $mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
    $mysqli->set_charset('utf8mb4');

    // Ενεργοποιούμε autocommit στην αρχή
    $mysqli->autocommit(true);
    $res = $mysqli->query("SELECT @@autocommit AS ac");
    $row = $res->fetch_assoc();
    $autocommitVal = (int)$row['ac'];
    $res->free();
    echo '<p>Τιμή <code>@@autocommit</code> στην αρχή: <strong>' . $autocommitVal . '</strong></p>';
    flush();

    echo '<p>Συνδέθηκα στον MySQL server ως <code>' . htmlspecialchars($DB_USER) . '</code>.</p>';
    flush();

    // Έλεγχος ότι υπάρχουν οι βάσεις
    $dbLocalEsc = $mysqli->real_escape_string($DB_NAME_LOCAL);
    $dbWebEsc   = $mysqli->real_escape_string($DB_NAME_WEB);

    $res = $mysqli->query("SHOW DATABASES LIKE '{$dbLocalEsc}'");
    if ($res->num_rows === 0) {
        throw new RuntimeException("Η βάση {$DB_NAME_LOCAL} δεν υπάρχει. Δημιούργησέ την πρώτα από το Plesk και ξανατρέξε το script.");
    }
    $res->free();

    $res = $mysqli->query("SHOW DATABASES LIKE '{$dbWebEsc}'");
    if ($res->num_rows === 0) {
        throw new RuntimeException("Η βάση {$DB_NAME_WEB} δεν υπάρχει. Έλεγξε το όνομα ή δημιούργησέ την από το Plesk.");
    }
    $res->free();

    echo '<p class="ok">Οι βάσεις <code>' . htmlspecialchars($DB_NAME_LOCAL) . '</code> και <code>' . htmlspecialchars($DB_NAME_WEB) . '</code> είναι διαθέσιμες.</p>';
    flush();

    // Καθάρισμα staging βάσης zapdb_local
    $mysqli->query("USE `{$DB_NAME_LOCAL}`");
    $mysqli->query("SET foreign_key_checks = 0");
    $res = $mysqli->query("SHOW TABLES");
    $tables = [];
    while ($row = $res->fetch_array(MYSQLI_NUM)) {
        $tables[] = $row[0];
    }
    $res->free();

    if ($tables) {
        foreach ($tables as $t) {
            $mysqli->query("DROP TABLE IF EXISTS `{$t}`");
        }
        echo '<p>Έγινε drop ' . count($tables) . ' πίνακες από τη βάση <code>' . htmlspecialchars($DB_NAME_LOCAL) . '</code>.</p>';
    } else {
        echo '<p>Η βάση <code>' . htmlspecialchars($DB_NAME_LOCAL) . '</code> ήταν ήδη άδεια.</p>';
    }
    $mysqli->query("SET foreign_key_checks = 1");
    flush();

    // Import του local_zapdb.sql στη zapdb_local, γραμμή-γραμμή
    echo '<p>Ξεκινάει το import του dump στη βάση <code>' . htmlspecialchars($DB_NAME_LOCAL) . '</code>…</p>';
    flush();

    $handle = fopen($dumpFile, 'r');
    if ($handle === false) {
        throw new RuntimeException("Αδυναμία ανοίγματος του dump file για ανάγνωση.");
    }

    $mysqli->select_db($DB_NAME_LOCAL);

    $templine = '';
    $inRoutineBlock = false; // αγνοούμε τυχόν DELIMITER blocks
    $queriesExecuted = 0;

    while (($line = fgets($handle)) !== false) {
        $trim = trim($line);

        if (stripos($trim, 'DELIMITER ') === 0) {
            $inRoutineBlock = !$inRoutineBlock;
            if (!$inRoutineBlock) {
                $templine = '';
            }
            continue;
        }

        if ($inRoutineBlock) {
            continue;
        }

        if ($trim === '' || strpos($trim, '--') === 0 || strpos($trim, '#') === 0) {
            continue;
        }

        $templine .= $line;

        if (substr(rtrim($trim), -1) === ';') {
            $query = $templine;
            $templine = '';

            $queryTrimmed = trim($query);
            if ($queryTrimmed !== '') {
                // Αγνοούμε CREATE DATABASE και USE για να μένουμε στη zapdb_local
                $upper = strtoupper(ltrim($queryTrimmed));
                if (strpos($upper, 'CREATE DATABASE') === 0 || strpos($upper, 'USE ') === 0) {
                    continue;
                }
                $mysqli->query($queryTrimmed);
                $queriesExecuted++;
            }
        }
    }

    fclose($handle);

    echo '<p class="ok">Ολοκληρώθηκε το import του dump στη βάση <code>' . htmlspecialchars($DB_NAME_LOCAL) . '</code>. Εκτελέστηκαν περίπου ' . (int)$queriesExecuted . ' SQL statements.</p>';
    flush();

    // ΠΟΛΥ ΣΗΜΑΝΤΙΚΟ: κλείνουμε τυχόν ανοιχτή συναλλαγή από το dump και ξαναβάζουμε autocommit=1
    $mysqli->commit();
    $mysqli->autocommit(true);
    $res = $mysqli->query("SELECT @@autocommit AS ac");
    $row = $res->fetch_assoc();
    $acAfterImport = (int)$row['ac'];
    $res->free();
    echo '<p>Τιμή <code>@@autocommit</code> ΜΕΤΑ το import: <strong>' . $acAfterImport . '</strong></p>';
    flush();

    // Σύνοψη staging βάσης
    echo '<h2>Σύνοψη staging βάσης (' . htmlspecialchars($DB_NAME_LOCAL) . ')</h2>';
    $localTablesToCount = [
        'doctor',
        'patient',
        'consent',
        'payment_category',
        'payment',
        'patient_deposit',
        'history_patient_deposit',
        'history_payment',
        'rrr',
        'repeat_treatments',
        'stat_zap2',
        'users'
    ];
    foreach ($localTablesToCount as $t) {
        try {
            $res = $mysqli->query("SELECT COUNT(*) AS c FROM `{$DB_NAME_LOCAL}`.`{$t}`");
            $row = $res->fetch_assoc();
            $cnt = (int)$row['c'];
            $res->free();
            echo '<p>' . htmlspecialchars($t) . ': ' . $cnt . ' εγγραφές</p>';
        } catch (Throwable $e) {
            echo '<p class="err">Πίνακας ' . htmlspecialchars($t) . ': ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        flush();
    }

    // Βήμα 2: Μεταφορά δεδομένων από zapdb_local -> zapdb
    echo '<h2>Βήμα 2: Μεταφορά δεδομένων από local σε web βάση (' . htmlspecialchars($DB_NAME_LOCAL) . ' → ' . htmlspecialchars($DB_NAME_WEB) . ')</h2>';
    flush();

    // Από εδώ και πέρα δουλεύουμε ρητά στην zapdb
    $mysqli->select_db($DB_NAME_WEB);

    // Απενεργοποίηση FK
    runQuery($mysqli, "SET FOREIGN_KEY_CHECKS = 0", "SET FOREIGN_KEY_CHECKS = 0", false);

    // TRUNCATE σε web πίνακες
    $truncateTables = [
        'receipt_allocation',
        'receipt',
        'patient_deposit',
        'history_patient_deposit',
        'history_payment',
        'rrr',
        'payment',
        'payment_category',
        'repeat_treatments',
        'stat_zap2',
        'consent',
        'patient',
        'doctor',
        'users'
    ];
    foreach ($truncateTables as $t) {
        runQuery($mysqli, "TRUNCATE TABLE `{$t}`", "TRUNCATE {$t}", false);
    }

    // doctor
    $sql = "INSERT INTO `doctor` (
                id, img_url, name, email, address, phone,
                department, profile, x, y, ion_user_id
            )
            SELECT
                id, img_url, name, email, address, phone,
                department, profile, x, y, ion_user_id
            FROM `{$DB_NAME_LOCAL}`.`doctor`";
    runQuery($mysqli, $sql, "Μεταφορά doctor", true);

    // patient
    $sql = "INSERT INTO `patient` (
                id, name, phone, email, doctor, address,
                gdpr, age, patient_id, memo, birthdate, add_date
            )
            SELECT
                id,
                name,
                phone,
                email,
                doctor,
                address,
                gdpr,
                age,
                patient_id,
                memo,
                CASE
                    WHEN birthdate IS NULL OR birthdate = '' THEN NULL
                    WHEN birthdate REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
                        THEN STR_TO_DATE(birthdate, '%Y-%m-%d')
                    WHEN birthdate REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$'
                        THEN STR_TO_DATE(birthdate, '%d/%m/%Y')
                    ELSE NULL
                END AS birthdate,
                CASE
                    WHEN add_date IS NULL OR add_date = '' THEN NULL
                    WHEN add_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$'
                        THEN STR_TO_DATE(add_date, '%Y-%m-%d %H:%i:%s')
                    WHEN add_date REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
                        THEN STR_TO_DATE(add_date, '%Y-%m-%d')
                    WHEN add_date REGEXP '^[0-9]{2}/[0-9]{2}/[0-9]{4}$'
                        THEN STR_TO_DATE(add_date, '%d/%m/%Y')
                    ELSE NULL
                END AS add_date
            FROM `{$DB_NAME_LOCAL}`.`patient`";
    runQuery($mysqli, $sql, "Μεταφορά patient", true);

    // consent
    $sql = "INSERT INTO `consent` (
                id, customer_name, customer_phone,
                customer_address, signature, consent,
                timestamp, city, email, patient_id
            )
            SELECT
                id, customer_name, customer_phone,
                customer_address, signature, consent,
                timestamp, city, email, patient_id
            FROM `{$DB_NAME_LOCAL}`.`consent`";
    runQuery($mysqli, $sql, "Μεταφορά consent", true);

    // users
    $sql = "INSERT INTO `users` (id, username, password)
            SELECT id, username, password
            FROM `{$DB_NAME_LOCAL}`.`users`";
    runQuery($mysqli, $sql, "Μεταφορά users", true);

    // payment_category
    $sql = "INSERT INTO `payment_category` (
                id, category, description, c_price
            )
            SELECT
                id, category, description, c_price
            FROM `{$DB_NAME_LOCAL}`.`payment_category`";
    runQuery($mysqli, $sql, "Μεταφορά payment_category", true);

    // payment
    $sql = "INSERT INTO `payment` (
                id, category, doctor, amount, vat, flat_vat,
                discount, flat_discount, gross_total, category_amount,
                category_name, status, date, ypoloipo, eispraxi,
                treatment, medicine, amount_received, patient, doctor_id
            )
            SELECT
                p.id,
                p.category,
                p.doctor,
                p.amount,
                p.vat,
                p.flat_vat,
                p.discount,
                p.flat_discount,
                p.gross_total,
                p.category_amount,
                p.category_name,
                p.status,
                p.date,
                p.ypoloipo,
                p.eispraxi,
                p.treatment,
                p.medicine,
                CASE
                    WHEN p.amount_received IS NULL OR p.amount_received = ''
                        THEN NULL
                    ELSE CAST(p.amount_received AS DECIMAL(10,2))
                END AS amount_received,
                p.patient,
                d.id AS doctor_id
            FROM `{$DB_NAME_LOCAL}`.`payment` AS p
            LEFT JOIN `doctor` AS d
                ON d.name = p.doctor";
    runQuery($mysqli, $sql, "Μεταφορά payment", true);

    // patient_deposit
    $sql = "INSERT INTO `patient_deposit` (
                id, patient, payment_id, amount_received_id,
                user, date, transaction_type, deposited_amount
            )
            SELECT
                id,
                patient,
                payment_id,
                amount_received_id,
                user,
                date,
                transaction_type,
                CASE
                    WHEN deposited_amount IS NULL OR deposited_amount = ''
                        THEN NULL
                    ELSE CAST(deposited_amount AS DECIMAL(10,2))
                END AS deposited_amount
            FROM `{$DB_NAME_LOCAL}`.`patient_deposit`";
    runQuery($mysqli, $sql, "Μεταφορά patient_deposit", true);

    // history_patient_deposit
    $sql = "INSERT INTO `history_patient_deposit` (
                id, patient, payment_id, deposited_amount,
                amount_received_id, user, date, transaction_type
            )
            SELECT
                id, patient, payment_id, deposited_amount,
                amount_received_id, user, date, transaction_type
            FROM `{$DB_NAME_LOCAL}`.`history_patient_deposit`";
    runQuery($mysqli, $sql, "Μεταφορά history_patient_deposit", true);

    // history_payment
    $sql = "INSERT INTO `history_payment` (
                id, category, patient, doctor, amount, vat,
                flat_vat, discount, flat_discount, gross_total,
                category_amount, category_name, amount_received,
                status, date, ypoloipo, eispraxi
            )
            SELECT
                id, category, patient, doctor, amount, vat,
                flat_vat, discount, flat_discount, gross_total,
                category_amount, category_name, amount_received,
                status, date, ypoloipo, eispraxi
            FROM `{$DB_NAME_LOCAL}`.`history_payment`";
    runQuery($mysqli, $sql, "Μεταφορά history_payment", true);

    // rrr με INSERT IGNORE
    $sql = "INSERT IGNORE INTO `rrr` (
                id, category, patient, doctor, amount, vat,
                flat_vat, discount, flat_discount, gross_total,
                category_amount, category_name, amount_received,
                status, date, ypoloipo, eispraxi
            )
            SELECT
                id, category, patient, doctor, amount, vat,
                flat_vat, discount, flat_discount, gross_total,
                category_amount, category_name, amount_received,
                status, date, ypoloipo, eispraxi
            FROM `{$DB_NAME_LOCAL}`.`rrr`";
    runQuery($mysqli, $sql, "Μεταφορά rrr (INSERT IGNORE)", true);

    // repeat_treatments
    $sql = "INSERT INTO `repeat_treatments` (
                id, patient, treatment_name, date, user, created_at
            )
            SELECT
                id, patient, treatment_name, date, user, created_at
            FROM `{$DB_NAME_LOCAL}`.`repeat_treatments`";
    runQuery($mysqli, $sql, "Μεταφορά repeat_treatments", true);

    // stat_zap2
    $sql = "INSERT INTO `stat_zap2` (
                id, therapy, count
            )
            SELECT
                id, therapy, count
            FROM `{$DB_NAME_LOCAL}`.`stat_zap2`";
    runQuery($mysqli, $sql, "Μεταφορά stat_zap2", true);

    // Επαναφορά FK (εντός autocommit=1)
    runQuery($mysqli, "SET FOREIGN_KEY_CHECKS = 1", "SET FOREIGN_KEY_CHECKS = 1", false);

    // Σύνοψη web βάσης
    echo '<h2>Σύνοψη web βάσης (' . htmlspecialchars($DB_NAME_WEB) . ')</h2>';
    $res = $mysqli->query("SELECT DATABASE() AS db, @@autocommit AS ac");
    $row = $res->fetch_assoc();
    $currentDb = $row['db'];
    $currentAc = (int)$row['ac'];
    $res->free();
    echo '<p>Τρέχουσα βάση σύμφωνα με <code>SELECT DATABASE()</code>: <strong>' . htmlspecialchars($currentDb) . '</strong></p>';
    echo '<p>Τιμή <code>@@autocommit</code> στο τέλος: <strong>' . $currentAc . '</strong></p>';

    $webTablesToCount = [
        'doctor',
        'patient',
        'consent',
        'payment_category',
        'payment',
        'patient_deposit',
        'history_patient_deposit',
        'history_payment',
        'rrr',
        'repeat_treatments',
        'stat_zap2',
        'users',
        'receipt',
        'receipt_allocation'
    ];
    foreach ($webTablesToCount as $t) {
        try {
            $res = $mysqli->query("SELECT COUNT(*) AS c FROM `{$t}`");
            $row = $res->fetch_assoc();
            $cnt = (int)$row['c'];
            $res->free();
            echo '<p>' . htmlspecialchars($t) . ': ' . $cnt . ' εγγραφές</p>';
        } catch (Throwable $e) {
            echo '<p class="err">Πίνακας ' . htmlspecialchars($t) . ': ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
        flush();
    }

    echo '<p class="ok"><strong>ΤΕΛΟΣ</strong> – η web βάση είναι πλέον ενημερωμένη από το τελευταίο dump.</p>';

} catch (Throwable $e) {
    echo '<p class="err"><strong>Τελικό σφάλμα:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
}

// σταματάμε το ρολόι
echo '<script>if (typeof timerId !== "undefined") { clearInterval(timerId); }</script>';

?>
</body>
</html>
