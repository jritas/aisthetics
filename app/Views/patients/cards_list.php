<?php
$title = 'Πελάτες – Καρτέλες';
ob_start();

$mk = function(string $col) use ($q, $sort, $dir) {
    $nd = (strtolower($sort) === strtolower($col) && strtoupper($dir)==='ASC') ? 'DESC' : 'ASC';
    $qs = http_build_query([
        'r'   => 'patients',
        'a'   => 'cards_list',
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

// Μικρό helper για ασφαλές escape χωρίς warnings σε null τιμές
if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
?>
<style>
.cards-page .toolbar {
  row-gap: .5rem;
}
.cards-page .toolbar .form-control {
  max-width: 320px;
}
.cards-page .btn {
  white-space: nowrap;
}

/* κρύβουμε email σε μικρές οθόνες */
@media (max-width: 991.98px) {
  .cards-page th:nth-child(4),
  .cards-page td:nth-child(4) {
    display: none;
  }
}
</style>

<div class="container-fluid py-3 cards-page">

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 toolbar">
    <form class="d-flex gap-2" method="get">
      <input type="hidden" name="r" value="patients">
      <input type="hidden" name="a" value="cards_list">
      <input type="hidden" name="sort" value="<?= e($sort ?? '') ?>">
      <input type="hidden" name="dir" value="<?= e($dir ?? '') ?>">
      <input name="q"
             id="q"
             class="form-control"
             placeholder="Αναζήτηση (όνομα ή τηλέφωνο)"
             value="<?= e($q ?? '') ?>">
      <button class="btn btn-outline-secondary" type="submit">Αναζήτηση</button>
      <?php if ($q !== ''): ?>
        <a class="btn btn-outline-secondary" href="?r=patients&a=cards_list">Καθαρισμός</a>
      <?php endif; ?>
    </form>
    <a class="btn btn-primary" href="?r=patients">
      <i class="bi bi-people me-1"></i>Μητρώο πελατών
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
            <th class="text-center">GDPR</th>
            <th class="text-end">Ενέργειες</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-3">Δεν βρέθηκαν πελάτες.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $email     = $row['email']      ?? '';
                $lastVisit = $row['last_visit'] ?? null;
              ?>
              <tr>
                <td><?= (int)$row['id'] ?></td>
                <td><?= e($row['name'] ?? '') ?></td>
                <td><?= e($row['phone'] ?? '') ?></td>
                <td class="d-none d-md-table-cell"><?= e($email) ?></td>
                <td>
                  <?= $lastVisit
                        ? e($lastVisit)
                        : '–'
                  ?>
                </td>
                <td class="text-center">
                  <?php if (!empty($row['gdpr_yes'])): ?>
                    <span class="badge bg-success">ΝΑΙ</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">ΟΧΙ</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary"
                     href="?r=patients&a=card_view&id=<?= (int)$row['id'] ?>">
                    Καρτέλα
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
      <div class="card-footer py-2">
        <nav>
          <ul class="pagination pagination-sm justify-content-center mb-0">
            <?php
              $prev = max(1, $page - 1);
              $next = min($pages, $page + 1);

              $base = ['r'=>'patients','a'=>'cards_list'];
              if ($q   !== '') $base['q']    = $q;
              if ($sort!== '') $base['sort'] = $sort;
              if ($dir !== '') $base['dir']  = $dir;

              $urlFor = function($p) use ($base) {
                $params = $base;
                $params['page'] = $p;
                return '?' . http_build_query($params);
              };
            ?>

            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= $page <= 1 ? '#' : e($urlFor(1)) ?>">&laquo; Πρώτη</a>
            </li>
            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= $page <= 1 ? '#' : e($urlFor($prev)) ?>">‹ Προηγ.</a>
            </li>

            <li class="page-item disabled">
              <span class="page-link">
                Σελίδα <?= (int)$page ?> / <?= (int)$pages ?>
              </span>
            </li>

            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= $page >= $pages ? '#' : e($urlFor($next)) ?>">Επόμ. ›</a>
            </li>
            <li class="page-item <?= $page >= $pages ? 'disabled' : '' ?>">
              <a class="page-link" href="<?= $page >= $pages ? '#' : e($urlFor($pages)) ?>">Τελευταία &raquo;</a>
            </li>
          </ul>
        </nav>
      </div>
    <?php endif; ?>
  </div>

</div>

<script>
(function() {
  const input = document.getElementById('q');
  if (!input) return;
  const form = input.form;
  if (!form) return;

  let timer = null;

  input.addEventListener('input', function () {
    const value = input.value;
    clearTimeout(timer);
    timer = setTimeout(function () {
      const trimmed = value.trim();
      if (trimmed.length >= 2 || trimmed.length === 0) {
        form.submit();
      }
    }, 400);
  });
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>
