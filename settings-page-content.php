<?php
$requirementsSchedule = decode_school_requirements(setting($pdo, 'requirements_schedule'), $classes);
$headSignature = setting($pdo, 'head_signature');
$schoolStamp = setting($pdo, 'school_stamp');
?>
<div class="page-intro">
  <div>
    <div class="eyebrow">SCHOOL IDENTITY</div>
    <h1>School settings</h1>
    <p>Manage school details, class-teacher signatures, and requirements for each class and attendance type.</p>
  </div>
</div>
<form class="panel form-panel" method="post" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <input type="hidden" name="action" value="save_settings">
  <div class="form-section-title"><span>01</span><div><h3>School information</h3><p>These details appear on learner reports.</p></div></div>
  <div class="form-grid">
    <label>School name<input name="school_name" required value="<?= h(setting($pdo,'school_name')) ?>"></label>
    <label>School motto<input name="motto" value="<?= h(setting($pdo,'motto')) ?>"></label>
    <label class="wide">Address<input name="address" value="<?= h(setting($pdo,'address')) ?>"></label>
    <label>Telephone<input name="phone" value="<?= h(setting($pdo,'phone')) ?>"></label>
    <label>Email<input type="email" name="email" value="<?= h(setting($pdo,'email')) ?>"></label>
    <label>Next term begins on<input name="next_term" value="<?= h(setting($pdo,'next_term')) ?>" placeholder="e.g. 08 September 2026"></label>
  </div>

  <div class="form-section-title"><span>02</span><div><h3>Signatures</h3><p>Upload clear JPG, PNG, or WEBP images. Transparent PNG works best.</p></div></div>
  <div class="form-grid signature-settings">
    <label>Head teacher name<input name="head_teacher_name" value="<?= h(setting($pdo,'head_teacher_name')) ?>" placeholder="Full name"></label>
    <label>Head teacher signature<input type="file" name="head_signature" accept="image/jpeg,image/png,image/webp"><small>Maximum 2 MB.</small>
      <?php if ($headSignature !== ''): ?><span class="signature-preview"><img src="<?= h($headSignature) ?>" alt="Saved head teacher signature"><span>Current head teacher signature</span></span><?php endif; ?>
    </label>
    <?php foreach ($classes as $class):
      $classId=(int)$class['id'];
      $key='class_signature_'.$classId;
      $savedClassSignature=setting($pdo,$key);
    ?>
    <label><?= h($class['name']) ?> class-teacher name<input name="class_teacher_name_<?= $classId ?>" value="<?= h(setting($pdo,'class_teacher_name_'.$classId)) ?>" placeholder="Full name"></label>
    <label><?= h($class['name']) ?> class-teacher signature<input type="file" name="<?= h($key) ?>" accept="image/jpeg,image/png,image/webp"><small>Signature used for this class only · maximum 2 MB.</small>
      <?php if ($savedClassSignature !== '' && preg_match('~^uploads/signature-[a-f0-9]{24}\.(?:jpg|png|webp)$~',$savedClassSignature) && is_file(__DIR__.'/'.$savedClassSignature)): ?><span class="signature-preview"><img src="<?= h($savedClassSignature) ?>" alt="Saved <?= h($class['name']) ?> class-teacher signature"><span>Current <?= h($class['name']) ?> signature</span></span><?php endif; ?>
    </label>
    <?php endforeach; ?>
    <label class="wide">Official school stamp image<input type="file" name="school_stamp" accept="image/png,image/webp"><small>Use a transparent PNG or WEBP stamp with no background, maximum 2 MB. The current print date will be placed over the stamp.</small>
      <?php if ($schoolStamp !== '' && preg_match('~^uploads/stamp-[a-f0-9]{24}\.(?:png|webp)$~',$schoolStamp) && is_file(__DIR__.'/'.$schoolStamp)): ?><span class="signature-preview"><img src="<?= h($schoolStamp) ?>" alt="Saved transparent school stamp"><span>Current official school stamp</span></span><?php endif; ?>
    </label>
  </div>

  <div class="form-section-title"><span>03</span><div><h3>Class requirements</h3><p>Enter one item per line in “Item: value” format. For example, School fee: UGX 200,000.</p></div></div>
  <div class="requirement-settings">
    <?php foreach ($classes as $class):
      $classId=(int)$class['id'];
    ?>
    <details class="requirement-class">
      <summary><?= h($class['name']) ?><span>Set day and boarding items</span></summary>
      <div class="requirement-types">
        <?php foreach (['day'=>'Day','boarding'=>'Boarding'] as $type=>$label): ?>
        <label><?= h($label) ?> requirements
          <textarea name="requirements[<?= $classId ?>][<?= h($type) ?>]" rows="5" placeholder="School fee: UGX 200,000&#10;Brooms: 2&#10;Toilet paper: 6"><?= h(format_school_requirements($requirementsSchedule[$classId][$type] ?? [])) ?></textarea>
        </label>
        <?php endforeach; ?>
      </div>
    </details>
    <?php endforeach; ?>
  </div>
  <div class="form-footer"><p>Save each class’s Day and Boarding requirements here. The report selects the matching list from the learner’s record.</p><button class="btn" type="submit">Save school settings</button></div>
</form>
<section class="panel">
  <div class="panel-head"><div><h3>School emblem</h3><p>The emblem appears on reports and can be shown as a light watermark.</p></div></div>
  <img class="settings-logo" src="assets/school-logo.jpg" alt="School emblem">
</section>
