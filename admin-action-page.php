<?php
$school=setting($pdo,'school_name','Grace Christian Primary School, Kacungwa');
$classes=$pdo->query('SELECT * FROM classes ORDER BY level')->fetchAll();
$year=(int)($_GET['year']??date('Y'));
$term=(int)($_GET['term']??1);
$classes=$pdo->query('SELECT * FROM classes ORDER BY level')->fetchAll();
if($page==='student_form'){
    $id=(int)($_GET['id']??0);
    $student=['id'=>0,'admission_no'=>'','full_name'=>'','gender'=>'','dob'=>'','class_id'=>$classes[0]['id']??1,'attendance_type'=>'day','parent_name'=>'','parent_phone'=>'','address'=>'','status'=>'Active','admission_date'=>date('Y-m-d')];
    if($id>0){
        $query=$pdo->prepare('SELECT * FROM students WHERE id=? AND deleted_at IS NULL');
        $query->execute([$id]);
        $studentRow=$query->fetch();
        if(!$studentRow)go('index.php?page=student_trash');
        $student=$studentRow;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($page==='settings'?'School settings':($page==='student_trash'?'Recently deleted':($page==='students'?'Students':'Learner details'))) ?> · <?= h($school) ?></title>
  <link rel="stylesheet" href="assets/style.css">
  <script defer src="assets/app.js"></script>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="side-brand" href="index.php"><img src="assets/school-logo.jpg" alt="School emblem"><span><b>GRACE</b><small>SCHOOL REPORTS</small></span></a>
    <div class="side-label">WORKSPACE</div>
    <a class="nav-link" href="index.php"><span class="nav-symbol">▦</span>Dashboard</a>
    <a class="nav-link" href="index.php?page=students"><span class="nav-symbol">◉</span>Students</a>
    <a class="nav-link <?= $page==='student_trash'?'active':'' ?>" href="index.php?page=student_trash"><span class="nav-symbol">▣</span>Recently deleted</a>
    <a class="nav-link" href="index.php?page=marks"><span class="nav-symbol">▤</span>Marks entry</a>
    <a class="nav-link" href="index.php?page=reports"><span class="nav-symbol">▧</span>Report cards</a>
    <div class="side-label">ADMINISTRATION</div>
    <a class="nav-link <?= $page==='settings'?'active':'' ?>" href="index.php?page=settings"><span class="nav-symbol">⚙</span>School settings</a>
    <a class="nav-link" href="index.php?page=security"><span class="nav-symbol">◇</span>Security</a>
    <div class="sidebar-bottom"><a class="nav-link" href="index.php?logout=1"><span class="nav-symbol">↪</span>Sign out</a></div>
  </aside>
  <main class="main-area">
    <header class="topbar">
      <button class="menu-toggle" id="menuToggle" aria-label="Open navigation">☰</button>
      <div><div class="eyebrow"><?= h($school) ?></div><div class="top-title"><?= h($page==='settings'?'School settings':($page==='student_trash'?'Recently deleted':($page==='students'?'Students':($student['id']?'Edit learner':'Register a learner')))) ?></div></div>
      <div class="top-actions"><span class="date-chip"><?= date('D, d M Y') ?></span><div class="avatar-admin">A</div><div class="admin-name"><b><?= h($_SESSION['admin']) ?></b><small>Administrator</small></div></div>
    </header>
    <div class="content">
      <?php if(isset($error)): ?><div class="alert error"><?= h($error) ?></div><?php elseif(isset($_SESSION['student_trash_error'])): ?><div class="alert error"><?= h($_SESSION['student_trash_error']) ?></div><?php unset($_SESSION['student_trash_error']); ?><?php elseif(isset($_GET['updated'])): ?><div class="alert success">Student record updated.</div><?php elseif(isset($_GET['saved'])): ?><div class="alert success">Changes saved successfully.</div><?php endif; ?>
      <?php require __DIR__ . ($page==='settings' ? '/settings-page-content.php' : ($page==='student_trash' ? '/student-trash-content.php' : ($page==='students' ? '/student-directory-content.php' : '/student-form-content.php'))); ?>
    </div>
  </main>
</div>
</body>
</html>
