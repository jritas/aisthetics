<?php
// Μεταβλητές από controller:
// $title, $from_date, $to_date,
// $sum_amount, $sum_discount, $sum_gross,
// $receipt_summary, $receipt_total,
// $receipt_rows, $method_labels
ob_start();
?>
<div class="container-fluid py-3">
  <div class="card shadow-sm">
    <div class="card-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h5 class="card-title mb-0">Ταμείο περιόδου</h5>
        <form method="get" class="d-flex flex-wrap align-items-center gap-2">
          <input type="hidden" name="r" value="reports">
          <input type="hidden" name="a" value="cash_period">
          <div class="d-flex align-items-center gap-1">
            <label for="from" class="form-label mb-0 small">Από</label>
            <input type="date" class="form-control form-control-sm" id="from" name="from"
                   value="<?= htmlspecialchars($from_date) ?>">
          </div>
          <div class="d-flex align-items-center gap-1">
            <label for="to" class="form-label mb-0 small">Έως</label>
            <input type="date" class="form-control form-control-sm" id="to" name="to"
                   value="<?= htmlspecialchars($to_date) ?>">
          </div>
          <button type="submit" class="btn btn-sm btn-primary">
            Εμφάνιση
          </button>
          <?php
            $csvParams = [
              'r'    => 'reports',
              'a'    => 'cash_period',
              'from' => $from_date,
              'to'   => $to_date,
              'csv'  => 1,
            ];
          ?>
          <a href="?<?= http_build_query($csvParams) ?>" class="btn btn-sm btn-outline-secondary">
            CSV
          </a>
        </form>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-lg-6">
          <div class="card h-100 border-secondary">
            <div class="card-body">
              <h6 class="text-muted">Χρεώσεις περιόδου (payments)</h6>
              <div class="d-flex justify-content-between mt-2">
                <span>Σύνολο ποσού</span>
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
            <small class="text-secondary d-block px-3 pb-2">
              Περιλαμβάνονται όλες οι χρεώσεις του επιλεγμένου διαστήματος,
              ανεξάρτητα από το αν εξοφλήθηκαν.
            </small>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card h-100 border-secondary">
            <div class="card-body">
              <h6 class="text-muted">Σύνοψη εισπράξεων περιόδου</h6>
              <div class="mt-2">
                <?php
                  $order = ['cash','card','bank','other',''];
                  $shown = [];
                  foreach ($order as $m) {
                    if (isset($receipt_summary[$m])) {
                      $shown[$m] = $receipt_summary[$m];
                    }
                  }
                  foreach ($receipt_summary as $m=>$v) {
                    if (!array_key_exists($m,$shown)) {
                      $shown[$m] = $v;
                    }
                  }
                ?>
                <?php if (empty($shown)): ?>
                  <div class="text-muted small">Δεν υπάρχουν εισπράξεις στην περίοδο.</div>
                <?php else: ?>
                  <?php foreach ($shown as $m=>$amt): ?>
                    <div class="d-flex justify-content-between">
                      <span><?= htmlspecialchars($method_labels[$m] ?? $m) ?></span>
                      <strong><?= number_format($amt, 2) ?> €</strong>
                    </div>
                  <?php endforeach; ?>
                  <hr class="my-2">
                  <div class="d-flex justify-content-between">
                    <span>Σύνολο εισπράξεων</span>
                    <strong class="text-success"><?= number_format($receipt_total, 2) ?> €</strong>
                  </div>
                <?php endif; ?>
              </div>
            </div>
            <small class="text-secondary d-block px-3 pb-2">
              Εισπράξεις από το νέο σύστημα αποδείξεων, καθώς και παλαιές
              εισπράξεις χωρίς αποδείξεις (eispraxi/amount_received).
            </small>
          </div>
        </div>
      </div>

      <div class="row g-3">
        <div class="col-12">
          <div class="card border-secondary">
            <div class="card-body">
              <h6 class="text-muted">Αναλυτικές εισπράξεις περιόδου</h6>
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
                      <tr>
                        <td colspan="6" class="text-center text-muted">
                          Δεν βρέθηκαν εισπράξεις για το επιλεγμένο διάστημα.
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($receipt_rows as $r): ?>
                        <?php
                          $dt = new DateTime($r['received_at']);
                          $d = $dt->format('d/m/Y');
                          $t = $dt->format('H:i');
                          $pat = (string)($r['patient_name'] ?? '');
                          $mid = (string)($r['method'] ?? '');
                          $mLabel = $mid === '' ? '—' : ($method_labels[$mid] ?? $mid);
                          $amt = (float)$r['amount'];
                          $note = (string)($r['note'] ?? '');
                        ?>
                        <tr>
                          <td><?= $d ?></td>
                          <td><?= $t ?></td>
                          <td><?= htmlspecialchars($pat) ?></td>
                          <td><?= htmlspecialchars($mLabel) ?></td>
                          <td class="text-end"><?= number_format($amt, 2) ?> €</td>
                          <td><?= htmlspecialchars($note) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
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
