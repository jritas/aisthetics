<?php
final class ReportsController {
  private PDO $pdo;
  public function __construct(PDO $pdo){ $this->pdo = $pdo; }

  public function handle(): void {
    $a = $_GET['a'] ?? 'balances';
    switch ($a) {
      case 'balances': $this->balances(); break;
      default: $this->balances(); break;
    }
  }

  private function fetchBalances(string $q = '', bool $onlyNonZero = true): array {
    // Συγκεντρωτικό υπόλοιπο ανά πελάτη, με due = SUM(max(gross_total - received, 0))
    $params = [];
    $where  = '1=1';
    if ($q !== '') {
      $where .= " AND (pat.name LIKE :q OR pat.phone LIKE :q OR pat.email LIKE :q)";
      $params[':q'] = '%'.$q.'%';
    }

    $sql = <<<SQL
SELECT
  pat.id,
  pat.name,
  pat.phone,
  pat.email,
  IFNULL(b.due,0) AS due
FROM patient pat
LEFT JOIN (
  SELECT p.patient AS patient_id,
         SUM(
           GREATEST(
             p.gross_total - GREATEST(
               COALESCE((SELECT SUM(ra.amount_applied)
                         FROM receipt_allocation ra
                         WHERE ra.charge_id = p.id), 0),
               COALESCE(NULLIF(p.eispraxi,0),0),
               COALESCE(NULLIF(p.amount_received,0),0)
             ),
             0
           )
         ) AS due
  FROM payment p
  GROUP BY p.patient
) b ON b.patient_id = pat.id
WHERE {$where}
SQL;
    if ($onlyNonZero) $sql .= " HAVING ABS(due) > 0.0009";
    $sql .= " ORDER BY ABS(due) DESC, pat.name ASC";

    $st = $this->pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }

  private function balances(): void {
    $q = trim($_GET['q'] ?? '');
    $nz = ($_GET['nz'] ?? '1') === '1';

    // CSV export
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
      $rows = $this->fetchBalances($q, $nz);
      header('Content-Type: text/csv; charset=UTF-8');
      header('Content-Disposition: attachment; filename="ypoloipa_pelaton.csv"');
      echo "\xEF\xBB\xBF"; // UTF-8 BOM για Excel
      $out = fopen('php://output', 'w');
      fputcsv($out, ['ID','Ονοματεπώνυμο','Τηλέφωνο','Email','Υπόλοιπο (€)'], ';');
      foreach($rows as $r){
        fputcsv($out, [
          $r['id'],
          $r['name'],
          $r['phone'],
          $r['email'],
          number_format((float)$r['due'], 2, ',', '')
        ], ';');
      }
      fclose($out);
      exit;
    }

    $rows = $this->fetchBalances($q, $nz);
    $total = 0.0; foreach($rows as $r) $total += (float)$r['due'];

    $title = 'Υπόλοιπα πελατών';
    include __DIR__ . '/../Views/reports/balances.php';
  }
}
