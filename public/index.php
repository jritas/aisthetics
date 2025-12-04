<?php
declare(strict_types=1);
session_start();

// Σύνδεση DB (όπως έχεις ήδη)
require __DIR__ . '/../app/Config/db.php';

// Routing param
$r = $_GET['r'] ?? 'home';

switch ($r) {
  case 'agenda':
    require __DIR__ . '/../app/Controllers/AgendaController.php';
    (new AgendaController($pdo))->handle();
    break;

  case 'patients':
    require __DIR__ . '/../app/Controllers/PatientsController.php';
    (new PatientsController($pdo))->handle();
    break;

  case 'reports':
    // Αν υπάρχει controller, τρέξε τον· αλλιώς δείξε τη σελίδα αναφορών (placeholder / index)
    $ctrl = __DIR__ . '/../app/Controllers/ReportsController.php';
    if (is_file($ctrl)) {
      require $ctrl;
      (new ReportsController($pdo))->handle();
    } else {
      $view = __DIR__ . '/../app/Views/reports/index.php';
      if (is_file($view)) {
        require $view;
      } else {
        // Πολύ απλό fallback για να μη "σκάει" τίποτα
        $title = 'Αναφορές';
        ob_start(); ?>
          <div class="container py-4">
            <h3 class="mb-3">Αναφορές</h3>
            <p class="text-muted">Η σελίδα αναφορών θα προστεθεί άμεσα.</p>
          </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../app/Views/layout.php';
      }
    }
    break;

  case 'manage':
    // Προσωρινή σελίδα διαχείρισης (placeholder για να υπάρχει διαδρομή)
    $view = __DIR__ . '/../app/Views/manage/index.php';
    if (is_file($view)) {
      require $view;
    } else {
      $title = 'Διαχείριση';
      ob_start(); ?>
        <div class="container py-4">
          <h3 class="mb-3">Διαχείριση</h3>
          <p class="text-muted">Εδώ θα προστεθούν οι ρυθμίσεις/διαχειριστικά.</p>
        </div>
      <?php
      $content = ob_get_clean();
      require __DIR__ . '/../app/Views/layout.php';
    }
    break;

  case 'home':
  default:
    require __DIR__ . '/../app/Views/home.php';
    break;
}
