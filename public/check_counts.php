<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$DB_HOST      = '10.2.49.46';
$DB_USER      = 'zapdbuser';
$DB_PASS      = 'zaq==ZAQ1!';
$DB_NAME_WEB  = 'zapdb';

$tables = [
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

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME_WEB);
$mysqli->set_charset('utf8mb4');

?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <title>Έλεγχος counts στη zapdb</title>
    <style>
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background:#111; color:#eee; padding:20px; }
        table { border-collapse: collapse; margin-top: 10px; }
        td, th { border:1px solid #444; padding:4px 8px; }
    </style>
</head>
<body>
<h1>Έλεγχος εγγραφών στη βάση zapdb</h1>
<table>
    <tr><th>Πίνακας</th><th>COUNT(*)</th></tr>
<?php
foreach ($tables as $t) {
    $res = $mysqli->query("SELECT COUNT(*) AS c FROM `$t`");
    $row = $res->fetch_assoc();
    $cnt = (int)$row['c'];
    $res->free();
    echo '<tr><td>'.htmlspecialchars($t).'</td><td>'.$cnt.'</td></tr>';
}
?>
</table>
</body>
</html>
