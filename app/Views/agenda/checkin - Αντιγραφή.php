<?php
$title = 'Επίσκεψη';
ob_start();
?>
<div class="row g-3">
  <!-- ============ LEFT COLUMN ============ -->
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <h5 class="card-title">Ιστορικό επισκέψεων</h5>

        <?php
        // helper: array/object safe get
        $__get = function($row, $key, $default='') {
          if (is_array($row))  return $row[$key]  ?? $default;
          if (is_object($row)) return $row->$key ?? $default;
          return $default;
        };
        $__history = isset($history) ? $history : (isset($visits) ? $visits : []);
        ?>

        <?php if (empty($__history)): ?>
          <p class="text-secondary">Δεν βρέθηκαν προηγούμενες επισκέψεις.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle" id="historyTable">
              <thead>
                <tr>
                  <th style="width: 46%;">Ημ/νία</th>
                  <th>Θεραπεία</th>
                  <th class="text-end" style="width: 100px;">Ποσό</th>
                </tr>
              </thead>
              <tbody>
                <?php $i=0; foreach ($__history as $h): 
                  $i++;
                  $dateTxt   = htmlspecialchars($__get($h,'dt', $__get($h,'date','')));
                  $therTxt   = htmlspecialchars($__get($h,'category_name', $__get($h,'treatment','')));
                  $amount    = number_format((float)$__get($h,'gross_total',0), 2);
                  $treatRaw  = trim((string)$__get($h,'treatment',''));
                  $medRaw    = trim((string)$__get($h,'medicine',''));
                  $hasDet    = ($treatRaw!=='' || $medRaw!=='');
                  $detId     = "visit-det-$i";
                ?>
                  <tr class="visit-row <?= $hasDet?'cursor-pointer':'' ?>" <?= $hasDet ? 'data-target="'.$detId.'"' : '' ?>>
                    <td>
                      <?php if ($hasDet): ?>
                        <span class="chev me-2">▸</span>
                      <?php else: ?>
                        <span class="text-secondary me-2">•</span>
                      <?php endif; ?>
                      <?= $dateTxt ?>
                    </td>
                    <td><?= $therTxt ?></td>
                    <td class="text-end"><?= $amount ?></td>
                  </tr>

                  <?php if ($hasDet): ?>
                    <tr id="<?= $detId ?>" class="visit-details d-none">
                      <td colspan="3">
                        <?php if ($treatRaw!==''): ?>
                          <div class="mb-1">
                            <span class="badge bg-secondary me-2">Οδηγίες</span>
                            <span class="text-light"><?= nl2br(htmlspecialchars($treatRaw)) ?></span>
                          </div>
                        <?php endif; ?>
                        <?php if ($medRaw!==''): ?>
                          <div>
                            <span class="badge bg-info text-dark me-2">Αγωγή</span>
                            <span class="text-light"><?= nl2br(htmlspecialchars($medRaw)) ?></span>
                          </div>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tbody>
            </table>

            <hr class="border-secondary opacity-25 mt-3 mb-2">
            <div class="px-2 pb-2">
              <div class="text-muted small mb-2">Σημειώσεις πελάτη</div>
              <div class="bg-dark rounded p-3 border border-secondary">
                <?php
                  $memo = '';
                  if (isset($patient)) {
                    if (is_array($patient))  $memo = (string)($patient['memo'] ?? '');
                    if (is_object($patient)) $memo = (string)($patient->memo ?? '');
                  }
                  echo $memo !== ''
                    ? nl2br(htmlspecialchars($memo))
                    : '<span class="text-secondary">—</span>';
                ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ============ RIGHT COLUMN ============ -->
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h5 class="card-title mb-0">Νέα επίσκεψη</h5>
          <div>
            <?php $bal = $patientBalance ?? 0.0; $cls = $bal > 0 ? 'bg-danger' : ($bal < 0 ? 'bg-info' : 'bg-success'); ?>
            <span class="badge <?= $cls ?> px-3 py-2 me-2" id="balanceBadge" data-balance="<?= number_format($bal,2,'.','') ?>">
              Υπόλοιπο: € <span id="balanceNow"><?= number_format($bal,2) ?></span>
            </span>
            <span class="badge bg-secondary px-3 py-2 me-2" id="balanceAfterWrap">
              Μετά την κίνηση: € <span id="balanceAfter">0.00</span>
            </span>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnPayBalance" <?= ($bal<=0 ? 'disabled' : '') ?>>Εξόφληση υπολοίπου</button>
          </div>
        </div>

        <small id="calcHelp" class="text-muted d-block mb-2">
          Υπολογισμός: <code>Μετά = Τρέχον + Καθαρό&nbsp;Σύνολο − Είσπραξη</code>.
          Όταν είναι τσεκαρισμένο <em>«Είσπραξη οφειλής»</em>, το Καθαρό&nbsp;Σύνολο θεωρείται 0.
          <span class="d-block mt-1">Τρέχον=<span id="hCur">0.00</span>, Καθαρό&nbsp;Σύνολο=<span id="hNet">0.00</span>, Είσπραξη=<span id="hRecv">0.00</span> ⟶ Μετά=<strong id="hAfter">0.00</strong></span>
        </small>

        <form method="post" action="?r=agenda&a=save_visit" id="visitForm">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="patient_id" value="<?= (int)(is_array($patient)?($patient['id']??0):($patient->id??0)) ?>">

          <div class="row g-2 align-items-end mb-2">
            <div class="col-8 position-relative">
              <label class="form-label">Θεραπεία</label>
              <input class="form-control" id="svcName" placeholder="Πληκτρολόγησε για αναζήτηση…">
              <div class="list-group position-absolute w-100" id="svcList" style="z-index:1000; max-height:300px; overflow:auto;"></div>
            </div>
            <div class="col-4">
              <label class="form-label">Ποσό (€)</label>
              <input class="form-control text-end" id="svcAmount" type="number" min="0" step="0.01" placeholder="0.00">
            </div>
            <div class="col-12">
              <button class="btn btn-outline-primary" type="button" id="addLine">+ Προσθήκη</button>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-sm" id="cart">
              <thead><tr><th>Θεραπεία</th><th style="width:140px" class="text-end">Ποσό (€)</th><th style="width:80px"></th></tr></thead>
              <tbody></tbody>
              <tfoot>
                <tr>
                  <th class="text-end">Σύνολο</th>
                  <th class="text-end" id="sumCell">0.00</th>
                  <th></th>
                </tr>
                <tr>
                  <th class="text-end">Τελικό σύνολο (με έκπτωση)</th>
                  <th class="text-end fw-bold" id="netCell">0.00</th>
                  <th></th>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="row g-2 mt-2">
            <div class="col-sm-4">
              <label class="form-label">Έκπτωση (€)</label>
              <input class="form-control text-end" name="discount" id="discount" type="number" min="0" step="0.01" value="0.00">
            </div>
            <div class="col-sm-4">
              <label class="form-label">Ποσό είσπραξης (€)</label>
              <input class="form-control text-end" name="received" id="received" type="number" min="0" step="0.01" value="0.00">
            </div>
            <div class="col-sm-4 d-flex align-items-end">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="collection_only" name="collection_only">
                <label class="form-check-label" for="collection_only">Είσπραξη οφειλής (χωρίς νέες θεραπείες)</label>
              </div>
            </div>
          </div>

          <div class="row g-2 mt-2">
            <div class="col-12">
              <label class="form-label">Οδηγίες (treatment)</label>
              <textarea class="form-control" name="treatment" rows="2" placeholder="Σύντομες οδηγίες..."></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Φαρμακευτική αγωγή (medicine)</label>
              <textarea class="form-control" name="medicine" rows="2" placeholder="Φάρμακα/δοσολογία..."></textarea>
            </div>
          </div>

          <div class="d-flex gap-2 mt-3">
            <button class="btn btn-success" type="submit">Καταχώρηση επίσκεψης</button>
            <a class="btn btn-outline-secondary" href="?r=agenda">Πίσω</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<style>
  .cursor-pointer { cursor: pointer; }
  .visit-details td {
    background: rgba(255,255,255,0.03);
    border-top: 1px dashed rgba(255,255,255,0.15);
  }
</style>

<script>
(function(){
  // ===== Collapsible ιστορικό (▸/▾) =====
  document.querySelectorAll('#historyTable .visit-row[data-target]').forEach(function(tr){
    tr.addEventListener('click', function(){
      const id = tr.getAttribute('data-target');
      const det = document.getElementById(id);
      if (!det) return;
      const hidden = det.classList.contains('d-none');
      det.classList.toggle('d-none');
      const chev = tr.querySelector('.chev');
      if (chev) chev.textContent = hidden ? '▾' : '▸';
    });
  });

  // ===== Το ήδη υπάρχον JS σου για τη δεξιά φόρμα (άγγιχτο) =====
  const svcName = document.getElementById('svcName');
  const svcAmount = document.getElementById('svcAmount');
  const svcList = document.getElementById('svcList');
  const addBtn = document.getElementById('addLine');
  const cartBody = document.querySelector('#cart tbody');
  const sumCell = document.getElementById('sumCell');
  const netCell = document.getElementById('netCell');
  const discount = document.getElementById('discount');
  const received = document.getElementById('received');
  const collection = document.getElementById('collection_only');
  const balanceBadge = document.getElementById('balanceBadge');
  const balanceAfter = document.getElementById('balanceAfter');
  const hCur = document.getElementById('hCur');
  const hNet = document.getElementById('hNet');
  const hRecv = document.getElementById('hRecv');
  const hAfter = document.getElementById('hAfter');

  const fmt = (n)=> (Number(n||0)).toFixed(2);
  const parseN = (v)=> Number(String(v||'0').replace(',', '.')) || 0;

  function recalc(){
    let sum = 0;
    cartBody.querySelectorAll('tr').forEach(tr=>{
      sum += parseN(tr.querySelector('input[name$="[amount]"]').value);
    });
    sumCell.textContent = fmt(sum);
    let net = Math.max(sum - parseN(discount.value), 0);
    if (collection.checked) { net = 0; }
    netCell.textContent = fmt(net);
    updateForecast(net);
  }

  function updateForecast(net){
    const cur = parseN(balanceBadge.getAttribute('data-balance'));
    const recv = parseN(received.value);
    let next = collection.checked ? (cur - recv) : (cur + net - recv);
    balanceAfter.textContent = fmt(next);

    hCur.textContent  = fmt(cur);
    hNet.textContent  = fmt(net);
    hRecv.textContent = fmt(recv);
    hAfter.textContent= fmt(next);

    const wrap = document.getElementById('balanceAfterWrap');
    wrap.classList.remove('bg-danger','bg-success','bg-info','bg-secondary');
    wrap.classList.add(next>0?'bg-danger':(next<0?'bg-info':'bg-success'));
  }

  let suggestTimer;
  svcName.addEventListener('input', function(){
    clearTimeout(suggestTimer);
    const q = this.value.trim();
    if (q.length < 2) { svcList.innerHTML = ''; return; }
    suggestTimer = setTimeout(()=>{
      fetch(`?r=agenda&a=search_services&q=${encodeURIComponent(q)}`)
        .then(r=>r.json())
        .then(rows=>{
          svcList.innerHTML = rows.map(it=>`<button type="button" class="list-group-item list-group-item-action" data-name="${it.name}" data-price="${fmt(it.price||0)}">${it.name} <span class="float-end">${fmt(it.price||0)} €</span></button>`).join('');
        }).catch(()=>{ svcList.innerHTML=''; });
    }, 200);
  });

  svcList.addEventListener('mousedown', (e)=>{
    const btn = e.target.closest('button[data-name]');
    if (!btn) return;
    e.preventDefault();
    svcName.value = btn.dataset.name;
    svcAmount.value = btn.dataset.price || '';
    svcList.innerHTML = '';
    svcAmount.focus();
  });

  function addLine(e){
    if (e) e.preventDefault();
    const name = (svcName.value||'').trim();
    const amount = parseN(svcAmount.value);
    if (!name || !(amount > 0)) { return; }
    const idx = cartBody.querySelectorAll('tr').length;
    const tr = document.createElement('tr');
    tr.innerHTML = `<td><input type="text" class="form-control" name="lines[${idx}][name]" value="${name}"></td>` +
                   `<td class="text-end"><input type="number" step="0.01" min="0" class="form-control text-end" name="lines[${idx}][amount]" value="${fmt(amount)}"></td>` +
                   `<td class="text-end"><button class="btn btn-sm btn-outline-danger remove">Διαγραφή</button></td>`;
    cartBody.appendChild(tr);
    svcName.value=''; svcAmount.value='';
    recalc(); svcName.focus();
  }
  document.getElementById('addLine').addEventListener('click', addLine);
  svcAmount.addEventListener('keydown', (e)=>{ if (e.key === 'Enter') addLine(e); });
  svcName.addEventListener('keydown', (e)=>{ if (e.key === 'Enter') addLine(e); });

  cartBody.addEventListener('input', recalc);
  cartBody.addEventListener('click', (e)=>{
    const btn = e.target.closest('.remove');
    if (!btn) return;
    e.preventDefault();
    btn.closest('tr').remove();
    recalc();
  });

  function toggleCollection(){
    const disabled = collection.checked;
    [svcName, svcAmount, addBtn].forEach(el=>{ el.disabled = disabled; });
    cartBody.querySelectorAll('input,button').forEach(el=> el.disabled = disabled);
    recalc();
  }
  collection.addEventListener('change', toggleCollection);

  discount.addEventListener('input', recalc);
  discount.addEventListener('keyup', recalc);
  const updateByNet = ()=>updateForecast(Number(netCell.textContent.replace(',','.'))||0);
  received.addEventListener('input', updateByNet);
  received.addEventListener('keyup', updateByNet);

  toggleCollection();
  recalc();

  const btnPayBalance = document.getElementById('btnPayBalance');
  if (btnPayBalance) {
    btnPayBalance.addEventListener('click', ()=>{
      const bal = Number((balanceBadge.getAttribute('data-balance')||'0').replace(',','.'))||0;
      if (bal > 0) {
        received.value = fmt(bal);
        updateByNet();
        received.focus();
      }
    });
  }
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
