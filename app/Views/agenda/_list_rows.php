<?php if (empty($rows)): ?>
  <tr><td colspan="6" class="text-center text-muted py-4">Δεν βρέθηκαν εγγραφές.</td></tr>
<?php else: ?>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td>
      <td><?= htmlspecialchars($r['name']) ?></td>
      <td><?= htmlspecialchars($r['phone']) ?></td>
      <td><?= htmlspecialchars($r['last_visit'] ?? '') ?></td>
      <td class="text-center"><?= (int)($r['visits'] ?? 0) ?></td>
      <td class="text-center">
        <a class="btn btn-sm btn-primary" href="?r=agenda&a=checkin&id=<?= (int)$r['id'] ?>">Check-in</a>
      </td>
    </tr>
  <?php endforeach; ?>
<?php endif; ?>
