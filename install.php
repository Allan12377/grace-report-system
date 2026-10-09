<?php
declare(strict_types=1);
require __DIR__.'/config.php';
if (!extension_loaded('pdo_sqlite')) { http_response_code(500); exit('PHP PDO SQLite extension is missing. In XAMPP, enable pdo_sqlite in php.ini, then restart Apache.'); }
if (!is_dir(__DIR__.'/storage')) mkdir(__DIR__.'/storage',0770,true);
if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR,0770,true);
$pdo=new PDO('sqlite:'.DB_FILE,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys=ON');
$schema=[
'CREATE TABLE IF NOT EXISTS admins(id INTEGER PRIMARY KEY, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
'CREATE TABLE IF NOT EXISTS classes(id INTEGER PRIMARY KEY, name TEXT UNIQUE NOT NULL, level INTEGER NOT NULL)',
'CREATE TABLE IF NOT EXISTS students(id INTEGER PRIMARY KEY, admission_no TEXT UNIQUE NOT NULL, full_name TEXT NOT NULL, gender TEXT DEFAULT "", dob TEXT DEFAULT "", class_id INTEGER NOT NULL REFERENCES classes(id), parent_name TEXT DEFAULT "", parent_phone TEXT DEFAULT "", address TEXT DEFAULT "", photo TEXT DEFAULT "", status TEXT NOT NULL DEFAULT "Active", admission_date TEXT DEFAULT "", attendance_type TEXT NOT NULL DEFAULT "day", deleted_at TEXT DEFAULT NULL, purge_after TEXT DEFAULT NULL, trashed_status TEXT DEFAULT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP)',
'CREATE TABLE IF NOT EXISTS enrollments(id INTEGER PRIMARY KEY, student_id INTEGER NOT NULL REFERENCES students(id), class_id INTEGER NOT NULL REFERENCES classes(id), academic_year INTEGER NOT NULL, note TEXT DEFAULT "", created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(student_id,class_id,academic_year))',
'CREATE TABLE IF NOT EXISTS subjects(id INTEGER PRIMARY KEY, name TEXT UNIQUE NOT NULL, stage TEXT NOT NULL DEFAULT "all")',
'CREATE TABLE IF NOT EXISTS marks(id INTEGER PRIMARY KEY, student_id INTEGER NOT NULL REFERENCES students(id), subject_id INTEGER NOT NULL REFERENCES subjects(id), academic_year INTEGER NOT NULL, term INTEGER NOT NULL CHECK(term BETWEEN 1 AND 3), assessment TEXT NOT NULL DEFAULT "EOT", score REAL NOT NULL CHECK(score>=0 AND score<=100), teacher_comment TEXT DEFAULT "", UNIQUE(student_id,subject_id,academic_year,term,assessment))',
'CREATE TABLE IF NOT EXISTS comments(id INTEGER PRIMARY KEY, student_id INTEGER NOT NULL REFERENCES students(id), academic_year INTEGER NOT NULL, term INTEGER NOT NULL, class_comment TEXT DEFAULT "", head_comment TEXT DEFAULT "", UNIQUE(student_id,academic_year,term))',
'CREATE TABLE IF NOT EXISTS settings(key TEXT PRIMARY KEY, value TEXT NOT NULL)',
'CREATE TABLE IF NOT EXISTS audit(id INTEGER PRIMARY KEY, username TEXT, action TEXT, detail TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP)'];
foreach($schema as $sql)$pdo->exec($sql);
require __DIR__.'/subject-catalog.php';
$pdo->prepare('INSERT OR IGNORE INTO admins(username,password_hash) VALUES(?,?)')->execute([DEFAULT_ADMIN,password_hash(DEFAULT_PASSWORD,PASSWORD_DEFAULT)]);
foreach(['P1'=>1,'P2'=>2,'P3'=>3,'P4'=>4,'P5'=>5,'P6'=>6,'P7'=>7] as $n=>$l)$pdo->prepare('INSERT OR IGNORE INTO classes(name,level) VALUES(?,?)')->execute([$n,$l]);
$lower=['Social Development','Language Development I','Language Development II','Health Habits','Numbers','Writing'];
$upper=['English','Mathematics','Social Studies','Science'];
foreach($lower as $s)$pdo->prepare('INSERT OR IGNORE INTO subjects(name,stage) VALUES(?,?)')->execute([$s,'lower']);
foreach($upper as $s)$pdo->prepare('INSERT OR IGNORE INTO subjects(name,stage) VALUES(?,?)')->execute([$s,'upper']);
$settings=['school_name'=>'GRACE CHRISTIAN PRIMARY SCHOOL, KACUNGWA','motto'=>'The Grace of God is sufficient','address'=>'Physical location: 2 miles off Mubende–Kabamba road, before 16 Trading Centre, P.O. BOX 333 Mubende','phone'=>'+256 782 662 656 / +256 757 056 620','email'=>'gracechristianprimaryschoolkac@gmail.com','next_term'=>'','school_requirements'=>'School fees; toilet paper; books; pens; broom'];
foreach($settings as $k=>$v)$pdo->prepare('INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)')->execute([$k,$v]);
$count=(int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
$seedDemo=PHP_SAPI==='cli'&&in_array('--demo',$argv??[],true);
if($count===0&&$seedDemo){$first=['Amina','Brian','Carol','Daniel','Esther','Frank','Grace','Henry','Irene','Joseph','Kevin','Lydia','Moses','Nancy','Oscar','Patricia','Robert','Sarah','Timothy','Violet'];$last=['Akena','Busingye','Kato','Nabirye','Tumusiime','Mugisha','Namara','Ssemanda','Atwine','Kakooza','Nansubuga','Okello','Asiimwe','Nabukenya','Kagoya','Tendo','Namutebi','Byaruhanga','Nsubuga','Auma'];$classes=$pdo->query('SELECT id,name,level FROM classes ORDER BY level')->fetchAll(PDO::FETCH_ASSOC);$ins=$pdo->prepare('INSERT INTO students(admission_no,full_name,gender,dob,class_id,parent_name,parent_phone,address,status,admission_date) VALUES(?,?,?,?,?,?,?,?,?,?)');$en=$pdo->prepare('INSERT OR IGNORE INTO enrollments(student_id,class_id,academic_year,note) VALUES(?,?,?,?)');$mi=$pdo->prepare('INSERT OR IGNORE INTO marks(student_id,subject_id,academic_year,term,assessment,score,teacher_comment) VALUES(?,?,?,?,?,?,?)');$subjects=$pdo->query('SELECT id,name,stage FROM subjects')->fetchAll(PDO::FETCH_ASSOC);for($i=1;$i<=1000;$i++){$cl=$classes[($i-1)%7];$name=$first[($i-1)%count($first)].' '.$last[(int)(($i-1)/count($first))%count($last)];$gender=$i%2?'Female':'Male';$dob=sprintf('%04d-%02d-%02d',2010+($i%7),1+($i%12),1+($i%27));$ins->execute([sprintf('GCP%04d',$i),$name,$gender,$dob,$cl['id'],'Parent of '.$name,'070'.str_pad((string)($i%10000000),7,'0',STR_PAD_LEFT),'Mubende','Active',sprintf('%04d-02-01',2020+($i%6))]);$sid=(int)$pdo->lastInsertId();$en->execute([$sid,$cl['id'],2026,'Demo enrolment']);foreach($subjects as $sub){if(($cl['level']<=3&&$sub['stage']==='upper')||($cl['level']>=4&&$sub['stage']==='lower'))continue;for($t=1;$t<=3;$t++){$score=35+(($i*7+(int)$sub['id']*11+$t*5)%66);$mi->execute([$sid,(int)$sub['id'],2026,$t,'EOT',$score,'']);}}}}
$pdo->exec('CREATE INDEX IF NOT EXISTS idx_students_class ON students(class_id,status)');$pdo->exec('CREATE INDEX IF NOT EXISTS idx_marks_term ON marks(academic_year,term,student_id)');
header('Location: index.php?installed=1');exit;
