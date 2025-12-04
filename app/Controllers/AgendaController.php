<?php
require_once __DIR__ . '/../Lib/Csrf.php';

final class AgendaController {
  private PDO $pdo;
  public function __construct(PDO $pdo) { $this->pdo = $pdo; }

  public function handle(): void {
    $a = $_GET['a'] ?? 'index';
    switch ($a) {
      case 'fetch':           $this->fetch(); break;
      case 'checkin':         $this->checkin(); break;
      case 'search_services': $this->searchServices(); break;
      case 'save_visit':      $this->saveVisit(); break;
      case 'daily_cash':      $this->dailyCash(); break;
      default:                $this->index(); break;
    }
  }

  /* ---------- Common helpers ---------- */
  private function ypoColumn(): string {
    $sql = "SELECT COLUMN_NAME FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name='payment'
              AND column_name IN ('ypoloipo','ypolipo')";
    $col = $this->pdo->query($sql)->fetchColumn();
    return $col ?: 'ypoloipo';
  }

  /** Recalculate status/eispraxi/ypoloipo for given charge ids */
  private function refreshCharges(array $chargeIds): void {
    if (empty($chargeIds)) return;
    $place = implode(',', array_fill(0, count($chargeIds), '?'));
    $ypo = $this->ypoColumn();
    $sql = <<<SQL
UPDATE payment p
LEFT JOIN (
  SELECT charge_id, SUM(amount_applied) AS paid
  FROM receipt_allocation
  WHERE charge_id IN ({$place})
  GROUP BY charge_id
) t ON t.charge_id = p.id
SET p.eispraxi = GREATEST(COALESCE(t.paid,0),
                          COALESCE(NULLIF(p.eispraxi,0),0),
                          COALESCE(NULLIF(p.amount_received,0),0)),
    p.{$ypo}   = GREATEST(
                   p.gross_total - GREATEST(COALESCE(t.paid,0),
                                            COALESCE(NULLIF(p.eispraxi,0),0),
                                            COALESCE(NULLIF(p.amount_received,0),0)), 0),
    p.status   = CASE
                   WHEN GREATEST(
                          p.gross_total - GREATEST(COALESCE(t.paid,0),
                                                   COALESCE(NULLIF(p.eispraxi,0),0),
                                                   COALESCE(NULLIF(p.amount_received,0),0)), 0) = 0
                     THEN 'paid'
                   WHEN GREATEST(COALESCE(t.paid,0),
                                 COALESCE(NULLIF(p.eispraxi,0),0),
                                 COALESCE(NULLIF(p.amount_received,0),0)) > 0
                     THEN 'partial'
                   ELSE 'pending'
                 END
WHERE p.id IN ({$place})
SQL;
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(array_merge($chargeIds, $chargeIds));
  }

  /** Robust patient balance */
  private function patientBalance(int $patientId): float {
    $sql = <<<SQL
SELECT
  COALESCE(SUM(
    GREATEST(
      p.gross_total - GREATEST(
        COALESCE((SELECT SUM(ra.amount_applied) FROM receipt_allocation ra WHERE ra.charge_id = p.id), 0),
        COALESCE(NULLIF(p.eispraxi,0),0),
        COALESCE(NULLIF(p.amount_received,0),0)
      ),
      0
    )
  ),0) AS due
FROM payment p
WHERE p.patient = :pid
SQL;
    $st = $this->pdo->prepare($sql);
    $st->execute([':pid'=>$patientId]);
    return (float)$st->fetchColumn();
  }

  /* ---------- List ---------- */
  private function buildFilters(): array {
    $q    = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $off   = ($page - 1) * $limit;

    $sort = $_GET['sort'] ?? 'last_visit';
    $dir  = strtolower($_GET['dir'] ?? 'desc');
    $dir  = $dir === 'asc' ? 'ASC' : 'DESC';

    $map = [
      'id'         => 'p.id',
      'name'       => 'p.name',
      'phone'      => 'p.phone',
      'last_visit' => 'lv.last_visit',
      'visits'     => 'lv.visits',
    ];
    $order    = $map[$sort] ?? 'lv.last_visit';
    $orderSql = $order . ' ' . $dir . ', p.id DESC';

    $where  = '1=1';
    $params = [];

    if ($q !== '') {
      $like = '%'.$q.'%';
      $where .= ' AND (p.name LIKE :q1 OR p.phone LIKE :q2 OR p.email LIKE :q3)';
      $params[':q1'] = $like;
      $params[':q2'] = $like;
      $params[':q3'] = $like;
    }

    return [$q, $page, $limit, $off, $orderSql, $params, $sort, $dir, $where];
  }

  private function baseQuery(string $where, array $params, string $order, int $off, int $limit): array {
    $sql = <<<SQL
SELECT SQL_CALC_FOUND_ROWS
  p.id,p.name,p.phone,p.email,
  DATE_FORMAT(lv.last_visit,'%d/%m/%Y %H:%i') AS last_visit,
  COALESCE(lv.visits,0) AS visits
FROM patient p
LEFT JOIN (
  SELECT patient AS patient_id, MAX(date) AS last_visit, COUNT(*) AS visits
  FROM payment GROUP BY patient
) lv ON lv.patient_id = p.id
WHERE {$where}
ORDER BY {$order}
LIMIT :off,:lim
SQL;
    $stmt=$this->pdo->prepare($sql);
    foreach($params as $k=>$v){ $stmt->bindValue($k,$v); }
    $stmt->bindValue(':off',$off,\PDO::PARAM_INT);
    $stmt->bindValue(':lim',$limit,\PDO::PARAM_INT);
    $stmt->execute();
    $rows=$stmt->fetchAll(\PDO::FETCH_ASSOC);
    $total=(int)$this->pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
    return [$rows,$total];
  }

  private function index(): void {
    [$q,$page,$limit,$off,$orderSql,$params,$sort,$dir,$where] = $this->buildFilters();
    [$rows,$total] = $this->baseQuery($where,$params,$orderSql,$off,$limit);
    $pages=max(1,(int)ceil($total/$limit));
    $csrf=Csrf::token();
    include __DIR__.'/../Views/agenda/index.php';
  }

  private function fetch(): void {
    [$q,$page,$limit,$off,$orderSql,$params,$sort,$dir,$where] = $this->buildFilters();
    [$rows,$total] = $this->baseQuery($where,$params,$orderSql,$off,$limit);
    $pages=max(1,(int)ceil($total/$limit));
    $csrf=Csrf::token();
    ob_start(); include __DIR__.'/../Views/agenda/_list_rows.php'; $tbody=ob_get_clean();
    ob_start(); include __DIR__.'/../Views/agenda/_pagination.php'; $pagination=ob_get_clean();
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['tbody'=>$tbody,'pagination'=>$pagination], JSON_UNESCAPED_UNICODE); exit;
  }

  /* ---------- Check-in ---------- */
  private function checkin(): void {
    // Δεχόμαστε είτε ?id=, είτε ?patient_id= (όπως από την καρτέλα), είτε ?patient=
    $patientKey = 0;
    if (isset($_GET['patient_id'])) {
      $patientKey = (int)$_GET['patient_id'];
    } elseif (isset($_GET['id'])) {
      $patientKey = (int)$_GET['id'];
    } elseif (isset($_GET['patient'])) {
      $patientKey = (int)$_GET['patient'];
    }

    if ($patientKey <= 0) {
      http_response_code(404);
      exit('Not found');
    }

    // Προσπαθούμε πρώτα με το primary key id
    $patient = null;
    $stmt = $this->pdo->prepare("SELECT * FROM patient WHERE id = :id");
    $stmt->execute([':id' => $patientKey]);
    $patient = $stmt->fetch(\PDO::FETCH_ASSOC);

    // Fallback: αν δεν βρεθεί, δοκιμάζουμε πεδίο patient_id (legacy ZAP)
    if (!$patient) {
      try {
        $stmt = $this->pdo->prepare("SELECT * FROM patient WHERE patient_id = :pid");
        $stmt->execute([':pid' => $patientKey]);
        $patient = $stmt->fetch(\PDO::FETCH_ASSOC);
      } catch (\Throwable $e) {
        // αν δεν υπάρχει τέτοιο πεδίο, απλά το αγνοούμε
      }
    }

    if (!$patient) {
      http_response_code(404);
      exit('Not found');
    }

    // Σίγουρο primary key του ασθενή
    $id = (int)$patient['id'];

    // refresh σε όλες τις χρεώσεις του ασθενή για συνεπές UI
    $ids=$this->pdo->prepare("SELECT id FROM payment WHERE patient=:id");
    $ids->execute([':id'=>$id]);
    $toRefresh=array_map('intval',$ids->fetchAll(\PDO::FETCH_COLUMN));
    if ($toRefresh) $this->refreshCharges($toRefresh);

    // Ιστορικό επισκέψεων
    $hist=$this->pdo->prepare("SELECT id, DATE_FORMAT(date, '%d/%m/%Y %H:%i') AS dt, category_name, gross_total, doctor_id, treatment, medicine, status FROM payment WHERE patient=:id ORDER BY date DESC LIMIT 20");
    $hist->execute([':id'=>$id]); $history=$hist->fetchAll(\PDO::FETCH_ASSOC);

    $patientBalance = $this->patientBalance($id);

    // Γιατροί + τελευταίος γιατρός για preselect
    $docs = $this->pdo->query("SELECT id, name FROM doctor ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
    $lastDocStmt = $this->pdo->prepare("SELECT doctor_id FROM payment WHERE patient=:id AND doctor_id IS NOT NULL ORDER BY date DESC LIMIT 1");
    $lastDocStmt->execute([':id'=>$id]);
    $lastDoctorId=(int)($lastDocStmt->fetchColumn()?:0);

    /* ===== GDPR badge (consent) ===== */
    $gdpr = ['exists'=>false, 'has'=>false, 'ts'=>null];
    try {
      $tbl = $this->pdo->prepare("
        SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'consent'
      ");
      $tbl->execute();
      if ((int)$tbl->fetchColumn() > 0) {
        $gdpr['exists'] = true;
        $st = $this->pdo->prepare("SELECT consent, timestamp FROM consent WHERE patient_id = :pid ORDER BY timestamp DESC LIMIT 1");
        $st->execute([':pid'=>$id]);
        if ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
          $gdpr['has'] = ((int)$row['consent'] === 1);
          $gdpr['ts']  = $row['timestamp'];
        }
      }
    } catch (\Throwable $e) {
      // σιωπηλή αποτυχία
    }

    $csrf=Csrf::token();
    include __DIR__.'/../Views/agenda/checkin.php';
  }

  /* ---------- Services ---------- */
  private function searchServices(): void {
    header('Content-Type: application/json; charset=utf-8');

    $q       = trim($_GET['q'] ?? '');
    $listAll = isset($_GET['list_all']) || isset($_GET['all']) || $q === '*';
    $rows    = [];

    try {
      $tblExists = $this->pdo->query("
        SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'payment_category'
      ")->fetchColumn();

      if ($tblExists) {
        $cols = $this->pdo->query("
          SELECT COLUMN_NAME FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = 'payment_category'
        ")->fetchAll(\PDO::FETCH_COLUMN);

        $labelCol = in_array('description',$cols,true) ? 'description' :
                    (in_array('name',$cols,true) ? 'name' :
                    (in_array('category_name',$cols,true) ? 'category_name' :
                    (in_array('category',$cols,true) ? 'category' : 'title')));

        $priceCol  = in_array('c_price',$cols,true) ? 'c_price' : (in_array('price',$cols,true) ? 'price' : null);
        $priceExpr = $priceCol ? "COALESCE($priceCol,0)" : "0";

        if ($listAll) {
          $st = $this->pdo->query("
            SELECT id, {$labelCol} AS name, {$priceExpr} AS price
            FROM payment_category
            WHERE {$labelCol} IS NOT NULL AND {$labelCol} <> ''
            ORDER BY {$labelCol}
            LIMIT 200
          ");
        } elseif ($q !== '') {
          $st = $this->pdo->prepare("
            SELECT id, {$labelCol} AS name, {$priceExpr} AS price
            FROM payment_category
            WHERE {$labelCol} LIKE :q
            ORDER BY {$labelCol}
            LIMIT 50
          ");
          $st->execute([':q'=>'%'.$q.'%']);
        } else {
          $st = null;
        }

        if ($st) {
          while ($r = $st->fetch(\PDO::FETCH_ASSOC)) {
            $rows[] = ['id'=>(int)$r['id'],'name'=>(string)$r['name'],'price'=>(float)$r['price']];
          }
        }
      }

      if (empty($rows)) {
        if ($listAll) {
          $st = $this->pdo->query("
            SELECT DISTINCT category_name AS name
            FROM payment
            WHERE category_name IS NOT NULL AND category_name <> ''
            ORDER BY name
            LIMIT 200
          ");
        } elseif ($q !== '') {
          $st = $this->pdo->prepare("
            SELECT DISTINCT category_name AS name
            FROM payment
            WHERE category_name IS NOT NULL AND category_name <> ''
              AND category_name LIKE :q
            ORDER BY name
            LIMIT 50
          ");
          $st->execute([':q'=>'%'.$q.'%']);
        } else {
          $st = null;
        }

        if ($st) {
          while ($r = $st->fetch(\PDO::FETCH_ASSOC)) {
            $rows[] = ['id'=>null,'name'=>(string)$r['name'],'price'=>0.0];
          }
        }
      }

      if (ob_get_level()) ob_clean();
      echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    } catch (\Throwable $e) {
      if (ob_get_level()) ob_clean();
      http_response_code(500);
      echo json_encode(['error'=>'search_failed','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
  }

  /* ---------- Save visit ---------- */
  private function saveVisit(): void {
    if (!Csrf::check($_POST['csrf'] ?? null)) { http_response_code(403); exit('Forbidden'); }

    $pid    = (int)($_POST['patient_id'] ?? 0);
    $docId  = isset($_POST['doctor_id']) && $_POST['doctor_id']!=='' ? (int)$_POST['doctor_id'] : null;
    $lines  = $_POST['lines'] ?? [];
    if (!is_array($lines)) $lines = [];
    $discount    = (float)($_POST['discount'] ?? 0);
    $received    = (float)($_POST['received'] ?? 0);
    $treatment   = trim($_POST['treatment'] ?? '');
    $medicine    = trim($_POST['medicine'] ?? '');
    $onlyCollect = isset($_POST['collection_only']) && $_POST['collection_only']=='1';
    $method      = trim($_POST['method'] ?? '');

    if (!$pid) { $_SESSION['flash']=['type'=>'warning','msg'=>'Δεν βρέθηκε ασθενής.']; header('Location:?r=agenda'); exit; }

    $this->pdo->beginTransaction();
    try {
      $newCharges = [];

      if (!$onlyCollect) {
        $clean = [];
        foreach ($lines as $ln) {
          if (!is_array($ln)) continue;
          $name = trim($ln['name'] ?? '');
          $amount = (float)($ln['amount'] ?? 0);
          if ($name !== '' && $amount > 0) $clean[] = ['name'=>$name,'amount'=>$amount];
        }

        if (empty($clean)) {
          $_SESSION['flash']=['type'=>'warning','msg'=>'Πρόσθεσε τουλάχιστον μία θεραπεία ή επίλεξε "Είσπραξη οφειλής".'];
          $this->pdo->rollBack(); header('Location:?r=agenda&a=checkin&id='.$pid); return;
        }

        $total = 0.0; foreach ($clean as $c) $total += $c['amount'];
        $discount = max(0.0, min($discount, $total));

        $ins = $this->pdo->prepare(<<<SQL
INSERT INTO payment
  (patient, doctor_id, category_name, amount, discount, flat_discount, gross_total, treatment, medicine, date, status)
VALUES
  (:patient, :doctor_id, :name, :amount, :discount, 0, :gross, :treatment, :medicine, NOW(), 'pending')
SQL
        );

        $accDisc = 0.0; $last = count($clean)-1; $idx=0;
        foreach ($clean as $c) {
          $share = ($total>0) ? ($c['amount']/$total) : 0;
          $rowDiscount = ($idx === $last) ? round($discount - $accDisc, 2) : round($discount * $share, 2);
          $accDisc += $rowDiscount; $idx++;

          $gross = round($c['amount'] - $rowDiscount, 2);
          $ins->execute([
            ':patient'=>$pid, ':doctor_id'=>$docId, ':name'=>$c['name'],
            ':amount'=>$c['amount'], ':discount'=>$rowDiscount, ':gross'=>$gross,
            ':treatment'=>$treatment, ':medicine'=>$medicine
          ]);
          $newCharges[] = (int)$this->pdo->lastInsertId();
        }
      }

      $affected = [];
      if ($received > 0) {
        $rIns = $this->pdo->prepare("INSERT INTO receipt (patient_id, amount, method, note, received_at) VALUES (:p,:a,:m,NULL,NOW())");
        $rIns->execute([':p'=>$pid, ':a'=>$received, ':m'=>($method!==''?$method:null)]);
        $receiptId = (int)$this->pdo->lastInsertId();
        $affected = $this->allocateReceipt($receiptId, $pid, $received, $newCharges);
      }

      $refreshIds = array_values(array_unique(array_merge($newCharges, $affected)));
      if (!empty($refreshIds)) { $this->refreshCharges($refreshIds); }

      $this->pdo->commit();
      $_SESSION['flash']=['type'=>'success','msg'=>'Η επίσκεψη καταχωρήθηκε.'];
      header('Location:?r=agenda&a=checkin&id='.$pid); exit;

    } catch (\Throwable $e) {
      $this->pdo->rollBack();
      $_SESSION['flash']=['type'=>'danger','msg'=>'Σφάλμα: '.$e->getMessage()];
      header('Location:?r=agenda&a=checkin&id='.$pid); exit;
    }
  }

  private function allocateReceipt(int $receiptId, int $patientId, float $amount, array $preferIds = []): array
  {
    $allocIns = $this->pdo->prepare(
        "INSERT INTO receipt_allocation (receipt_id, charge_id, amount_applied)
         VALUES (:r,:c,:amt)"
    );
    $affected = [];
    $to = round($amount, 2);

    $dueExpr = "(p.gross_total - GREATEST(
                  COALESCE((SELECT SUM(ra.amount_applied)
                            FROM receipt_allocation ra
                            WHERE ra.charge_id=p.id),0),
                  COALESCE(NULLIF(p.eispraxi,0),0),
                  COALESCE(NULLIF(p.amount_received,0),0)
                ))";

    if ($to > 0 && !empty($preferIds)) {
        $place = implode(',', array_fill(0, count($preferIds), '?'));
        $sql = <<<SQL
SELECT p.id, {$dueExpr} AS due
FROM payment p
WHERE p.id IN ({$place})
HAVING due > 0.009
ORDER BY FIELD(p.id, {$place})
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($preferIds, $preferIds));

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($to <= 0) break;
            $apply = min($to, (float)$row['due']);
            if ($apply > 0.009) {
                $allocIns->execute([':r'=>$receiptId, ':c'=>$row['id'], ':amt'=>$apply]);
                $affected[] = (int)$row['id'];
                $to = round($to - $apply, 2);
            }
        }
    }

    if ($to > 0) {
        $notIn = '';
        $params = [$patientId];
        if (!empty($preferIds)) {
            $place = implode(',', array_fill(0, count($preferIds), '?'));
            $notIn = " AND p.id NOT IN ({$place}) ";
            $params = array_merge($params, $preferIds);
        }
        $sql = <<<SQL
SELECT p.id, {$dueExpr} AS due
FROM payment p
WHERE p.patient = ? {$notIn}
HAVING due > 0.009
ORDER BY p.date ASC, p.id ASC
SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($to <= 0) break;
            $apply = min($to, (float)$row['due']);
            if ($apply > 0.009) {
                $allocIns->execute([':r'=>$receiptId, ':c'=>$row['id'], ':amt'=>$apply]);
                $affected[] = (int)$row['id'];
                $to = round($to - $apply, 2);
            }
        }
    }

    return array_values(array_unique($affected));
  }

  function dailyCash(): void
  {
    $dStr = trim($_GET['d'] ?? '');
    $dObj = \DateTime::createFromFormat('Y-m-d', $dStr) ?: new \DateTime('today');
    $dStr = $dObj->format('Y-m-d');

    $from = $dObj->format('Y-m-d 00:00:00');
    $to   = $dObj->modify('+1 day')->format('Y-m-d 00:00:00');

    // Χρεώσεις της ημέρας (Payments)
    $st = $this->pdo->prepare("
      SELECT
        COALESCE(SUM(amount),0)      AS sum_amount,
        COALESCE(SUM(discount),0)    AS sum_discount,
        COALESCE(SUM(gross_total),0) AS sum_gross
      FROM payment
      WHERE date >= :from AND date < :to
    ");
    $st->execute([':from' => $from, ':to' => $to]);
    $pay = $st->fetch(\PDO::FETCH_ASSOC) ?: [
      'sum_amount'   => 0,
      'sum_discount' => 0,
      'sum_gross'    => 0,
    ];
    $sumAmount   = (float)$pay['sum_amount'];
    $sumDiscount = (float)$pay['sum_discount'];
    $sumGross    = (float)$pay['sum_gross'];

    // Εισπράξεις από το νέο σύστημα receipts
    $st = $this->pdo->prepare("
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

    // Fallback: παλιές εισπράξεις χωρίς receipts (eispraxi / amount_received)
    $fallbackExpr = "
      GREATEST(
        COALESCE(NULLIF(p.eispraxi,0),0),
        COALESCE(NULLIF(p.amount_received,0),0)
      )
    ";

    // Σύνοψη fallback εισπράξεων (χωρίς διάκριση μεθόδου -> πάνε στο '—')
    $sqlFbSummary = "
      SELECT SUM({$fallbackExpr}) AS amt
      FROM payment p
      LEFT JOIN receipt_allocation ra ON ra.charge_id = p.id
      WHERE p.date >= :from AND p.date < :to
        AND ra.charge_id IS NULL
    ";
    $st = $this->pdo->prepare($sqlFbSummary);
    $st->execute([':from' => $from, ':to' => $to]);
    $fbAmt = (float)($st->fetchColumn() ?: 0);

    if ($fbAmt > 0) {
      // κενό method -> εμφανίζεται ως '—' στο UI
      $recSummary[''] = ($recSummary[''] ?? 0.0) + $fbAmt;
      $recTotal      += $fbAmt;
    }

    // Αναλυτικές εισπράξεις από receipts
    $st = $this->pdo->prepare("
      SELECT r.id, r.received_at, r.method, r.amount, r.note, p.name AS patient_name
      FROM receipt r
      LEFT JOIN patient p ON p.id = r.patient_id
      WHERE r.received_at >= :from AND r.received_at < :to
    ");
    $st->execute([':from' => $from, ':to' => $to]);
    $rows = $st->fetchAll(\PDO::FETCH_ASSOC);

    // Αναλυτικές fallback εισπράξεις (παλιό μοντέλο, χωρίς receipts)
    $sqlFbRows = "
      SELECT
        p.id AS id,
        p.`date` AS received_at,
        NULL AS method,
        {$fallbackExpr} AS amount,
        CONCAT('(παλαιό σύστημα εισπράξεων, payment #', p.id, ')') AS note,
        pt.name AS patient_name
      FROM payment p
      LEFT JOIN receipt_allocation ra ON ra.charge_id = p.id
      LEFT JOIN patient pt ON pt.id = p.patient
      WHERE p.`date` >= :from AND p.`date` < :to
        AND ra.charge_id IS NULL
        AND {$fallbackExpr} > 0
    ";
    $st = $this->pdo->prepare($sqlFbRows);
    $st->execute([':from' => $from, ':to' => $to]);
    $fbRows = $st->fetchAll(\PDO::FETCH_ASSOC);

    // Ενοποίηση και ταξινόμηση με βάση την ημερομηνία/ώρα
    $rows = array_merge($rows, $fbRows);
    usort(
      $rows,
      function (array $a, array $b): int {
        return strcmp($a['received_at'] ?? '', $b['received_at'] ?? '');
      }
    );

    // CSV export
    if (isset($_GET['csv'])) {
      $filename = "daily_cash_{$dStr}.csv";
      header('Content-Type: text/csv; charset=utf-8');
      header("Content-Disposition: attachment; filename=\"{$filename}\"");
      echo "\xEF\xBB\xBF";
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
      fputcsv($out, ['Σύνολο εισπράξεων', '', '', '', number_format($recTotal, 2, '.', ''), '']);
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

    $title = 'Ημερήσιο Ταμείο';
    $csrf  = Csrf::token();
    $d = $dStr;
    $sum_amount      = $sumAmount;
    $sum_discount    = $sumDiscount;
    $sum_gross       = $sumGross;
    $receipt_summary = $recSummary;
    $receipt_total   = $recTotal;
    $receipt_rows    = $rows;
    $method_labels   = $methodLabels;

    include __DIR__ . '/../Views/agenda/daily_cash.php';
  }


}
