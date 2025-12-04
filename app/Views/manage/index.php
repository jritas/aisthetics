<?php
// Αν ζητήθηκε explicitly backup (?r=manage&a=backup) κάνουμε export και τερματίζουμε
if (($_GET['a'] ?? '') === 'backup') {
    // Χρειαζόμαστε το $pdo από το db.php
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        header('Content-Type: text/plain; charset=utf-8');
        echo "PDO connection not available.\n";
        exit;
    }

    // Καθάρισε τυχόν buffers για να μην ανακατευτεί με layout
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // Όνομα βάσης
    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!$dbName) {
        $dbName = 'database';
    }

    $now      = date('Ymd_His');
    $filename = sprintf('%s_backup_%s.sql', $dbName, $now);

    // Headers για download
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    // Header του dump
    echo "-- Aesthetics CRM database backup\n";
    echo "-- Database: `{$dbName}`\n";
    echo "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    echo "SET NAMES utf8mb4;\n";
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    // Λίστα πινάκων
    $tables = [];
    $stmt   = $pdo->query('SHOW TABLES');
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        // Δομή πίνακα
        echo "-- ----------------------------------------\n";
        echo "-- Table structure for table `{$table}`\n";

        $res       = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $createRow = $res->fetch(PDO::FETCH_NUM);
        $createSql = $createRow[1] ?? '';

        echo "DROP TABLE IF EXISTS `{$table}`;\n";
        echo $createSql . ";\n\n";

        // Δεδομένα πίνακα
        echo "-- Data for table `{$table}`\n";
        $res = $pdo->query("SELECT * FROM `{$table}`");

        while ($row = $res->fetch(PDO::FETCH_NUM)) {
            $values = [];
            foreach ($row as $value) {
                if ($value === null) {
                    $values[] = 'NULL';
                } else {
                    $values[] = $pdo->quote($value);
                }
            }
            echo "INSERT INTO `{$table}` VALUES (" . implode(',', $values) . ");\n";
        }

        echo "\n\n";
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit;
}

// Αν ΔΕΝ είναι backup, δείξε κανονικά τη σελίδα ρυθμίσεων
ob_start();
?>

<div class="container-fluid py-3">
  <div class="row g-3">

    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <h5 class="card-title mb-2">Γενικές ρυθμίσεις</h5>
          <p class="text-secondary small mb-2">
            Εδώ θα συγκεντρώνονται οι βασικές ρυθμίσεις της εφαρμογής.
          </p>
          <p class="text-muted small mb-0">
            Προς το παρόν δεν υπάρχουν επιπλέον επιλογές πέρα από το backup της βάσης.
          </p>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="card shadow-sm h-100">
        <div class="card-body d-flex flex-column justify-content-between">
          <div>
            <h5 class="card-title mb-2">Αντίγραφο ασφαλείας βάσης</h5>
            <p class="text-secondary small mb-2">
              Με το παρακάτω κουμπί δημιουργείται άμεσα ένα πλήρες αντίγραφο ασφαλείας
              της βάσης δεδομένων σε αρχείο SQL, το οποίο κατεβαίνει στον υπολογιστή σου.
            </p>
            <p class="text-muted small mb-0">
              Το backup περιλαμβάνει τη δομή και όλα τα δεδομένα όλων των πινάκων της βάσης.
              Μπορεί να επαναφερθεί μέσω phpMyAdmin ή από τη γραμμή εντολών MySQL.
            </p>
          </div>
          <div class="mt-3">
            <a href="?r=manage&a=backup" class="btn btn-primary">
              <i class="bi bi-download me-1"></i>
              Λήψη backup τώρα
            </a>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
