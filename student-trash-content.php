<?php
$trashRows = $pdo->query(
    'SELECT s.id,s.admission_no,s.full_name,s.deleted_at,s.purge_after,c.name class_name
     FROM students s JOIN classes c ON c.id=s.class_id
     WHERE s.deleted_at IS NOT NULL ORDER BY s.deleted_at DESC'
)->fetchAll();
?>
<div class="page-intro">
  <div>
    <div class="eyebrow">STUDENT RECORDS</div>
    <h1>Recently deleted</h1>
    <p>Deleted records are retained for 90 days. Restoring a record returns its marks, comments, and enrolment history.</p>
  </div>
  <a class="btn secondary" href="index.php?page=students">Back to students</a>
</div>
<section class="panel">
  <?php if ($trashRows === []): ?>
    <div class="empty">Trash is empty.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Learner</th><th>Admission no.</th><th>Class</th><th>Deleted</th><th>Auto-delete date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($trashRows as $row): ?>
          <tr>
            <td><b><?= h($row['full_name']) ?></b></td>
            <td><?= h($row['admission_no']) ?></td>
            <td><?= h($row['class_name']) ?></td>
            <td><?= h($row['deleted_at']) ?></td>
            <td><?= h($row['purge_after']) ?></td>
            <td class="trash-actions">
              <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <input type="hidden" name="action" value="restore_student">
                <input type="hidden" name="student_id" value="<?= (int)$row['id'] ?>">
                <button class="btn secondary" type="submit">Restore</button>
              </form>
              <form method="post" onsubmit="return confirm('Permanently delete this learner and all associated marks, comments, and enrolment history? This cannot be undone.')">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <input type="hidden" name="action" value="purge_student">
                <input type="hidden" name="student_id" value="<?= (int)$row['id'] ?>">
                <button class="btn danger" type="submit">Delete permanently</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
