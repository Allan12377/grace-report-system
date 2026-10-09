<?php
$search=trim((string)($_GET['q']??''));
$classFilter=(int)($_GET['class']??0);
$statusFilter=(string)($_GET['status']??'Active');
if(!in_array($statusFilter,['Active','Inactive','All'],true))$statusFilter='Active';
$sql='SELECT s.id,s.admission_no,s.full_name,s.status,c.name class_name FROM students s JOIN classes c ON c.id=s.class_id WHERE s.deleted_at IS NULL';
$args=[];
if($search!==''){
    $sql.=' AND (s.full_name LIKE ? OR s.admission_no LIKE ? OR s.parent_name LIKE ? OR s.parent_phone LIKE ?)';
    array_push($args,"%$search%","%$search%","%$search%","%$search%");
}
if($classFilter>0){$sql.=' AND s.class_id=?';$args[]=$classFilter;}
if($statusFilter!=='All'){$sql.=' AND s.status=?';$args[]=$statusFilter;}
$sql.=' ORDER BY c.level,s.full_name LIMIT 500';
$query=$pdo->prepare($sql);
$query->execute($args);
$students=$query->fetchAll();
?>
<div class="page-intro">
  <div>
    <div class="eyebrow">LEARNER DIRECTORY</div>
    <h1>Students</h1>
    <p>Search, review, and maintain pupil records across every primary class.</p>
  </div>
  <div class="action-row">
    <a class="btn secondary" href="index.php?page=student_trash">Recently deleted</a>
    <a class="btn" href="index.php?page=student_form">Register pupil</a>
  </div>
</div>
<form class="filter-bar" method="get">
  <input type="hidden" name="page" value="students">
  <div class="search-input"><input name="q" value="<?= h($search) ?>" placeholder="Search name, admission no., parent or phone..."></div>
  <select name="class">
    <option value="0">All classes</option>
    <?php foreach($classes as $class): ?><option value="<?= (int)$class['id'] ?>" <?= $classFilter===(int)$class['id']?'selected':'' ?>><?= h($class['name']) ?></option><?php endforeach; ?>
  </select>
  <select name="status">
    <?php foreach(['Active','Inactive','All'] as $status): ?><option value="<?= h($status) ?>" <?= $statusFilter===$status?'selected':'' ?>><?= h($status) ?></option><?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Search</button>
</form>
<section class="panel">
  <div class="panel-head"><div><h3>Student records</h3><p><?= count($students) ?> record(s) · removed records are kept in Recently deleted for 90 days</p></div></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Learner</th><th>Admission no.</th><th>Class</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if($students===[]): ?><tr><td colspan="5">No matching students found.</td></tr><?php endif; ?>
      <?php foreach($students as $student): ?>
        <tr>
          <td><b><?= h($student['full_name']) ?></b></td>
          <td><?= h($student['admission_no']) ?></td>
          <td><?= h($student['class_name']) ?></td>
          <td><span class="status <?= strtolower((string)$student['status']) ?>"><?= h($student['status']) ?></span></td>
          <td class="trash-actions">
            <a class="btn secondary" href="index.php?page=profile&id=<?= (int)$student['id'] ?>">View</a>
            <a class="btn secondary" href="index.php?page=student_form&id=<?= (int)$student['id'] ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
