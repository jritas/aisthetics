<?php if (!empty($pages) && $pages > 1): ?>
  <?php
    $current = (int)$page;
    $total   = (int)$pages;
    $prev    = max(1, $current - 1);
    $next    = min($total, $current + 1);
  ?>
  <nav class="mt-2 small">
    <ul class="pagination pagination-sm justify-content-center mb-0">
      <li class="page-item <?= $current <= 1 ? 'disabled' : '' ?>">
        <a href="#"
           class="page-link pagelink"
           data-page="1">&laquo; Πρώτη</a>
      </li>
      <li class="page-item <?= $current <= 1 ? 'disabled' : '' ?>">
        <a href="#"
           class="page-link pagelink"
           data-page="<?= $prev ?>">‹ Προηγ.</a>
      </li>

      <li class="page-item disabled">
        <span class="page-link">
          Σελίδα <?= $current ?> / <?= $total ?>
        </span>
      </li>

      <li class="page-item <?= $current >= $total ? 'disabled' : '' ?>">
        <a href="#"
           class="page-link pagelink"
           data-page="<?= $next ?>">Επόμ. ›</a>
      </li>
      <li class="page-item <?= $current >= $total ? 'disabled' : '' ?>">
        <a href="#"
           class="page-link pagelink"
           data-page="<?= $total ?>">Τελευταία &raquo;</a>
      </li>
    </ul>
  </nav>
<?php else: ?>
  <div></div>
<?php endif; ?>
