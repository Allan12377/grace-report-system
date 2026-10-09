<div class="page-intro">
  <div><div class="eyebrow">STUDENT INFORMATION</div><h1><?= $student['id']?'Edit learner':'Register a learner' ?></h1><p>Assign the learner’s class and Day or Boarding status so the correct requirements appear on the report.</p></div>
  <a class="btn secondary" href="index.php?page=students">Back to students</a>
</div>
<form class="panel form-panel" method="post" enctype="multipart/form-data" onsubmit="return this.querySelector('[name=status]').value!=='Trash'||confirm('Move this learner to Recently deleted? The record can be restored for 90 days before automatic deletion.')">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <input type="hidden" name="action" value="save_student">
  <input type="hidden" name="id" value="<?= (int)$student['id'] ?>">
  <div class="form-section-title"><span>01</span><div><h3>Personal information</h3><p>Basic details and class placement</p></div></div>
  <div class="form-grid">
    <label>Full name *<input required name="full_name" value="<?= h($student['full_name']) ?>" placeholder="Student's full name"></label>
    <label>Admission number<?= (int)$student['id']>0?' *':'' ?><input name="admission_no" value="<?= h($student['admission_no']) ?>" <?= (int)$student['id']>0?'required':'readonly' ?> placeholder="Generated when saved"><small class="field-hint"><?= (int)$student['id']>0?'Edit the assigned admission number if required.':'Automatically generated from the next available school number.' ?></small></label>
    <label>Gender<select name="gender"><option value="">Select gender</option><?php foreach(['Female','Male','Other'] as $gender): ?><option value="<?= h($gender) ?>" <?= $student['gender']===$gender?'selected':'' ?>><?= h($gender) ?></option><?php endforeach; ?></select></label>
    <label>Date of birth<input type="date" name="dob" value="<?= h($student['dob']) ?>"></label>
    <label>Class<select name="class_id" required><?php foreach($classes as $class): ?><option value="<?= (int)$class['id'] ?>" <?= (int)$student['class_id']===(int)$class['id']?'selected':'' ?>><?= h($class['name']) ?></option><?php endforeach; ?></select></label>
    <label>Attendance type<select name="attendance_type"><option value="day" <?= ($student['attendance_type']??'day')==='day'?'selected':'' ?>>Day</option><option value="boarding" <?= ($student['attendance_type']??'day')==='boarding'?'selected':'' ?>>Boarding</option></select><small class="field-hint">Determines which requirements print on this learner’s report.</small></label>
    <label>Admission date<input type="date" name="admission_date" value="<?= h($student['admission_date']) ?>"></label>
    <label>Profile photograph<input type="file" name="photo" accept="image/png,image/jpeg,image/webp"><small class="field-hint">JPG, PNG or WEBP · maximum 3 MB</small></label>
    <label>Status<select name="status"><option value="Active" <?= $student['status']==='Active'?'selected':'' ?>>Active</option><option value="Inactive" <?= $student['status']==='Inactive'?'selected':'' ?>>Inactive</option><?php if ((int)$student['id']>0): ?><option value="Trash">Trash</option><?php endif; ?></select><small class="field-hint">Inactive pupils remain in history. Choose Trash to move this learner to Recently deleted for 90 days.</small></label>
  </div>
  <div class="form-section-title"><span>02</span><div><h3>Parent or guardian</h3><p>Contact information for school communication</p></div></div>
  <div class="form-grid">
    <label>Parent / guardian name<input name="parent_name" value="<?= h($student['parent_name']) ?>" placeholder="Parent or guardian"></label>
    <label>Phone number<input name="parent_phone" value="<?= h($student['parent_phone']) ?>" placeholder="e.g. 07xx xxx xxx"></label>
    <label class="wide">Home address<input name="address" value="<?= h($student['address']) ?>" placeholder="Village, parish, district"></label>
  </div>
  <div class="form-footer"><p>Fields marked * are required.</p><button class="btn" type="submit">Save student record</button></div>
</form>
