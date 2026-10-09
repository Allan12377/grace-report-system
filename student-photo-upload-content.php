<?php
$photoStats=$pdo->query(
    'SELECT COUNT(*) total,SUM(CASE WHEN photo IS NULL OR photo=\'\' THEN 1 ELSE 0 END) missing
     FROM students WHERE deleted_at IS NULL'
)->fetch();
?>
<div class="page-intro">
  <div>
    <div class="eyebrow">LEARNER DIRECTORY</div>
    <h1>Upload pupil photos</h1>
    <p>Upload real learner photographs in batches. Each image is matched by its filename to an admission number.</p>
  </div>
  <a class="btn secondary" href="index.php?page=students">Back to students</a>
</div>
<section class="panel">
  <div class="panel-head"><div><h3>Photo coverage</h3><p><?= (int)$photoStats['missing'] ?> of <?= (int)$photoStats['total'] ?> current pupils do not have a photograph yet.</p></div></div>
  <div class="photo-upload-instructions">
    <b>Before selecting photos</b>
    <p>Name every image with the pupil’s exact admission number, followed by the image extension. For example: <code>GCP0001.jpg</code>, <code>GCP0002.png</code>.</p>
    <p>JPG, PNG and WEBP are accepted; each file must be 3 MB or smaller. Upload in smaller batches if your hosting service limits the number or total size of files.</p>
    <p>Only upload a child’s real photo when the admission number matches that child. Photos for pupils moved to trash are not accepted.</p>
  </div>
  <form class="panel form-panel photo-upload-form" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="action" value="upload_student_photos">
    <label>Select pupil photos<input type="file" name="student_photos[]" accept="image/jpeg,image/png,image/webp" multiple required><small>Choose a batch of images whose filenames match pupil admission numbers.</small></label>
    <div class="form-footer"><a class="btn secondary" href="index.php?page=students">Cancel</a><button class="btn" type="submit">Upload and match photos</button></div>
  </form>
</section>
