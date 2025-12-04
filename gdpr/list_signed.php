<?php
/*************************************************
 * list_signed.php — Λίστα ασθενών με GDPR = ΝΑΙ
 * ...
 **************************************************/

// Χρήση της κεντρικής σύνδεσης της εφαρμογής
require_once __DIR__ . '/../app/db.php';
$pdo = pdo();

/* ===== AJAX: λίστα υπογεγραμμένων =====
   GET: action=list&q=&page=&from=&to=
   Επιστρέφει: rows, meta, signed_total (γενικό σύνολο ΝΑΙ)
*/
if(($_GET['action']??'')==='list'){
  $q    = trim($_GET['q']??'');
  $page = max(1,(int)($_GET['page']??1));
  $per  = 20;

  $from = trim($_GET['from']??''); // YYYY-MM-DD
  $to   = trim($_GET['to']??'');   // YYYY-MM-DD

  // GDPR = ΝΑΙ (δέχομαι επίσης YES/NAI, case-insensitive, αγνοώ κενά)
  $gdprWhere = "REPLACE(UPPER(TRIM(p.gdpr)),' ','') IN ('ΝΑΙ','YES','NAI')";

  // Γενικό σύνολο (χωρίς search/date φίλτρα)
  $signed_total = (int)$pdo->query("SELECT COUNT(*) FROM patient p WHERE $gdprWhere")->fetchColumn();

  // WHERE (με search + date range)
  $w = ["($gdprWhere)"];
  $p = [];

  if($q!==''){
    $p[':qname'] = "%{$q}%";
    $w[] = "(CONVERT(p.name USING utf8) COLLATE utf8_general_ci LIKE :qname)";
    $digits = preg_replace('/\D+/','',$q);
    if($digits!==''){
      $p[':qphone'] = "%{$digits}%";
      $w[] = "(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(p.phone,'+',''),' ',''),'-',''),'.',''),'/','') LIKE :qphone)";
    }
  }

  // Φίλτρο ημερομηνιών πάνω στο add_date (varchar → datetime)
  // inclusive: [from 00:00:00, to 23:59:59]
  if($from!==''){
    $p[':from'] = "$from 00:00:00";
    $w[] = "(STR_TO_DATE(p.add_date, '%Y-%m-%d %H:%i:%s') >= :from)";
  }
  if($to!==''){
    $p[':to'] = "$to 23:59:59";
    $w[] = "(STR_TO_DATE(p.add_date, '%Y-%m-%d %H:%i:%s') <= :to)";
  }

  $where = "WHERE ".implode(" AND ", $w);

  // Σύνολο τρέχοντος φίλτρου
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM patient p $where");
  $stmt->execute($p);
  $total = (int)$stmt->fetchColumn();

  $pages  = max(1,(int)ceil($total/$per));
  $page   = min($page, $pages);
  $offset = ($page-1)*$per;

  // default ταξινόμηση: πιο πρόσφατο add_date πρώτο
  $orderExpr = "STR_TO_DATE(p.add_date, '%Y-%m-%d %H:%i:%s') DESC, p.id DESC";

  // Δεδομένα σελίδας
  $sql = "
    SELECT p.id, p.name, p.phone, p.add_date, p.gdpr
    FROM patient p
    $where
    ORDER BY $orderExpr
    LIMIT :off, :per
  ";
  $stmt = $pdo->prepare($sql);
  foreach($p as $k=>$v) $stmt->bindValue($k,$v);
  $stmt->bindValue(':off',(int)$offset,PDO::PARAM_INT);
  $stmt->bindValue(':per',(int)$per,PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll();

  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode([
    'ok'=>true,
    'rows'=>$rows,
    'meta'=>[
      'page'=>$page,'pages'=>$pages,'per_page'=>$per,'total'=>$total,'offset'=>$offset
    ],
    'signed_total'=>$signed_total
  ], JSON_UNESCAPED_UNICODE);
  exit;
}
?>
<!doctype html>
<html lang="el">
<head>
<meta charset="utf-8">
<title>Λίστα GDPR (ΝΑΙ)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  /* dark theme  */
  body { background:#0f172a; color:#e5e7eb; }
  .app-card { background:#0b1220; border:1px solid #21314d; }
  .muted { color:#94a3b8; }
  .summary-card { background:#0b1220; border:1px solid #2b3b5a; }
  .summary-number { font-size:2rem; font-weight:700; line-height:1; }
  .table thead th { white-space:nowrap; user-select:none; }
</style>
</head>
<body>
<div class="container-lg py-3">

  <!-- Επάνω μπάρα με αναζήτηση & φίλτρα -->
  <div class="app-card rounded-3 p-3 mb-3">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-lg-5">
        <label class="form-label">Αναζήτηση Ασθενή</label>
        <input id="search" class="form-control form-control-lg bg-dark text-light" placeholder="Όνομα ή τηλέφωνο…">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Από</label>
        <input id="from" type="date" class="form-control bg-dark text-light">
      </div>
      <div class="col-6 col-lg-2">
        <label class="form-label">Έως</label>
        <input id="to" type="date" class="form-control bg-dark text-light">
      </div>
      <div class="col-12 col-lg-3 text-lg-end">
        <button id="clearFilters" class="btn btn-outline-secondary mt-3 mt-lg-0">Καθαρισμός</button>
      </div>
    </div>
  </div>

  <!-- Κάρτα Γενικού Συνόλου -->
  <div class="summary-card rounded-3 p-3 mb-3 d-flex align-items-center justify-content-between">
    <div>
      <div class="muted">Γενικό σύνολο υπογεγραμμένων GDPR (ΝΑΙ)</div>
      <div class="summary-number" id="signedTotal">0</div>
    </div>
    <div class="muted text-end">
      Εμφανίζονται μόνο ασθενείς με GDPR: <strong>ΝΑΙ</strong>
    </div>
  </div>

  <!-- Πίνακας -->
  <div class="app-card rounded-3">
    <div class="table-responsive">
      <table class="table table-dark table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:70px">#</th>
            <th style="width:90px">ID</th>
            <th>Ονοματεπώνυμο</th>
            <th style="width:180px">Τηλέφωνο</th>
            <th style="width:200px">Ημερ/νία εισαγωγής</th>
            <th style="width:100px">GDPR</th>
          </tr>
        </thead>
        <tbody id="rows">
          <tr><td colspan="6" class="text-center py-4">Φόρτωση…</td></tr>
        </tbody>
      </table>
    </div>

    <div class="p-2 d-flex align-items-center gap-3">
      <div>
        Αποτελέσματα: <span id="metaTotal">0</span>
        • Σελίδα <span id="metaPage">1</span>/<span id="metaPages">1</span>
      </div>
      <div class="ms-auto btn-group">
        <button class="btn btn-outline-secondary btn-sm" id="prevBtn">Πίσω</button>
        <button class="btn btn-outline-secondary btn-sm" id="nextBtn">Μπροστά</button>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  const state={ q:'', page:1, rows:[], total:0, pages:1, offset:0, signed_total:0, from:'', to:'' };

  const $rows=document.getElementById('rows');
  const $search=document.getElementById('search');
  const $from=document.getElementById('from');
  const $to=document.getElementById('to');
  const $clear=document.getElementById('clearFilters');
  const $metaPage=document.getElementById('metaPage');
  const $metaPages=document.getElementById('metaPages');
  const $metaTotal=document.getElementById('metaTotal');
  const $prev=document.getElementById('prevBtn');
  const $next=document.getElementById('nextBtn');
  const $signedTotal=document.getElementById('signedTotal');

  function esc(s){ return (s??'').toString()
    .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
    .replaceAll('"','&quot;').replaceAll("'","&#39;"); }
  function debounce(fn,ms=300){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),ms); }; }

  async function fetchData(){
    const params = new URLSearchParams({action:'list', q:state.q, page:state.page});
    if(state.from) params.set('from', state.from);
    if(state.to)   params.set('to', state.to);

    const d = await fetch('?'+params.toString()).then(r=>r.json()).catch(()=>({ok:false}));
    if(d && d.ok){
      state.rows=d.rows||[]; state.total=d.meta?.total||0; state.pages=d.meta?.pages||1; state.offset=d.meta?.offset||0;
      state.signed_total = d.signed_total || 0;
      render();
    } else {
      $rows.innerHTML = `<tr><td colspan="6" class="text-center py-4">Σφάλμα φόρτωσης</td></tr>`;
    }
  }

  function render(){
    if(state.rows.length===0){
      $rows.innerHTML = `<tr><td colspan="6" class="text-center py-4">Δεν βρέθηκαν αποτελέσματα</td></tr>`;
    } else {
      $rows.innerHTML = state.rows.map((r,i)=>`
        <tr>
          <td>${state.offset + i + 1}</td>
          <td>${esc(r.id)}</td>
          <td>${esc(r.name||'')}</td>
          <td>${esc(r.phone||'')}</td>
          <td>${esc(r.add_date||'')}</td>
          <td>${esc(r.gdpr||'')}</td>
        </tr>
      `).join('');
    }
    $metaPage.textContent = state.page;
    $metaPages.textContent = state.pages;
    $metaTotal.textContent = state.total;
    $signedTotal.textContent = state.signed_total;

    $prev.disabled = (state.page<=1);
    $next.disabled = (state.page>=state.pages);
  }

  // events
  $search.addEventListener('input', debounce(()=>{ state.q=$search.value.trim(); state.page=1; fetchData(); }, 300));
  $from.addEventListener('change', ()=>{ state.from=$from.value; state.page=1; fetchData(); });
  $to.addEventListener('change',   ()=>{ state.to=$to.value;   state.page=1; fetchData(); });
  $clear.addEventListener('click', ()=>{
    $search.value=''; $from.value=''; $to.value='';
    state.q=''; state.from=''; state.to=''; state.page=1; fetchData();
  });
  $prev.addEventListener('click', ()=>{ if(state.page>1){ state.page--; fetchData(); }});
  $next.addEventListener('click', ()=>{ if(state.page<state.pages){ state.page++; fetchData(); }});

  fetchData(); // αρχικό
})();
</script>
</body>
</html>
