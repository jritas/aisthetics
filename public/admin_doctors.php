<?php
// /site/public/admin_doctors.php
declare(strict_types=1);

// 1) DB bootstrap: προτιμά υπάρχον app/db.php με pdo(), αλλιώς .env
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

$assetEnv  = env_load(__DIR__ . '/../.env');
$assetBase = '/' . ltrim($assetEnv['ASSETS_BASE_URL'] ?? 'assets', '/');
$assetBase = rtrim($assetBase, '/');




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
  $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
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

// 2) AJAX actions (create/update/delete/load)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header('Content-Type: application/json; charset=utf-8');
  try {
    $act = $_POST['action'];

    if ($act === 'list') {
      $q = trim($_POST['q'] ?? '');
      if ($q !== '') {
        $stmt = $pdo->prepare("SELECT id, name, phone, email, department FROM doctor
                               WHERE name LIKE :q OR phone LIKE :q OR email LIKE :q
                               ORDER BY name");
        $stmt->execute([':q' => "%$q%"]);
      } else {
        $stmt = $pdo->query("SELECT id, name, phone, email, department FROM doctor ORDER BY name");
      }
      echo json_encode(['ok' => true, 'rows' => $stmt->fetchAll()]);
      exit;
    }

    if ($act === 'create' || $act === 'update') {
      $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
      $name = trim($_POST['name'] ?? '');
      $phone= trim($_POST['phone'] ?? '');
      $email= trim($_POST['email'] ?? '');
      $dept = trim($_POST['department'] ?? '');

      if ($name === '') throw new RuntimeException('Το όνομα είναι υποχρεωτικό.');

      if ($act === 'create') {
        $stmt = $pdo->prepare("INSERT INTO doctor (name, phone, email, department) VALUES (?,?,?,?)");
        $stmt->execute([$name, $phone, $email, $dept]);
        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
      } else {
        $stmt = $pdo->prepare("UPDATE doctor SET name=?, phone=?, email=?, department=? WHERE id=?");
        $stmt->execute([$name, $phone, $email, $dept, $id]);
        echo json_encode(['ok' => true, 'id' => $id]);
      }
      exit;
    }

    if ($act === 'delete') {
      $id = (int)($_POST['id'] ?? 0);
      $pdo->prepare("DELETE FROM doctor WHERE id=?")->execute([$id]);
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

// 3) HTML
?>
<!doctype html>
<html lang="el" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <title>Εργαζόμενοι</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Icons για όμορφα κουμπιά -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Global theme (όπως στις υπόλοιπες σελίδες) -->
  
	
  <!-- <link rel="stylesheet" href="/assets/theme.css">
  <script defer src="/assets/theme.js"></script>  -->

<link rel="stylesheet" href="<?= htmlspecialchars($assetBase) ?>/theme.css">
<script defer src="<?= htmlspecialchars($assetBase) ?>/theme.js"></script>
	
	
	
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
  </style>
</head>
<body>
  <div class="container-lg">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h1 class="h3 m-0">Εργαζόμενοι</h1>

      <div class="d-flex align-items-center toolbar">
        <form class="d-none d-md-flex" onsubmit="event.preventDefault(); loadRows(searchEl.value.trim());">
          <div class="input-group search-input">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input id="search" class="form-control" placeholder="Αναζήτηση ονόματος / email / τηλεφώνου">
          </div>
        </form>

        <button class="btn btn-primary btn-pill" onclick="openModal()">
          <i class="bi bi-plus-lg me-1"></i> Προσθήκη
        </button>

        <!-- Dark/Light toggle, ίδιο με τις άλλες σελίδες -->
        <button id="themeToggle" type="button" class="btn btn-outline-secondary btn-pill" title="Εναλλαγή θέματος">🌓</button>

        <a class="btn btn-outline-secondary btn-pill" href="/">Αρχική</a>
      </div>
    </div>

    <!-- Φόρμα αναζήτησης για κινητά -->
    <form class="d-md-none mb-3" onsubmit="event.preventDefault(); loadRows(searchEl.value.trim());">
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input id="search_m" class="form-control" placeholder="Αναζήτηση ονόματος / email / τηλεφώνου">
        <button class="btn btn-primary">OK</button>
      </div>
    </form>

    <div class="table-responsive border rounded">
      <table class="table table-hover align-middle m-0">
        <thead>
          <tr>
            <th style="width:70px">#</th>
            <th>Όνομα</th>
            <th>Τηλέφωνο</th>
            <th>Email</th>
            <th>Τμήμα</th>
            <th style="width:220px" class="text-end">Ενέργειες</th>
          </tr>
        </thead>
        <tbody id="rows">
          <tr><td colspan="6" class="text-center p-4 text-muted">Φόρτωση…</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <form class="modal-content" onsubmit="return saveEmployee(event)">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Νέος εργαζόμενος</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Κλείσιμο"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="id">
          <div class="mb-3">
            <label class="form-label">Όνομα *</label>
            <input class="form-control" id="name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Τηλέφωνο</label>
            <input class="form-control" id="phone">
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" id="email">
          </div>
          <div class="mb-2">
            <label class="form-label">Τμήμα</label>
            <input class="form-control" id="department" placeholder="π.χ. Αισθητική, Laser, Ιατρείο">
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
    const rowsEl  = document.getElementById('rows');
    const searchEl= document.getElementById('search');
    const modal   = new bootstrap.Modal('#editModal');

    // Fallback toggle (αν δεν υπάρχει window.toggleTheme από /assets/theme.js)
    (function(){
      const btn=document.getElementById('themeToggle');
      if(!btn) return;
      const KEY='theme';
      const root=document.documentElement;
      function apply(v){ root.setAttribute('data-bs-theme',v); localStorage.setItem(KEY,v); }
      const saved=localStorage.getItem(KEY);
      if(saved) apply(saved);
      btn.addEventListener('click', ()=>{
        if (window.toggleTheme) { window.toggleTheme(); return; }
        const next = root.getAttribute('data-bs-theme')==='dark'?'light':'dark';
        apply(next);
      });
    })();

    async function loadRows(q='') {
      const fd = new FormData();
      fd.append('action', 'list');
      fd.append('q', q);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) throw new Error(j.error || 'load failed');
      rowsEl.innerHTML = '';
      if (!j.rows.length) {
        rowsEl.innerHTML = '<tr><td colspan="6" class="text-center p-4 text-muted">Καμία εγγραφή</td></tr>';
        return;
      }
      for (const row of j.rows) {
        rowsEl.insertAdjacentHTML('beforeend', `
          <tr>
            <td>${row.id}</td>
            <td>${escapeHtml(row.name ?? '')}</td>
            <td>${escapeHtml(row.phone ?? '')}</td>
            <td>${emailLink(row.email)}</td>
            <td>${escapeHtml(row.department ?? '')}</td>
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

    function emailLink(e){
      if(!e) return '';
      const s = escapeHtml(e);
      return `<a href="mailto:${s}">${s}</a>`;
    }
    function json(o){ return JSON.stringify(o).replace(/</g,'\\u003c'); }
    function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m])); }

    function openModal(){
      document.getElementById('id').value = '';
      document.getElementById('name').value = '';
      document.getElementById('phone').value = '';
      document.getElementById('email').value = '';
      document.getElementById('department').value = '';
      document.getElementById('modalTitle').textContent = 'Νέος εργαζόμενος';
      modal.show();
    }

    function editRow(id, row){
      document.getElementById('id').value = id;
      document.getElementById('name').value = row.name ?? '';
      document.getElementById('phone').value = row.phone ?? '';
      document.getElementById('email').value = row.email ?? '';
      document.getElementById('department').value = row.department ?? '';
      document.getElementById('modalTitle').textContent = 'Επεξεργασία εργαζομένου';
      modal.show();
    }

    async function saveEmployee(ev){
      ev.preventDefault();
      const id = document.getElementById('id').value;
      const fd = new FormData();
      fd.append('action', id ? 'update' : 'create');
      fd.append('id', id);
      fd.append('name', document.getElementById('name').value.trim());
      fd.append('phone', document.getElementById('phone').value.trim());
      fd.append('email', document.getElementById('email').value.trim());
      fd.append('department', document.getElementById('department').value.trim());
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) { alert(j.error || 'Αποτυχία'); return; }
      modal.hide();
      await loadRows((document.getElementById('search')?.value || '').trim());
    }

    async function deleteRow(id){
      if (!confirm('Οριστική διαγραφή;')) return;
      const fd = new FormData();
      fd.append('action', 'delete');
      fd.append('id', id);
      const r = await fetch(location.href, { method:'POST', body:fd });
      const j = await r.json();
      if (!j.ok) { alert(j.error || 'Αποτυχία'); return; }
      await loadRows((document.getElementById('search')?.value || '').trim());
    }

    // live search
    const searchElMobile = document.getElementById('search_m');
    let t;
    [searchEl, searchElMobile].forEach(inp=>{
      if(!inp) return;
      inp.addEventListener('input', ()=>{
        clearTimeout(t);
        t = setTimeout(()=> loadRows(inp.value.trim()), 250);
      });
    });

    loadRows().catch(err=>{
      rowsEl.innerHTML = `<tr><td colspan="6" class="text-danger p-4">${escapeHtml(err.message)}</td></tr>`;
    });
  </script>
</body>
</html>
