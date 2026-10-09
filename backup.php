<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found.\n");
}

if ($argc < 2 || $argc > 3) {
    fwrite(STDERR, "Usage: php backup.php <application-folder> [backup-folder]\n");
    exit(2);
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "The PDO SQLite extension is required. Enable pdo_sqlite in php.ini.\n");
    exit(1);
}

$appDir = realpath($argv[1]);
if ($appDir === false || !is_dir($appDir)) {
    fwrite(STDERR, "Application folder not found: {$argv[1]}\n");
    exit(1);
}

$databasePath = $appDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'school.sqlite';
$uploadsPath = $appDir . DIRECTORY_SEPARATOR . 'uploads';
$backupRoot = $argv[2] ?? 'C:\\SchoolReportBackups';

if (!is_file($databasePath)) {
    fwrite(STDERR, "Database not found: {$databasePath}\n");
    exit(1);
}
if (!is_dir($uploadsPath)) {
    fwrite(STDERR, "Uploads folder not found; stopping so pupil photos are not silently omitted.\n");
    exit(1);
}
if (!is_dir($backupRoot) && !mkdir($backupRoot, 0770, true) && !is_dir($backupRoot)) {
    fwrite(STDERR, "Could not create backup folder: {$backupRoot}\n");
    exit(1);
}

$backupRoot = realpath($backupRoot);
if ($backupRoot === false) {
    fwrite(STDERR, "Could not resolve the backup folder.\n");
    exit(1);
}

$backupName = 'school-report-' . date('Ymd-His');
$finalPath = $backupRoot . DIRECTORY_SEPARATOR . $backupName;
$suffix = 1;
while (file_exists($finalPath)) {
    $finalPath = $backupRoot . DIRECTORY_SEPARATOR . $backupName . '-' . $suffix++;
}
$stagingPath = $finalPath . '.partial';
if (!mkdir($stagingPath, 0770)) {
    fwrite(STDERR, "Could not create backup staging folder: {$stagingPath}\n");
    exit(1);
}

try {
    $source = new PDO('sqlite:' . $databasePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $source->exec('PRAGMA busy_timeout=10000');
    $sqliteVersion = (string)$source->query('SELECT sqlite_version()')->fetchColumn();
    if (version_compare($sqliteVersion, '3.27.0', '<')) {
        throw new RuntimeException("SQLite {$sqliteVersion} is too old for a consistent online backup; use a current XAMPP PHP release.");
    }

    $databaseCopy = $stagingPath . DIRECTORY_SEPARATOR . 'school.sqlite';
    $quotedCopyPath = str_replace("'", "''", $databaseCopy);
    $source->exec("VACUUM INTO '{$quotedCopyPath}'");

    $target = new PDO('sqlite:' . $databaseCopy, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $check = $target->query('PRAGMA quick_check')->fetchColumn();
    if ($check !== 'ok') {
        throw new RuntimeException('The copied database did not pass SQLite quick_check.');
    }
    $target = null;
    $source = null;

    $destinationUploads = $stagingPath . DIRECTORY_SEPARATOR . 'uploads';
    if (!mkdir($destinationUploads, 0770)) {
        throw new RuntimeException('Could not create the uploads backup folder.');
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if ($item->isLink()) {
            throw new RuntimeException('A symbolic link was found in uploads; backup stopped.');
        }

        $relativePath = substr($item->getPathname(), strlen($uploadsPath) + 1);
        $destinationPath = $destinationUploads . DIRECTORY_SEPARATOR . $relativePath;
        if ($item->isDir()) {
            if (!is_dir($destinationPath) && !mkdir($destinationPath, 0770, true) && !is_dir($destinationPath)) {
                throw new RuntimeException("Could not create folder in upload backup: {$relativePath}");
            }
        } elseif ($item->isFile() && !copy($item->getPathname(), $destinationPath)) {
            throw new RuntimeException("Could not copy uploaded file: {$relativePath}");
        }
    }

    if (!rename($stagingPath, $finalPath)) {
        throw new RuntimeException('Could not finalize the backup folder.');
    }

    fwrite(STDOUT, "Backup completed and database integrity checked:\n{$finalPath}\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Backup failed: {$error->getMessage()}\nIncomplete files, if any, remain at:\n{$stagingPath}\n");
    exit(1);
}
