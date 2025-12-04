<?php
require_once __DIR__ . '/../Lib/Csrf.php';
require_once __DIR__ . '/../Lib/Validator.php';

final class PatientsController {
  private PDO $pdo;
  public function __construct(PDO $pdo) { $this->pdo = $pdo; }

  public function handle(): void {
    $action = $_GET['a'] ?? 'index';
    switch ($action) {
      case 'create':     $this->create();     break;
      case 'store':      $this->store();      break;
      case 'edit':       $this->edit();       break;
      case 'update':     $this->update();     break;
      case 'delete':     $this->delete();     break;
      case 'fetch':      $this->fetch();      break; // AJAX
      case 'cards_list': $this->cardsList();  break;
      case 'card_view':  $this->cardView();   break;
      case 'card_csv':   $this->cardCsv();    break;
      default:           $this->index();      break;
    }
  }

    /** Build filters/sort/pagination */
  private function buildFilters(): array {
    // Παίρνουμε τις τιμές είτε από GET είτε από POST (ώστε να δουλεύει και AJAX με POST)
    $qRaw   = $_GET['q']   ?? $_POST['q']   ?? '';
    $pageRaw= $_GET['page']?? $_POST['page']?? 1;
    $sortRaw= $_GET['sort']?? $_POST['sort']?? 'last_visit';
    $dirRaw = $_GET['dir'] ?? $_POST['dir'] ?? 'desc';

    $q    = trim((string)$qRaw);
    $page = (int)$pageRaw;
    if ($page < 1) { $page = 1; }

    $limit = 20;
    $off   = ($page - 1) * $limit;

    $sort = (string)$sortRaw;
    $dir  = strtolower((string)$dirRaw);
    $dir  = ($dir === 'asc') ? 'ASC' : 'DESC';

    $map = [
      'id'         => 'p.id',
      'name'       => 'p.name',
      'phone'      => 'p.phone',
      'email'      => 'p.email',
      'birthdate'  => 'p.birthdate',
      'last_visit' => 'lv.last_visit',
      'gdpr'       => 'gdpr_yes',
    ];
    $order    = $map[$sort] ?? 'lv.last_visit';
    $orderSql = $order . ' ' . $dir . ', p.id DESC';

    $where  = '1=1';
    $params = [];

    if ($q !== '') {
      $where .= ' AND (p.name LIKE :q_name OR p.phone LIKE :q_phone OR p.email LIKE :q_email)';
      $like = '%' . $q . '%';
      $params[':q_name']  = $like;
      $params[':q_phone'] = $like;
      $params[':q_email'] = $like;
    }

    return [$q, $page, $limit, $off, $orderSql, $params, $sort, $dir, $where];
  }

  /** Βασικό query για μητρώο (με GDPR, με fallbacks) */
  private function baseQuery(string $where, array $params, string $order, int $off, int $limit): array {
    // Προσπαθούμε πρώτα να χρησιμοποιήσουμε τον πίνακα consent (νέα GDPR λογική).
    try {
      $sql = "SELECT SQL_CALC_FOUND_ROWS
                p.id,
                p.name,
                p.phone,
                p.email,
                DATE_FORMAT(p.birthdate, '%d/%m/%Y') AS birthdate,
                DATE_FORMAT(lv.last_visit, '%d/%m/%Y %H:%i') AS last_visit,
                IF(EXISTS(SELECT 1 FROM consent c WHERE c.patient_id = p.id AND c.consent = 1), 1, 0) AS gdpr_yes
              FROM patient p
              LEFT JOIN (
                SELECT patient AS patient_id, MAX(date) AS last_visit
                FROM payment
                GROUP BY patient
              ) lv ON lv.patient_id = p.id
              WHERE $where
              ORDER BY $order
              LIMIT :off, :lim";
      $stmt = $this->pdo->prepare($sql);
      foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
      }
      $stmt->bindValue(':off', $off, PDO::PARAM_INT);
      $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
      $stmt->execute();
      $rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $total = (int)$this->pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
      return [$rows, $total];
    } catch (\Throwable $e) {
      // Αν αποτύχει (π.χ. δεν υπάρχει ο πίνακας consent), γράφουμε log και δοκιμάζουμε fallback.
      error_log('Patients baseQuery (consent) error: ' . $e->getMessage());
    }

    // Fallback 1: προσπάθεια να χρησιμοποιήσουμε πεδίο p.gdpr, αν υπάρχει.
    try {
      $sql = "SELECT SQL_CALC_FOUND_ROWS
                p.id,
                p.name,
                p.phone,
                p.email,
                DATE_FORMAT(p.birthdate, '%d/%m/%Y') AS birthdate,
                DATE_FORMAT(lv.last_visit, '%d/%m/%Y %H:%i') AS last_visit,
                COALESCE(p.gdpr, 0) AS gdpr_yes
              FROM patient p
              LEFT JOIN (
                SELECT patient AS patient_id, MAX(date) AS last_visit
                FROM payment
                GROUP BY patient
              ) lv ON lv.patient_id = p.id
              WHERE $where
              ORDER BY $order
              LIMIT :off, :lim";
      $stmt = $this->pdo->prepare($sql);
      foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
      }
      $stmt->bindValue(':off', $off, PDO::PARAM_INT);
      $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
      $stmt->execute();
      $rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $total = (int)$this->pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
      return [$rows, $total];
    } catch (\Throwable $e2) {
      error_log('Patients baseQuery (patient.gdpr fallback) error: ' . $e2->getMessage());
    }

    // Fallback 2: αν όλα αποτύχουν, δεν βάζουμε καθόλου GDPR πληροφορία.
    try {
      $sql = "SELECT SQL_CALC_FOUND_ROWS
                p.id,
                p.name,
                p.phone,
                p.email,
                DATE_FORMAT(p.birthdate, '%d/%m/%Y') AS birthdate,
                DATE_FORMAT(lv.last_visit, '%d/%m/%Y %H:%i') AS last_visit,
                0 AS gdpr_yes
              FROM patient p
              LEFT JOIN (
                SELECT patient AS patient_id, MAX(date) AS last_visit
                FROM payment
                GROUP BY patient
              ) lv ON lv.patient_id = p.id
              WHERE $where
              ORDER BY $order
              LIMIT :off, :lim";
      $stmt = $this->pdo->prepare($sql);
      foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
      }
      $stmt->bindValue(':off', $off, PDO::PARAM_INT);
      $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
      $stmt->execute();
      $rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $total = (int)$this->pdo->query("SELECT FOUND_ROWS()")->fetchColumn();
      return [$rows, $total];
    } catch (\Throwable $e3) {
      error_log('Patients baseQuery (no-gdpr final fallback) error: ' . $e3->getMessage());
      return [[], 0];
    }
  }

  /** Page: index */
  private function index(): void {
    [$q, $page, $limit, $off, $orderSql, $params, $sort, $dir, $where] = $this->buildFilters();
    [$rows, $total] = $this->baseQuery($where, $params, $orderSql, $off, $limit);
    $pages = max(1, (int)ceil($total / $limit));
    $csrf  = Csrf::token();
    include __DIR__ . '/../Views/patients/index.php';
  }

  /** AJAX: return tbody + pagination HTML */
  private function fetch(): void {
    [$q, $page, $limit, $off, $orderSql, $params, $sort, $dir, $where] = $this->buildFilters();
    [$rows, $total] = $this->baseQuery($where, $params, $orderSql, $off, $limit);
    $pages = max(1, (int)ceil($total / $limit));
    $csrf  = Csrf::token();

    ob_start();
    include __DIR__ . '/../Views/patients/_table_rows.php';
    $tbody = ob_get_clean();

    ob_start();
    include __DIR__ . '/../Views/patients/_pagination.php';
    $pagination = ob_get_clean();

    if (ob_get_level()) { ob_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode(['tbody' => $tbody, 'pagination' => $pagination], JSON_UNESCAPED_UNICODE);
    exit;
  }

  /** Forms */
  private function create(): void {
    $csrf   = Csrf::token();
    $errors = [];
    $old    = [];
    include __DIR__ . '/../Views/patients/create.php';
  }

  private function store(): void {
    if (!Csrf::check($_POST['csrf'] ?? null)) { $this->forbidden(); }

    $v         = new Validator();
    $name      = trim($_POST['name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    $v->required('name', $name, 'Ονοματεπώνυμο');
    $v->email('email', $email, 'Email');
    if ($birthdate !== '') { $v->date('birthdate', $birthdate, 'Ημερ. γέννησης'); }

    if (!$v->ok()) {
      $errors = $v->errors;
      $old    = compact('name','phone','email','birthdate','address');
      $csrf   = Csrf::token();
      include __DIR__ . '/../Views/patients/create.php';
      return;
    }

    $sql = "INSERT INTO patient (name, phone, email, birthdate, address, add_date)
            VALUES (:name, :phone, :email, NULLIF(:birthdate,''), :address, NOW())";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':name'      => $name,
      ':phone'     => $phone,
      ':email'     => $email,
      ':birthdate' => $birthdate,
      ':address'   => $address,
    ]);

    header('Location: ?r=patients'); exit;
  }

  private function edit(): void {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { $this->notFound(); }

    $stmt = $this->pdo->prepare("SELECT * FROM patient WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) { $this->notFound(); }

    $csrf   = Csrf::token();
    $errors = [];
    $old    = $patient;
    include __DIR__ . '/../Views/patients/edit.php';
  }

  private function editWithErrors(int $id, array $errors): void {
    $stmt = $this->pdo->prepare("SELECT * FROM patient WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) { $this->notFound(); }

    $csrf = Csrf::token();
    $old  = array_merge($patient, $_POST);
    include __DIR__ . '/../Views/patients/edit.php';
  }

  private function update(): void {
    if (!Csrf::check($_POST['csrf'] ?? null)) { $this->forbidden(); }
    $id = (int)($_POST['id'] ?? 0);

    $v         = new Validator();
    $name      = trim($_POST['name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $address   = trim($_POST['address'] ?? '');

    $v->required('name', $name, 'Ονοματεπώνυμο');
    $v->email('email', $email, 'Email');
    if ($birthdate !== '') { $v->date('birthdate', $birthdate, 'Ημερ. γέννησης'); }

    if (!$v->ok()) { $this->editWithErrors($id, $v->errors); return; }

    $sql = "UPDATE patient
            SET name = :name, phone = :phone, email = :email,
                birthdate = NULLIF(:birthdate,''), address = :address
            WHERE id = :id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':name'      => $name,
      ':phone'     => $phone,
      ':email'     => $email,
      ':birthdate' => $birthdate,
      ':address'   => $address,
      ':id'        => $id,
    ]);

    header('Location: ?r=patients'); exit;
  }

  private function delete(): void {
    if (!Csrf::check($_POST['csrf'] ?? null)) { $this->forbidden(); }
    $id = (int)($_POST['id'] ?? 0);

    try {
      $stmt = $this->pdo->prepare("DELETE FROM patient WHERE id = :id");
      $stmt->execute([':id' => $id]);
      $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Ο πελάτης διαγράφηκε.'];
    } catch (\Throwable $e) {
      $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Δεν μπορεί να διαγραφεί (υπάρχουν συνδεδεμένες εγγραφές).'];
    }
    header('Location: ?r=patients'); exit;
  }

  private function cardsList(): void {
    [$q, $page, $limit, $off, $orderSql, $params, $sort, $dir, $where] = $this->buildFilters();
    [$rows, $total] = $this->baseQuery($where, $params, $orderSql, $off, $limit);
    $pages = max(1, (int)ceil($total / $limit));
    include __DIR__ . '/../Views/patients/cards_list.php';
  }

  private function cardView(): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
      $this->notFound();
      return;
    }

    // 1. Φόρτωση ασθενούς + GDPR
    try {
      $sql = "
        SELECT
          p.*,
          IF(EXISTS(
            SELECT 1 FROM consent c
            WHERE c.patient_id = p.id AND c.consent = 1
          ), 1, 0) AS gdpr_yes
        FROM patient p
        WHERE p.id = :id
      ";
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute([':id' => $id]);
      $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
      // Αν για κάποιο λόγο δεν παίζει ο consent πίνακας, δοκιμάζουμε πεδίο gdpr στον patient
      error_log('cardView patient/consent error: '.$e->getMessage());
      try {
        $stmt = $this->pdo->prepare("SELECT p.*, COALESCE(p.gdpr, 0) AS gdpr_yes FROM patient p WHERE p.id = :id");
        $stmt->execute([':id' => $id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
      } catch (\Throwable $e2) {
        // Τελικό fallback: χωρίς καθόλου πεδίο gdpr, θεωρούμε ΟΧΙ
        error_log('cardView patient gdpr fallback error: '.$e2->getMessage());
        $stmt = $this->pdo->prepare("SELECT * FROM patient WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($patient) {
          $patient['gdpr_yes'] = 0;
        }
      }
    }

    if (!$patient) {
      $this->notFound();
      return;
    }

    // 2. Σύνοψη οικονομικών (τρέχουσα λογική receipts first, αλλιώς eispraxi)
    $summary = [
      'charges'       => 0.0,
      'payments'      => 0.0,
      'balance'       => 0.0,
      'last_charge'   => null,
      'last_receipt'  => null,
      'last_movement' => null,
    ];

    try {
      $summarySql = <<<SQL
SELECT
  COALESCE(ch.charges, 0) AS charges,
  (COALESCE(rc.paid, 0) + COALESCE(fb.paid_fallback, 0)) AS payments,
  (COALESCE(ch.charges, 0) - (COALESCE(rc.paid, 0) + COALESCE(fb.paid_fallback, 0))) AS balance,
  ch.last_charge,
  rc.last_receipt,
  CASE
    WHEN ch.last_charge IS NULL AND rc.last_receipt IS NULL THEN NULL
    WHEN ch.last_charge IS NULL THEN rc.last_receipt
    WHEN rc.last_receipt IS NULL THEN ch.last_charge
    ELSE GREATEST(ch.last_charge, rc.last_receipt)
  END AS last_movement
FROM (
  SELECT
    SUM(COALESCE(p.gross_total,0)) AS charges,
    MAX(p.`date`) AS last_charge
  FROM payment p
  WHERE p.patient = :pid
) AS ch
LEFT JOIN (
  SELECT
    SUM(COALESCE(ra.amount_applied,0)) AS paid,
    MAX(r.received_at) AS last_receipt
  FROM receipt r
  JOIN receipt_allocation ra ON ra.receipt_id = r.id
  JOIN payment p ON p.id = ra.charge_id
  WHERE p.patient = :pid
) AS rc ON 1=1
LEFT JOIN (
  SELECT
    SUM(COALESCE(p.eispraxi,0)) AS paid_fallback
  FROM payment p
  LEFT JOIN receipt_allocation ra ON ra.charge_id = p.id
  WHERE p.patient = :pid
    AND ra.charge_id IS NULL
) AS fb ON 1=1
SQL;
      $st = $this->pdo->prepare($summarySql);
      $st->execute([':pid' => $id]);
      $row = $st->fetch(PDO::FETCH_ASSOC);
      if ($row) {
        $summary['charges']       = (float)($row['charges'] ?? 0);
        $summary['payments']      = (float)($row['payments'] ?? 0);
        $summary['balance']       = (float)($row['balance'] ?? 0);
        $summary['last_charge']   = $row['last_charge']   ?? null;
        $summary['last_receipt']  = $row['last_receipt']  ?? null;
        $summary['last_movement'] = $row['last_movement'] ?? null;
      }
    } catch (\Throwable $e) {
      error_log('cardView summary hybrid error: '.$e->getMessage());
      try {
        $fallbackSql = "
          SELECT
            SUM(COALESCE(gross_total,0)) AS charges,
            SUM(COALESCE(eispraxi,0))    AS payments,
            MAX(`date`)                  AS last_movement
          FROM payment
          WHERE patient = :pid
        ";
        $st = $this->pdo->prepare($fallbackSql);
        $st->execute([':pid' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
          $charges  = (float)($row['charges'] ?? 0);
          $payments = (float)($row['payments'] ?? 0);
          $summary['charges']       = $charges;
          $summary['payments']      = $payments;
          $summary['balance']       = $charges - $payments;
          $summary['last_movement'] = $row['last_movement'] ?? null;
        }
      } catch (\Throwable $e2) {
        error_log('cardView summary fallback error: '.$e2->getMessage());
      }
    }

    // 3. Ιστορικό επισκέψεων
    $visits = [];
    try {
      $sql = <<<SQL
SELECT
  p.id,
  p.`date`,
  p.category_name,
  p.gross_total,
  p.status,
  d.name AS doctor_name
FROM payment p
LEFT JOIN doctor d ON d.id = p.doctor_id
WHERE p.patient = :pid
ORDER BY p.`date` DESC, p.id DESC
SQL;
      $st = $this->pdo->prepare($sql);
      $st->execute([':pid' => $id]);
      $visits = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
      error_log('cardView visits error: '.$e->getMessage());
      $visits = [];
    }

    $gdprBase = getenv('GDPR_BASE_URL') ?: '/gdpr/index.php';
    $sep      = (strpos($gdprBase, '?') === false) ? '?' : '&';
    $gdprUrl  = $gdprBase . $sep . 'patient_id=' . (int)$patient['id'];

    $view_patient = $patient;
    $view_summary = $summary;
    $view_visits  = $visits;
    $view_gdprUrl = $gdprUrl;

    include __DIR__ . '/../Views/patients/card_view.php';
  }

  function cardCsv(): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
      $this->notFound();
      return;
    }

    // Φόρτωση πελάτη
    $stmt = $this->pdo->prepare("SELECT * FROM patient WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$patient) {
      $this->notFound();
      return;
    }

    // Επισκέψεις
    try {
      $sql = <<<SQL
SELECT
  p.`date`,
  p.category_name,
  d.name AS doctor_name,
  p.gross_total,
  p.status
FROM payment p
LEFT JOIN doctor d ON d.id = p.doctor_id
WHERE p.patient = :pid
ORDER BY p.`date` DESC, p.id DESC
SQL;
      $st = $this->pdo->prepare($sql);
      $st->execute([':pid' => $id]);
      $visits = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (\Throwable $e) {
      error_log('cardCsv visits error: '.$e->getMessage());
      try {
        $sql2 = "
          SELECT
            `date`,
            category_name,
            NULL AS doctor_name,
            gross_total,
            status
          FROM payment
          WHERE patient = :pid
          ORDER BY `date` DESC, id DESC
        ";
        $st = $this->pdo->prepare($sql2);
        $st->execute([':pid' => $id]);
        $visits = $st->fetchAll(PDO::FETCH_ASSOC);
      } catch (\Throwable $e2) {
        error_log('cardCsv visits fallback error: '.$e2->getMessage());
        $visits = [];
      }
    }

    // Έξοδος CSV
    $filename = 'patient_' . $id . '_visits_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.$filename.'"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    fputcsv($out, ['ID', $patient['id']], ';');
    fputcsv($out, ['Όνομα', $patient['name']], ';');
    fputcsv($out, ['Τηλέφωνο', $patient['phone']], ';');
    fputcsv($out, [], ';');

    fputcsv($out, ['Ημ/νία', 'Υπηρεσία', 'Γιατρός', 'Ποσό', 'Status'], ';');

    foreach ($visits as $v) {
      $amount = number_format((float)($v['gross_total'] ?? 0), 2, ',', '.');
      fputcsv($out, [
        (string)($v['date'] ?? ''),
        (string)($v['category_name'] ?? ''),
        (string)($v['doctor_name'] ?? ''),
        (string)$amount,
        (string)($v['status'] ?? ''),
      ], ';');
    }

    fclose($out);
    exit;
  }

  private function notFound(): void { http_response_code(404); exit('Not found'); }
  private function forbidden(): void { http_response_code(403); exit('Forbidden'); }
}

