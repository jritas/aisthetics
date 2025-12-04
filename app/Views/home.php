<?php
$title = 'Αρχική';
ob_start(); ?>
<div class="row g-3">
  <!-- Κάρτα: Πελάτες (μητρώο) -->
  <div class="col-12 col-md-2 col-lg-2">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-2">Πελάτες</h5>
        <p class="text-secondary small">Μητρώο πελατών</p>
        <a class="btn btn-primary" href="?r=patients">Άνοιγμα</a>
      </div>
    </div>
  </div>

  <!-- Κάρτα: Πελάτες – Καρτέλες -->
  <div class="col-12 col-md-2 col-lg-2">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-2">Πελάτες – καρτέλες</h5>
        <p class="text-secondary small">Επισκόπηση καρτέλας πελάτη</p>
        <a class="btn btn-primary" href="?r=patients&a=cards_list">Άνοιγμα</a>
      </div>
    </div>
  </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>
