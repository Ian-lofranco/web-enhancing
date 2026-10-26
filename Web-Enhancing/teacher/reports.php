
<?php
require __DIR__ . '/../includes/config.php';
requireRole('teacher');

$pageTitle = 'Student Reports | CodeUp';

$conn = db();
$userId = (int)($_SESSION['user_id'] ?? 0);
$teacher = getTeacherData($userId);

/**
 * Safely display text in HTML.
 */
function reportEscape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Display a friendly error if the teacher profile is missing.
 */
if (!$teacher || empty($teacher['id'])) {
    $_SESSION['error_message'] = 'Your teacher profile could not be found.';
    redirect('teacher/dashboard.php');
}

$teacherId = (int)$teacher['id'];

/**
 * Grade a coding challenge submission.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['grade_submission'])) {

    verifyCsrfToken();

    $submissionId = filter_var(
        $_POST['submission_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    $score = filter_var(
        $_POST['score'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $submissionId === false ||
        $submissionId === null ||
        $submissionId < 1 ||
        $score === false ||
        $score === null ||
        $score < 0 ||
        $score > 100
    ) {
        $_SESSION['error_message'] =
            'Please enter a valid submission and a score from 0 to 100.';
        redirect('teacher/reports.php');
    }

    try {
        // Only allow grading submissions from this teacher's challenges.
        $check = $conn->prepare(
            'SELECT s.id, s.student_id, s.status
             FROM submissions s
             INNER JOIN coding_challenges c
                ON c.id = s.challenge_id
             WHERE s.id = ?
               AND c.teacher_id = ?
             LIMIT 1'
        );

        $check->bind_param('ii', $submissionId, $teacherId);
        $check->execute();

        $submission = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$submission) {
            $_SESSION['error_message'] =
                'Submission not found or you do not have permission to grade it.';
            redirect('teacher/reports.php');
        }

        if ($submission['status'] !== 'Pending Review') {
            $_SESSION['error_message'] =
                'This submission has already been reviewed.';
            redirect('teacher/reports.php');
        }

        $conn->begin_transaction();

        $update = $conn->prepare(
            'UPDATE submissions
             SET score = ?, status = ?
             WHERE id = ?'
        );

        $newStatus = 'Reviewed';

        $update->bind_param(
            'isi',
            $score,
            $newStatus,
            $submissionId
        );

        $update->execute();
        $update->close();

        // Award the badge for a passing score.
        if ($score >= 70) {
            awardBadgeIfMissing(
                (int)$submission['student_id'],
                'Challenge Solver',
                'Earned a passing score on a coding challenge.'
            );
        }

        $conn->commit();

        $_SESSION['flash_message'] =
            'Challenge submission reviewed successfully.';

    } catch (Throwable $exception) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackException) {
            // The transaction may not have started.
        }

        error_log(
            'CodeUp grading error: ' . $exception->getMessage()
        );

        $_SESSION['error_message'] =
            'The score could not be saved. Please check the database structure and try again.';
    }

    redirect('teacher/reports.php');
}

/**
 * Load quiz results.
 *
 * Includes quizzes assigned to this teacher and shared quizzes
 * where teacher_id is NULL.
 */
$quizReports = null;

try {
    $quizReportStmt = $conn->prepare(
        'SELECT
            u.name AS student_name,
            q.title AS quiz_title,
            s.percentage,
            s.score,
            s.total_questions,
            s.submitted_at
         FROM scores s
         INNER JOIN quizzes q
            ON q.id = s.quiz_id
         INNER JOIN students st
            ON st.id = s.student_id
         INNER JOIN users u
            ON u.id = st.user_id
         WHERE q.teacher_id = ?
            OR q.teacher_id IS NULL
         ORDER BY s.submitted_at DESC'
    );

    $quizReportStmt->bind_param('i', $teacherId);
    $quizReportStmt->execute();
    $quizReports = $quizReportStmt->get_result();

} catch (Throwable $exception) {
    error_log(
        'CodeUp quiz reports error: ' . $exception->getMessage()
    );

    $_SESSION['error_message'] =
        'Quiz reports could not be loaded. Please check your database tables and columns.';
}

/**
 * Load coding challenge submissions belonging to this teacher.
 */
$challengeReports = null;

try {
    $challengeReportStmt = $conn->prepare(
        'SELECT
            s.id,
            s.code,
            s.status,
            s.score,
            s.submitted_at,
            c.title AS challenge_title,
            u.name AS student_name
         FROM submissions s
         INNER JOIN coding_challenges c
            ON c.id = s.challenge_id
         INNER JOIN students st
            ON st.id = s.student_id
         INNER JOIN users u
            ON u.id = st.user_id
         WHERE c.teacher_id = ?
         ORDER BY s.submitted_at DESC'
    );

    $challengeReportStmt->bind_param('i', $teacherId);
    $challengeReportStmt->execute();
    $challengeReports = $challengeReportStmt->get_result();

} catch (Throwable $exception) {
    error_log(
        'CodeUp challenge reports error: ' . $exception->getMessage()
    );

    $_SESSION['error_message'] =
        'Coding challenge reports could not be loaded. Please check your database tables and columns.';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <aside class="sidebar">
        <h3>Teacher Panel</h3>

        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="lessons.php">Lessons</a>
            <a href="quizzes.php">Quizzes</a>
            <a class="active" href="reports.php">Reports</a>
        </nav>
    </aside>

    <main class="main-panel">

        <div class="page-header">
            <div>
                <p class="eyebrow">Student Monitoring</p>
                <h1>Progress Reports</h1>
                <p>Review quiz results and evaluate coding submissions.</p>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <!-- QUIZ RESULTS -->
        <section class="card">
            <h3>Quiz Results</h3>

            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Quiz</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Date Submitted</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if ($quizReports && $quizReports->num_rows > 0): ?>

                            <?php while ($row = $quizReports->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?= reportEscape($row['student_name']); ?>
                                    </td>

                                    <td>
                                        <?= reportEscape($row['quiz_title']); ?>
                                    </td>

                                    <td>
                                        <?= (int)$row['score']; ?>
                                        /
                                        <?= (int)$row['total_questions']; ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            (float)$row['percentage'],
                                            2
                                        ); ?>%
                                    </td>

                                    <td>
                                        <?= reportEscape(
                                            formatDate($row['submitted_at'])
                                        ); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    No quiz results are available yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- CODING CHALLENGE SUBMISSIONS -->
        <section class="card">
            <h3>Coding Challenge Submissions</h3>

            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Challenge</th>
                            <th>Submitted Code</th>
                            <th>Status</th>
                            <th>Score / Review</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (
                            $challengeReports &&
                            $challengeReports->num_rows > 0
                        ): ?>

                            <?php while (
                                $submission = $challengeReports->fetch_assoc()
                            ): ?>

                                <tr>
                                    <td>
                                        <?= reportEscape(
                                            $submission['student_name']
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= reportEscape(
                                            $submission['challenge_title']
                                        ); ?>
                                    </td>

                                    <td>
                                        <pre class="code-block" style="max-width:350px; max-height:250px; overflow:auto; white-space:pre-wrap; overflow-wrap:anywhere;"><?= reportEscape(
                                            $submission['code']
                                        ); ?></pre>
                                    </td>

                                    <td>
                                        <?= reportEscape(
                                            $submission['status']
                                        ); ?>
                                    </td>

                                    <td>
                                        <?php if (
                                            $submission['status'] === 'Pending Review'
                                        ): ?>

                                            <form
                                                method="POST"
                                                class="stack-form"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?= reportEscape(
                                                        csrfToken()
                                                    ); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="submission_id"
                                                    value="<?= (int)$submission['id']; ?>"
                                                >

                                                <label for="score-<?= (int)$submission['id']; ?>">
                                                    Score (0–100)
                                                </label>

                                                <input
                                                    id="score-<?= (int)$submission['id']; ?>"
                                                    type="number"
                                                    name="score"
                                                    min="0"
                                                    max="100"
                                                    step="1"
                                                    value="0"
                                                    required
                                                >

                                                <button
                                                    type="submit"
                                                    name="grade_submission"
                                                    value="1"
                                                    class="btn btn-primary"
                                                >
                                                    Save Score
                                                </button>
                                            </form>

                                        <?php else: ?>

                                            <?= (int)$submission['score']; ?>%

                                        <?php endif; ?>
                                    </td>
                                </tr>

                            <?php endwhile; ?>

                        <?php else: ?>

                            <tr>
                                <td colspan="5">
                                    No coding challenge submissions are available yet.
                                </td>
                            </tr>

                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
