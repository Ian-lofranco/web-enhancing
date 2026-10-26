
<?php
require __DIR__ . '/../includes/config.php';

requireRole('student');

$pageTitle = 'Programming Lessons | CodeUp';

$user = currentUser();

if (!$user || empty($user['id'])) {
    $_SESSION['error_message'] = 'Please log in to continue.';
    redirect('login.php');
    exit;
}

$student = getStudentData((int) $user['id']);

if (!$student || empty($student['id'])) {
    $_SESSION['error_message'] = 'Student profile not found.';
    redirect('login.php');
    exit;
}

$studentId = (int) $student['id'];
$connection = db();

/**
 * Escape output before displaying it in HTML.
 */
function lessonEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Handle marking a lesson as completed.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['mark_complete'])
) {
    verifyCsrfToken();

    $lessonId = filter_var(
        $_POST['lesson_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$lessonId || $lessonId < 1) {
        $_SESSION['error_message'] =
            'Invalid lesson. Please try again.';

        redirect('student/lessons.php');
        exit;
    }

    $transactionStarted = false;

    try {
        // Confirm that the lesson exists.
        $lessonCheck = $connection->prepare(
            'SELECT id FROM lessons WHERE id = ? LIMIT 1'
        );

        $lessonCheck->bind_param('i', $lessonId);
        $lessonCheck->execute();

        $lessonExists = $lessonCheck->get_result()->fetch_assoc();
        $lessonCheck->close();

        if (!$lessonExists) {
            $_SESSION['error_message'] =
                'That lesson could not be found.';

            redirect('student/lessons.php');
            exit;
        }

        // Begin transaction so progress and badge changes stay consistent.
        $connection->begin_transaction();
        $transactionStarted = true;

        // Find the student's existing progress record.
        $checkProgress = $connection->prepare(
            'SELECT id
             FROM progress
             WHERE student_id = ? AND lesson_id = ?
             LIMIT 1
             FOR UPDATE'
        );

        $checkProgress->bind_param(
            'ii',
            $studentId,
            $lessonId
        );

        $checkProgress->execute();

        $existingProgress = $checkProgress
            ->get_result()
            ->fetch_assoc();

        $checkProgress->close();

        if ($existingProgress) {
            // Update the existing record instead of creating another one.
            $progressId = (int) $existingProgress['id'];

            $updateProgress = $connection->prepare(
                "UPDATE progress
                 SET status = 'completed',
                     completion_percent = 100,
                     updated_at = NOW()
                 WHERE id = ?
                   AND student_id = ?
                   AND lesson_id = ?"
            );

            $updateProgress->bind_param(
                'iii',
                $progressId,
                $studentId,
                $lessonId
            );

            $updateProgress->execute();
            $updateProgress->close();

        } else {
            // Create a new progress record.
            $insertProgress = $connection->prepare(
                "INSERT INTO progress
                    (student_id, lesson_id, status,
                     completion_percent, updated_at)
                 VALUES (?, ?, 'completed', 100, NOW())"
            );

            $insertProgress->bind_param(
                'ii',
                $studentId,
                $lessonId
            );

            $insertProgress->execute();
            $insertProgress->close();
        }

        // Award the badge if the student has not earned it already.
        awardBadgeIfMissing(
            $studentId,
            'First Lesson',
            'Completed your first learning module.'
        );

        $connection->commit();
        $transactionStarted = false;

        $_SESSION['flash_message'] =
            'Lesson marked as completed successfully!';

    } catch (Throwable $exception) {
        if ($transactionStarted) {
            try {
                $connection->rollback();
            } catch (Throwable $rollbackException) {
                error_log(
                    'CodeUp progress rollback failed: '
                    . $rollbackException->getMessage()
                );
            }
        }

        error_log(
            'CodeUp lesson completion failed: '
            . $exception->getMessage()
        );

        $_SESSION['error_message'] =
            'Unable to mark this lesson as complete. '
            . 'Please try again.';
    }

    redirect('student/lessons.php');
    exit;
}

/**
 * Load all lessons.
 */
try {
    $lessonsResult = $connection->query(
        'SELECT id, title, topic, content, objectives
         FROM lessons
         ORDER BY created_at DESC'
    );

    $lessons = [];

    while ($lesson = $lessonsResult->fetch_assoc()) {
        $lessons[] = $lesson;
    }

} catch (Throwable $exception) {
    error_log(
        'CodeUp lessons loading failed: '
        . $exception->getMessage()
    );

    $lessons = [];

    $_SESSION['error_message'] =
        'Unable to load lessons. Please check your database.';
}

/**
 * Load this student's lesson progress.
 */
$lessonProgress = [];

try {
    $progressStmt = $connection->prepare(
        'SELECT lesson_id, status, completion_percent
         FROM progress
         WHERE student_id = ?'
    );

    $progressStmt->bind_param('i', $studentId);
    $progressStmt->execute();

    $progressResult = $progressStmt->get_result();

    while ($progress = $progressResult->fetch_assoc()) {
        $lessonProgress[(int) $progress['lesson_id']] = $progress;
    }

    $progressStmt->close();

} catch (Throwable $exception) {
    error_log(
        'CodeUp student progress loading failed: '
        . $exception->getMessage()
    );

    $_SESSION['error_message'] =
        'Unable to load your lesson progress. Please refresh the page.';
}

$completedCount = 0;

foreach ($lessons as $lesson) {
    $id = (int) $lesson['id'];
    $progress = $lessonProgress[$id] ?? null;

    if (
        $progress
        && strtolower((string) $progress['status']) === 'completed'
        && (int) $progress['completion_percent'] >= 100
    ) {
        $completedCount++;
    }
}

$totalLessons = count($lessons);

$overallProgress = $totalLessons > 0
    ? (int) round(($completedCount / $totalLessons) * 100)
    : 0;

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <aside class="sidebar">
        <h3>Student Panel</h3>

        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a class="active" href="lessons.php">Lessons</a>
            <a href="quizzes.php">Quizzes</a>
            <a href="challenges.php">Coding Challenges</a>
            <a href="../settings.php">Settings</a>
        </nav>
    </aside>

    <main class="main-panel">

        <div class="page-header">
            <div>
                <p class="eyebrow">Learning Path</p>
                <h1>Programming Lessons</h1>
                <p>
                    Study each topic and mark it complete when
                    you finish learning it.
                </p>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <!-- Lesson progress summary -->
        <div class="card">
            <h3>Your Learning Progress</h3>

            <p>
                Completed:
                <strong>
                    <?= $completedCount; ?> /
                    <?= $totalLessons; ?> lessons
                </strong>
            </p>

            <p>
                Overall progress:
                <strong><?= $overallProgress; ?>%</strong>
            </p>

            <div
                style="background:#e5e7eb;border-radius:10px;height:12px;overflow:hidden;"
                role="progressbar"
                aria-label="Lesson completion progress"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="<?= $overallProgress; ?>"
            >
                <div
                    style="height:12px;width:<?= $overallProgress; ?>%;background:#2563eb;border-radius:10px;transition:width .3s;"
                ></div>
            </div>
        </div>

        <?php if (empty($lessons)): ?>

            <div class="card">
                <h3>No Lessons Available</h3>
                <p>Your teacher has not added any lessons yet.</p>
            </div>

        <?php else: ?>

            <div class="content-grid">

                <?php foreach ($lessons as $lesson): ?>

                    <?php
                    $lessonId = (int) $lesson['id'];
                    $progress = $lessonProgress[$lessonId] ?? null;

                    $isCompleted = (
                        $progress
                        && strtolower(
                            (string) ($progress['status'] ?? '')
                        ) === 'completed'
                        && (int) (
                            $progress['completion_percent'] ?? 0
                        ) >= 100
                    );
                    ?>

                    <div class="card lesson-card">

                        <div class="challenge-header">
                            <span class="tag">
                                <?= lessonEscape(
                                    $lesson['topic'] ?: 'Programming'
                                ); ?>
                            </span>

                            <?php if ($isCompleted): ?>
                                <span class="tag">
                                    Completed
                                </span>
                            <?php endif; ?>
                        </div>

                        <h3>
                            <?= lessonEscape($lesson['title']); ?>
                        </h3>

                        <div class="lesson-content">
                            <p>
                                <?= nl2br(
                                    lessonEscape($lesson['content'])
                                ); ?>
                            </p>
                        </div>

                        <?php if (!empty($lesson['objectives'])): ?>
                            <div class="lesson-objectives">
                                <p><strong>Learning Objectives</strong></p>

                                <p>
                                    <?= nl2br(
                                        lessonEscape(
                                            $lesson['objectives']
                                        )
                                    ); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <form
                            method="POST"
                            action="lessons.php"
                            onsubmit="this.querySelector('button[type=submit]').disabled=true;"
                        >
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= lessonEscape(csrfToken()); ?>"
                            >

                            <input
                                type="hidden"
                                name="lesson_id"
                                value="<?= $lessonId; ?>"
                            >

                            <button
                                type="submit"
                                name="mark_complete"
                                value="1"
                                class="btn btn-primary"
                                <?= $isCompleted ? 'disabled' : ''; ?>
                            >
                                <?= $isCompleted
                                    ? 'Lesson Completed'
                                    : 'Mark Complete'; ?>
                            </button>
                        </form>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
