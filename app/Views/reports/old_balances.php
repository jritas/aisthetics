<?php
// app/Views/reports/old_balances.php
$title = 'Υπόλοιπα πελατών – Παλιά λογική (patient_deposit)';

$q    = $q    ?? '';
$from = $from ?? '';
$to   = $to   ?? '';
$rows = $rows ?? [];

// Μόνο με υπόλοιπο (φιλτράρισμα στην εμφάνιση)
$onlyDue = isset($_GET['only_due']) && $_GET['only_due'] === '1';

$displayRows = $rows;
if ($onlyDue) {
    $displayRows = array_values(array_filter($rows, function ($r) {
        $charges  = (float)($r['charges']  ?? 0);
        $payments = (float)($r['payments'] ?? 0);
        $bal      = $charges - $payments;
        return $bal > 0.00001;
    }));
}

// URL για CSV export με τα ίδια φίλτρα
$exportParams = [
    'r'      => 'reports',
    'a'      => 'old_balances',
    'export' => 1,
];
if ($q    !== '') $exportParams['q']    = $q;
if ($from !== '') $exportParams['from'] = $from;
if ($to   !== '') $exportParams['to']   = $to;
if ($onlyDue)     $exportParams['only_due'] = '1';

$exportUrl = '?' . http_build_query($exportParams, '', '&', PHP_QUERY_RFC3986);

ob_start();
?>
<style>
  .balances-table thead th {
    position: sticky;
    top: 0;
    background: var(--bs-body-bg);
    z-index: 1;
  }
  .balances-num {
    text-align: right;
    font-variant-numeric: tabular-nums;
  }
  .balances-pos {
    color: #ff6b6b;
    font-weight: 600;
  }
  .balances-neg {
    color: #4dabf7;
    font-weight: 600;
  }
  .balances-zero {
    color: var(--bs-secondary-color);
  }
</style>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-3">
     <h5 class="card-title mb-0">
        Υπόλοιπα πελατών
        <span class="badge rounded-pill bg-warning text-dark ms-2">ΠΑΛΙΑ ΛΟΓΙΚΗ</span>
      </h5>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="?r=reports&a=balances">↩ Νέα Υπόλοιπα</a>
      </div>
    </div>

    <?php if (!empty($error)): ?>
      <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form id="oldBalancesForm" class="row g-2 align-items-end mb-3" method="get" action="">
      <input type="hidden" name="r" value="reports">
      <input type="hidden" name="a" value="old_balances">

      <div class="col-12 col-md-3">
        <label class="form-label" for="q">Αναζήτηση (όνομα/τηλ)</label>
        <input id="q"
               class="form-control"
               name="q"
               value="<?= htmlspecialchars($q) ?>"
               placeholder="π.χ. Μαρία ή 69...">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label" for="from">Από</label>
        <input id="from"
               class="form-control"
               type="date"
               name="from"
               value="<?= htmlspecialchars($from) ?>">
      </div>

      <div class="col-6 col-md-2">
        <label class="form-label" for="to">Έως</label>
        <input id="to"
               class="form-control"
               type="date"
               name="to"
               value="<?= htmlspecialchars($to) ?>">
      </div>

      <div class="col-12 col-md-5 d-flex flex-wrap gap-2">
        <div class="form-check form-switch align-self-center">
          <input class="form-check-input"
                 type="checkbox"
                 role="switch"
                 id="only_due"
                 name="only_due"
                 value="1"
            <?= $onlyDue ? 'checked' : '' ?>>
          <label class="form-check-label" for="only_due">Μόνο με υπόλοιπο</label>
        </div>

        <button class="btn btn-primary" type="submit">Αναζήτηση</button>
        <a class="btn btn-outline-secondary" href="?r=reports&a=old_balances">Καθαρισμός</a>
        <a class="btn btn-outline-success" href="<?= htmlspecialchars($exportUrl) ?>">Εξαγωγή CSV</a>

        <?php if (!empty($displayRows)): ?>
          <span class="badge bg-info align-self-center">
            Σύνολο: <?= count($displayRows) ?>
          </span>
        <?php endif; ?>
      </div>
    </form>

    <div class="table-responsive border rounded">
      <table class="table table-hover align-middle m-0 balances-table">
        <thead>
          <tr>
            <th style="width:80px">#</th>
            <th>Όνομα</th>
            <th style="width:160px">Τηλέφωνο</th>
            <th style="width:180px">Τελευταία κίνηση</th>
            <th class="text-end" style="width:140px">Χρεώσεις</th>
            <th class="text-end" style="width:140px">Πληρωμές</th>
            <th class="text-end" style="width:140px">Υπόλοιπο</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($displayRows)): ?>
          <tr>
            <td colspan="7" class="text-center p-4 text-muted">
              Καμία εγγραφή
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($displayRows as $r):
            $charges  = (float)($r['charges']  ?? 0);
            $payments = (float)($r['payments'] ?? 0);
            $bal      = $charges - $payments;
            $cls      = $bal > 0
              ? 'balances-pos'
              : ($bal < 0 ? 'balances-neg' : 'balances-zero');
          ?>
            <tr>
              <td><?= (int)($r['id'] ?? 0) ?></td>
              <td><?= htmlspecialchars($r['name'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['last_dt'] ?? '') ?></td>
              <td class="balances-num"><?= number_format($charges, 2, ',', '.') ?></td>
              <td class="balances-num"><?= number_format($payments, 2, ',', '.') ?></td>
              <td class="balances-num <?= $cls ?>">
                <?= number_format($bal, 2, ',', '.') ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const form    = document.getElementById('oldBalancesForm');
  const q       = document.getElementById('q');
  const fromEl  = document.getElementById('from');
  const toEl    = document.getElementById('to');
  const onlyDue = document.getElementById('only_due');

  let timer = null;

  function scheduleSubmit() {
    if (!form) return;
    if (timer) clearTimeout(timer);
    timer = setTimeout(function () {
      form.submit();
    }, 400);
  }

  if (q) {
    q.addEventListener('input', scheduleSubmit);
    q.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault();
      }
    });
  }
  if (fromEl)  fromEl.addEventListener('change', scheduleSubmit);
  if (toEl)    toEl.addEventListener('change', scheduleSubmit);
  if (onlyDue) onlyDue.addEventListener('change', scheduleSubmit);
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
