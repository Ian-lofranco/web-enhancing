
<?php
require __DIR__ . '/../includes/config.php';

requireRole('student');

$pageTitle = 'Quizzes | CodeUp';

/**
 * Safely escape output for HTML.
 */
function quizEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/**
 * Submit and grade a quiz.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['submit_quiz'])
) {
    verifyCsrfToken();

    $student = getStudentData((int) ($_SESSION['user_id'] ?? 0));
    $quizId = filter_var(
        $_POST['quiz_id'] ?? null,
        FILTER_VALIDATE_INT
    );

    if (!$student) {
        $_SESSION['error_message'] =
            'Your student profile could not be found. Contact an administrator.';

        redirect('student/quizzes.php');
        exit;
    }

    if (!$quizId || $quizId < 1) {
        $_SESSION['error_message'] =
            'Invalid quiz. Please select a valid quiz.';

        redirect('student/quizzes.php');
        exit;
    }

    try {
        $connection = db();

        // Confirm the quiz exists.
        $quizStmt = $connection->prepare(
            'SELECT id, title FROM quizzes WHERE id = ? LIMIT 1'
        );

        $quizStmt->bind_param('i', $quizId);
        $quizStmt->execute();

        $quiz = $quizStmt->get_result()->fetch_assoc();
        $quizStmt->close();

        if (!$quiz) {
            $_SESSION['error_message'] =
                'That quiz could not be found. Please refresh the page.';

            redirect('student/quizzes.php');
            exit;
        }

        // Get the actual questions belonging to this quiz.
        $questionsStmt = $connection->prepare(
            'SELECT id, correct_option
             FROM questions
             WHERE quiz_id = ?
             ORDER BY id'
        );

        $questionsStmt->bind_param('i', $quizId);
        $questionsStmt->execute();

        $result = $questionsStmt->get_result();
        $questions = [];

        while ($question = $result->fetch_assoc()) {
            $questions[] = $question;
        }

        $questionsStmt->close();

        $total = count($questions);

        if ($total === 0) {
            $_SESSION['error_message'] =
                'This quiz has no questions yet, so it cannot be submitted.';

            redirect('student/quizzes.php');
            exit;
        }

        // Grade answers using only questions from this quiz.
        $score = 0;

        foreach ($questions as $question) {
            $questionId = (int) $question['id'];
            $submittedAnswer = $_POST['q_' . $questionId] ?? '';

            // Ignore invalid answer formats.
            if (!is_string($submittedAnswer)) {
                continue;
            }

            $answer = strtoupper(trim($submittedAnswer));
            $correctAnswer = strtoupper(
                trim((string) $question['correct_option'])
            );

            if (
                in_array($answer, ['A', 'B', 'C', 'D'], true)
                && $answer === $correctAnswer
            ) {
                $score++;
            }
        }

        $percentage = round(($score / $total) * 100, 2);
        $studentId = (int) $student['id'];

        // Save the score and award the badge together.
        $connection->begin_transaction();

        $insertStmt = $connection->prepare(
            'INSERT INTO scores
                (student_id, quiz_id, score, total_questions, percentage, submitted_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );

        $insertStmt->bind_param(
            'iiiid',
            $studentId,
            $quizId,
            $score,
            $total,
            $percentage
        );

        $insertStmt->execute();
        $insertStmt->close();

        awardBadgeIfMissing(
            $studentId,
            'Quiz Starter',
            'Completed your first quiz.'
        );

        $connection->commit();

        $_SESSION['flash_message'] =
            'Quiz submitted successfully! You scored '
            . $score . ' out of ' . $total
            . ' (' . number_format($percentage, 2) . '%).';

    } catch (Throwable $exception) {
        if (isset($connection) && $connection instanceof mysqli) {
            try {
                $connection->rollback();
            } catch (Throwable $rollbackException) {
                // Ignore rollback errors if no transaction is active.
            }
        }

        error_log(
            'CodeUp quiz submission failed: '
            . $exception->getMessage()
        );

        $_SESSION['error_message'] =
            'Your quiz could not be submitted. Please try again.';
    }

    redirect('student/quizzes.php');
    exit;
}

// Load available quizzes.
try {
    $quizzesResult = db()->query(
        'SELECT id, title, description
         FROM quizzes
         ORDER BY created_at DESC'
    );

    $quizzes = [];

    while ($quiz = $quizzesResult->fetch_assoc()) {
        $quizzes[] = $quiz;
    }
} catch (Throwable $exception) {
    error_log(
        'CodeUp quiz loading failed: '
        . $exception->getMessage()
    );

    $quizzes = [];
    $_SESSION['error_message'] =
        'Unable to load quizzes right now. Please try again later.';
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <aside class="sidebar">
        <h3>Student Panel</h3>

        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="lessons.php">Lessons</a>
            <a class="active" href="quizzes.php">Quizzes</a>
            <a href="challenges.php">Coding Challenges</a>
            <a href="../settings.php">Settings</a>
        </nav>
    </aside>

    <main class="main-panel">

        <div class="page-header">
            <div>
                <p class="eyebrow">Assessment</p>
                <h1>Multiple Choice Quizzes</h1>
                <p>Test your understanding of fundamental programming.</p>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <?php if (empty($quizzes)): ?>
            <div class="card">
                <h3>No quizzes available</h3>
                <p>
                    There are currently no quizzes to display.
                    Please check again later.
                </p>
            </div>
        <?php else: ?>

            <?php foreach ($quizzes as $quiz): ?>
                <?php
                $quizIdForDisplay = (int) $quiz['id'];

                $questionStmt = db()->prepare(
                    'SELECT id, question_text, option_a, option_b,
                            option_c, option_d
                     FROM questions
                     WHERE quiz_id = ?
                     ORDER BY id'
                );

                $questionStmt->bind_param(
                    'i',
                    $quizIdForDisplay
                );

                $questionStmt->execute();

                $questionResult = $questionStmt->get_result();
                $quizQuestions = [];

                while ($question = $questionResult->fetch_assoc()) {
                    $quizQuestions[] = $question;
                }

                $questionStmt->close();
                ?>

                <div class="card">
                    <h2><?= quizEscape($quiz['title']); ?></h2>

                    <?php if (!empty($quiz['description'])): ?>
                        <p><?= nl2br(quizEscape($quiz['description'])); ?></p>
                    <?php endif; ?>

                    <p>
                        <strong>
                            Questions: <?= count($quizQuestions); ?>
                        </strong>
                    </p>

                    <?php if (empty($quizQuestions)): ?>
                        <p>
                            This quiz does not have any questions yet.
                        </p>
                    <?php else: ?>

                        <form method="POST" action="quizzes.php">
                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= quizEscape(csrfToken()); ?>"
                            >

                            <input
                                type="hidden"
                                name="quiz_id"
                                value="<?= $quizIdForDisplay; ?>"
                            >

                            <?php foreach ($quizQuestions as $index => $question): ?>
                                <div class="quiz-question">
                                    <p>
                                        <strong>
                                            <?= ($index + 1) . '. '; ?>
                                            <?= quizEscape($question['question_text']); ?>
                                        </strong>
                                    </p>

                                    <?php
                                    $questionId = (int) $question['id'];

                                    $options = [
                                        'A' => $question['option_a'],
                                        'B' => $question['option_b'],
                                        'C' => $question['option_c'],
                                        'D' => $question['option_d'],
                                    ];
                                    ?>

                                    <?php foreach ($options as $letter => $option): ?>
                                        <label>
                                            <input
                                                type="radio"
                                                name="q_<?= $questionId; ?>"
                                                value="<?= $letter; ?>"
                                            >

                                            <?= $letter; ?>.
                                            <?= quizEscape($option); ?>
                                        </label>
                                        <br>
                                    <?php endforeach; ?>
                                </div>

                                <hr>
                            <?php endforeach; ?>

                            <button
                                type="submit"
                                name="submit_quiz"
                                value="1"
                                class="btn btn-primary"
                            >
                                Submit Quiz
                            </button>
                        </form>

                    <?php endif; ?>
                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
