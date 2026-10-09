<?php
declare(strict_types=1);

$classId = (int)($_GET['class'] ?? 0);
$studentId = (int)($_GET['student'] ?? 0);
$periodTerm = max(1, min(3, $term));
$assessmentNames = ['BOT' => 'Beginning of Term', 'MID' => 'Mid-term', 'EOT' => 'End of Term'];
$termNames = ['', 'ONE', 'TWO', 'THREE'];
$studentSql = 'SELECT s.*,c.name class_name,c.level FROM students s JOIN classes c ON c.id=s.class_id WHERE ';
$studentArgs = [];
if ($studentId > 0) {
    $studentSql .= 's.id=?';
    $studentArgs[] = $studentId;
} else {
    $studentSql .= 's.class_id=? AND s.status="Active"';
    $studentArgs[] = $classId;
}
$studentSql .= ' ORDER BY s.full_name';
$studentQuery = $pdo->prepare($studentSql);
$studentQuery->execute($studentArgs);
$pupils = $studentQuery->fetchAll();

$schoolSettings = [];
foreach ($pdo->query('SELECT key,value FROM settings') as $settingRow) {
    $schoolSettings[$settingRow['key']] = $settingRow['value'];
}
$schoolName = $schoolSettings['school_name'] ?? $school;
$logoPath = 'assets/school-logo.jpg';
$selectedLevel = (int)($pupils[0]['level'] ?? 1);
$subjectNames = $selectedLevel <= 3
    ? ['Social Development', 'Language Development I', 'Language Development II', 'Health Habits', 'Numbers', 'Writing']
    : ['English', 'Mathematics', 'Social Studies', 'Science'];
$subjectSlots = implode(',', array_fill(0, count($subjectNames), '?'));
$subjectQuery = $pdo->prepare("SELECT id,name FROM subjects WHERE name IN ($subjectSlots) ORDER BY id");
$subjectQuery->execute($subjectNames);
$reportSubjects = $subjectQuery->fetchAll();
$subjectIds = array_column($reportSubjects, 'id');

$pupilIds = array_map(static fn(array $pupil): int => (int)$pupil['id'], $pupils);
$marksByStudent = [];
$commentsByStudent = [];
$positions = [];
$classSize = 0;
if ($pupilIds !== [] && $subjectIds !== []) {
    $pupilSlots = implode(',', array_fill(0, count($pupilIds), '?'));
    $subjectIdSlots = implode(',', array_fill(0, count($subjectIds), '?'));
    $marksQuery = $pdo->prepare(
        "SELECT m.student_id,su.name,m.assessment,m.score,m.teacher_comment
         FROM marks m JOIN subjects su ON su.id=m.subject_id
         WHERE m.student_id IN ($pupilSlots) AND m.academic_year=? AND m.term=?
           AND m.assessment IN ('BOT','MID','EOT') AND m.subject_id IN ($subjectIdSlots)"
    );
    $marksQuery->execute([...$pupilIds, $year, $periodTerm, ...$subjectIds]);
    foreach ($marksQuery as $mark) {
        $marksByStudent[(int)$mark['student_id']][$mark['name']][$mark['assessment']] = $mark;
    }

    $commentsQuery = $pdo->prepare(
        "SELECT * FROM comments WHERE student_id IN ($pupilSlots) AND academic_year=? AND term=?"
    );
    $commentsQuery->execute([...$pupilIds, $year, $periodTerm]);
    foreach ($commentsQuery as $commentRow) {
        $commentsByStudent[(int)$commentRow['student_id']] = $commentRow;
    }

    $classIdForRank = (int)($pupils[0]['class_id'] ?? $classId);
    $rankQuery = $pdo->prepare(
        "SELECT s.id,COALESCE(SUM(m.score),0) total,COUNT(m.id) marked
         FROM students s LEFT JOIN marks m ON m.student_id=s.id AND m.academic_year=? AND m.term=?
           AND m.assessment='EOT' AND m.subject_id IN ($subjectIdSlots)
         WHERE s.class_id=? AND s.status='Active'
         GROUP BY s.id ORDER BY total DESC,s.full_name"
    );
    $rankQuery->execute([$year, $periodTerm, ...$subjectIds, $classIdForRank]);
    $rankedPupils = $rankQuery->fetchAll();
    $classSize = count($rankedPupils);
    foreach ($rankedPupils as $candidate) {
        if ((int)$candidate['marked'] > 0) {
            $positions[(int)$candidate['id']] = 1 + count(array_filter(
                $rankedPupils,
                static fn(array $other): bool => (int)$other['marked'] > 0
                    && (float)$other['total'] > (float)$candidate['total']
            ));
        }
    }
}

$requirements = trim((string)($schoolSettings['school_requirements'] ?? 'School fees; toilet paper; books; pens; broom'));
$requirementItems = array_values(array_filter(array_map('trim', preg_split('/[,;\r\n]+/', $requirements) ?: [])));
$nextTermDate = trim((string)($schoolSettings['next_term'] ?? ''));
$reportTitle = 'Term ' . $periodTerm . ' report';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title></title>
<link rel="stylesheet" href="assets/style.css">
<style>
@page{size:A4 portrait;margin:0}
*{box-sizing:border-box}
body.report-page{margin:0;background:#edf2ed;color:#16352f;font:15px/1.45 Arial,Helvetica,sans-serif}
.print-actions{position:sticky;top:0;z-index:5;display:flex;align-items:center;justify-content:center;gap:14px;padding:13px;background:#173f3a;color:#fff}
.print-actions span{font-size:14px}
.report{position:relative;width:210mm;min-height:297mm;margin:20px auto;padding:11mm 12mm 10mm;background:#fff;border:2px solid #397a55;box-shadow:0 12px 36px #102d2030;page-break-after:always;break-after:page}
.report:last-child{page-break-after:auto;break-after:auto}
.report:before{content:"";position:absolute;inset:5mm;border:1px solid #d2e5d5;pointer-events:none}
.report>*{position:relative}
.report-head{display:grid;grid-template-columns:74px 1fr auto;align-items:center;gap:13px;padding:0 0 12px;border-bottom:3px solid #2b6b93}
.report-logo{width:70px;height:70px;object-fit:contain}
.report-head h1{margin:0;color:#155b85;font-size:22px;line-height:1.2}
.report-head p{margin:3px 0 0;font-size:12px;color:#344e47}
.report-heading{text-align:center;margin:13px 0 10px}
.report-heading .eyebrow{font-size:11px;letter-spacing:.16em;color:#667d70;font-weight:bold}
.report-heading h2{font-size:20px;letter-spacing:.06em;color:#8b3438;margin:2px 0}
.learner-photo{width:78px;height:92px;object-fit:cover;border:2px solid #b7d4c0;border-radius:7px;background:#edf4ed}
.identity{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;border:1px solid #558064;background:#fbfdf9}
.identity div{min-height:38px;padding:9px 10px;border:1px solid #b5cbb6;font-size:14px}
.identity b{color:#214d39}
.report-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin:10px 0}
.summary-item{padding:9px;border:1px solid #d4e4d4;border-radius:7px;background:#f4f8f1;font-size:13px}
.summary-item b{display:block;color:#356148;font-size:11px;text-transform:uppercase;letter-spacing:.05em}
.results{width:100%;margin:10px 0;border-collapse:collapse;table-layout:fixed;font-size:12px}
.results th,.results td{border:1px solid #76967a;padding:7px 6px;text-align:center;vertical-align:middle}
.report .results td{white-space:normal;overflow-wrap:anywhere;word-break:normal}
.report .results{font-size:13px}
.results thead th{background:#e9f2e7;color:#244b36;font-size:11px}
.results .subject{text-align:left;font-weight:700;width:29%}
.results .remark{text-align:left;width:25%}
.results .sum-row{background:#f1f6ef;font-weight:bold}
.grade-scale{display:grid;grid-template-columns:1.3fr repeat(5,1fr);border:1px solid #83a287;margin:10px 0}
.grade-scale div{padding:6px 4px;border-right:1px solid #a9c0a8;text-align:center;font-size:11px}
.grade-scale div:last-child{border:0}
.grade-scale b{display:block;color:#356148}
.comment-box,.requirements{border:1px solid #83a287;padding:10px 12px;margin-top:9px;min-height:57px;background:#fff}
.comment-box h3,.requirements h3{font-size:13px;color:#2f543d;margin:0 0 5px}
.comment-box p{margin:0;font-size:13px;min-height:19px}
.signature-row{display:flex;justify-content:flex-end;align-items:end;margin-top:5px}
.signature-line{min-width:190px;border-bottom:1px solid #60766b;text-align:center;padding:5px;color:#225b9c;font-family:Georgia,serif;font-size:12px;font-style:italic}
.signature-line small{display:block;color:#294d3a;font:11px Arial,sans-serif}
.requirements ul{display:flex;flex-wrap:wrap;gap:6px 18px;padding-left:20px;margin:5px 0;font-size:12px}
.requirements p{font-size:12px;margin:5px 0}
.report-bottom{display:flex;justify-content:space-between;gap:14px;margin-top:9px;color:#405d4b;font-size:12px}
.motto{text-align:center;margin-top:10px;padding-top:7px;border-top:1px solid #9aaf9c;color:#25634a;font-weight:bold;font-style:italic;font-size:12px}
.no-records{max-width:850px;margin:40px auto;padding:26px;border:1px solid #d7e4d9;border-radius:14px;background:#fff}
@media screen and (max-width:850px){.report{width:calc(100% - 18px);min-height:0;padding:25px 18px;margin:10px auto}.report:before{inset:8px}.report-head{grid-template-columns:54px 1fr auto;gap:8px}.report-logo{width:52px;height:52px}.report-head h1{font-size:16px}.report-head p{font-size:10px}.learner-photo{width:58px;height:70px}.identity{grid-template-columns:1fr 1fr}.results{font-size:10px}.results th,.results td{padding:5px 3px}.results .subject{width:25%}.results .remark{width:24%}}
@media print{body.report-page{background:#fff!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}.print-actions,.no-print{display:none!important}.report{width:210mm;min-height:297mm;margin:0;border:0;box-shadow:none;padding:11mm 12mm 10mm;break-after:page;page-break-after:always}.report:last-child{break-after:auto;page-break-after:auto}.report:before{inset:5mm}.report *{visibility:visible}}
</style>
</head>
<body class="report-page">
<div class="print-actions no-print">
  <button class="btn" onclick="window.print()">Print <?= $studentId ? 'this report' : 'all ' . count($pupils) . ' class reports' ?></button>
  <button class="btn secondary" onclick="history.back()">Back</button>
  <span>Review the reports before printing. In print settings, turn off “Headers and footers”.</span>
</div>
<?php if ($pupils === []): ?>
<section class="no-records"><h1>No pupils found</h1><p>Choose a class with active pupils and try again.</p></section>
<?php endif; ?>
<?php foreach ($pupils as $pupil):
    $pupilId = (int)$pupil['id'];
    $level = (int)$pupil['level'];
    $subjectNames = $level <= 3
        ? ['Social Development', 'Language Development I', 'Language Development II', 'Health Habits', 'Numbers', 'Writing']
        : ['English', 'Mathematics', 'Social Studies', 'Science'];
    $pupilMarks = $marksByStudent[$pupilId] ?? [];
    $assessmentTotals = ['BOT' => 0.0, 'MID' => 0.0, 'EOT' => 0.0];
    $assessmentCounts = ['BOT' => 0, 'MID' => 0, 'EOT' => 0];
    $aggregate = 0;
    $aggregateCount = 0;
    $eotAverage = null;
    foreach ($subjectNames as $subjectName) {
        foreach (array_keys($assessmentNames) as $assessmentCode) {
            $rawScore = $pupilMarks[$subjectName][$assessmentCode]['score'] ?? null;
            if ($rawScore !== null) {
                $score = (float)$rawScore;
                $assessmentTotals[$assessmentCode] += $score;
                $assessmentCounts[$assessmentCode]++;
                if ($assessmentCode === 'EOT') {
                    $aggregate += report_grade_points($score) ?? 0;
                    $aggregateCount++;
                }
            }
        }
    }
    if ($assessmentCounts['EOT'] > 0) {
        $eotAverage = $assessmentTotals['EOT'] / $assessmentCounts['EOT'];
    }
    $position = $positions[$pupilId] ?? null;
    $storedComments = $commentsByStudent[$pupilId] ?? [];
    $classComment = trim((string)($storedComments['class_comment'] ?? ''));
    $headComment = trim((string)($storedComments['head_comment'] ?? ''));
    $classComment = $classComment !== '' ? $classComment : report_overall_comment($eotAverage, 'class', $pupilId + $periodTerm);
    $headComment = $headComment !== '' ? $headComment : report_overall_comment($eotAverage, 'head', $pupilId + $year);
    $aggregateForDivision = $aggregateCount === count($subjectNames) ? $aggregate : null;
    $photo = trim((string)($pupil['photo'] ?? ''));
    $fallbackInitials = htmlspecialchars(substr((string)$pupil['full_name'], 0, 1), ENT_QUOTES, 'UTF-8');
    $fallbackImage = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 120 140"><rect width="120" height="140" rx="12" fill="#e8f3eb"/><circle cx="60" cy="48" r="25" fill="#88b5a0"/><path d="M18 132c4-29 20-43 42-43s38 14 42 43" fill="#4f8b72"/><text x="60" y="60" text-anchor="middle" font-family="Arial,sans-serif" font-size="25" font-weight="700" fill="#fff">' . $fallbackInitials . '</text></svg>';
    $photoUrl = $photo !== '' ? $photo : 'data:image/svg+xml;base64,' . base64_encode($fallbackImage);
    ?>
<article class="report">
  <header class="report-head">
    <img class="report-logo" src="<?= h($logoPath) ?>" alt="School emblem">
    <div>
      <h1><?= h($schoolName) ?></h1>
      <p><?= h($schoolSettings['address'] ?? '') ?></p>
      <p><?= h($schoolSettings['phone'] ?? '') ?><?= !empty($schoolSettings['email']) ? ' · ' . h($schoolSettings['email']) : '' ?></p>
    </div>
    <img class="learner-photo" src="<?= h($photoUrl) ?>" alt="Photo of <?= h($pupil['full_name']) ?>">
  </header>
  <div class="report-heading">
    <div class="eyebrow">LEARNER PROGRESS · <?= h($year) ?></div>
    <h2>TERMINAL REPORT — TERM <?= h($termNames[$periodTerm]) ?></h2>
  </div>
  <section class="identity">
    <div><b>LEARNER:</b> <?= h($pupil['full_name']) ?></div>
    <div><b>CLASS:</b> <?= h($pupil['class_name']) ?></div>
    <div><b>ADMISSION NO.:</b> <?= h($pupil['admission_no']) ?></div>
    <div><b>ACADEMIC YEAR:</b> <?= h($year) ?></div>
    <div><b>TERM:</b> <?= h($termNames[$periodTerm]) ?></div>
    <div><b>POSITION IN CLASS:</b> <?= $position ? h((string)$position) . ' / ' . $classSize : 'Pending' ?></div>
  </section>
  <section class="report-summary">
    <div class="summary-item"><b>End-of-term average</b><?= $eotAverage === null ? 'Pending' : h(number_format($eotAverage, 1)) . '%' ?></div>
    <div class="summary-item"><b>Total EOT marks</b><?= $assessmentCounts['EOT'] ? h(number_format($assessmentTotals['EOT'], 0)) . ' / ' . count($subjectNames) * 100 : 'Pending' ?></div>
    <div class="summary-item"><b><?= $level >= 4 ? 'Aggregate' : 'Subjects marked' ?></b><?= $level >= 4 ? ($aggregateForDivision === null ? 'Pending' : h((string)$aggregateForDivision)) : h((string)$assessmentCounts['EOT']) . ' / ' . count($subjectNames) ?></div>
    <div class="summary-item"><b><?= $level >= 4 ? 'Division' : 'Primary stage' ?></b><?= $level >= 4 ? h(report_division($aggregateForDivision, count($subjectNames))) : 'Lower primary' ?></div>
  </section>
  <table class="results">
    <thead>
      <tr>
        <th rowspan="2" class="subject">SUBJECT</th>
        <th colspan="2">BOT</th><th colspan="2">MID</th><th colspan="2">EOT</th>
        <th rowspan="2" class="remark">REMARKS</th>
      </tr>
      <tr><th>MARK</th><th>GRADE</th><th>MARK</th><th>GRADE</th><th>MARK</th><th>GRADE</th></tr>
    </thead>
    <tbody>
    <?php foreach ($subjectNames as $subjectIndex => $subjectName):
        $eotScore = isset($pupilMarks[$subjectName]['EOT']['score']) ? (float)$pupilMarks[$subjectName]['EOT']['score'] : null;
        $remark = trim((string)($pupilMarks[$subjectName]['EOT']['teacher_comment'] ?? ''));
        if ($remark === '') {
            $remark = report_remark($eotScore, $pupilId + $subjectIndex + $year + $periodTerm);
        }
        ?>
      <tr>
        <td class="subject"><?= h($subjectName) ?></td>
        <?php foreach (array_keys($assessmentNames) as $assessmentCode):
            $scoreValue = $pupilMarks[$subjectName][$assessmentCode]['score'] ?? null;
            $score = $scoreValue === null ? null : (float)$scoreValue;
            ?>
        <td><?= $score === null ? '—' : h(number_format($score, 0)) ?></td><td><?= h(report_grade($score)) ?></td>
        <?php endforeach; ?>
        <td class="remark"><?= h($remark) ?></td>
      </tr>
    <?php endforeach; ?>
      <tr class="sum-row">
        <td class="subject">TOTAL</td>
        <?php foreach (array_keys($assessmentNames) as $assessmentCode): ?>
        <td colspan="2"><?= $assessmentCounts[$assessmentCode] ? h(number_format($assessmentTotals[$assessmentCode], 0)) . ' / ' . count($subjectNames) * 100 : '—' ?></td>
        <?php endforeach; ?>
        <td class="remark">EOT average <?= $eotAverage === null ? '—' : h(number_format($eotAverage, 1)) . '%' ?></td>
      </tr>
    </tbody>
  </table>
  <div class="grade-scale">
    <div><b>GRADING SCALE</b></div><div><b>A</b>90–100</div><div><b>B</b>80–89</div><div><b>C</b>60–79</div><div><b>D</b>50–59</div><div><b>E</b>0–49</div>
  </div>
  <section class="comment-box">
    <h3>CLASS TEACHER’S COMMENTS</h3><p><?= h($classComment) ?></p>
    <div class="signature-row"><div class="signature-line">Signature required<small>Class teacher · <?= h($pupil['class_name']) ?></small></div></div>
  </section>
  <section class="comment-box">
    <h3>HEAD TEACHER’S COMMENTS</h3><p><?= h($headComment) ?></p>
    <div class="signature-row"><div class="signature-line">Signature required<small>Head teacher</small></div></div>
  </section>
  <section class="requirements">
    <h3>SCHOOL REQUIREMENTS FOR NEXT TERM</h3>
    <?php if ($requirementItems !== []): ?><ul><?php foreach ($requirementItems as $item): ?><li><?= h($item) ?></li><?php endforeach; ?></ul><?php else: ?><p>See the school office for next-term requirements.</p><?php endif; ?>
  </section>
  <div class="report-bottom">
    <span><?= $nextTermDate !== '' ? 'NEXT TERM: ' . h($nextTermDate) : '' ?></span>
    <span>Position is based on end-of-term marks recorded for this class.</span>
  </div>
  <div class="motto"><?= h($schoolSettings['motto'] ?? '') ?></div>
</article>
<?php endforeach; ?>
</body>
</html>
