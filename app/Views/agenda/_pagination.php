<?php
$cur = (int)($page ?? 1);
$tot = (int)($pages ?? 1);
$q   = $q   ?? '';
$sort= $sort?? 'last_visit';
$dir = strtolower($dir ?? 'desc');
if ($tot < 1) $tot = 1;

function page_url($p, $q, $sort, $dir){
  return '?' . http_build_query([
    'r'    => 'agenda',
    'q'    => $q,
    'page' => $p,
    'sort' => $sort,
    'dir'  => $dir
  ]);
}
function pg_link($p, $label, $disabled, $active, $q, $sort, $dir){
  if ($disabled) {
    echo '<li class="page-item disabled"><span class="page-link">'.htmlspecialchars($label).'</span></li>';
  } elseif ($active) {
    echo '<li class="page-item active"><span class="page-link">'.htmlspecialchars($label).'</span></li>';
  } else {
    $url = page_url($p, $q, $sort, $dir);
    echo '<li class="page-item"><a class="page-link" href="'.$url.'" data-page="'.$p.'">'.htmlspecialchars($label).'</a></li>';
  }
}
?>
<nav>
  <ul class="pagination pagination-sm mb-0">
    <?php pg_link(max(1, $cur-1), '«', $cur<=1, false, $q, $sort, $dir); ?>

    <?php
    $from = max(1, $cur - 2);
    $to   = min($tot, $cur + 2);
    if ($from > 1) { pg_link(1, '1', false, false, $q, $sort, $dir); if ($from > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; }
    for ($i=$from; $i<=$to; $i++) pg_link($i, (string)$i, false, $i===$cur, $q, $sort, $dir);
    if ($to < $tot) { if ($to < $tot-1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>'; pg_link($tot, (string)$tot, false, false, $q, $sort, $dir); }
    ?>

    <?php pg_link(min($tot, $cur+1), '»', $cur>=$tot, false, $q, $sort, $dir); ?>
  </ul>
</nav>
