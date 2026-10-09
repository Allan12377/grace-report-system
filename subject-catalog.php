<?php
declare(strict_types=1);

$legacySubjects = [
    'Literacy I' => 'Language Development I',
    'Literacy II' => 'Language Development II',
    'Numeracy' => 'Numbers',
    'Creative Activities' => 'Writing',
];

foreach ($legacySubjects as $oldName => $newName) {
    $rename = $pdo->prepare(
        'UPDATE subjects SET name=? WHERE name=? AND NOT EXISTS (SELECT 1 FROM subjects WHERE name=?)'
    );
    $rename->execute([$newName, $oldName, $newName]);
}

$lowerSubjects = [
    'Social Development',
    'Language Development I',
    'Language Development II',
    'Health Habits',
    'Numbers',
    'Writing',
];
$upperSubjects = ['English', 'Mathematics', 'Social Studies', 'Science'];

$insertSubject = $pdo->prepare('INSERT OR IGNORE INTO subjects(name,stage) VALUES(?,?)');
foreach ($lowerSubjects as $subjectName) {
    $insertSubject->execute([$subjectName, 'lower']);
}
foreach ($upperSubjects as $subjectName) {
    $insertSubject->execute([$subjectName, 'upper']);
}
