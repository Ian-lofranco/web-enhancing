<?php
require __DIR__ . '/../includes/config.php';
requireRole('admin');

$pageTitle = 'Admin Dashboard | CodeUp';

$totalStudents = db()->query('SELECT COUNT(*) AS total FROM users WHERE role = "student"')->fetch_assoc()['total'];
$totalTeachers = db()->query('SELECT COUNT(*) AS total FROM users WHERE role = "teacher"')->fetch_assoc()['total'];
$totalLessons = db()->query('SELECT COUNT(*) AS total FROM lessons')->fetch_assoc()['total'];
$totalQuizzes = db()->query('SELECT COUNT(*) AS total FROM quizzes')->fetch_assoc()['total'];
$totalChallenges = db()->query('SELECT COUNT(*) AS total FROM coding_challenges')->fetch_assoc()['total'];
$pendingReviews = db()->query('SELECT COUNT(*) AS total FROM submissions WHERE status = "Pending Review"')->fetch_assoc()['total'];
$recentUsers = db()->query('SELECT * FROM users ORDER BY created_at DESC LIMIT 5');
$recentScores = db()->query('SELECT u.name, q.title AS quiz_title, s.percentage, s.submitted_at FROM scores s JOIN quizzes q ON q.id = s.quiz_id JOIN students st ON st.id = s.student_id JOIN users u ON u.id = st.user_id ORDER BY s.submitted_at DESC LIMIT 5');

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">
    <aside class="sidebar">
        <h3>Admin Panel</h3>
        <nav>
            <a class="active" href="dashboard.php">Dashboard</a>
            <a href="manage_students.php">Manage Students</a>
            <a href="manage_teachers.php">Manage Teachers</a>
            <a href="manage_lessons.php">Manage Lessons</a>
            <a href="manage_quizzes.php">Manage Quizzes</a>
            <a href="backup_database.php">Backup Database</a>
        </nav>
    </aside>

    <main class="main-panel" data-live-dashboard="dashboard_data.php">
        <div class="page-header">
            <div>
                <p class="eyebrow">System Overview</p>
                <h1>Admin Dashboard</h1>
            </div>
        </div>

        <div class="stats-grid">
            <div class="metric-card">
                <span>Students</span>
                <strong data-live-value="students"><?= $totalStudents; ?></strong>
            </div>
            <div class="metric-card">
                <span>Teachers</span>
                <strong data-live-value="teachers"><?= $totalTeachers; ?></strong>
            </div>
            <div class="metric-card">
                <span>Lessons</span>
                <strong data-live-value="lessons"><?= $totalLessons; ?></strong>
            </div>
            <div class="metric-card">
                <span>Quizzes</span>
                <strong data-live-value="quizzes"><?= $totalQuizzes; ?></strong>
            </div>
            <div class="metric-card"><span>Coding Challenges</span><strong data-live-value="challenges"><?= $totalChallenges; ?></strong></div>
            <div class="metric-card"><span>Pending Reviews</span><strong data-live-value="pending_reviews"><?= $pendingReviews; ?></strong></div>
        </div>

        <div class="content-grid two-column">
            <div class="card">
                <h3>Recent Accounts</h3>
                <ul class="list-group">
                    <?php while ($row = $recentUsers->fetch_assoc()): ?>
                        <li>
                            <span><?= htmlspecialchars($row['name']); ?></span>
                            <small><?= htmlspecialchars($row['role']); ?></small>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>

            <div class="card">
                <h3>Recent Quiz Activity</h3>
                <ul class="list-group" data-live-list="recent_scores">
                    <?php while ($score = $recentScores->fetch_assoc()): ?>
                        <li>
                            <span><?= htmlspecialchars($score['name']); ?> · <?= htmlspecialchars($score['quiz_title']); ?></span>
                            <small><?= number_format((float)$score['percentage'], 2); ?>%</small>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
