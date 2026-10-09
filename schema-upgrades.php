<?php
declare(strict_types=1);

function upgrade_database_schema(PDO $pdo): void
{
    $studentColumns = array_column($pdo->query('PRAGMA table_info(students)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('attendance_type', $studentColumns, true)) {
        $pdo->exec("ALTER TABLE students ADD COLUMN attendance_type TEXT NOT NULL DEFAULT 'day'");
    }
    if (!in_array('deleted_at', $studentColumns, true)) {
        $pdo->exec('ALTER TABLE students ADD COLUMN deleted_at TEXT DEFAULT NULL');
    }
    if (!in_array('purge_after', $studentColumns, true)) {
        $pdo->exec('ALTER TABLE students ADD COLUMN purge_after TEXT DEFAULT NULL');
    }
    if (!in_array('trashed_status', $studentColumns, true)) {
        $pdo->exec('ALTER TABLE students ADD COLUMN trashed_status TEXT DEFAULT NULL');
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_students_trash_expiry ON students(deleted_at,purge_after)');
}

function purge_expired_student_trash(PDO $pdo): int
{
    $expired = $pdo->query(
        "SELECT id,photo FROM students WHERE deleted_at IS NOT NULL AND purge_after <= CURRENT_TIMESTAMP"
    )->fetchAll(PDO::FETCH_ASSOC);
    if ($expired === []) {
        return 0;
    }

    $pdo->beginTransaction();
    try {
        foreach ($expired as $student) {
            $id = (int)$student['id'];
            foreach (['marks', 'comments', 'enrollments'] as $table) {
                $delete = $pdo->prepare("DELETE FROM {$table} WHERE student_id=?");
                $delete->execute([$id]);
            }
            $deleteStudent = $pdo->prepare(
                'DELETE FROM students WHERE id=? AND deleted_at IS NOT NULL AND purge_after <= CURRENT_TIMESTAMP'
            );
            $deleteStudent->execute([$id]);
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }

    foreach ($expired as $student) {
        $photo = (string)($student['photo'] ?? '');
        if (preg_match('~^uploads/[a-f0-9]{24}\.(?:jpg|png|webp)$~', $photo) && is_file(__DIR__ . '/' . $photo)) {
            if (!unlink(__DIR__ . '/' . $photo)) {
                error_log('Could not remove expired student photo: ' . $photo);
            }
        }
    }

    return count($expired);
}

function next_student_admission_number(PDO $pdo): string
{
    $numbers = $pdo->query('SELECT admission_no FROM students')->fetchAll(PDO::FETCH_COLUMN);
    $used = [];
    $highest = 0;
    foreach ($numbers as $number) {
        $number = (string)$number;
        $used[$number] = true;
        if (preg_match('/^GCP([0-9]+)$/i', $number, $matches)) {
            $highest = max($highest, (int)$matches[1]);
        }
    }

    do {
        $candidate = 'GCP' . str_pad((string)++$highest, 4, '0', STR_PAD_LEFT);
    } while (isset($used[$candidate]));

    return $candidate;
}
