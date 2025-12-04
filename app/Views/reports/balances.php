<?php
// app/Views/reports/balances.php
$title        = 'Υπόλοιπα πελατών – Νέα λογική';
$view_q       = $view_q       ?? '';
$view_onlyDue = $view_onlyDue ?? '0';
$view_sort    = $view_sort    ?? 'last_movement';
$view_dir     = $view_dir     ?? 'DESC';
$view_rows    = $view_rows    ?? [];
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
  .balances-zero {
    color: var(--bs-secondary-color);
  }
  .balances-sortable {
    cursor: pointer;
    user-select: none;
  }
</style>

<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="card-title mb-0">
        Υπόλοιπα πελατών
        <span class="badge rounded-pill bg-success ms-2">ΝΕΑ ΛΟΓΙΚΗ</span>
      </h5>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="history.back()">Πίσω</button>
    </div>

    <div class="row g-2 align-items-center mb-3">
      <div class="col-md-6">
        <input id="q" class="form-control"
               placeholder="Αναζήτηση σε όνομα ή τηλέφωνο"
               value="<?= htmlspecialchars($view_q, ENT_QUOTES) ?>">
      </div>
      <div class="col-auto form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="onlyDue"
          <?= ($view_onlyDue === '1') ? 'checked' : '' ?>>
        <label class="form-check-label" for="onlyDue">Μόνο με υπόλοιπο</label>
      </div>
      <div class="col-auto">
        <button id="btnSearch" class="btn btn-primary">Αναζήτηση</button>
      </div>
    </div>

    <div class="table-responsive border rounded">
      <table class="table table-hover align-middle m-0 balances-table" id="grid">
        <thead>
        <tr>
          <th class="balances-sortable" data-sort="last_movement">Τελευταία κίνηση</th>
          <th class="balances-sortable" data-sort="name">Όνομα</th>
          <th class="balances-sortable" data-sort="phone">Τηλέφωνο</th>
          <th class="balances-sortable text-end" data-sort="charges">Χρεώσεις (€)</th>
          <th class="balances-sortable text-end" data-sort="payments">Πληρωμές (€)</th>
          <th class="balances-sortable text-end" data-sort="balance">Υπόλοιπο (€)</th>
        </tr>
        </thead>
        <tbody id="rows">
        <?php if (!empty($view_rows)): foreach ($view_rows as $r):
          $bal = (float)($r['balance'] ?? 0); ?>
          <tr>
            <td><?= htmlspecialchars($r['last_movement'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['name'] ?? '', ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($r['phone'] ?? '', ENT_QUOTES) ?></td>
            <td class="balances-num">
              <?= number_format((float)($r['charges'] ?? 0), 2, ',', '.') ?>
            </td>
            <td class="balances-num">
              <?= number_format((float)($r['payments'] ?? 0), 2, ',', '.') ?>
            </td>
            <td class="balances-num <?= $bal > 0 ? 'balances-pos' : 'balances-zero' ?>">
              <?= number_format($bal, 2, ',', '.') ?>
            </td>
          </tr>
        <?php endforeach;
        else: ?>
          <tr>
            <td colspan="6" class="text-center text-secondary p-4">Καμία εγγραφή</td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const qEl       = document.getElementById('q');
  const onlyDueEl = document.getElementById('onlyDue');
  const rowsEl    = document.getElementById('rows');
  const btnEl     = document.getElementById('btnSearch');

  const state = {
    sort: '<?= addslashes($view_sort) ?>' || 'last_movement',
    dir:  '<?= addslashes($view_dir)  ?>' || 'DESC'
  };

  let timer = null;

  function esc(s) {
    return String(s).replace(/[&<>"']/g, m => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));
  }

  function scheduleFetch() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(fetchRows, 400);  // debounce
  }

  async function fetchRows() {
    const params = new URLSearchParams();
    params.set('r', 'reports');
    params.set('a', 'balances');
    params.set('ajax', '1');
    params.set('q', qEl.value.trim());
    params.set('only_due', onlyDueEl.checked ? '1' : '0');
    params.set('sort', state.sort);
    params.set('dir', state.dir);

    const res = await fetch('?' + params.toString(), {
      headers: { 'Accept': 'application/json' }
    });

    let data = null;
    try { data = await res.json(); } catch (e) {}

    if (!data || !data.ok) {
      rowsEl.innerHTML =
        '<tr><td colspan="6" class="text-danger p-3">Σφάλμα JSON</td></tr>';
      return;
    }

    const fmt = (n) => Number(n)
      .toLocaleString('el-GR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    rowsEl.innerHTML = '';
    if (!data.rows.length) {
      rowsEl.innerHTML =
        '<tr><td colspan="6" class="text-center text-secondary p-4">Καμία εγγραφή</td></tr>';
      return;
    }

    data.rows.forEach(r => {
      const bal = Number(r.balance || 0);
      const tr = document.createElement('tr');
      tr.innerHTML = ''
        + '<td>' + esc(r.last_movement || '') + '</td>'
        + '<td>' + esc(r.name || '') + '</td>'
        + '<td>' + esc(r.phone || '') + '</td>'
        + '<td class="balances-num">' + fmt(r.charges || 0) + '</td>'
        + '<td class="balances-num">' + fmt(r.payments || 0) + '</td>'
        + '<td class="balances-num ' + (bal > 0 ? 'balances-pos' : 'balances-zero') + '">'
        + fmt(bal) + '</td>';
      rowsEl.appendChild(tr);
    });
  }

  if (btnEl) {
    btnEl.addEventListener('click', function (ev) {
      ev.preventDefault();
      fetchRows();
    });
  }

  if (qEl) {
    qEl.addEventListener('input', scheduleFetch);
    qEl.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') {
        ev.preventDefault(); // να μην κάνει full submit
      }
    });
  }

  if (onlyDueEl) {
    onlyDueEl.addEventListener('change', scheduleFetch);
  }

  document.querySelectorAll('.balances-sortable').forEach(th => {
    th.addEventListener('click', () => {
      const s = th.getAttribute('data-sort');
      if (!s) return;
      if (state.sort === s) {
        state.dir = (state.dir === 'ASC') ? 'DESC' : 'ASC';
      } else {
        state.sort = s;
        state.dir  = 'ASC';
      }
      scheduleFetch();
    });
  });
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
