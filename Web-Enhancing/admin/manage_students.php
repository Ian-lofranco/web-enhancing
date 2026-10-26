<?php
require __DIR__ . '/../includes/config.php';
requireRole('admin');

$pageTitle = 'Manage Students | CodeUp';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    verifyCsrfToken();
    $studentId = (int)($_POST['student_id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';

    if ($studentId > 0 && strlen($newPassword) >= 8) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = db()->prepare('UPDATE users SET password = ? WHERE id = ? AND role = "student"');
        $stmt->bind_param('si', $hash, $studentId);
        $stmt->execute();
        if ($stmt->affected_rows === 1) {
            $_SESSION['flash_message'] = 'Student password reset successfully.';
        } else {
            $_SESSION['error_message'] = 'No matching student account was found.';
        }
    } else {
        $_SESSION['error_message'] = 'Enter a password with at least 8 characters.';
    }

    redirect('admin/manage_students.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    verifyCsrfToken();
    $studentId = (int)$_POST['student_id'];
    $stmt = db()->prepare('DELETE FROM users WHERE id = ? AND role = "student"');
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    if ($stmt->affected_rows === 1) {
        $_SESSION['flash_message'] = 'Student account deleted successfully.';
    } else {
        $_SESSION['error_message'] = 'No matching student account was found.';
    }
    redirect('admin/manage_students.php');
}

$students = db()->query('SELECT u.id, u.name, u.email, s.student_id, u.created_at FROM users u LEFT JOIN students s ON s.user_id = u.id WHERE u.role = "student" ORDER BY u.created_at DESC');

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">
    <aside class="sidebar">
        <h3>Admin Panel</h3>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a class="active" href="manage_students.php">Manage Students</a>
            <a href="manage_teachers.php">Manage Teachers</a>
            <a href="manage_lessons.php">Manage Lessons</a>
            <a href="manage_quizzes.php">Manage Quizzes</a>
            <a href="backup_database.php">Backup Database</a>
        </nav>
    </aside>

    <main class="main-panel">
        <div class="page-header">
            <div>
                <p class="eyebrow">User Management</p>
                <h1>Student Accounts</h1>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Student ID</th>
                        <th>Joined</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($student = $students->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['name']); ?></td>
                            <td><?= htmlspecialchars($student['email']); ?></td>
                            <td><?= htmlspecialchars($student['student_id'] ?? 'N/A'); ?></td>
                            <td><?= formatDate($student['created_at']); ?></td>
                            <td>
                                <form method="POST" class="stack-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="student_id" value="<?= (int)$student['id']; ?>">
                                    <input type="password" name="new_password" minlength="8" placeholder="New password" required>
                                    <button type="submit" name="reset_password" class="btn btn-primary btn-small">Reset Password</button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Delete this student?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="student_id" value="<?= (int)$student['id']; ?>">
                                    <button type="submit" name="delete_student" class="btn btn-danger btn-small">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
