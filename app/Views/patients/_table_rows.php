<?php foreach ($rows as $r): ?>
<tr>
  <td><?= (int)$r['id'] ?></td>
  <td><?= htmlspecialchars($r['name']) ?></td>
  <td><?= htmlspecialchars($r['phone'] ?? '') ?></td>
  <td class="d-none d-md-table-cell"><?= htmlspecialchars($r['email'] ?? '') ?></td>
  <td><?= htmlspecialchars($r['last_visit'] ?? '') ?></td>
  <td class="text-center"><?= !empty($r['gdpr_yes']) ? 'ΝΑΙ' : 'ΟΧΙ' ?></td>
  <td class="text-end">
    <a class="btn btn-sm btn-outline-secondary"
       href="?r=patients&a=edit&id=<?= (int)$r['id'] ?>">Επεξεργασία</a>
    <form method="post"
          action="?r=patients&a=delete"
          class="d-inline"
          onsubmit="return confirm('Οριστική διαγραφή;');">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
      <button class="btn btn-sm btn-outline-danger">Διαγραφή</button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
