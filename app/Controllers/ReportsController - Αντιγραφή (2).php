<?php
// app/Controllers/ReportsController.php
declare(strict_types=1);

final class ReportsController
{
    private PDO $pdo;

    public function __construct()
    {
        // Προσπαθώ πρώτα από app/db.php (pdo()) – αλλιώς .env fallback.
        $this->pdo = $this->bootstrapPdo();
    }

    private function bootstrapPdo(): PDO
    {
        // 1) app/db.php με pdo()
        $pdo = null;
        $dbPhp = __DIR__ . '/../db.php';
        if (is_file($dbPhp)) {
            require_once $dbPhp;
            if (function_exists('pdo')) {
                $pdo = pdo();
            }
        }
        if ($pdo instanceof PDO) return $pdo;

        // 2) .env fallback
        $env = $this->envLoad(dirname(__DIR__) . '/../.env');
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $env['DB_HOST'] ?? '127.0.0.1',
            $env['DB_PORT'] ?? '3306',
            $env['DB_NAME'] ?? 'zapdb',
            $env['DB_CHARSET'] ?? 'utf8mb4'
        );
        $user = $env['DB_USER'] ?? 'root';
        $pass = $env['DB_PASS'] ?? '';

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }

    private function envLoad(string $path): array
    {
        if (!is_file($path)) return [];
        $out = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
            $ln = trim($ln);
            if ($ln === '' || $ln[0] === '#') continue;
            if (!str_contains($ln, '=')) continue;
            [$k, $v] = array_map('trim', explode('=', $ln, 2));
            $out[$k] = trim($v, " \t\n\r\0\x0B\"'");
        }
        return $out;
    }

    public function handle(): void
    {
        $a = $_GET['a'] ?? 'balances';
        if ($a === 'balances') {
            $this->balances();
            return;
        }
        http_response_code(404);
        echo 'Not found';
    }

    /**
     * /?r=reports&a=balances
     * AJAX: /?r=reports&a=balances&ajax=1&q=...&only_due=1
     */
    public function balances(): void
    {
        // AJAX endpoint
        if (isset($_GET['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            try {
                $q        = trim((string)($_GET['q'] ?? ''));
                $onlyDue  = isset($_GET['only_due']) && $_GET['only_due'] == '1';
                $rows     = $this->fetchBalances($q, $onlyDue);
                echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            }
            return;
        }

        // View
        $view = __DIR__ . '/../Views/reports/balances.php';
        if (!is_file($view)) {
            echo '<p class="text-danger">View not found: reports/balances.php</p>';
            return;
        }
        require $view;
    }

    /**
     * Επιστρέφει ανά ασθενή:
     * id, name, phone, last_movement, charges, payments, balance
     * Η λογική είναι η «νέα»: χρεώσεις από payment (gross_total - discount),
     * πληρωμές από receipt_allocation (sum amount_applied ανά charge_id->payment->patient).
     */
    // μέσα στην κλάση ReportsController
private function fetchBalances(?string $q = null, bool $onlyDue = false, ?string $dateFrom = null, ?string $dateTo = null): array
{
    $pdo = pdo();

    // Normalise φίλτρα
    $q = trim((string)$q);
    $hasQ = ($q !== '');

    // Κρατάμε ημερομηνίες ως 'YYYY-MM-DD'
    $from = $dateFrom ? substr($dateFrom, 0, 10) : null;
    $to   = $dateTo   ? substr($dateTo, 0, 10)   : null;

    // Χτίζουμε δύο υποερωτήματα: charges (payment) και payments (receipt_allocation)
    // για να ΜΗΝ πολλαπλασιάζονται τα ποσά από joins.
    $sql = "
    WITH
    ch AS (
      SELECT
        p.patient                               AS patient_id,
        SUM( COALESCE(p.gross_total,0) - COALESCE(p.discount,0) ) AS charges,
        MAX(p.date)                             AS last_charge_date
      FROM payment p
      WHERE 1=1
        " . ($from ? "AND p.date >= :ch_from " : "") . "
        " . ($to   ? "AND p.date <= :ch_to   " : "") . "
      GROUP BY p.patient
    ),
    pay AS (
      SELECT
        p.patient                               AS patient_id,
        SUM( COALESCE(ra.amount_applied,0) )    AS paid,
        MAX(r.issue_date)                       AS last_receipt_date
      FROM receipt_allocation ra
      JOIN receipt r       ON r.id = ra.receipt_id
      JOIN payment p       ON p.id = ra.charge_id   -- για να βρούμε τον ασθενή
      WHERE 1=1
        " . ($from ? "AND r.issue_date >= :pay_from " : "") . "
        " . ($to   ? "AND r.issue_date <= :pay_to   " : "") . "
      GROUP BY p.patient
    )
    SELECT
      c.id,
      c.name,
      c.phone,
      COALESCE(ch.charges,0)                         AS charges,
      COALESCE(pay.paid,0)                           AS payments,
      COALESCE(ch.charges,0) - COALESCE(pay.paid,0)  AS balance,
      GREATEST(
        COALESCE(ch.last_charge_date, '1970-01-01'),
        COALESCE(pay.last_receipt_date, '1970-01-01')
      ) AS last_move
    FROM patient c
    LEFT JOIN ch  ON ch.patient_id  = c.id
    LEFT JOIN pay ON pay.patient_id = c.id
    WHERE 1=1
      " . ($hasQ ? "AND (c.name LIKE :q OR c.phone LIKE :q) " : "") . "
    " . ($onlyDue ? "HAVING balance > 0.005 " : "") . "
    ORDER BY last_move DESC, c.name ASC
    ";

    $params = [];
    if ($from) { $params[':ch_from']  = $from;  $params[':pay_from'] = $from; }
    if ($to)   { $params[':ch_to']    = $to;    $params[':pay_to']   = $to;   }
    if ($hasQ) { $params[':q']        = "%{$q}%"; }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

        unset($r);

        return $rows;
    }
}
