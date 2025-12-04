<?php
// Μεταβλητές από controller:
// $title, $from_date, $to_date,
// $patients_list, $selected_patient_id, $current_patient,
// $movements_data, $total_charges, $total_receipts, $final_balance
ob_start();
?>
<div class="container-fluid py-3">
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <h5 class="card-title mb-3">Ταμείο πελάτη</h5>
      <form method="get" class="row g-2 align-items-end">
        <input type="hidden" name="r" value="reports">
        <input type="hidden" name="a" value="cash_patient">

        <div class="col-md-4">
          <label for="patient_id" class="form-label small mb-1">Πελάτης</label>
          <select class="form-select form-select-sm" id="patient_id" name="patient_id">
            <option value="0">– Επιλέξτε πελάτη –</option>
            <?php foreach ($patients_list as $p): ?>
              <?php
                $pid = (int)$p['id'];
                $label = $p['name'];
                if (!empty($p['phone'])) {
                  $label .= ' (' . $p['phone'] . ')';
                }
              ?>
              <option value="<?= $pid ?>" <?= $pid === (int)$selected_patient_id ? 'selected' : '' ?>>
                <?= htmlspecialchars($label) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label for="from" class="form-label small mb-1">Από</label>
          <input type="date" class="form-control form-control-sm" id="from" name="from"
                 value="<?= htmlspecialchars($from_date) ?>">
        </div>
        <div class="col-md-3">
          <label for="to" class="form-label small mb-1">Έως</label>
          <input type="date" class="form-control form-control-sm" id="to" name="to"
                 value="<?= htmlspecialchars($to_date) ?>">
        </div>

        <div class="col-md-2 d-flex gap-2">
          <button type="submit" class="btn btn-sm btn-primary flex-fill">
            Εμφάνιση
          </button>
          <?php if ($selected_patient_id > 0 && !empty($movements_data)): ?>
            <?php
              $csvParams = [
                'r'          => 'reports',
                'a'          => 'cash_patient',
                'patient_id' => $selected_patient_id,
                'from'       => $from_date,
                'to'         => $to_date,
                'csv'        => 1,
              ];
            ?>
            <a href="?<?= http_build_query($csvParams) ?>"
               class="btn btn-sm btn-outline-secondary flex-fill">
              CSV
            </a>
          <?php endif; ?>
        </div>
      </form>
      <small class="text-muted d-block mt-2">
        Προεπιλογή εύρους: από <?= htmlspecialchars(date('01/01/Y', strtotime($from_date))) ?>
        έως σήμερα, αν δεν δοθεί κάτι διαφορετικό.
      </small>
    </div>
  </div>

  <?php if ($selected_patient_id > 0 && $current_patient): ?>
    <div class="row g-3 mb-3">
      <div class="col-md-4">
        <div class="card border-secondary h-100">
          <div class="card-body">
            <h6 class="text-muted">Στοιχεία πελάτη</h6>
            <div><strong><?= htmlspecialchars($current_patient['name']) ?></strong></div>
            <?php if (!empty($current_patient['phone'])): ?>
              <div class="small text-muted">Τηλ: <?= htmlspecialchars($current_patient['phone']) ?></div>
            <?php endif; ?>
            <div class="mt-2">
              <a href="?r=patients&a=card_view&id=<?= (int)$current_patient['id'] ?>"
                 class="btn btn-sm btn-outline-primary">
                Προβολή καρτέλας
              </a>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-8">
        <div class="card border-secondary h-100">
          <div class="card-body">
            <h6 class="text-muted">Σύνοψη κινήσεων</h6>
            <div class="d-flex justify-content-between mt-2">
              <span>Σύνολο χρεώσεων</span>
              <strong><?= number_format($total_charges, 2) ?> €</strong>
            </div>
            <div class="d-flex justify-content-between">
              <span>Σύνολο εισπράξεων</span>
              <strong><?= number_format($total_receipts, 2) ?> €</strong>
            </div>
            <hr class="my-2">
            <div class="d-flex justify-content-between">
              <span>Τελικό υπόλοιπο</span>
              <strong class="<?= $final_balance >= 0 ? 'text-danger' : 'text-success' ?>">
                <?= number_format($final_balance, 2) ?> €
              </strong>
            </div>
            <small class="text-muted d-block mt-2">
              Θετικό υπόλοιπο σημαίνει ότι ο πελάτης οφείλει, αρνητικό ότι
              έχει πληρώσει περισσότερα από τις χρεώσεις στο διάστημα.
            </small>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-secondary">
      <div class="card-body">
        <h6 class="text-muted">Αναλυτικές κινήσεις</h6>
        <div class="table-responsive mt-2">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th style="width:120px;">Ημ/νία</th>
                <th style="width:80px;">Ώρα</th>
                <th>Κίνηση</th>
                <th class="text-end" style="width:120px;">Χρέωση (€)</th>
                <th class="text-end" style="width:120px;">Είσπραξη (€)</th>
                <th class="text-end" style="width:120px;">Υπόλοιπο (€)</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($movements_data)): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted">
                    Δεν βρέθηκαν κινήσεις για τον επιλεγμένο πελάτη και διάστημα.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($movements_data as $m): ?>
                  <?php
                    $dt = new DateTime($m['datetime']);
                    $d  = $dt->format('d/m/Y');
                    $t  = $dt->format('H:i');
                  ?>
                  <tr>
                    <td><?= $d ?></td>
                    <td><?= $t ?></td>
                    <td><?= htmlspecialchars($m['desc']) ?></td>
                    <td class="text-end">
                      <?= $m['debit'] > 0 ? number_format($m['debit'], 2) . ' €' : '' ?>
                    </td>
                    <td class="text-end">
                      <?= $m['credit'] > 0 ? number_format($m['credit'], 2) . ' €' : '' ?>
                    </td>
                    <td class="text-end">
                      <?= number_format($m['balance'], 2) ?> €
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php elseif ($selected_patient_id > 0): ?>
    <div class="alert alert-warning">
      Δεν βρέθηκαν στοιχεία πελάτη.
    </div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
