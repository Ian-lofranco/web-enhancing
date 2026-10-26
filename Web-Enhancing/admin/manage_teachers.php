<?php
require __DIR__ . '/../includes/config.php';
requireRole('admin');

$pageTitle = 'Manage Teachers | CodeUp';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_teacher'])) {
    verifyCsrfToken();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($password) >= 8) {
        try {
            db()->begin_transaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db()->prepare('INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, "teacher", NOW())');
            $stmt->bind_param('sss', $name, $email, $hash);
            $stmt->execute();

            $userId = $stmt->insert_id;
            $teacherCode = 'TR-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);
            $teacherStmt = db()->prepare('INSERT INTO teachers (user_id, employee_id, created_at) VALUES (?, ?, NOW())');
            $teacherStmt->bind_param('is', $userId, $teacherCode);
            $teacherStmt->execute();
            db()->commit();

            $_SESSION['flash_message'] = 'Teacher account created successfully.';
            redirect('admin/manage_teachers.php');
        } catch (mysqli_sql_exception $exception) {
            db()->rollback();
            error_log('Teacher creation failed: ' . $exception->getMessage());
            $_SESSION['error_message'] = (int)$exception->getCode() === 1062
                ? 'An account with that email already exists.'
                : 'Unable to create the teacher account. Please try again.';
        }
    } else {
        $_SESSION['error_message'] = 'Enter a name, valid email, and password with at least 8 characters.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    verifyCsrfToken();
    $teacherId = (int)($_POST['teacher_id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';

    if ($teacherId > 0 && strlen($newPassword) >= 8) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = db()->prepare('UPDATE users SET password = ? WHERE id = ? AND role = "teacher"');
        $stmt->bind_param('si', $hash, $teacherId);
        $stmt->execute();
        if ($stmt->affected_rows === 1) {
            $_SESSION['flash_message'] = 'Teacher password reset successfully.';
        } else {
            $_SESSION['error_message'] = 'No matching teacher account was found.';
        }
    } else {
        $_SESSION['error_message'] = 'Enter a password with at least 8 characters.';
    }

    redirect('admin/manage_teachers.php');
}

$teachers = db()->query('SELECT u.id, u.name, u.email, t.employee_id, u.created_at FROM users u LEFT JOIN teachers t ON t.user_id = u.id WHERE u.role = "teacher" ORDER BY u.created_at DESC');

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">
    <aside class="sidebar">
        <h3>Admin Panel</h3>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="manage_students.php">Manage Students</a>
            <a class="active" href="manage_teachers.php">Manage Teachers</a>
            <a href="manage_lessons.php">Manage Lessons</a>
            <a href="manage_quizzes.php">Manage Quizzes</a>
            <a href="backup_database.php">Backup Database</a>
        </nav>
    </aside>

    <main class="main-panel">
        <div class="page-header">
            <div>
                <p class="eyebrow">User Management</p>
                <h1>Teacher Accounts</h1>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <div class="card">
            <h3>Create Teacher</h3>
            <form method="POST" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                </div>
                <button type="submit" name="create_teacher" class="btn btn-primary">Add Teacher</button>
            </form>
        </div>

        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Employee ID</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($teacher = $teachers->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($teacher['name']); ?></td>
                            <td><?= htmlspecialchars($teacher['email']); ?></td>
                            <td><?= htmlspecialchars($teacher['employee_id'] ?? 'N/A'); ?></td>
                            <td><?= formatDate($teacher['created_at']); ?></td>
                            <td>
                                <form method="POST" class="stack-form">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="teacher_id" value="<?= (int)$teacher['id']; ?>">
                                    <input type="password" name="new_password" minlength="8" placeholder="New password" required>
                                    <button type="submit" name="reset_password" class="btn btn-primary btn-small">Reset Password</button>
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
