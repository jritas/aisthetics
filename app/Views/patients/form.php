<?php
$isEdit = !empty($patient['id']);
$title = $isEdit ? 'Επεξεργασία πελάτη' : 'Νέος πελάτης';
$errors = $errors ?? [];
ob_start(); ?>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" action="?r=patients&a=<?= $isEdit ? 'update' : 'store' ?>">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int)$patient['id'] ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Ονοματεπώνυμο *</label>
          <input name="name" class="form-control <?= isset($errors['name'])?'is-invalid':'' ?>"
                 value="<?= htmlspecialchars($patient['name'] ?? '') ?>">
          <?php if(isset($errors['name'])): ?><div class="invalid-feedback"><?= $errors['name'] ?></div><?php endif; ?>
        </div>
        <div class="col-md-3">
          <label class="form-label">Τηλέφωνο</label>
          <input name="phone" class="form-control" value="<?= htmlspecialchars($patient['phone'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Email</label>
          <input name="email" class="form-control <?= isset($errors['email'])?'is-invalid':'' ?>"
                 value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
          <?php if(isset($errors['email'])): ?><div class="invalid-feedback"><?= $errors['email'] ?></div><?php endif; ?>
        </div>
        <div class="col-md-3">
          <label class="form-label">Ημερ. γέννησης</label>
          <input type="date" name="birthdate" class="form-control <?= isset($errors['birthdate'])?'is-invalid':'' ?>"
                 value="<?= htmlspecialchars($patient['birthdate'] ?? '') ?>">
          <?php if(isset($errors['birthdate'])): ?><div class="invalid-feedback"><?= $errors['birthdate'] ?></div><?php endif; ?>
        </div>
        <div class="col-md-9">
          <label class="form-label">Διεύθυνση</label>
          <input name="address" class="form-control" value="<?= htmlspecialchars($patient['address'] ?? '') ?>">
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button class="btn btn-primary"><?= $isEdit ? 'Αποθήκευση' : 'Καταχώρηση' ?></button>
        <a class="btn btn-outline-secondary" href="?r=patients">Άκυρο</a>
      </div>
    </form>
  </div>
</div>

<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
