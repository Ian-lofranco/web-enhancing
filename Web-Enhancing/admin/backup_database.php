<?php
require __DIR__ . '/../includes/config.php';
requireRole('admin');

$pageTitle = 'Backup Database | CodeUp';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
    verifyCsrfToken();
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $backupRoot = $documentRoot ? dirname($documentRoot) : dirname(__DIR__, 3);
    $backupDirectory = $backupRoot . DIRECTORY_SEPARATOR . 'codeup-backups';
    $backupFile = $backupDirectory . DIRECTORY_SEPARATOR . 'codeup_backup_' . date('Ymd_His') . '.sql';
    $xamppDump = dirname($documentRoot ?: dirname(__DIR__, 2))
        . DIRECTORY_SEPARATOR . 'mysql'
        . DIRECTORY_SEPARATOR . 'bin'
        . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'mysqldump.exe' : 'mysqldump');
    $dumpBinary = is_file($xamppDump) ? $xamppDump : 'mysqldump';

    if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0700, true) && !is_dir($backupDirectory)) {
        $_SESSION['error_message'] = 'Unable to create a private backup directory.';
    } elseif (!function_exists('exec')) {
        $_SESSION['error_message'] = 'Database backup is unavailable because command execution is disabled.';
    } else {
        $command = sprintf(
            '%s --user=%s --password=%s --host=%s %s > %s',
            escapeshellarg($dumpBinary),
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($host),
            escapeshellarg($dbname),
            escapeshellarg($backupFile)
        );
        $output = [];
        $status = 0;
        exec($command, $output, $status);

        if ($status === 0 && is_file($backupFile) && filesize($backupFile) > 0) {
            $_SESSION['flash_message'] = 'Database backup created successfully outside the public web folder.';
        } else {
            if (is_file($backupFile)) {
                unlink($backupFile);
            }
            $_SESSION['error_message'] = 'Backup failed. Check that mysqldump is installed and the backup folder is writable.';
        }
    }

    redirect('admin/backup_database.php');
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">
    <aside class="sidebar">
        <h3>Admin Panel</h3>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="manage_students.php">Manage Students</a>
            <a href="manage_teachers.php">Manage Teachers</a>
            <a href="manage_lessons.php">Manage Lessons</a>
            <a href="manage_quizzes.php">Manage Quizzes</a>
            <a class="active" href="backup_database.php">Backup Database</a>
        </nav>
    </aside>

    <main class="main-panel">
        <div class="page-header">
            <div>
                <p class="eyebrow">System Tools</p>
                <h1>Database Backup</h1>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <div class="card center-card">
            <h3>Generate a database backup</h3>
            <p>Click below to export your current MySQL database to a .sql file.</p>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" name="run" value="1" class="btn btn-primary">Create Backup</button>
            </form>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
