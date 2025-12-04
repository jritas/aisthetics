<?php
declare(strict_types=1);

// app/Controllers/ReportsController.php

final class ReportsController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function handle(): void
    {
        $a = $_GET['a'] ?? 'balances';

        switch ($a) {
            case 'old_balances':
                $this->old_balances();
                break;

            case 'cash_period':
                $this->cash_period();
                break;

            case 'cash_patient':
                $this->cash_patient();
                break;

            case 'balances':
            default:
                $this->balances();
                break;
        }
    }

    // ---------------- Παλιά Υπόλοιπα (legacy patient_deposit) ----------------

    public function old_balances(): void
    {
        $q       = trim($_GET['q'] ?? '');
        $from    = trim($_GET['from'] ?? '');
        $to      = trim($_GET['to'] ?? '');
        $onlyDue = isset($_GET['only_due']) && $_GET['only_due'] === '1';
        $export  = isset($_GET['export']);

        if ($from === '' && $to === '') {
            // προεπιλογή: δεν περιορίζουμε ημερομηνίες
            $fromDate = null;
            $toDate   = null;
        } else {
            if ($from === '' && $to !== '') {
                $from = $to;
            } elseif ($from !== '' && $to === '') {
                $to = $from;
            }
            $fromDate = $from;
            $toDate   = $to;
        }

        $where  = [];
        $params = [];

        if ($fromDate !== null) {
            $where[]             = 'pd.date >= :from';
            $params[':from']     = $fromDate;
        }
        if ($toDate !== null) {
            $where[]             = 'pd.date <= :to';
            $params[':to']       = $toDate;
        }

        $whereSql = '';
        if ($where) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        $sql = <<<SQL
SELECT 
    p.id          AS patient_id,
    p.name        AS patient_name,
    p.phone       AS patient_phone,
    IFNULL(SUM(CASE WHEN pd.transaction_type = 'charge'  THEN pd.amount ELSE 0 END), 0) AS charges,
    IFNULL(SUM(CASE WHEN pd.transaction_type = 'pay'     THEN pd.amount ELSE 0 END), 0) AS payments,
    MAX(pd.date)  AS last_movement
FROM patient p
JOIN patient_deposit pd ON pd.patient = p.id
{$whereSql}
GROUP BY p.id, p.name, p.phone
ORDER BY p.name
SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // υπολογισμός υπολοίπου, φιλτράρισμα q / only_due
        $filtered = [];
        foreach ($rows as $row) {
            $charges  = (float)$row['charges'];
            $payments = (float)$row['payments'];
            $balance  = $charges - $payments;

            $row['balance'] = $balance;

            if ($onlyDue && abs($balance) < 0.005) {
                continue;
            }

            if ($q !== '') {
                $needle = mb_strtolower($q, 'UTF-8');
                $hay    = mb_strtolower($row['patient_name'] . ' ' . $row['patient_phone'], 'UTF-8');
                if (mb_strpos($hay, $needle, 0, 'UTF-8') === false) {
                    continue;
                }
            }

            $filtered[] = $row;
        }

        if ($export) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="old_balances.csv"');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Όνομα', 'Τηλέφωνο', 'Χρεώσεις', 'Πληρωμές', 'Υπόλοιπο'], ';');

            foreach ($filtered as $row) {
                fputcsv(
                    $out,
                    [
                        $row['patient_id'],
                        $row['patient_name'],
                        $row['patient_phone'],
                        number_format((float)$row['charges'], 2, ',', '.'),
                        number_format((float)$row['payments'], 2, ',', '.'),
                        number_format((float)$row['balance'], 2, ',', '.'),
                    ],
                    ';'
                );
            }

            fclose($out);
            return;
        }

        $view_rows      = $filtered;
        $view_q         = $q;
        $view_from      = $fromDate;
        $view_to        = $toDate;
        $view_only_due  = $onlyDue;

        require __DIR__ . '/../Views/reports/old_balances.php';
    }

    // ---------------- Υπόλοιπα πελατών (νέα λογική) ----------------

    public function balances(): void
    {
        $q       = trim($_GET['q'] ?? '');
        $onlyDue = isset($_GET['only_due']) && $_GET['only_due'] === '1';
        $sort    = $_GET['sort'] ?? 'name';
        $dir     = $_GET['dir'] ?? 'asc';
        $export  = isset($_GET['export']);

        if (!in_array($sort, ['name', 'last_movement', 'balance'], true)) {
            $sort = 'name';
        }
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        $rows = $this->fetchBalances($q, $onlyDue, $sort, $dir);

        if ($export) {
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="balances.csv"');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Όνομα', 'Τηλέφωνο', 'Τελευταία κίνηση', 'Χρεώσεις', 'Πληρωμές', 'Υπόλοιπο'], ';');

            foreach ($rows as $row) {
                fputcsv(
                    $out,
                    [
                        $row['patient_id'],
                        $row['patient_name'],
                        $row['patient_phone'],
                        $row['last_movement'],
                        number_format((float)$row['charges'], 2, ',', '.'),
                        number_format((float)$row['payments'], 2, ',', '.'),
                        number_format((float)$row['balance'], 2, ',', '.'),
                    ],
                    ';'
                );
            }

            fclose($out);
            return;
        }

        $view_rows     = $rows;
        $view_q        = $q;
        $view_only_due = $onlyDue;
        $view_sort     = $sort;
        $view_dir      = $dir;

        require __DIR__ . '/../Views/reports/balances.php';
    }

    private function fetchBalances(string $q, bool $onlyDue, string $sort, string $dir): array
    {
        // Βασικό query: charges από payment, εισπράξεις από receipts (allocations) ή, αν δεν υπάρχουν,
        // fallback από payment.eispraxi (παλιά λογική)

        $sql = <<<SQL
SELECT
    p.id      AS patient_id,
    p.name    AS patient_name,
    p.phone   AS patient_phone,
    COALESCE(charges.total_charges, 0)  AS charges,
    COALESCE(payments.total_paid, 0)    AS payments,
    COALESCE(charges.total_charges, 0) - COALESCE(payments.total_paid, 0) AS balance,
    GREATEST(
        COALESCE(charges.last_charge, '0000-00-00 00:00:00'),
        COALESCE(payments.last_payment, '0000-00-00 00:00:00')
    ) AS last_movement
FROM patient p
LEFT JOIN (
    SELECT
        pay.patient           AS patient_id,
        SUM(pay.gross_total)  AS total_charges,
        MAX(pay.date)         AS last_charge
    FROM payment pay
    GROUP BY pay.patient
) AS charges
    ON charges.patient_id = p.id
LEFT JOIN (
    SELECT
        x.patient_id,
        SUM(x.amount) AS total_paid,
        MAX(x.last_date) AS last_payment
    FROM (
        -- ποσά από allocations (receipts)
        SELECT
            pay.patient AS patient_id,
            SUM(ra.amount_applied) AS amount,
            MAX(r.received_at) AS last_date
        FROM receipt_allocation ra
        JOIN receipt r   ON r.id   = ra.receipt_id
        JOIN payment pay ON pay.id = ra.charge_id
        GROUP BY pay.patient

        UNION ALL

        -- fallback: πληρωμές χωρίς allocations, από το παλιό πεδίο eispraxi
        SELECT
            pay.patient AS patient_id,
            SUM(pay.eispraxi) AS amount,
            MAX(pay.date) AS last_date
        FROM payment pay
        LEFT JOIN receipt_allocation ra ON ra.charge_id = pay.id
        WHERE ra.charge_id IS NULL AND pay.eispraxi IS NOT NULL AND pay.eispraxi <> 0
        GROUP BY pay.patient
    ) AS x
    GROUP BY x.patient_id
) AS payments
    ON payments.patient_id = p.id
SQL;

        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // Φιλτράρισμα σε PHP για q / only_due και ταξινόμηση
        $filtered = [];

        foreach ($rows as $row) {
            $charges  = (float)$row['charges'];
            $payments = (float)$row['payments'];
            $balance  = $charges - $payments;
            $row['balance'] = $balance;

            if ($onlyDue && abs($balance) < 0.005) {
                continue;
            }

            if ($q !== '') {
                $needle = mb_strtolower($q, 'UTF-8');
                $hay    = mb_strtolower($row['patient_name'] . ' ' . $row['patient_phone'], 'UTF-8');
                if (mb_strpos($hay, $needle, 0, 'UTF-8') === false) {
                    continue;
                }
            }

            $filtered[] = $row;
        }

        usort($filtered, function (array $a, array $b) use ($sort, $dir): int {
            $mult = $dir === 'desc' ? -1 : 1;

            switch ($sort) {
                case 'last_movement':
                    return $mult * strcmp((string)$a['last_movement'], (string)$b['last_movement']);
                case 'balance':
                    $ba = (float)$a['balance'];
                    $bb = (float)$b['balance'];
                    if ($ba === $bb) {
                        return 0;
                    }
                    return $ba < $bb ? -1 * $mult : 1 * $mult;
                case 'name':
                default:
                    return $mult * strcmp((string)$a['patient_name'], (string)$b['patient_name']);
            }
        });

        return $filtered;
    }

    // ---------------- Ταμείο περιόδου ----------------
public function cash_period(): void
{
    $pdo = $this->pdo;

    $fromStr = trim($_GET['from'] ?? '');
    $toStr   = trim($_GET['to'] ?? '');

    // Default: τρέχων μήνας
    if ($fromStr === '' && $toStr === '') {
        $fromStr = date('Y-m-01');
        $toStr   = date('Y-m-d');
    }
    if ($fromStr !== '' && $toStr === '') {
        $toStr = $fromStr;
    }
    if ($fromStr === '' && $toStr !== '') {
        $fromStr = $toStr;
    }

    $fromDate = \DateTime::createFromFormat('Y-m-d', $fromStr) ?: new \DateTime(date('Y-m-01'));
    $toDate   = \DateTime::createFromFormat('Y-m-d', $toStr)   ?: new \DateTime();

    if ($fromDate > $toDate) {
        $tmp      = $fromDate;
        $fromDate = $toDate;
        $toDate   = $tmp;
        $fromStr  = $fromDate->format('Y-m-d');
        $toStr    = $toDate->format('Y-m-d');
    }

    $from = $fromDate->format('Y-m-d 00:00:00');
    $to   = (clone $toDate)->modify('+1 day')->format('Y-m-d 00:00:00');

    // Χρεώσεις περιόδου (payments)
    $st = $pdo->prepare("
      SELECT
        COALESCE(SUM(amount),0)      AS sum_amount,
        COALESCE(SUM(discount),0)    AS sum_discount,
        COALESCE(SUM(gross_total),0) AS sum_gross
      FROM payment
      WHERE date >= :from AND date < :to
    ");
    $st->execute([':from' => $from, ':to' => $to]);
    $pay = $st->fetch(\PDO::FETCH_ASSOC) ?: ['sum_amount'=>0,'sum_discount'=>0,'sum_gross'=>0];

    $sumAmount   = (float)$pay['sum_amount'];
    $sumDiscount = (float)$pay['sum_discount'];
    $sumGross    = (float)$pay['sum_gross'];

    // Εισπράξεις από receipts (νέο σύστημα αποδείξεων)
    $st = $pdo->prepare("
      SELECT COALESCE(method,'') AS method, SUM(amount) AS amt
      FROM receipt
      WHERE received_at >= :from AND received_at < :to
      GROUP BY COALESCE(method,'')
      ORDER BY method
    ");
    $st->execute([':from' => $from, ':to' => $to]);

    $recSummary = [];
    $recTotal   = 0.0;
    while ($r = $st->fetch(\PDO::FETCH_ASSOC)) {
        $m = (string)$r['method'];
        $a = (float)$r['amt'];
        $recSummary[$m] = ($recSummary[$m] ?? 0.0) + $a;
        $recTotal      += $a;
    }

    // Fallback για παλιές εισπράξεις (χωρίς receipts: eispraxi / amount_received)
    $fallbackExpr = "
      GREATEST(
        COALESCE(NULLIF(p.eispraxi,0),0),
        COALESCE(NULLIF(p.amount_received,0),0)
      )
    ";

    $sqlFbSummary = "
      SELECT SUM({$fallbackExpr}) AS amt
      FROM payment p
      LEFT JOIN receipt_allocation ra ON ra.charge_id = p.id
      WHERE p.date >= :from AND p.date < :to
        AND ra.charge_id IS NULL
    ";
    $st = $pdo->prepare($sqlFbSummary);
    $st->execute([':from' => $from, ':to' => $to]);
    $fbAmt = (float)($st->fetchColumn() ?: 0);

    if ($fbAmt > 0) {
        // βάζουμε τις «παλιές» εισπράξεις στη μέθοδο ''
        $recSummary[''] = ($recSummary[''] ?? 0.0) + $fbAmt;
        $recTotal      += $fbAmt;
    }

    // Αναλυτικές εισπράξεις περιόδου (για πίνακα και CSV)
    $st = $pdo->prepare("
      SELECT r.id, r.received_at, r.method, r.amount, r.note,
             p.name AS patient_name, p.id AS patient_id
      FROM receipt r
      LEFT JOIN patient p ON p.id = r.patient_id
      WHERE r.received_at >= :from AND r.received_at < :to
      ORDER BY r.received_at, r.id
    ");
    $st->execute([':from' => $from, ':to' => $to]);
    $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

    if (isset($_GET['csv']) && (int)$_GET['csv'] === 1) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="cash_period.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Ημερομηνία', 'Ώρα', 'Ασθενής', 'Μέθοδος', 'Ποσό', 'Σχόλιο']);
        foreach ($rows as $r) {
            $dt  = new \DateTime($r['received_at']);
            $d   = $dt->format('d/m/Y');
            $t   = $dt->format('H:i');
            $pat = (string)($r['patient_name'] ?? '');
            $m   = (string)($r['method'] ?? '');
            if ($m === '') {
                $m = '—';
            }
            $amt  = number_format((float)$r['amount'], 2, '.', '');
            $note = (string)($r['note'] ?? '');
            fputcsv($out, [$d, $t, $pat, $m, $amt, $note]);
        }
        fputcsv($out, []);
        fputcsv($out, ['Σύνολο εισπράξεων', '', '', '', number_format($recTotal,2,'.',''), '']);
        fclose($out);
        exit;
    }

    $methodLabels = [
      ''      => '—',
      'cash'  => 'Μετρητά',
      'card'  => 'Κάρτα',
      'bank'  => 'Τράπεζα',
      'other' => 'Άλλο',
    ];

    $title           = 'Ταμείο περιόδου';
    $from_date       = $fromStr;
    $to_date         = $toStr;
    $sum_amount      = $sumAmount;
    $sum_discount    = $sumDiscount;
    $sum_gross       = $sumGross;
    $receipt_summary = $recSummary;
    $receipt_total   = $recTotal;
    $receipt_rows    = $rows;
    $method_labels   = $methodLabels;

    include __DIR__ . '/../Views/reports/cash_period.php';
}

public function cash_patient(): void
{
    $pdo = $this->pdo;

    // Λίστα πελατών για το dropdown
    $st = $pdo->query("SELECT id, name, phone FROM patient ORDER BY name");
    $patients = $st->fetchAll(\PDO::FETCH_ASSOC);

    $patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

    $fromStr = trim($_GET['from'] ?? '');
    $toStr   = trim($_GET['to'] ?? '');

    // Προεπιλογή: τρέχων μήνας (1η του μήνα έως σήμερα)
    if ($fromStr === '' && $toStr === '') {
        $fromStr = date('Y-m-01');
        $toStr   = date('Y-m-d');
    }
    if ($fromStr !== '' && $toStr === '') {
        $toStr = $fromStr;
    }
    if ($fromStr === '' && $toStr !== '') {
        $fromStr = $toStr;
    }

    $fromDate = \DateTime::createFromFormat('Y-m-d', $fromStr) ?: new \DateTime(date('Y-m-01'));
    $toDate   = \DateTime::createFromFormat('Y-m-d', $toStr)   ?: new \DateTime();

    if ($fromDate > $toDate) {
        $tmp      = $fromDate;
        $fromDate = $toDate;
        $toDate   = $tmp;
        $fromStr  = $fromDate->format('Y-m-d');
        $toStr    = $toDate->format('Y-m-d');
    }

    $from = $fromDate->format('Y-m-d 00:00:00');
    $to   = (clone $toDate)->modify('+1 day')->format('Y-m-d 00:00:00');

    $methodLabels = [
        ''      => '—',
        'cash'  => 'Μετρητά',
        'card'  => 'Κάρτα',
        'bank'  => 'Τράπεζα',
        'other' => 'Άλλο',
    ];

    $movements      = [];
    $totalCharges   = 0.0;
    $totalReceipts  = 0.0;
    $finalBalance   = 0.0;
    $currentPatient = null;

    if ($patientId > 0) {
        // Βρίσκουμε τα στοιχεία του επιλεγμένου πελάτη για την επικεφαλίδα
        foreach ($patients as $p) {
            if ((int)$p['id'] === $patientId) {
                $currentPatient = $p;
                break;
            }
        }

        // ΧΡΕΩΣΕΙΣ πελάτη (payments)
        $st = $pdo->prepare("
            SELECT id, `date`, category, category_name, gross_total
            FROM payment
            WHERE patient = :pid
              AND `date` >= :from AND `date` < :to
            ORDER BY `date`
        ");
        $st->execute([':pid' => $patientId, ':from' => $from, ':to' => $to]);
        $charges = $st->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($charges as $c) {
            $movements[] = [
                'type'          => 'charge',
                'datetime'      => $c['date'],
                'category'      => $c['category'],
                'category_name' => $c['category_name'],
                'amount'        => (float)$c['gross_total'],
            ];
            $totalCharges += (float)$c['gross_total'];
        }

        // ΕΙΣΠΡΑΞΕΙΣ με receipts
        $st = $pdo->prepare("
            SELECT r.id, r.received_at, r.method, r.amount, r.note
            FROM receipt r
            WHERE r.patient_id = :pid
              AND r.received_at >= :from AND r.received_at < :to
            ORDER BY r.received_at, r.id
        ");
        $st->execute([':pid' => $patientId, ':from' => $from, ':to' => $to]);
        $receipts = $st->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($receipts as $r) {
            $movements[] = [
                'type'     => 'receipt',
                'datetime' => $r['received_at'],
                'method'   => $r['method'],
                'note'     => $r['note'],
                'amount'   => (float)$r['amount'],
            ];
            $totalReceipts += (float)$r['amount'];
        }

        // Fallback εισπράξεων χωρίς receipts (eispraxi / amount_received)
        $fallbackExpr = "
            GREATEST(
                COALESCE(NULLIF(p.eispraxi,0),0),
                COALESCE(NULLIF(p.amount_received,0),0)
            )
        ";

        $sqlFb = "
            SELECT p.date AS dt, {$fallbackExpr} AS amt
            FROM payment p
            LEFT JOIN receipt_allocation ra ON ra.charge_id = p.id
            WHERE p.patient = :pid
              AND p.date >= :from AND p.date < :to
              AND ra.charge_id IS NULL
        ";
        $st = $pdo->prepare($sqlFb);
        $st->execute([':pid' => $patientId, ':from' => $from, ':to' => $to]);
        $fbRows = $st->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($fbRows as $fb) {
            $amt = (float)$fb['amt'];
            if ($amt <= 0) {
                continue;
            }
            $movements[] = [
                'type'     => 'receipt_fallback',
                'datetime' => $fb['dt'],
                'method'   => '',
                'note'     => 'Είσπραξη χωρίς απόδειξη (eispraxi/amount_received)',
                'amount'   => $amt,
            ];
            $totalReceipts += $amt;
        }

        // Ταξινόμηση κινήσεων με βάση ημερομηνία/ώρα
        usort($movements, function (array $a, array $b): int {
            return strcmp($a['datetime'], $b['datetime']);
        });

        $finalBalance = $totalCharges - $totalReceipts;
    }

    // CSV export
    if (isset($_GET['csv']) && (int)$_GET['csv'] === 1 && $patientId > 0) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="cash_patient_' . $patientId . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Ημερομηνία', 'Ώρα', 'Τύπος', 'Κατηγορία/Μέθοδος', 'Ποσό', 'Σχόλιο']);

        foreach ($movements as $m) {
            $dt = new \DateTime($m['datetime']);
            $d  = $dt->format('d/m/Y');
            $t  = $dt->format('H:i');

            if ($m['type'] === 'charge') {
                $typeLabel = 'Χρέωση';
                $catLabel  = $m['category_name'] ?: $m['category'];
                $amount    = number_format($m['amount'], 2, '.', '');
                $note      = '';
            } else { // receipt ή fallback
                $typeLabel = 'Είσπραξη';
                $method    = (string)$m['method'];
                if ($method === '') {
                    $method = '—';
                }
                $catLabel = $method;
                $amount   = number_format($m['amount'], 2, '.', '');
                $note     = (string)($m['note'] ?? '');
            }

            fputcsv($out, [$d, $t, $typeLabel, $catLabel, $amount, $note]);
        }

        fputcsv($out, []);
        fputcsv($out, ['Σύνολο χρεώσεων', '', '', '', number_format($totalCharges, 2, '.', ''), '']);
        fputcsv($out, ['Σύνολο εισπράξεων', '', '', '', number_format($totalReceipts, 2, '.', ''), '']);
        fputcsv($out, ['Τελικό υπόλοιπο', '', '', '', number_format($finalBalance, 2, '.', ''), '']);

        fclose($out);
        exit;
    }

    // Μεταβλητές για το view
    $title           = 'Ταμείο πελάτη';
    $patients_list   = $patients;
    $current_patient = $currentPatient;
    $from_date       = $fromStr;
    $to_date         = $toStr;

    // ΓΙΑ ΝΑ ΜΗ ΒΓΑΖΕΙ WARNINGS ΤΟ VIEW:
    $selected_id         = $patientId;
    $selected_patient_id = $patientId;

    $movements_rows = $movements;
    $total_charges  = $totalCharges;
    $total_receipts = $totalReceipts;
    $final_balance  = $finalBalance;
    $method_labels  = $methodLabels;

    include __DIR__ . '/../Views/reports/cash_patient.php';
}


}
