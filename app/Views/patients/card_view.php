<?php
$title = 'Καρτέλα πελάτη';
ob_start();

$fmtAmount = function($v): string {
  return number_format((float)$v, 2, ',', '.');
};

$fmtDateTime = function($dt): string {
  if (empty($dt)) { return '-'; }
  try {
    $d = new DateTime($dt);
    return $d->format('d/m/Y H:i');
  } catch (Throwable $e) {
    return (string)$dt;
  }
};

// Υπολογίζουμε ένα URL για το GDPR (τρέχει σε tablet στο /gdpr/index.html)
$gdprBase = getenv('GDPR_BASE_URL') ?: '/gdpr/index.html';
$sep      = (strpos($gdprBase, '?') === false) ? '?' : '&';
$gdprUrl  = $gdprBase . $sep . 'patient_id=' . (int)($patient['id'] ?? 0);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <a href="?r=patients&a=cards_list" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left"></i> Επιστροφή στη λίστα
  </a>
  <div class="d-flex gap-2">
    <button type="button" id="printCardBtn" class="btn btn-sm btn-outline-secondary">
      Εκτύπωση
    </button>
    <a href="?r=patients&a=card_csv&id=<?= (int)$patient['id'] ?>" class="btn btn-sm btn-outline-secondary">
      CSV επισκέψεων
    </a>
    <a href="?r=patients&a=edit&id=<?= (int)$patient['id'] ?>" class="btn btn-sm btn-outline-primary">
      Επεξεργασία στοιχείων
    </a>
    <a href="?r=agenda&a=checkin&patient_id=<?= (int)$patient['id'] ?>" class="btn btn-sm btn-primary">
      Νέα επίσκεψη
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <h5 class="card-title mb-3">Στοιχεία πελάτη</h5>
        <p class="mb-1">
          <strong>#<?= (int)$patient['id'] ?></strong> — <?= htmlspecialchars($patient['name'] ?? '') ?>
        </p>
        <p class="mb-1 text-secondary">
          Τηλέφωνο:
          <?php if (!empty($patient['phone'])): ?>
            <?= htmlspecialchars($patient['phone']) ?>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </p>
        <p class="mb-1 text-secondary">
          Email:
          <?php if (!empty($patient['email'])): ?>
            <?= htmlspecialchars($patient['email']) ?>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </p>
        <p class="mb-1 text-secondary">
          Ημερ. γέννησης:
          <?php if (!empty($patient['birthdate'])): ?>
            <?php
              try {
                $bd = new DateTime($patient['birthdate']);
                $bdStr = $bd->format('d/m/Y');
              } catch (Throwable $e) {
                $bdStr = (string)$patient['birthdate'];
              }
            ?>
            <?= htmlspecialchars($bdStr) ?>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </p>
        <p class="mb-3 text-secondary">
          Διεύθυνση:
          <?php if (!empty($patient['address'])): ?>
            <?= htmlspecialchars($patient['address']) ?>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </p>

        <p class="mb-2">
          GDPR:
          <?php if (!empty($patient['gdpr_yes'])): ?>
            <span class="badge bg-success">ΝΑΙ</span>
          <?php else: ?>
            <span class="badge bg-secondary">ΟΧΙ</span>
          <?php endif; ?>
        </p>
        <p class="mb-3">
          <a href="<?= htmlspecialchars($gdprUrl) ?>" target="_blank" class="btn btn-sm btn-outline-light">
            Προβολή / συμπλήρωση GDPR
          </a>
        </p>

        <?php if (!empty($patient['memo'])): ?>
          <hr>
          <h6 class="mb-2">Σημειώσεις</h6>
          <p class="mb-0"><?= nl2br(htmlspecialchars($patient['memo'])) ?></p>
        <?php endif; ?>

        <hr>

        <h6 class="mb-2">Σύνοψη οικονομικών</h6>
        <p class="mb-1">
          Σύνολο αξίας:
          <strong><?= $fmtAmount($summary['charges'] ?? 0) ?></strong>
        </p>
        <p class="mb-1">
          Εισπράξεις:
          <strong><?= $fmtAmount($summary['payments'] ?? 0) ?></strong>
        </p>
        <p class="mb-1">
          Υπόλοιπο:
          <?php $bal = (float)($summary['balance'] ?? 0); ?>
          <strong class="<?= $bal > 0.01 ? 'text-warning' : 'text-success' ?>">
            <?= $fmtAmount($bal) ?>
          </strong>
        </p>
        <?php if (!empty($summary['last_movement'])): ?>
          <p class="mb-0 text-secondary small">
            Τελευταία κίνηση: <?= htmlspecialchars($fmtDateTime($summary['last_movement'])) ?>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card shadow-sm h-100">
      <div class="card-body">
        <h5 class="card-title mb-3">Ιστορικό επισκέψεων</h5>
        <?php if (empty($visits)): ?>
          <p class="text-muted mb-0">Δεν υπάρχουν καταχωρημένες επισκέψεις για τον πελάτη.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead>
                <tr>
                  <th>Ημερομηνία</th>
                  <th>Υπηρεσία</th>
                  <th>Γιατρός</th>
                  <th class="text-end">Ποσό</th>
                  <th>Κατάσταση</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($visits as $v): ?>
                  <tr>
                    <td><?= htmlspecialchars($fmtDateTime($v['date'] ?? null)) ?></td>
                    <td><?= htmlspecialchars($v['category_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($v['doctor_name'] ?? '') ?></td>
                    <td class="text-end"><?= $fmtAmount($v['gross_total'] ?? 0) ?></td>
                    <td>
                      <?php
                        $status = (string)($v['status'] ?? '');
                        $label  = '';
                        $cls    = 'bg-secondary';
                        if ($status === 'paid') {
                          $label = 'Εξοφλημένο';
                          $cls   = 'bg-success';
                        } elseif ($status === 'partial') {
                          $label = 'Μερικώς';
                          $cls   = 'bg-warning text-dark';
                        } elseif ($status === 'pending') {
                          $label = 'Ανοιχτό';
                          $cls   = 'bg-danger';
                        } else {
                          $label = $status;
                        }
                      ?>
                      <?php if ($label !== ''): ?>
                        <span class="badge <?= $cls ?>"><?= htmlspecialchars($label) ?></span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
  var btn = document.getElementById('printCardBtn');
  if (!btn) return;
  btn.addEventListener('click', function (e) {
    e.preventDefault();
    window.print(); // ο browser θα στείλει στην προεπιλεγμένη εκτυπωτική ρύθμιση
  });
})();
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
