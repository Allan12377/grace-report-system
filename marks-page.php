<?php
declare(strict_types=1);

$classId = (int)($_GET['class'] ?? 0);
$studentId = (int)($_GET['student'] ?? 0);
$assessment = (string)($_GET['assessment'] ?? 'EOT');
if (!in_array($assessment, ['BOT', 'MID', 'EOT'], true)) $assessment = 'EOT';
$term = max(1, min(3, $term));
$classQuery = $pdo->prepare('SELECT * FROM classes WHERE id=?');
$classQuery->execute([$classId]);
$selectedClass = $classQuery->fetch();
if ($studentId > 0) {
    $classFromStudent = $pdo->prepare('SELECT c.* FROM students s JOIN classes c ON c.id=s.class_id WHERE s.id=? AND s.deleted_at IS NULL AND s.status="Active"');
    $classFromStudent->execute([$studentId]);
    $studentClass = $classFromStudent->fetch();
    if ($studentClass) {
        $selectedClass = $studentClass;
        $classId = (int)$studentClass['id'];
    }
}
if (!$selectedClass && $classes !== []) {
    $selectedClass = $classes[0];
    $classId = (int)$selectedClass['id'];
}
$classStudents = [];
$student = null;
$subjects = [];
$savedMarks = [];
$comments = [];
$classRankings = [];
$classSize = 0;
$division = 'Pending';
$average = null;
if ($selectedClass) {
    $studentsQuery = $pdo->prepare(
        'SELECT s.id,s.full_name,s.admission_no,s.photo,c.name class_name,c.level,c.id class_id
         FROM students s JOIN classes c ON c.id=s.class_id
         WHERE s.class_id=? AND s.status="Active" ORDER BY s.full_name'
    );
    $studentsQuery->execute([$classId]);
    $classStudents = $studentsQuery->fetchAll();
    foreach ($classStudents as $candidate) {
        if ((int)$candidate['id'] === $studentId) {
            $student = $candidate;
            break;
        }
    }
    if (!$student && $classStudents !== []) {
        $student = $classStudents[0];
        $studentId = (int)$student['id'];
    }
    if ($student) {
        $names = (int)$selectedClass['level'] <= 3
            ? ['Social Development', 'Language Development I', 'Language Development II', 'Health Habits', 'Numbers', 'Writing']
            : ['English', 'Mathematics', 'Social Studies', 'Science'];
        $nameSlots = implode(',', array_fill(0, count($names), '?'));
        $subjectQuery = $pdo->prepare("SELECT * FROM subjects WHERE name IN ($nameSlots) ORDER BY id");
        $subjectQuery->execute($names);
        $subjects = $subjectQuery->fetchAll();
        $marksQuery = $pdo->prepare(
            'SELECT subject_id,score,teacher_comment FROM marks
             WHERE student_id=? AND academic_year=? AND term=? AND assessment=?'
        );
        $marksQuery->execute([$studentId, $year, $term, $assessment]);
        foreach ($marksQuery as $markRow) {
            $savedMarks[(int)$markRow['subject_id']] = $markRow;
        }
        $commentsQuery = $pdo->prepare('SELECT * FROM comments WHERE student_id=? AND academic_year=? AND term=?');
        $commentsQuery->execute([$studentId, $year, $term]);
        $comments = $commentsQuery->fetch() ?: [];

        $eotQuery = $pdo->prepare(
            'SELECT COALESCE(SUM(m.score),0) total,COUNT(m.id) marked
             FROM marks m JOIN subjects su ON su.id=m.subject_id
             WHERE m.student_id=? AND m.academic_year=? AND m.term=? AND m.assessment="EOT"
               AND su.name IN (' . implode(',', array_fill(0, count($names), '?')) . ')'
        );
        $eotQuery->execute([$studentId, $year, $term, ...$names]);
        $eotSummary = $eotQuery->fetch();
        $average = (int)$eotSummary['marked'] > 0 ? (float)$eotSummary['total'] / (int)$eotSummary['marked'] : null;
        $aggregate = 0;
        $aggregateCount = 0;
        $eotGrades = $pdo->prepare(
            'SELECT m.score FROM marks m JOIN subjects su ON su.id=m.subject_id
             WHERE m.student_id=? AND m.academic_year=? AND m.term=? AND m.assessment="EOT"
               AND su.name IN (' . implode(',', array_fill(0, count($names), '?')) . ')'
        );
        $eotGrades->execute([$studentId, $year, $term, ...$names]);
        foreach ($eotGrades as $gradeRow) {
            $aggregate += report_grade_points((float)$gradeRow['score']) ?? 0;
            $aggregateCount++;
        }
        $division = report_division($aggregateCount === count($names) ? $aggregate : null, count($names));

        $rankQuery = $pdo->prepare(
            'SELECT s.id,COALESCE(SUM(m.score),0) total,COUNT(m.id) marked
             FROM students s LEFT JOIN marks m ON m.student_id=s.id AND m.academic_year=? AND m.term=?
               AND m.assessment="EOT" AND m.subject_id IN (' . implode(',', array_fill(0, count($subjects), '?')) . ')
             WHERE s.class_id=? AND s.status="Active" GROUP BY s.id ORDER BY total DESC,s.full_name'
        );
        $rankQuery->execute([$year, $term, ...array_column($subjects, 'id'), $classId]);
        $classRankings = $rankQuery->fetchAll();
        $classSize = count($classRankings);
    }
}
$schoolName = setting($pdo, 'school_name', 'Grace Christian Primary School, Kacungwa');
$studentRank = null;
foreach ($classRankings as $candidate) {
    if ((int)$candidate['id'] === $studentId && (int)$candidate['marked'] > 0) {
        $studentRank = 1 + count(array_filter(
            $classRankings,
            static fn(array $other): bool => (int)$other['marked'] > 0
                && (float)$other['total'] > (float)$candidate['total']
        ));
        break;
    }
}
$photoPath = $student && trim((string)$student['photo']) !== '' ? $student['photo'] : 'assets/school-logo.jpg';
$classComment = trim((string)($comments['class_comment'] ?? ''));
$headComment = trim((string)($comments['head_comment'] ?? ''));
if ($classComment === '') $classComment = report_overall_comment($average, 'class', $studentId + $term);
if ($headComment === '') $headComment = report_overall_comment($average, 'head', $studentId + $year);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($schoolName) ?> · Marks entry</title>
<link rel="stylesheet" href="assets/style.css">
<script defer src="assets/app.js"></script>
<style>
.marks-top{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-bottom:20px}
.marks-top h1{margin:0}
.marks-filters{display:grid;grid-template-columns:1.1fr 1.4fr 1.7fr .8fr .8fr .9fr auto;gap:12px;align-items:end;margin-bottom:20px}
.marks-filters label{display:flex;flex-direction:column;gap:6px;font-size:14px;font-weight:700;color:#29483f}
.marks-filters input,.marks-filters select{min-height:50px;padding:10px 12px;border:1px solid #cbdad0;border-radius:10px;background:#fff;font:inherit}
.student-search{margin-bottom:16px}
.student-overview{display:flex;align-items:center;gap:14px;padding:15px;margin:0 0 18px;border:1px solid #dce8e0;border-radius:14px;background:#f6faf5}
.student-overview img{width:66px;height:66px;object-fit:cover;border:2px solid #c5dcc9;border-radius:12px}
.student-overview b{display:block;font-size:19px}
.student-overview small{display:block;color:#526a63;font-size:14px}
.student-overview .class-pill{margin-left:auto}
.marks-table input[type=number]{min-height:44px;width:110px;padding:7px 9px;border:1px solid #cbdad0;border-radius:8px;font:inherit}
.marks-table .mark-remark{min-width:225px;width:100%;min-height:62px;padding:8px;border:1px solid #cbdad0;border-radius:8px;font:14px/1.45 Arial,sans-serif}
.marks-table td{vertical-align:middle}
.assessment-label{display:inline-block;padding:5px 9px;border-radius:20px;background:#e5f4ec;color:#145e4e;font-size:12px;font-weight:800}
.marks-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px}
.marks-summary>div{padding:14px;border:1px solid #dce8e0;border-radius:12px;background:#fff}
.marks-summary b{display:block;color:#526a63;font-size:12px;text-transform:uppercase}
.marks-summary span{font-size:20px;font-weight:800}
.marks-links{display:flex;gap:10px;flex-wrap:wrap}
.marks-info{padding:14px 16px;margin-bottom:16px;border:1px solid #d7e6dc;border-radius:12px;background:#f4f8f1;color:#345849}
@media(max-width:1100px){.marks-filters{grid-template-columns:repeat(3,1fr)}}
@media(max-width:650px){.marks-top{align-items:flex-start;flex-direction:column}.marks-filters{grid-template-columns:1fr 1fr;padding:14px}.marks-filters label:nth-child(2),.marks-filters label:nth-child(3){grid-column:1/-1}.marks-filters button{grid-column:1/-1;width:100%}.student-overview{flex-wrap:wrap;gap:10px;padding:12px}.student-overview img{width:54px;height:54px}.student-overview b{font-size:16px}.student-overview .class-pill{margin-left:0}.marks-summary{grid-template-columns:1fr}.marks-summary>div{display:flex;justify-content:space-between;align-items:center;gap:10px}.marks-summary span{font-size:17px}.marks-table{display:block;width:100%}.marks-table thead{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}.marks-table tbody{display:grid;gap:12px}.marks-table tr{display:grid;grid-template-columns:1fr 1fr;gap:0;padding:10px;border:1px solid #dce8e0;border-radius:12px;background:#fff}.marks-table td{display:grid;grid-template-columns:1fr;gap:7px;padding:9px 6px;border:0}.marks-table td:first-child{grid-column:1/-1;padding:4px 6px 10px;border-bottom:1px solid #e4ebe8;font-size:16px}.marks-table td:not(:first-child)::before{content:attr(data-label);font-size:11px;font-weight:800;color:#526a63}.marks-table td:nth-child(2) input{width:100%;min-height:56px;font-size:20px}.marks-table td:nth-child(3){align-content:start}.marks-table .mark-remark{grid-column:1/-1;min-width:0;width:100%;min-height:72px;font-size:16px}.form-footer button{width:100%;min-height:54px}.comment-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" id="sidebar">
    <a class="side-brand" href="index.php"><img src="assets/school-logo.jpg" alt="School emblem"><span><b>GRACE</b><small>SCHOOL REPORTS</small></span></a>
    <div class="side-label">WORKSPACE</div>
    <a class="nav-link" href="index.php"><span class="nav-symbol">▦</span>Dashboard</a>
    <a class="nav-link" href="index.php?page=students"><span class="nav-symbol">♙</span>Students</a>
    <a class="nav-link active" href="index.php?page=marks"><span class="nav-symbol">✎</span>Marks entry</a>
    <a class="nav-link" href="index.php?page=reports"><span class="nav-symbol">▤</span>Report cards</a>
    <a class="nav-link" href="index.php?page=performance"><span class="nav-symbol">↗</span>Class performance</a>
    <a class="nav-link" href="index.php?page=promotion"><span class="nav-symbol">⇢</span>Class promotion</a>
    <div class="side-label">ADMINISTRATION</div>
    <a class="nav-link" href="index.php?page=settings"><span class="nav-symbol">⚙</span>School settings</a>
    <a class="nav-link" href="index.php?page=security"><span class="nav-symbol">◇</span>Security</a>
    <div class="sidebar-bottom"><div class="offline-indicator"><i></i><span><b>Offline mode</b><small>Data stays on this PC</small></span></div><a class="nav-link" href="index.php?logout=1"><span class="nav-symbol">↪</span>Sign out</a></div>
  </aside>
  <main class="main-area">
    <header class="topbar"><button class="menu-toggle" id="menuToggle" aria-label="Open navigation">☰</button><div><div class="eyebrow">GRACE CHRISTIAN PRIMARY SCHOOL</div><div class="top-title">Marks entry</div></div><div class="top-actions"><span class="date-chip"><?= date('D, d M Y') ?></span><div class="avatar-admin">A</div><div class="admin-name"><b><?= h($_SESSION['admin']) ?></b><small>Administrator</small></div></div></header>
    <div class="content">
      <?php if (isset($_GET['saved'])): ?><div class="alert success">Marks and comments saved successfully.</div><?php endif; ?>
      <?php if (isset($error)): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>
      <div class="marks-top"><div><div class="eyebrow">ACADEMIC ASSESSMENT</div><h1>Enter learner marks</h1><p class="muted">Choose a class first, then find any learner in that class.</p></div>
      <?php if ($student): ?><div class="marks-links"><a class="btn secondary" target="_blank" href="index.php?page=print&student=<?= $studentId ?>&year=<?= $year ?>&term=<?= $term ?>">Review this report</a><a class="btn secondary" target="_blank" href="index.php?page=print&class=<?= $classId ?>&year=<?= $year ?>&term=<?= $term ?>">Review class reports</a></div><?php endif; ?></div>
      <form class="panel marks-filters" method="get" action="index.php">
        <input type="hidden" name="page" value="marks">
        <label>Choose class<select name="class" required onchange="this.form.student.value='';this.form.submit()"><?php foreach ($classes as $class): ?><option value="<?= (int)$class['id'] ?>" <?= $classId === (int)$class['id'] ? 'selected' : '' ?>><?= h($class['name']) ?></option><?php endforeach; ?></select></label>
        <label>Find learner by name<input type="search" id="studentSearch" placeholder="Type a name or admission number" autocomplete="off"></label>
        <label>Choose learner<select name="student" required><?php foreach ($classStudents as $classStudent): ?><option value="<?= (int)$classStudent['id'] ?>" <?= $studentId === (int)$classStudent['id'] ? 'selected' : '' ?>><?= h($classStudent['full_name'] . ' · ' . $classStudent['admission_no']) ?></option><?php endforeach; ?></select></label>
        <label>Year<input type="number" name="year" min="2020" max="2100" value="<?= $year ?>"></label>
        <label>Term<select name="term"><?php for ($t = 1; $t <= 3; $t++): ?><option value="<?= $t ?>" <?= $term === $t ? 'selected' : '' ?>>Term <?= $t ?></option><?php endfor; ?></select></label>
        <label>Assessment<select name="assessment"><?php foreach (['BOT' => 'Beginning of term', 'MID' => 'Mid-term', 'EOT' => 'End of term'] as $code => $label): ?><option value="<?= $code ?>" <?= $assessment === $code ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
        <button class="btn" type="submit">Load learner</button>
      </form>
      <?php if ($student): ?>
      <div class="marks-info">The subjects below match <?= h($selectedClass['name']) ?>. You can enter all three assessments, edit the suggested remarks, and review the report before printing.</div>
      <form class="panel form-panel" method="post" action="index.php?page=marks">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
        <input type="hidden" name="action" value="save_marks">
        <input type="hidden" name="student_id" value="<?= $studentId ?>">
        <input type="hidden" name="class_id" value="<?= $classId ?>">
        <input type="hidden" name="year" value="<?= $year ?>">
        <input type="hidden" name="term" value="<?= $term ?>">
        <input type="hidden" name="assessment" value="<?= h($assessment) ?>">
        <div class="student-overview"><img src="<?= h($photoPath) ?>" alt="Photo of <?= h($student['full_name']) ?>"><div><b><?= h($student['full_name']) ?></b><small><?= h($student['class_name'] . ' · Admission no. ' . $student['admission_no']) ?></small></div><span class="class-pill"><?= h($assessment) ?> · Term <?= $term ?> · <?= $year ?></span></div>
        <div class="marks-summary"><div><b>Position in class</b><span><?= $studentRank ? h((string)$studentRank) . ' / ' . $classSize : 'Pending' ?></span></div><div><b>Upper primary division (EOT)</b><span id="markDivision" data-level="<?= (int)$selectedClass['level'] ?>" data-subject-count="<?= count($subjects) ?>"><?= (int)$selectedClass['level'] >= 4 ? h($division) : 'Not used in P1–P3' ?></span></div><div><b>EOT average</b><span id="markAverage"><?= $average === null ? 'Pending' : h(number_format($average, 1)) . '%' ?></span></div></div>
        <div class="table-wrap"><table class="marks-table"><thead><tr><th>Subject</th><th>Mark / 100</th><th>Grade</th><th>Teacher remark — edit if needed</th></tr></thead><tbody>
        <?php foreach ($subjects as $subject): $subjectId = (int)$subject['id'];$score = $savedMarks[$subjectId]['score'] ?? ''; $storedRemark = trim((string)($savedMarks[$subjectId]['teacher_comment'] ?? '')); $remark = $storedRemark !== '' ? $storedRemark : report_remark($score === '' ? null : (float)$score, $studentId + $subjectId + $year + $term); ?>
          <tr><td><b><?= h($subject['name']) ?></b></td><td data-label="Mark / 100"><input class="score-input" type="number" inputmode="numeric" min="0" max="100" step="1" name="score[<?= $subjectId ?>]" value="<?= h($score) ?>" placeholder="Enter mark" data-grade="grade-<?= $subjectId ?>" data-remark="remark-<?= $subjectId ?>" data-seed="<?= $studentId + $subjectId + $year + $term ?>" data-current-assessment="<?= h($assessment) ?>"></td><td data-label="Grade"><span class="grade-chip" id="grade-<?= $subjectId ?>"><?= h(report_grade($score === '' ? null : (float)$score)) ?></span></td><td data-label="Teacher remark — edit if needed"><textarea class="mark-remark" id="remark-<?= $subjectId ?>" name="remark[<?= $subjectId ?>]" rows="2" <?= $storedRemark === '' ? 'data-generated="yes"' : '' ?>><?= h($remark) ?></textarea></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
        <div class="form-grid comment-grid"><label>Class teacher’s comment<textarea name="class_comment" rows="3"><?= h($classComment) ?></textarea></label><label>Head teacher’s comment<textarea name="head_comment" rows="3"><?= h($headComment) ?></textarea></label></div>
        <div class="form-footer"><p>Division is calculated from the four EOT subject grades: A=1, B=2, C=3, D=4, E=5. A division appears after all four marks have been entered.</p><button class="btn" type="submit">Save <?= h($assessment) ?> marks and comments</button></div>
      </form>
      <?php else: ?>
      <section class="panel"><h2>No active learners in this class</h2><p>Choose a different class or register an active learner first.</p></section>
      <?php endif; ?>
      <footer class="app-footer"><span>© <?= date('Y') ?> <?= h($schoolName) ?></span><span>Private local records · Built for clear, confident school administration</span></footer>
    </div>
  </main>
</div>
</body>
</html>
