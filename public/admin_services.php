<?php
// /site/public/admin_services.php
declare(strict_types=1);

// ---- DB bootstrap (όπως στο admin_doctors.php) ----
function env_load($path) {
  if (!is_file($path)) return [];
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  $env = [];
  foreach ($lines as $ln) {
    if (str_starts_with(trim($ln), '#')) continue;
    if (!str_contains($ln, '=')) continue;
    [$k, $v] = array_map('trim', explode('=', $ln, 2));
    $env[$k] = trim($v, " \t\n\r\0\x0B\"'");
  }
  return $env;
}

$pdo = null;
if (file_exists(__DIR__ . '/../app/db.php')) {
  require_once __DIR__ . '/../app/db.php';
  if (function_exists('pdo')) {
    $pdo = pdo();
  } elseif (isset($pdo) && $pdo instanceof PDO) {
    // already from app/db.php
  }
}
if (!$pdo) {
  $env = env_load(__DIR__ . '/../.env');
  $dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $env['DB_HOST'] ?? '127.0.0.1',
    $env['DB_PORT'] ?? '3306',
    $env['DB_NAME'] ?? 'zapdb',
    ($env['DB_CHARSET'] ?? 'utf8mb4')
  );
  $user = $env['DB_USER'] ?? 'root';
  $pass = $env['DB_PASS'] ?? '';
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
}

// ---- Helpers ----
function norm_price(?string $s): ?string {
  if ($s === null) return null;
  $s = trim($s);
  if ($s === '') return null;
  // δέχεται "12,50" ή "12.50"
  $s = str_replace(',', '.', $s);
  // επιτρέπουμε μόνο ψηφία/τελεία/μείον
  if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) return null;
  return number_format((float)$s, 2, '.', '');
}

// ---- AJAX ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json; charset=utf-8');
  try {
    $act = $_POST['action'];

    if ($act === 'list') {
      $q = trim($_POST['q'] ?? '');
      if ($q !== '') {
        $stmt = $pdo->prepare(
          "SELECT id, description, c_price
           FROM payment_category
           WHERE description LIKE :q OR CAST(c_price AS CHAR) LIKE :q
           ORDER BY description"
        );
        $stmt->execute([':q' => "%$q%"]);
      } else {
        $stmt = $pdo->query(
          "SELECT id, description, c_price
           FROM payment_category
           ORDER BY description"
        );
      }
      echo json_encode(['ok' => true, 'rows' => $stmt->fetchAll()]);
      exit;
    }

    if ($act === 'create' || $act === 'update') {
      $id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;
      $desc  = trim($_POST['description'] ?? '');
      $price = norm_price($_POST['c_price'] ?? null);

      if ($desc === '') throw new RuntimeException('Η περιγραφή είναι υποχρεωτική.');
      if ($act === 'create') {
       // $stmt = $pdo->prepare("INSERT INTO payment_category (description, c_price) VALUES (?, ?)");
       // $stmt->execute([$desc, $price]);
        $stmt = $pdo->prepare("INSERT INTO payment_category (category, description, c_price) VALUES (?, ?, ?)");
        $stmt->execute([$descr, $descr, $price]);
		
		
		
		echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
      } else {
        // $stmt = $pdo->prepare("UPDATE payment_category SET description=?, c_price=? WHERE id=?");
        // $stmt->execute([$desc, $price, $id]);
		$stmt = $pdo->prepare("UPDATE payment_category SET category=?, description=?, c_price=? WHERE id=?");
        $stmt->execute([$descr, $descr, $price, $id]);
		
		
        echo json_encode(['ok' => true, 'id' => $id]);
      }
      exit;
    }

    if ($act === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      // Optional: έλεγχος αν χρησιμοποιείται αλλού
      $pdo->prepare("DELETE FROM payment_category WHERE id=?")->execute([$id]);
      echo json_encode(['ok' => true]);
      exit;
    }

    throw new RuntimeException('Άγνωστη ενέργεια.');
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
  }
  exit;
}

// ---- HTML ----
?>
<!doctype html>
<html lang="el" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <title>Διαχείριση Υπηρεσιών</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="/assets/theme.css">
  <script defer src="/assets/theme.js"></script>

  <style>
    body { padding: 20px; }
    .table thead th { position: sticky; top: 0; background: var(--bs-body-bg); z-index: 1; }
    .btn-pill { border-radius: 9999px; padding: .35rem .7rem; }
    .btn-edit   { --bs-btn-color: var(--bs-primary); --bs-btn-border-color: var(--bs-primary); }
    .btn-edit:hover   { color:#fff; background:var(--bs-primary); }
    .btn-delete { --bs-btn-color: var(--bs-danger);  --bs-btn-border-color: var(--bs-danger);  }
    .btn-delete:hover { color:#fff; background:var(--bs-danger); }
    .toolbar { gap:.5rem; flex-wrap: wrap; }
    .search-input { min-width: 260px; max-width: 420px; }
    td.price { text-align: right; white-space: nowrap; }
  </style>
</head>
<body>
  <div class="container-lg">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h3 m-0">Διαχείριση Υπηρεσιών</h1>

      <div class="d-flex align-items-center toolbar">
        <form class="d-none d-md-flex" onsubmit="event.preventDefault(); loadRows(searchEl.value.trim());">
          <div class="input-group search-input">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input id="search" class="form-control" placeholder="Αναζήτηση περιγραφής ή τιμής">
          </div>
        </form>

        <button class="btn btn-primary btn-pill" onclick="openModal()">
          <i class="bi bi-plus-lg me-1"></i> Προσθήκη
        </button>

        <button id="themeToggle" type="button" class="btn btn-outline-secondary btn-pill" title="Εναλλαγή θέματος">🌓</button>
        <a class="btn btn-outline-secondary btn-pill" href="/">Αρχική</a>
      </div>
    </div>

    <form class="d-md-none mb-3" onsubmit="event.preventDefault(); loadRows(searchElMobile.value.trim());">
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input id="search_m" class="form-control" placeholder="Αναζήτηση">
        <button class="btn btn-primary">OK</button>
      </div>
    </form>

    <div class="table-responsive border rounded">
      <table class="table table-hover align-middle m-0">
        <thead>
          <tr>
            <th style="width:70px">#</th>
            <th>Περιγραφή</th>
            <th style="width:160px" class="text-end">Τιμή</th>
            <th style="width:220px" class="text-end">Ενέργειες</th>
          </tr>
        </thead>
        <tbody id="rows">
          <tr><td colspan="4" class="text-center p-4 text-muted">Φόρτωση…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" onsubmit="return saveService(event)">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Νέα υπηρεσία</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="id">
          <div class="mb-3">
            <label class="form-label">Περιγραφή *</label>
            <input class="form-control" id="description" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Τιμή (€)</label>
            <input class="form-control" id="c_price" inputmode="decimal" placeholder="π.χ. 50 ή 50,00">
            <div class="form-text">Δέχεται και κόμμα ως δεκαδικό.</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary btn-pill" data-bs-dismiss="modal">Άκυρο</button>
          <button type="submit" class="btn btn-primary btn-pill">Αποθήκευση</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const rowsEl = document.getElementById('rows');
    const searchEl = document.getElementById('search');
    const searchElMobile = document.getElementById('search_m');
    const modal = new bootstrap.Modal('#editModal');

    // Fallback theme toggle όπως στο admin_doctors
    (function(){
      const btn=document.getElementById('themeToggle');
      if(!btn) return;
      const KEY='theme'; const root=document.documentElement;
      function apply(v){ root.setAttribute('data-bs-theme',v); localStorage.setItem(KEY,v); }
      const saved=localStorage.getItem(KEY); if(saved) apply(saved);
      btn.addEventListener('click', ()=>{
        if (window.toggleTheme) { window.toggleTheme(); return; }
        const next = root.getAttribute('data-bs-theme')==='dark'?'light':'dark';
        apply(next);
      });
    })();

    function euro(v){
      if (v===null || v===undefined || v==='') return '';
      const n = Number(v);
      if (Number.isNaN(n)) return '';
      return n.toFixed(2) + ' €';
    }
    function escapeHtml(s){ return String(s ?? '').replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }
    function json(o){ return JSON.stringify(o).replace(/</g,'\\u003c'); }

    async function loadRows(q='') {
      const fd = new FormData();
      fd.append('action', 'list'); fd.append('q', q);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) throw new Error(j.error || 'load failed');
      rowsEl.innerHTML = '';
      if (!j.rows.length) {
        rowsEl.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-muted">Καμία εγγραφή</td></tr>';
        return;
      }
      for (const row of j.rows) {
        rowsEl.insertAdjacentHTML('beforeend', `
          <tr>
            <td>${row.id}</td>
            <td>${escapeHtml(row.description)}</td>
            <td class="price">${euro(row.c_price)}</td>
            <td class="text-end">
              <div class="btn-group">
                <button class="btn btn-sm btn-outline-primary btn-pill btn-edit me-1"
                        onclick='editRow(${row.id}, ${json(row)})'>
                  <i class="bi bi-pencil-square me-1"></i> Επεξεργασία
                </button>
                <button class="btn btn-sm btn-outline-danger btn-pill btn-delete"
                        onclick='deleteRow(${row.id})'>
                  <i class="bi bi-trash3 me-1"></i> Διαγραφή
                </button>
              </div>
            </td>
          </tr>
        `);
      }
    }

    function openModal(){
      document.getElementById('id').value = '';
      document.getElementById('description').value = '';
      document.getElementById('c_price').value = '';
      document.getElementById('modalTitle').textContent = 'Νέα υπηρεσία';
      modal.show();
    }

    function editRow(id, row){
      document.getElementById('id').value = id;
      document.getElementById('description').value = row.description ?? '';
      document.getElementById('c_price').value = row.c_price ?? '';
      document.getElementById('modalTitle').textContent = 'Επεξεργασία υπηρεσίας';
      modal.show();
    }

    async function saveService(ev){
      ev.preventDefault();
      const id = document.getElementById('id').value;
      const fd = new FormData();
      fd.append('action', id ? 'update' : 'create');
      fd.append('id', id);
      fd.append('description', document.getElementById('description').value.trim());
      fd.append('c_price', document.getElementById('c_price').value.trim());
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) { alert(j.error || 'Αποτυχία'); return; }
      modal.hide();
      await loadRows((searchEl?.value || searchElMobile?.value || '').trim());
    }

    async function deleteRow(id){
      if (!confirm('Οριστική διαγραφή;')) return;
      const fd = new FormData();
      fd.append('action', 'delete'); fd.append('id', id);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) { alert(j.error || 'Αποτυχία'); return; }
      await loadRows((searchEl?.value || searchElMobile?.value || '').trim());
    }

    let t;
    [searchEl, searchElMobile].forEach(inp=>{
      if(!inp) return;
      inp.addEventListener('input', ()=>{
        clearTimeout(t);
        t = setTimeout(()=> loadRows(inp.value.trim()), 250);
      });
    });

    loadRows().catch(err=>{
      rowsEl.innerHTML = `<tr><td colspan="4" class="text-danger p-4">${escapeHtml(err.message)}</td></tr>`;
    });
  </script>
</body>
</html>
