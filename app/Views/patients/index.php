<?php
$title = 'Πελάτες';
ob_start();

$mk = function(string $col) use ($q, $sort, $dir) {
    $nd = (strtolower($sort) === strtolower($col) && strtoupper($dir)==='ASC') ? 'DESC' : 'ASC';
    $qs = http_build_query([
        'r'   => 'patients',
        'q'   => $q,
        'sort'=> $col,
        'dir' => $nd,
        'page'=> 1
    ]);
    return '?' . $qs;
};

$arrow = function(string $col) use ($sort,$dir) {
    if (strtolower($sort)!==strtolower($col)) return '';
    return strtoupper($dir)==='ASC' ? ' ▲' : ' ▼';
};
?>
<style>
.patient-page .toolbar {
  row-gap: .5rem;
}
.patient-page .toolbar .form-control {
  max-width: 320px;
}
.patient-page .btn {
  white-space: nowrap;
}

/* κρύβουμε email σε μικρές οθόνες, όπως στα άλλα views */
@media (max-width: 991.98px) {
  .patient-page th:nth-child(4),
  .patient-page td:nth-child(4) {
    display: none;
  }
}
</style>

<div class="container-fluid py-3 patient-page">

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 toolbar">
    <form class="d-flex gap-2" onsubmit="return false;">
      <input id="q"
             class="form-control"
             placeholder="Αναζήτηση (όνομα/τηλ/email)"
             value="<?= htmlspecialchars($q) ?>">
      <button class="btn btn-outline-primary" id="searchBtn" type="button">Αναζήτηση</button>
    </form>
    <a class="btn btn-primary" href="?r=patients&a=create">
      <i class="bi bi-plus-lg me-1"></i>Νέος πελάτης
    </a>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead>
          <tr>
            <th><a href="<?= $mk('id') ?>">ID<?= $arrow('id') ?></a></th>
            <th><a href="<?= $mk('name') ?>">Ονοματεπώνυμο<?= $arrow('name') ?></a></th>
            <th><a href="<?= $mk('phone') ?>">Τηλέφωνο<?= $arrow('phone') ?></a></th>
            <th class="d-none d-md-table-cell"><a href="<?= $mk('email') ?>">Email<?= $arrow('email') ?></a></th>
            <th><a href="<?= $mk('last_visit') ?>">Τελ. επίσκεψη<?= $arrow('last_visit') ?></a></th>
            <th class="text-center"><a href="<?= $mk('gdpr') ?>">GDPR<?= $arrow('gdpr') ?></a></th>
            <th class="text-end">Ενέργειες</th>
          </tr>
        </thead>
        <tbody id="tbody">
        <?php include __DIR__ . '/_table_rows.php'; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div id="pagination" class="mt-2">
    <?php include __DIR__ . '/_pagination.php'; ?>
  </div>

</div>

<script>
(function(){
  const q      = document.getElementById('q');
  const tbody  = document.getElementById('tbody');
  const pag    = document.getElementById('pagination');
  let page     = 1;
  let sort     = '<?= htmlspecialchars($sort) ?>';
  let dir      = '<?= htmlspecialchars($dir) ?>';

  const fetchRows = () => {
    const params = new URLSearchParams({
      r: 'patients',
      a: 'fetch',
      q: q.value,
      page: String(page),
      sort: sort,
      dir: dir
    });

    fetch('?' + params.toString(), {cache:'no-store'})
      .then(r => r.json())
      .then(data => {
        tbody.innerHTML = data.tbody;
        pag.innerHTML   = data.pagination;
      })
      .catch(console.error);
  };

  const debounce = (fn, ms=250) => {
    let t;
    return (...a) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...a), ms);
    };
  };

  q.addEventListener('input', debounce(fetchRows, 250));
  document.getElementById('searchBtn').addEventListener('click', fetchRows);

  document.addEventListener('click', (e) => {
    const p = e.target.closest('.pagelink');
    if (p) {
      e.preventDefault();
      page = parseInt(p.dataset.page || '1', 10);
      fetchRows();
    }
  });
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
