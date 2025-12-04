<?php
// Περιμένουμε από τον controller: $rows, $page, $pages, $q, $sort, $dir
$title = 'Ραντεβού / Επισκέψεις';
$q     = $q   ?? '';
$sort  = $sort?? 'last_visit';
$dir   = strtolower($dir ?? 'desc');
$page  = (int)($page ?? 1);
$pages = (int)($pages ?? 1);
ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="card-title mb-0">Ραντεβού / Επισκέψεις</h5>
	  <a class="btn btn-sm btn-outline-secondary ms-2" href="?r=agenda&a=daily_cash">Ημερήσιο Ταμείο</a>


      <!-- Φόρμα με GET (fallback χωρίς JS) + κρυφά sort/dir/page -->
      <form id="agForm" class="input-group" method="get" style="max-width: 520px;">
        <input type="hidden" name="r" value="agenda">
        <input type="hidden" name="page" id="agPage" value="<?= $page ?>">
        <input type="hidden" name="sort" id="agSort" value="<?= htmlspecialchars($sort) ?>">
        <input type="hidden" name="dir"  id="agDir"  value="<?= htmlspecialchars($dir) ?>">
        <input name="q" id="agQ" type="text" class="form-control"
               placeholder="Αναζήτηση (όνομα/τηλέφωνο/email)"
               value="<?= htmlspecialchars($q) ?>">
        <button class="btn btn-outline-secondary" type="submit">Αναζήτηση</button>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead>
          <?php
          // helper για δημιουργία σωστού href με GET (fallback)
          $href = function(string $by, string $label) use ($q,$sort,$dir){
            $nextDir = ($sort===$by && $dir==='asc') ? 'desc' : 'asc';
            $params = [
              'r'    => 'agenda',
              'q'    => $q,
              'page' => 1,
              'sort' => $by,
              'dir'  => $nextDir
            ];
            $mark = ($sort===$by) ? ' ' . ($dir==='asc'?'▲':'▼') : '';
            return [
              'url'   => '?' . http_build_query($params),
              'label' => $label . $mark
            ];
          };
          $hId   = $href('id',         'ID');
          $hName = $href('name',       'Ονοματεπώνυμο');
          $hTel  = $href('phone',      'Τηλέφωνο');
          $hLast = $href('last_visit', 'Τελευταία επίσκεψη');
          $hCnt  = $href('visits',     '#');
          ?>
          <tr>
            <th style="width:80px;">
              <a href="<?= $hId['url'] ?>" class="text-decoration-none sort" data-sort="id"><?= $hId['label'] ?></a>
            </th>
            <th>
              <a href="<?= $hName['url'] ?>" class="text-decoration-none sort" data-sort="name"><?= $hName['label'] ?></a>
            </th>
            <th style="width:180px;">
              <a href="<?= $hTel['url'] ?>" class="text-decoration-none sort" data-sort="phone"><?= $hTel['label'] ?></a>
            </th>
            <th style="width:220px;">
              <a href="<?= $hLast['url'] ?>" class="text-decoration-none sort" data-sort="last_visit"><?= $hLast['label'] ?></a>
            </th>
            <th style="width:60px;" class="text-center">
              <a href="<?= $hCnt['url'] ?>" class="text-decoration-none sort" data-sort="visits"><?= $hCnt['label'] ?></a>
            </th>
            <th style="width:120px;" class="text-center">Ενέργεια</th>
          </tr>
        </thead>
        <tbody id="agendaRows">
          <?php include __DIR__ . '/_list_rows.php'; ?>
        </tbody>
      </table>
    </div>

    <div id="agendaPager">
      <?php include __DIR__ . '/_pagination.php'; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const form  = document.getElementById('agForm');
  const q     = document.getElementById('agQ');
  const pageI = document.getElementById('agPage');
  const sortI = document.getElementById('agSort');
  const dirI  = document.getElementById('agDir');
  const rows  = document.getElementById('agendaRows');
  const pager = document.getElementById('agendaPager');

  // Τρέχουσες τιμές (για AJAX)
  let curSort = sortI.value || 'last_visit';
  let curDir  = (dirI.value || 'desc').toLowerCase();
  let curPage = parseInt(pageI.value || '1', 10);

  function paramsObj(page){
    return {
      a: 'fetch',
      q: q.value.trim(),
      page: page || 1,
      sort: curSort,
      dir: curDir
    };
  }
  function toQS(o){ return new URLSearchParams(o).toString(); }

  function attachPagerDelegation(){
    pager.querySelectorAll('a[data-page]').forEach(a=>{
      a.addEventListener('click', function(ev){
        ev.preventDefault();
        const p = parseInt(this.getAttribute('data-page'), 10);
        if (!isNaN(p)) load(p);
      });
    });
  }
  function attachSortDelegation(){
    document.querySelectorAll('a.sort[data-sort]').forEach(a=>{
      a.addEventListener('click', function(ev){
        // επιτρέπουμε και πλήρες reload αν κρατά πατημένο Ctrl/Meta
        if (ev.ctrlKey || ev.metaKey) return;
        ev.preventDefault();
        const s = this.getAttribute('data-sort');
        if (curSort === s) {
          curDir = (curDir === 'asc') ? 'desc' : 'asc';
        } else {
          curSort = s; curDir = 'asc';
        }
        sortI.value = curSort;
        dirI.value  = curDir;
        load(1);
      });
    });
  }

  function load(page){
    // Αν για κάποιο λόγο αποτύχει το fetch, κάνε κανονικό submit (fallback).
    const qs = toQS(paramsObj(page));
    fetch(`?r=agenda&${qs}`, {credentials:'same-origin'})
      .then(r=>r.ok ? r.json() : Promise.reject())
      .then(data=>{
        rows.innerHTML = data.tbody || '';
        pager.innerHTML = data.pagination || '';
        curPage = page;
        pageI.value = String(curPage);
        attachPagerDelegation();
      })
      .catch(()=> form.submit());
  }

  // Αναζήτηση με Enter (AJAX)
  q.addEventListener('keydown', (e)=>{ if (e.key === 'Enter') { e.preventDefault(); load(1);} });

  // ΝΕΟ: “ζωντανή” αναζήτηση χωρίς Enter (debounce 250ms)
  let agSearchTimer, agLast = q.value;
  q.addEventListener('input', function(){
    clearTimeout(agSearchTimer);
    agSearchTimer = setTimeout(function(){
      const now = q.value;
      if (now !== agLast) {
        agLast = now;
        pageI.value = '1';
        load(1);
      }
    }, 250);
  });

  // Υποβολή με κουμπί → AJAX, αλλιώς full GET fallback
  form.addEventListener('submit', function(e){
    e.preventDefault();
    // συγχρονίζουμε τα κρυφά πεδία
    sortI.value = curSort;
    dirI.value  = curDir;
    pageI.value = '1';
    load(1);
  });

  // Αρχικά
  attachPagerDelegation();
  attachSortDelegation();
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
