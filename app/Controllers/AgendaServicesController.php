<?php
require_once __DIR__ . '/../Lib/Csrf.php';

final class AgendaController {
  private PDO $pdo;
  public function __construct(PDO $pdo) { $this->pdo = $pdo; }

  public function handle(): void {
    $a = $_GET['a'] ?? 'index';
    switch ($a) {
      case 'search_services': $this->searchServices(); break;
      default: http_response_code(400); echo 'Bad request'; break;
    }
  }

  private function searchServices(): void {
    $q = trim($_GET['q'] ?? '');
    $rows = [];
    if ($q === '') { header('Content-Type: application/json; charset=utf-8'); echo json_encode($rows); return; }

    // Υπάρχει payment_category;
    $tblExists = $this->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='payment_category'")->fetchColumn();

    if ($tblExists) {
      // Έλεγχος στηλών για name/price
      $cols = $this->pdo->query("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='payment_category'")->fetchAll(PDO::FETCH_COLUMN);
      // Προτεραιότητα: description, c_price
      $labelCol = in_array('description', $cols, true) ? 'description' : (in_array('name',$cols,true)?'name':(in_array('category_name',$cols,true)?'category_name':(in_array('category',$cols,true)?'category':'title')));
      $priceCol = in_array('c_price', $cols, true) ? 'c_price' : (in_array('price',$cols,true)?'price':null);

      $sql = "SELECT id, {$labelCol} AS name".($priceCol? ", {$priceCol} AS price":" , 0 AS price")." 
              FROM payment_category 
              WHERE {$labelCol} LIKE :q 
              ORDER BY {$labelCol} 
              LIMIT 30";
      $st = $this->pdo->prepare($sql); 
      $st->execute([':q'=>'%'.$q.'%']);
      while($r=$st->fetch(PDO::FETCH_ASSOC)){
        $rows[] = ['id'=>(int)$r['id'], 'name'=>$r['name'], 'price'=>(float)$r['price']];
      }
    }

    // Fallback από payment.category_name (χωρίς τιμή)
    if (empty($rows)) {
      $st = $this->pdo->prepare("SELECT DISTINCT category_name AS name FROM payment WHERE category_name IS NOT NULL AND category_name <> '' AND category_name LIKE :q ORDER BY name LIMIT 30");
      $st->execute([':q'=>'%'.$q.'%']);
      while($r=$st->fetch(PDO::FETCH_ASSOC)){
        $rows[] = ['id'=>null, 'name'=>$r['name'], 'price'=>0.0];
      }
    }

    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json; charset=utf-8'); 
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
    exit;
  }
}
