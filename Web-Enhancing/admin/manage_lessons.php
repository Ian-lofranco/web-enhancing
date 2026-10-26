<?php
require __DIR__ . '/../includes/config.php';
requireRole('admin');

$pageTitle = 'Manage Lessons | CodeUp';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lesson'])) {
    verifyCsrfToken();
    $title = trim($_POST['title'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $objectives = trim($_POST['objectives'] ?? '');

    if ($title && $topic && $content) {
        $stmt = db()->prepare('INSERT INTO lessons (title, topic, content, objectives, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $createdBy = (int)$_SESSION['user_id'];
        $stmt->bind_param('ssssi', $title, $topic, $content, $objectives, $createdBy);
        $stmt->execute();
        $_SESSION['flash_message'] = 'Lesson added successfully.';
        redirect('admin/manage_lessons.php');
    } else {
        $_SESSION['error_message'] = 'Please fill in all lesson fields.';
    }
}

$lessons = db()->query('SELECT * FROM lessons ORDER BY created_at DESC');

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">
    <aside class="sidebar">
        <h3>Admin Panel</h3>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="manage_students.php">Manage Students</a>
            <a href="manage_teachers.php">Manage Teachers</a>
            <a class="active" href="manage_lessons.php">Manage Lessons</a>
            <a href="manage_quizzes.php">Manage Quizzes</a>
            <a href="backup_database.php">Backup Database</a>
        </nav>
    </aside>

    <main class="main-panel">
        <div class="page-header">
            <div>
                <p class="eyebrow">Content Management</p>
                <h1>Lessons</h1>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <div class="card">
            <h3>Add Lesson</h3>
            <form method="POST" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label>Lesson Title</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>Topic</label>
                    <input type="text" name="topic" required>
                </div>
                <div class="form-group">
                    <label>Content</label>
                    <textarea name="content" rows="6" required></textarea>
                </div>
                <div class="form-group">
                    <label>Learning Objectives</label>
                    <textarea name="objectives" rows="3"></textarea>
                </div>
                <button type="submit" name="add_lesson" class="btn btn-primary">Save Lesson</button>
            </form>
        </div>

        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Topic</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($lesson = $lessons->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($lesson['title']); ?></td>
                            <td><?= htmlspecialchars($lesson['topic']); ?></td>
                            <td><?= formatDate($lesson['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
