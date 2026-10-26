
<?php
require __DIR__ . '/../includes/config.php';

requireRole('teacher');

$pageTitle = 'Manage Quizzes | CodeUp';

$conn = db();
$userId = (int)($_SESSION['user_id'] ?? 0);

// Safely escape output.
function quizEscape($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Get the logged-in teacher.
$teacher = getTeacherData($userId);

if (!$teacher || !isset($teacher['id'])) {
    http_response_code(404);
    exit('Teacher profile not found. Please contact the administrator.');
}

$teacherId = (int)$teacher['id'];

// Handle quiz creation.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['create_quiz'])
) {
    verifyCsrfToken();

    $title = trim($_POST['title'] ?? '');
    $lessonId = (int)($_POST['lesson_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');

    $a = trim($_POST['option_a'] ?? '');
    $b = trim($_POST['option_b'] ?? '');
    $c = trim($_POST['option_c'] ?? '');
    $d = trim($_POST['option_d'] ?? '');

    $correct = strtoupper(
        trim($_POST['correct_option'] ?? '')
    );

    if (
        $title === '' ||
        $lessonId <= 0 ||
        $question === '' ||
        $a === '' ||
        $b === '' ||
        $c === '' ||
        $d === '' ||
        !in_array($correct, ['A', 'B', 'C', 'D'], true)
    ) {
        $_SESSION['error_message'] =
            'Please complete all fields and select a valid correct answer.';

        redirect('teacher/quizzes.php');
        exit;
    }

    try {
        /*
         * Confirm that the selected lesson belongs to this teacher
         * or is a shared lesson.
         *
         * Assumption: lessons.created_by stores users.id.
         */
        $lessonCheck = $conn->prepare(
            'SELECT id
             FROM lessons
             WHERE id = ?
               AND (created_by = ? OR created_by IS NULL)
             LIMIT 1'
        );

        $lessonCheck->bind_param(
            'ii',
            $lessonId,
            $userId
        );

        $lessonCheck->execute();

        $lessonResult = $lessonCheck->get_result();
        $lessonExists = $lessonResult->num_rows > 0;

        $lessonCheck->close();

        if (!$lessonExists) {
            $_SESSION['error_message'] =
                'The selected lesson does not exist or is not available to you.';

            redirect('teacher/quizzes.php');
            exit;
        }

        // Save the quiz and its first question together.
        $conn->begin_transaction();

        try {
            $quizStmt = $conn->prepare(
                'INSERT INTO quizzes
                    (title, lesson_id, teacher_id, created_at)
                 VALUES (?, ?, ?, NOW())'
            );

            $quizStmt->bind_param(
                'sii',
                $title,
                $lessonId,
                $teacherId
            );

            $quizStmt->execute();

            $quizId = (int)$conn->insert_id;

            $quizStmt->close();

            $questionStmt = $conn->prepare(
                'INSERT INTO questions
                    (
                        quiz_id,
                        question_text,
                        option_a,
                        option_b,
                        option_c,
                        option_d,
                        correct_option,
                        created_at
                    )
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );

            $questionStmt->bind_param(
                'issssss',
                $quizId,
                $question,
                $a,
                $b,
                $c,
                $d,
                $correct
            );

            $questionStmt->execute();
            $questionStmt->close();

            $conn->commit();

            $_SESSION['flash_message'] =
                'Quiz created successfully.';

        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

    } catch (Throwable $e) {
        error_log(
            'CodeUp quiz creation error: ' . $e->getMessage()
        );

        $_SESSION['error_message'] =
            'Unable to save the quiz. Please check your database tables and selected lesson.';
    }

    redirect('teacher/quizzes.php');
    exit;
}

// Get lessons available to this teacher.
try {
    $lessonStmt = $conn->prepare(
        'SELECT id, title
         FROM lessons
         WHERE created_by = ? OR created_by IS NULL
         ORDER BY created_at DESC'
    );

    $lessonStmt->bind_param('i', $userId);
    $lessonStmt->execute();

    $lessons = $lessonStmt->get_result();

    // Get this teacher's quizzes and shared quizzes.
    $quizStmt = $conn->prepare(
        'SELECT id, title, created_at
         FROM quizzes
         WHERE teacher_id = ? OR teacher_id IS NULL
         ORDER BY created_at DESC'
    );

    $quizStmt->bind_param('i', $teacherId);
    $quizStmt->execute();

    $quizzes = $quizStmt->get_result();

} catch (Throwable $e) {
    error_log(
        'CodeUp quiz listing error: ' . $e->getMessage()
    );

    http_response_code(500);
    exit(
        'Unable to load quizzes. Please check the database configuration and PHP error log.'
    );
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <aside class="sidebar">
        <h3>Teacher Panel</h3>

        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="lessons.php">Lessons</a>
            <a class="active" href="quizzes.php">Quizzes</a>
            <a href="reports.php">Reports</a>
        </nav>
    </aside>

    <main class="main-panel">

        <div class="page-header">
            <div>
                <p class="eyebrow">Assessment</p>
                <h1>Manage Quizzes</h1>
                <p>Create programming quizzes for your students.</p>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <!-- Create Quiz Form -->
        <div class="card">
            <h3>Create New Quiz</h3>

            <form method="POST" class="stack-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= quizEscape(csrfToken()); ?>"
                >

                <div class="form-group">
                    <label for="title">Quiz Title</label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="255"
                        placeholder="Example: Variables Quiz"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="lesson_id">Select Lesson</label>

                    <select
                        id="lesson_id"
                        name="lesson_id"
                        required
                    >
                        <option value="" disabled selected>
                            Select a lesson
                        </option>

                        <?php if ($lessons->num_rows > 0): ?>

                            <?php while ($lesson = $lessons->fetch_assoc()): ?>
                                <option value="<?= (int)$lesson['id']; ?>">
                                    <?= quizEscape($lesson['title']); ?>
                                </option>
                            <?php endwhile; ?>

                        <?php endif; ?>
                    </select>

                    <?php if ($lessons->num_rows === 0): ?>
                        <p>
                            No lessons are available.
                            <a href="lessons.php">Create a lesson first</a>.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="question">Question</label>

                    <textarea
                        id="question"
                        name="question"
                        rows="3"
                        placeholder="Enter your question..."
                        required
                    ></textarea>
                </div>

                <div class="form-row">

                    <div class="form-group">
                        <label for="option_a">Option A</label>

                        <input
                            type="text"
                            id="option_a"
                            name="option_a"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="option_b">Option B</label>

                        <input
                            type="text"
                            id="option_b"
                            name="option_b"
                            required
                        >
                    </div>

                </div>

                <div class="form-row">

                    <div class="form-group">
                        <label for="option_c">Option C</label>

                        <input
                            type="text"
                            id="option_c"
                            name="option_c"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="option_d">Option D</label>

                        <input
                            type="text"
                            id="option_d"
                            name="option_d"
                            required
                        >
                    </div>

                </div>

                <div class="form-group">
                    <label for="correct_option">Correct Answer</label>

                    <select
                        id="correct_option"
                        name="correct_option"
                        required
                    >
                        <option value="A">Option A</option>
                        <option value="B">Option B</option>
                        <option value="C">Option C</option>
                        <option value="D">Option D</option>
                    </select>
                </div>

                <button
                    type="submit"
                    name="create_quiz"
                    value="1"
                    class="btn btn-primary"
                    <?= $lessons->num_rows === 0 ? 'disabled' : ''; ?>
                >
                    Save Quiz
                </button>

            </form>
        </div>

        <!-- Existing Quizzes -->
        <div class="card">
            <h3>Available Quizzes</h3>

            <div style="width:100%; overflow-x:auto;">
                <table class="data-table" style="width:100%;">

                    <thead>
                        <tr>
                            <th>Quiz Title</th>
                            <th>Date Created</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($quizzes->num_rows > 0): ?>

                        <?php while ($quiz = $quizzes->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= quizEscape($quiz['title']); ?>
                                </td>

                                <td>
                                    <?= quizEscape(
                                        !empty($quiz['created_at'])
                                            ? formatDate($quiz['created_at'])
                                            : 'Not available'
                                    ); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="2" style="text-align:center; padding:20px;">
                                No quizzes found. Create your first quiz above.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>
            </div>
        </div>

    </main>
</div>

<?php
$lessonStmt->close();
$quizStmt->close();

include __DIR__ . '/../includes/footer.php';
?>
