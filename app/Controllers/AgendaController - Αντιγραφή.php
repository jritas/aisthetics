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

  /** Robust patient balance using correlated subselect (no GROUP/LEFT JOIN pitfalls) */
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
    $q = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 20;
    $off = ($page-1) * $limit;
    $sort = $_GET['sort'] ?? 'last_visit';
    $dir  = strtolower($_GET['dir'] ?? 'desc'); $dir = $dir==='asc'?'ASC':'DESC';
    $map = ['id'=>'p.id','name'=>'p.name','phone'=>'p.phone','last_visit'=>'lv.last_visit','visits'=>'lv.visits'];
    $order = $map[$sort] ?? 'lv.last_visit';
    $orderSql = $order.' '.$dir.', p.id DESC';
    $where = '1=1'; $params=[];
    if ($q!=='') { $like='%'.$q.'%'; $where.=" AND (p.name LIKE :q OR p.phone LIKE :q OR p.email LIKE :q)"; $params[':q']=$like; }
    return [$q,$page,$limit,$off,$orderSql,$params,$sort,$dir,$where];
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
    $id=(int)($_GET['id']??0);
    $stmt=$this->pdo->prepare("SELECT * FROM patient WHERE id=:id"); $stmt->execute([':id'=>$id]);
    $patient=$stmt->fetch(\PDO::FETCH_ASSOC); if(!$patient){http_response_code(404); exit('Not found');}

    // Προληπτικό refresh για ΟΛΕΣ τις χρεώσεις του ασθενή (κρατάμε το UI συνεπές)
    $ids=$this->pdo->prepare("SELECT id FROM payment WHERE patient=:id");
    $ids->execute([':id'=>$id]);
    $toRefresh=array_map('intval',$ids->fetchAll(PDO::FETCH_COLUMN));
    if ($toRefresh) $this->refreshCharges($toRefresh);

    $hist=$this->pdo->prepare("SELECT id, DATE_FORMAT(date, '%d/%m/%Y %H:%i') AS dt, category_name, gross_total, doctor_id, treatment, medicine, status FROM payment WHERE patient=:id ORDER BY date DESC LIMIT 20");
    $hist->execute([':id'=>$id]); $history=$hist->fetchAll(\PDO::FETCH_ASSOC);

    $patientBalance = $this->patientBalance($id);

    $docs = $this->pdo->query("SELECT id, name FROM doctor ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
    $lastDocStmt = $this->pdo->prepare("SELECT doctor_id FROM payment WHERE patient=:id AND doctor_id IS NOT NULL ORDER BY date DESC LIMIT 1");
    $lastDocStmt->execute([':id'=>$id]);
    $lastDoctorId=(int)($lastDocStmt->fetchColumn()?:0);

    $csrf=Csrf::token();
    include __DIR__.'/../Views/agenda/checkin.php';
  }

  /* ---------- Services ---------- */
  private function searchServices(): void {
    $q=trim($_GET['q']??''); $rows=[];
    if($q===''){ header('Content-Type: application/json; charset=utf-8'); echo json_encode($rows); return; }

    $tblExists = $this->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='payment_category'")->fetchColumn();
    if ($tblExists) {
      $cols = $this->pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='payment_category'")->fetchAll(\PDO::FETCH_COLUMN);
      $labelCol = in_array('description',$cols,true) ? 'description' :
                  (in_array('name',$cols,true) ? 'name' :
                  (in_array('category_name',$cols,true) ? 'category_name' :
                  (in_array('category',$cols,true) ? 'category' : 'title')));
      $priceCol = in_array('c_price',$cols,true) ? 'c_price' : (in_array('price',$cols,true) ? 'price' : null);

      $sql = <<<SQL
SELECT id, {$labelCol} AS name, COALESCE({$priceCol},0) AS price
FROM payment_category
WHERE {$labelCol} LIKE :q
ORDER BY {$labelCol}
LIMIT 50
SQL;
      $st = $this->pdo->prepare($sql); $st->execute([':q'=>'%'.$q.'%']);
      while($r=$st->fetch(\PDO::FETCH_ASSOC)){
        $rows[]=['id'=>(int)$r['id'],'name'=>$r['name'],'price'=>(float)$r['price']];
      }
    }
    if (empty($rows)) {
      $st = $this->pdo->prepare("SELECT DISTINCT category_name AS name FROM payment WHERE category_name IS NOT NULL AND category_name <> '' AND category_name LIKE :q ORDER BY name LIMIT 50");
      $st->execute([':q'=>'%'.$q.'%']);
      while($r=$st->fetch(\PDO::FETCH_ASSOC)){
        $rows[]=['id'=>null,'name'=>$r['name'],'price'=>0.0];
      }
    }
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json; charset=utf-8'); echo json_encode($rows, JSON_UNESCAPED_UNICODE); exit;
  }

  /* ---------- Save visit ---------- */
  private function saveVisit(): void {
    if (!Csrf::check($_POST['csrf'] ?? null)) { http_response_code(403); exit('Forbidden'); }

    $pid   = (int)($_POST['patient_id'] ?? 0);
    $docId = isset($_POST['doctor_id']) && $_POST['doctor_id']!=='' ? (int)$_POST['doctor_id'] : null;
    $lines = $_POST['lines'] ?? [];
    $discount  = (float)($_POST['discount'] ?? 0);
    $received  = (float)($_POST['received'] ?? 0);
    $treatment = trim($_POST['treatment'] ?? '');
    $medicine  = trim($_POST['medicine'] ?? '');
    $onlyCollect = isset($_POST['collection_only']) && $_POST['collection_only']=='1';

    if (!$pid) { $_SESSION['flash']=['type'=>'warning','msg'=>'Δεν βρέθηκε ασθενής.']; header('Location:?r=agenda'); exit; }

    $this->pdo->beginTransaction();
    try {
      $newCharges = [];

      if (!$onlyCollect) {
        $clean = [];
        foreach ($lines as $ln) {
          $name = trim($ln['name'] ?? '');
          $amount = (float)($ln['amount'] ?? 0);
          if ($name !== '' && $amount > 0) { $clean[] = ['name'=>$name, 'amount'=>$amount]; }
        }
        if (empty($clean)) {
          $_SESSION['flash']=['type'=>'warning','msg'=>'Πρόσθεσε τουλάχιστον μία θεραπεία ή επέλεξε "Είσπραξη οφειλής".'];
          $this->pdo->rollBack(); header('Location:?r=agenda&a=checkin&id='.$pid); return;
        }

        $total = 0.0; foreach ($clean as $c) { $total += $c['amount']; }
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
        $rIns = $this->pdo->prepare("INSERT INTO receipt (patient_id, amount, method, note, received_at) VALUES (:p,:a,NULL,NULL,NOW())");
        $rIns->execute([':p'=>$pid, ':a'=>$received]);
        $receiptId = (int)$this->pdo->lastInsertId();
        $affected = $this->allocateReceipt($receiptId, $pid, $received, $newCharges);
      }

      $refreshIds = array_values(array_unique(array_merge($newCharges, $affected)));
      if (!empty($refreshIds)) { $this->refreshCharges($refreshIds); }

      $this->pdo->commit();
      $_SESSION['flash']=['type'=>'success','msg'=>'Η επίσκεψη καταχωρήθηκε.'];
      header('Location:?r=agenda&a=checkin&id='.$pid); exit;

    } catch (Throwable $e) {
      $this->pdo->rollBack();
      $_SESSION['flash']=['type'=>'danger','msg'=>'Σφάλμα: '.$e->getMessage()];
      header('Location:?r=agenda&a=checkin&id='.$pid); exit;
    }
  }

  private function allocateReceipt(int $receiptId, int $patientId, float $amount, array $preferIds = []): array {
    $allocIns = $this->pdo->prepare("INSERT INTO receipt_allocation (receipt_id, charge_id, amount_applied) VALUES (:r,:c,:amt)");
    $affected = [];
    $to = round($amount, 2);

    $dueExpr = "(p.gross_total - GREATEST(COALESCE((SELECT SUM(ra.amount_applied) FROM receipt_allocation ra WHERE ra.charge_id=p.id),0), COALESCE(NULLIF(p.eispraxi,0),0), COALESCE(NULLIF(p.amount_received,0),0)))";

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
      $stmt->execute($preferIds);
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
      $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
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
}
