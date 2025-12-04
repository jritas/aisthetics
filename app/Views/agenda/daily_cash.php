<?php
// Αναμένονται μεταβλητές από controller:
// $d, $sum_amount, $sum_discount, $sum_gross,
// $receipt_summary (method=>amount), $receipt_total,
// $receipt_rows (list), $method_labels
$title = 'Ημερήσιο Ταμείο';
ob_start();
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h5 class="card-title mb-0">Ημερήσιο Ταμείο</h5>
      <div class="d-flex align-items-center gap-2">
        <form method="get" class="d-flex align-items-center gap-2">
          <input type="hidden" name="r" value="agenda">
          <input type="hidden" name="a" value="daily_cash">
          <input type="date" name="d" value="<?= htmlspecialchars($d) ?>" class="form-control" style="width: 180px;">
          <button class="btn btn-outline-secondary" type="submit">Εμφάνιση</button>
        </form>
        <a class="btn btn-outline-primary"
           href="?r=agenda&a=daily_cash&d=<?= urlencode($d) ?>&csv=1">Εξαγωγή CSV</a>
        <a class="btn btn-outline-secondary" href="?r=agenda">↩ Ραντεβού</a>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card h-100 border-secondary">
          <div class="card-body">
            <h6 class="text-muted">Χρεώσεις ημέρας (Payments)</h6>
            <div class="mt-2">
              <div class="d-flex justify-content-between">
                <span>Ακαθάριστα (ποσά)</span>
                <strong><?= number_format($sum_amount, 2) ?> €</strong>
              </div>
              <div class="d-flex justify-content-between">
                <span>Εκπτώσεις</span>
                <strong><?= number_format($sum_discount, 2) ?> €</strong>
              </div>
              <hr class="my-2">
              <div class="d-flex justify-content-between">
                <span>Καθαρά (μετά από εκπτώσεις)</span>
                <strong class="text-success"><?= number_format($sum_gross, 2) ?> €</strong>
              </div>
            </div>
            <small class="text-secondary d-block mt-2">
              Σημείωση: Περιλαμβάνονται ΟΛΕΣ οι χρεώσεις της ημέρας, ανεξάρτητα από το αν εξοφλήθηκαν.
            </small>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="card h-100 border-secondary">
          <div class="card-body">
            <h6 class="text-muted">Σύνοψη εισπράξεων (Receipts) ανά μέθοδο</h6>
            <div class="mt-2">
              <?php
                $order = ['cash','card','bank','other',''];
                $shown = [];
                foreach ($order as $m) {
                  if (isset($receipt_summary[$m])) {
                    $shown[$m] = $receipt_summary[$m];
                  }
                }
                // ό,τι έμεινε (ασυνήθιστες τιμές)
                foreach ($receipt_summary as $m=>$v) {
                  if (!array_key_exists($m,$shown)) $shown[$m] = $v;
                }
              ?>
              <?php foreach ($shown as $m=>$amt): ?>
                <div class="d-flex justify-content-between">
                  <span><?= htmlspecialchars($method_labels[$m] ?? $m) ?></span>
                  <strong><?= number_format($amt, 2) ?> €</strong>
                </div>
              <?php endforeach; ?>
              <hr class="my-2">
              <div class="d-flex justify-content-between">
                <span>Σύνολο εισπράξεων</span>
                <strong class="text-primary"><?= number_format($receipt_total, 2) ?> €</strong>
              </div>
            </div>
            <small class="text-secondary d-block mt-2">
              Σημείωση: Οι εισπράξεις μπορεί να αφορούν και παλαιότερες οφειλές (allocate).
            </small>
          </div>
        </div>
      </div>

      <div class="col-12">
        <div class="card border-secondary">
          <div class="card-body">
            <h6 class="text-muted">Αναλυτικά εισπράξεις ημέρας</h6>
            <div class="table-responsive mt-2">
              <table class="table table-sm align-middle">
                <thead>
                  <tr>
                    <th style="width:120px;">Ημ/νία</th>
                    <th style="width:80px;">Ώρα</th>
                    <th>Ασθενής</th>
                    <th style="width:160px;">Μέθοδος</th>
                    <th class="text-end" style="width:120px;">Ποσό (€)</th>
                    <th>Σχόλιο</th>
                  </tr>
                </thead>
                <tbody>
                <?php if (empty($receipt_rows)): ?>
                  <tr><td colspan="6" class="text-secondary">Δεν βρέθηκαν εισπράξεις για την ημέρα.</td></tr>
                <?php else: ?>
                  <?php foreach ($receipt_rows as $r):
                    $dt = new DateTime($r['received_at']);
                    $d  = $dt->format('d/m/Y');
                    $t  = $dt->format('H:i');
                    $pat= (string)($r['patient_name'] ?? '');
                    $m  = (string)($r['method'] ?? '');
                    $ml = $method_labels[$m] ?? ($m !== '' ? $m : '—');
                    $amt= number_format((float)$r['amount'], 2);
                    $note=(string)($r['note'] ?? '');
                  ?>
                  <tr>
                    <td><?= $d ?></td>
                    <td><?= $t ?></td>
                    <td><?= htmlspecialchars($pat) ?></td>
                    <td><?= htmlspecialchars($ml) ?></td>
                    <td class="text-end"><?= $amt ?></td>
                    <td><?= htmlspecialchars($note) ?></td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
                <?php if (!empty($receipt_rows)): ?>
                <tfoot>
                  <tr>
                    <th colspan="4" class="text-end">Σύνολο</th>
                    <th class="text-end"><?= number_format($receipt_total, 2) ?></th>
                    <th></th>
                  </tr>
                </tfoot>
                <?php endif; ?>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
