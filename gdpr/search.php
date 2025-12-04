<?php
header('Content-Type: application/json');

// Κεντρική σύνδεση DB
require_once __DIR__ . '/../app/db.php';
$pdo = pdo();
try {
  $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
  exit;
}

// Λήψη του query (αναζήτηση) από το GET parameter "q"
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q === '') {
  // Αν δεν υπάρχει αναζήτηση, επιστρέφουμε όλες τις εγγραφές
  $stmt = $pdo->query("SELECT * FROM consent ORDER BY timestamp DESC");
  $records = $stmt->fetchAll();
} else {
  // Αναζήτηση στα πεδία: customer_name, customer_phone, email και city
  $query = "
    SELECT *
    FROM consent
    WHERE customer_name LIKE ?
       OR customer_phone LIKE ?
       OR email LIKE ?
       OR city LIKE ?
    ORDER BY timestamp DESC
  ";
  $stmt = $pdo->prepare($query);
  $term = '%' . $q . '%';
  $stmt->execute([$term, $term, $term, $term]);
  $records = $stmt->fetchAll();
}

// Επιστροφή αποτελεσμάτων σε JSON μορφή
echo json_encode($records);
?>
