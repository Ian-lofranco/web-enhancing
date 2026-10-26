
<?php
require __DIR__ . '/../includes/config.php';
requireRole('student');

$pageTitle = 'Student Dashboard | CodeUp';

$user = currentUser();
$student = getStudentData((int) ($user['id'] ?? 0));

if (!$student) {
    $_SESSION['error_message'] = 'Student profile not found.';
    redirect('login.php');
    exit;
}

$studentId = (int) $student['id'];

$progressSummary = getProgressSummary($studentId);
$scoreSummary = getStudentScoreSummary($studentId);
$badgeCount = getBadgeCount($studentId);

// Recommended lessons
$lessons = db()->query(
    'SELECT title, topic
     FROM lessons
     ORDER BY created_at DESC
     LIMIT 5'
);

// Student badges
$badgesStmt = db()->prepare(
    'SELECT name, description
     FROM badges
     WHERE student_id = ?
     ORDER BY earned_at DESC
     LIMIT 5'
);
$badgesStmt->bind_param('i', $studentId);
$badgesStmt->execute();
$badgesResult = $badgesStmt->get_result();

// Recent quiz scores
$recentScoresStmt = db()->prepare(
    'SELECT
        q.title AS quiz_title,
        s.score,
        s.total_questions,
        s.percentage,
        s.submitted_at
     FROM scores s
     INNER JOIN quizzes q ON q.id = s.quiz_id
     WHERE s.student_id = ?
     ORDER BY s.submitted_at DESC
     LIMIT 5'
);
$recentScoresStmt->bind_param('i', $studentId);
$recentScoresStmt->execute();
$recentScores = $recentScoresStmt->get_result();

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <aside class="sidebar">
        <h3>Student Panel</h3>

        <nav>
            <a class="active" href="dashboard.php">Dashboard</a>
            <a href="lessons.php">Lessons</a>
            <a href="quizzes.php">Quizzes</a>
            <a href="challenges.php">Coding Challenges</a>
            <a href="../settings.php">Settings</a>
        </nav>
    </aside>

    <main class="main-panel">

        <div class="page-header">
            <div>
                <p class="eyebrow">Progress Overview</p>
                <h1>Student Dashboard</h1>
                <p>Your learning progress and achievements.</p>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <div class="stats-grid">

            <div class="metric-card">
                <span>Progress</span>
                <strong data-live-value="progress">
                    <?= number_format(
                        (float) ($progressSummary['average_progress'] ?? 0),
                        0
                    ); ?>%
                </strong>
            </div>

            <div class="metric-card">
                <span>Completed Lessons</span>
                <strong data-live-value="completed">
                    <?= (int) ($progressSummary['completed_lessons'] ?? 0); ?>
                </strong>
            </div>

            <div class="metric-card">
                <span>Badges</span>
                <strong data-live-value="badges">
                    <?= (int) $badgeCount; ?>
                </strong>
            </div>

            <div class="metric-card">
                <span>Average Score</span>
                <strong data-live-value="score">
                    <?= number_format(
                        (float) ($scoreSummary['average_score'] ?? 0),
                        2
                    ); ?>%
                </strong>
            </div>

        </div>

        <div class="content-grid two-column">

            <div class="card">
                <h3>Recommended Lessons</h3>

                <ul class="list-group" data-live-list="lessons">
                    <?php if ($lessons->num_rows > 0): ?>
                        <?php while ($lesson = $lessons->fetch_assoc()): ?>
                            <li>
                                <span><?= htmlspecialchars(
                                    $lesson['title'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?></span>

                                <small><?= htmlspecialchars(
                                    $lesson['topic'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?></small>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li><span>No lessons available yet.</span></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="card">
                <h3>Achievement Badges</h3>

                <ul class="list-group" data-live-list="badges">
                    <?php if ($badgesResult->num_rows > 0): ?>
                        <?php while ($badge = $badgesResult->fetch_assoc()): ?>
                            <li>
                                <span><?= htmlspecialchars(
                                    $badge['name'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?></span>

                                <small><?= htmlspecialchars(
                                    $badge['description'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?></small>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li><span>No badges earned yet.</span></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="card">
                <h3>Recent Quiz Attempts</h3>

                <ul class="list-group" data-live-list="scores">
                    <?php if ($recentScores->num_rows > 0): ?>
                        <?php while ($score = $recentScores->fetch_assoc()): ?>
                            <li>
                                <span><?= htmlspecialchars(
                                    $score['quiz_title'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?></span>

                                <small>
                                    <?= (int) ($score['score'] ?? 0); ?>
                                    /
                                    <?= (int) ($score['total_questions'] ?? 0); ?>
                                    ·
                                    <?= number_format(
                                        (float) ($score['percentage'] ?? 0),
                                        2
                                    ); ?>%
                                </small>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li><span>No quizzes taken yet.</span></li>
                    <?php endif; ?>
                </ul>
            </div>

        </div>

        <p id="dashboard-update-status"
           style="font-size:12px; opacity:.7; margin-top:16px;">
            Dashboard data is being synchronized.
        </p>

    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    let isUpdating = false;

    const statusElement = document.getElementById(
        'dashboard-update-status'
    );

    function setText(selector, value) {
        const element = document.querySelector(selector);

        if (element) {
            element.textContent = value;
        }
    }

    function renderList(selector, items, titleKey, detailKey, emptyMessage) {
        const list = document.querySelector(selector);

        if (!list) return;

        list.replaceChildren();

        if (!Array.isArray(items) || items.length === 0) {
            const li = document.createElement('li');
            const span = document.createElement('span');

            span.textContent = emptyMessage;
            li.appendChild(span);
            list.appendChild(li);
            return;
        }

        items.forEach(function (item) {
            const li = document.createElement('li');
            const title = document.createElement('span');
            const detail = document.createElement('small');

            title.textContent = item[titleKey] ?? '';
            detail.textContent = item[detailKey] ?? '';

            li.append(title, detail);
            list.appendChild(li);
        });
    }

    async function refreshDashboard() {
        if (isUpdating) return;

        isUpdating = true;

        try {
            const response = await fetch('dashboard-data.php', {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error('API request failed: ' + response.status);
            }

            const data = await response.json();

            if (data.error || data.success === false) {
                throw new Error(data.error || 'Unable to load dashboard.');
            }

            setText(
                '[data-live-value="progress"]',
                (Number(data.progress) || 0).toFixed(0) + '%'
            );

            setText(
                '[data-live-value="completed"]',
                String(Number(data.completed) || 0)
            );

            setText(
                '[data-live-value="badges"]',
                String(Number(data.badges_count) || 0)
            );

            setText(
                '[data-live-value="score"]',
                (Number(data.average_score) || 0).toFixed(2) + '%'
            );

            renderList(
                '[data-live-list="lessons"]',
                data.lessons,
                'title',
                'topic',
                'No lessons available yet.'
            );

            renderList(
                '[data-live-list="badges"]',
                data.badges,
                'name',
                'description',
                'No badges earned yet.'
            );

            renderList(
                '[data-live-list="scores"]',
                data.recent_scores,
                'quiz_title',
                'detail',
                'No quizzes taken yet.'
            );

            if (statusElement) {
                statusElement.textContent =
                    'Last updated: ' + new Date().toLocaleTimeString();
            }

        } catch (error) {
            console.error('Dashboard refresh error:', error);

            if (statusElement) {
                statusElement.textContent =
                    'Unable to refresh dashboard. Showing the last loaded data.';
            }
        } finally {
            isUpdating = false;
        }
    }

    // Load the latest saved information
    refreshDashboard();

    // Refresh every 10 seconds
    window.setInterval(refreshDashboard, 10000);
});
</script>

<?php
$badgesStmt->close();
include __DIR__ . '/../includes/footer.php';
?>
