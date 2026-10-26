
<?php
require __DIR__ . '/../includes/config.php';

requireRole('teacher');

$pageTitle = 'Teacher Dashboard | CodeUp';

$conn = db();
$userId = (int)($_SESSION['user_id'] ?? 0);

// Escape output safely.
function teacherDashboardEscape($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

try {
    // Get the logged-in teacher's profile.
    $teacherData = getTeacherData($userId);

    if (!$teacherData || !isset($teacherData['id'])) {
        http_response_code(404);
        exit('Teacher profile not found. Please contact the administrator.');
    }

    $teacherId = (int)$teacherData['id'];

    // 1. Count registered student accounts.
    $studentResult = $conn->query(
        "SELECT COUNT(*) AS total
         FROM users
         WHERE role = 'student'"
    );

    $studentCount = (int)$studentResult->fetch_assoc()['total'];

    // 2. Count lessons created by this teacher.
    // Assumes lessons.created_by stores users.id.
    $lessonStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM lessons
         WHERE created_by = ?"
    );

    $lessonStmt->bind_param('i', $userId);
    $lessonStmt->execute();

    $lessonCount = (int)$lessonStmt
        ->get_result()
        ->fetch_assoc()['total'];

    $lessonStmt->close();

    // 3. Count this teacher's quizzes and shared quizzes.
    // Assumes quizzes.teacher_id stores teachers.id.
    $quizStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM quizzes
         WHERE teacher_id IS NULL
            OR teacher_id = ?"
    );

    $quizStmt->bind_param('i', $teacherId);
    $quizStmt->execute();

    $quizCount = (int)$quizStmt
        ->get_result()
        ->fetch_assoc()['total'];

    $quizStmt->close();

    // 4. Get the five most recent quiz scores.
    $scoreStmt = $conn->prepare(
        "SELECT
            s.percentage,
            u.name,
            q.title AS quiz_title
         FROM scores AS s
         INNER JOIN quizzes AS q
            ON q.id = s.quiz_id
         INNER JOIN students AS st
            ON st.id = s.student_id
         INNER JOIN users AS u
            ON u.id = st.user_id
         WHERE q.teacher_id IS NULL
            OR q.teacher_id = ?
         ORDER BY s.submitted_at DESC
         LIMIT 5"
    );

    $scoreStmt->bind_param('i', $teacherId);
    $scoreStmt->execute();

    $recentScores = $scoreStmt->get_result();

    // 5. Count coding challenge submissions awaiting review.
    // Assumes submissions and coding_challenges use these columns.
    $pendingStmt = $conn->prepare(
        "SELECT COUNT(*) AS total
         FROM submissions AS s
         INNER JOIN coding_challenges AS c
            ON c.id = s.challenge_id
         WHERE c.teacher_id = ?
           AND s.status = 'Pending Review'"
    );

    $pendingStmt->bind_param('i', $teacherId);
    $pendingStmt->execute();

    $pendingReviews = (int)$pendingStmt
        ->get_result()
        ->fetch_assoc()['total'];

    $pendingStmt->close();

} catch (Throwable $e) {
    error_log('CodeUp teacher dashboard: ' . $e->getMessage());

    http_response_code(500);
    exit('Unable to load the teacher dashboard. Please check the database configuration and PHP error log.');
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <aside class="sidebar">
        <h3>Teacher Panel</h3>

        <nav>
            <a class="active" href="dashboard.php">Dashboard</a>
            <a href="lessons.php">Lessons</a>
            <a href="quizzes.php">Quizzes</a>
            <a href="reports.php">Reports</a>
        </nav>
    </aside>

    <main
        class="main-panel"
        data-live-dashboard="dashboard_data.php"
    >

        <div class="page-header">
            <div>
                <p class="eyebrow">Instruction</p>
                <h1>Teacher Dashboard</h1>

                <p>
                    Welcome,
                    <strong>
                        <?= teacherDashboardEscape(
                            $teacherData['name']
                                ?? $_SESSION['name']
                                ?? 'Teacher'
                        ); ?>
                    </strong>
                </p>
            </div>
        </div>

        <!-- Dashboard Statistics -->
        <div class="stats-grid">

            <div class="metric-card">
                <span>Students</span>
                <strong data-live-value="students">
                    <?= $studentCount; ?>
                </strong>
            </div>

            <div class="metric-card">
                <span>My Lessons</span>
                <strong data-live-value="lessons">
                    <?= $lessonCount; ?>
                </strong>
            </div>

            <div class="metric-card">
                <span>My Quizzes</span>
                <strong data-live-value="quizzes">
                    <?= $quizCount; ?>
                </strong>
            </div>

            <div class="metric-card">
                <span>Teacher ID</span>
                <strong>
                    <?= teacherDashboardEscape(
                        $teacherData['employee_id'] ?? 'N/A'
                    ); ?>
                </strong>
            </div>

            <div class="metric-card">
                <span>Pending Reviews</span>
                <strong data-live-value="pending_reviews">
                    <?= $pendingReviews; ?>
                </strong>
            </div>

        </div>

        <!-- Dashboard Content -->
        <div class="content-grid two-column">

            <div class="card">
                <h3>Latest Quiz Scores</h3>

                <ul
                    class="list-group"
                    data-live-list="recent_scores"
                >

                    <?php if ($recentScores->num_rows > 0): ?>

                        <?php while ($score = $recentScores->fetch_assoc()): ?>

                            <li>
                                <span>
                                    <?= teacherDashboardEscape(
                                        $score['name']
                                    ); ?>

                                    ·

                                    <?= teacherDashboardEscape(
                                        $score['quiz_title']
                                    ); ?>
                                </span>

                                <strong>
                                    <?= number_format(
                                        (float)$score['percentage'],
                                        2
                                    ); ?>%
                                </strong>
                            </li>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <li class="empty-state">
                            No quiz scores available yet.
                        </li>

                    <?php endif; ?>

                </ul>
            </div>

            <div class="card">
                <h3>Coding Challenge Reviews</h3>

                <p>
                    You have
                    <strong data-live-value="pending_reviews">
                        <?= $pendingReviews; ?>
                    </strong>
                    submissions awaiting review.
                </p>

                <a class="btn btn-primary" href="reports.php">
                    Open Reports
                </a>
            </div>

        </div>

    </main>
</div>

<?php
$scoreStmt->close();

include __DIR__ . '/../includes/footer.php';
?>
