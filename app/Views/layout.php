<?php /** @var string $title */ /** @var string $content */ ?>
<!doctype html>
<html lang="el" data-bs-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title ?? 'Aesthetics Admin') ?></title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root {
      --sidebar-width: 260px;
    }

    body {
      min-height: 100vh;
    }

    .navbar-brand {
      font-weight: 600;
    }

    .card {
      border-radius: 1rem;
    }

    /* Sidebar layout */
    #sidebar {
      width: var(--sidebar-width);
      padding: 1rem .75rem;
    }

    #sidebar .section-title {
      font-size: .70rem;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--bs-secondary-color);
      font-weight: 600;
      margin-bottom: .35rem;
      margin-top: .25rem;
    }

    #sidebar .nav-link {
      border-radius: .50rem;
      font-size: .92rem;
      padding: .35rem .75rem;
      color: var(--bs-body-color);
      display: flex;
      align-items: center;
      gap: .35rem;
    }

    #sidebar .nav-link i {
      font-size: 1rem;
    }

    #sidebar .nav-link.active {
      background-color: rgba(var(--bs-primary-rgb), 0.15);
      color: var(--bs-primary);
      font-weight: 600;
    }

    #sidebar .nav-link:hover:not(.active) {
      background-color: rgba(var(--bs-primary-rgb), 0.08);
      text-decoration: none;
    }

    /* Responsive συμπεριφορά sidebar */
    @media (max-width: 991.98px) {
      #sidebar {
        position: fixed;
        top: 56px; /* ύψος navbar */
        bottom: 0;
        left: calc(-1 * var(--sidebar-width));
        z-index: 1040;
        background-color: var(--bs-body-bg);
        box-shadow: 0 0.25rem 0.75rem rgba(0,0,0,.15);
        transition: left .2s ease-in-out;
      }
      #sidebar.sidebar-open {
        left: 0;
      }
      body.has-sidebar-open {
        overflow: hidden;
      }
    }
  </style>
</head>
<body class="bg-body-tertiary">
<?php
  $r = $_GET['r'] ?? 'home';
  $a = $_GET['a'] ?? '';

  $isPatientsList   = ($r === 'patients' && $a === '');
  $isPatientsCards  = ($r === 'patients' && ($a === 'cards_list' || $a === 'card_view' || $a === 'edit' || $a === 'create'));
  $isAgenda         = ($r === 'agenda' && ($a === '' || $a === 'checkin' || $a === 'save_visit'));
  $isDailyCash      = ($r === 'agenda' && $a === 'daily_cash');
  $isRepBalances    = ($r === 'reports' && $a === 'balances');
  $isRepOldBalances = ($r === 'reports' && $a === 'old_balances');
  $isRepCashPeriod  = ($r === 'reports' && $a === 'cash_period');
  $isRepCashPatient = ($r === 'reports' && $a === 'cash_patient'); 	  
  $isManage         = ($r === 'manage');
?>

<!-- Επάνω μπάρα: brand + theme toggle + mobile menu -->
<nav class="navbar navbar-expand bg-body border-bottom sticky-top">
  <div class="container-fluid">
    <button class="btn btn-outline-secondary d-lg-none me-2" id="sidebar-toggle" type="button" aria-label="Μενού">
      <i class="bi bi-list"></i>
    </button>

    <a class="navbar-brand" href="?r=home">Aesthetics</a>

    <div class="ms-auto">
      <button class="btn btn-outline-secondary" id="theme-toggle" type="button" aria-label="Εναλλαγή θέματος">
        <i id="theme-icon" class="bi bi-moon-stars"></i>
      </button>
    </div>
  </div>
</nav>

<div class="d-flex">
  <!-- Αριστερό sidebar -->
  <nav id="sidebar" class="border-end bg-body d-flex flex-column">
    <div class="flex-grow-1">
      <div class="section-title mt-1">Πελάτες</div>
      <ul class="nav flex-column mb-2">
        <li class="nav-item">
          <a href="?r=patients"
             class="nav-link <?= $isPatientsList ? 'active' : '' ?>">
            <i class="bi bi-people"></i>
            <span>Λίστα πελατών</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="?r=patients&a=cards_list"
             class="nav-link <?= $isPatientsCards ? 'active' : '' ?>">
            <i class="bi bi-person-vcard"></i>
            <span>Καρτέλες πελατών</span>
          </a>
        </li>
      </ul>

      <div class="section-title mt-3">Ραντεβού</div>
      <ul class="nav flex-column mb-2">
        <li class="nav-item">
          <a href="?r=agenda"
             class="nav-link <?= $isAgenda ? 'active' : '' ?>">
            <i class="bi bi-calendar-check"></i>
            <span>Ημερήσια ατζέντα</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="?r=agenda&a=daily_cash"
             class="nav-link <?= $isDailyCash ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i>
            <span>Ημερήσιο ταμείο</span>
          </a>
        </li>
      </ul>

          <div class="section-title mt-3">Αναφορές</div>
      <ul class="nav flex-column mb-2">
        <li class="nav-item">
          <a href="?r=reports&a=balances"
             class="nav-link <?= $isRepBalances ? 'active' : '' ?>">
            <i class="bi bi-graph-up"></i>
            <span>Υπόλοιπα πελατών (νέο)</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="?r=reports&a=old_balances"
             class="nav-link <?= $isRepOldBalances ? 'active' : '' ?>">
            <i class="bi bi-graph-down"></i>
            <span>Υπόλοιπα πελατών (παλιό)</span>
          </a>
        </li>

        <li class="nav-item mt-2">
          <span class="nav-link disabled text-uppercase small text-muted">
            Ταμείο
          </span>
        </li>
        <li class="nav-item">
          <a href="?r=agenda&a=daily_cash"
             class="nav-link <?= $isDailyCash ? 'active' : '' ?>">
            <i class="bi bi-cash-stack"></i>
            <span>Ταμείο ημέρας</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="?r=reports&a=cash_period"
             class="nav-link <?= $isRepCashPeriod ? 'active' : '' ?>">
            <i class="bi bi-calendar-range"></i>
            <span>Ταμείο περιόδου</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="?r=reports&a=cash_patient"
             class="nav-link <?= $isRepCashPatient ? 'active' : '' ?>">
            <i class="bi bi-person-lines-fill"></i>
            <span>Ταμείο πελάτη</span>
          </a>
        </li>
      </ul>


      <div class="section-title mt-3">Ανθρώπινο Δυναμικό</div>
      <ul class="nav flex-column mb-2">
        <li class="nav-item">
          <a href="admin_doctors.php"
             class="nav-link">
            <i class="bi bi-person-badge"></i>
            <span>Εργαζόμενοι</span>
          </a>
        </li>
      </ul>

      <div class="section-title mt-3">Ρυθμίσεις</div>
      <ul class="nav flex-column mb-2">
        <li class="nav-item">
          <a href="admin_services.php"
             class="nav-link">
            <i class="bi bi-sliders"></i>
            <span>Διαχείριση Υπηρεσιών</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="?r=manage"
             class="nav-link <?= $isManage ? 'active' : '' ?>">
            <i class="bi bi-gear"></i>
            <span>Γενικές ρυθμίσεις</span>
          </a>
        </li>
      </ul>
    </div>
  </nav>

  <!-- Κεντρικό περιεχόμενο -->
  <main class="flex-grow-1 container-fluid py-4">
    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="alert alert-<?= $_SESSION['flash']['type'] ?>">
        <?= htmlspecialchars($_SESSION['flash']['msg']) ?>
      </div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <?= $content ?? '' ?>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_ASSET_BASE ?>/theme.js"></script>

<script>
  (function () {
    const sidebar   = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebar-toggle');

    if (sidebar && toggleBtn) {
      toggleBtn.addEventListener('click', function () {
        sidebar.classList.toggle('sidebar-open');
        document.body.classList.toggle('has-sidebar-open');
      });
    }
  })();
</script>

</body>
</html>
