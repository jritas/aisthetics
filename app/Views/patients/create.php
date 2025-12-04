<?php
// Φόρμα «Νέος πελάτης»
$title = 'Νέος πελάτης';
$errors = $errors ?? [];
$old    = $old    ?? [];
$csrf   = $csrf   ?? '';
ob_start();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Νέος πελάτης</h1>
  <a href="?r=patients" class="btn btn-sm btn-outline-secondary">« Επιστροφή στο μητρώο</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" action="?r=patients&a=store" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Ονοματεπώνυμο *</label>
          <input type="text" name="name" class="form-control"
                 required
                 value="<?= htmlspecialchars($old['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <?php if (!empty($errors['name'])): ?>
            <div class="text-danger small mt-1"><?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
        </div>

        <div class="col-md-3">
          <label class="form-label">Τηλέφωνο</label>
          <input type="text" name="phone" class="form-control"
                 value="<?= htmlspecialchars($old['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-md-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control"
                 value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <?php if (!empty($errors['email'])): ?>
            <div class="text-danger small mt-1"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
        </div>

        <div class="col-md-3">
          <label class="form-label">Ημ. γέννησης</label>
          <input type="date" name="birthdate" class="form-control"
                 value="<?= htmlspecialchars($old['birthdate'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          <?php if (!empty($errors['birthdate'])): ?>
            <div class="text-danger small mt-1"><?= htmlspecialchars($errors['birthdate'], ENT_QUOTES, 'UTF-8') ?></div>
          <?php endif; ?>
        </div>

        <div class="col-md-9">
          <label class="form-label">Διεύθυνση</label>
          <input type="text" name="address" class="form-control"
                 value="<?= htmlspecialchars($old['address'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        </div>

        <div class="col-12">
          <label class="form-label">Σημειώσεις (memo)</label>
          <textarea name="memo" rows="3" class="form-control"><?= htmlspecialchars($old['memo'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>
      </div>

      <div class="mt-4 d-flex justify-content-between">
        <button type="submit" class="btn btn-primary">Αποθήκευση</button>
        <a href="?r=patients" class="btn btn-outline-secondary">Ακύρωση</a>
      </div>
    </form>
  </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
