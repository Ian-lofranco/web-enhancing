
<?php
require __DIR__ . '/../includes/config.php';

requireRole('teacher');

$pageTitle = 'Manage Lessons | CodeUp';

$conn = db();
$userId = (int)($_SESSION['user_id'] ?? 0);

// Safely escape output.
function lessonEscape($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Handle lesson creation.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['add_lesson'])
) {
    verifyCsrfToken();

    $title = trim($_POST['title'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $objectives = trim($_POST['objectives'] ?? '');

    if ($title === '' || $topic === '' || $content === '') {
        $_SESSION['error_message'] =
            'Please complete the lesson title, topic, and content.';

        redirect('teacher/lessons.php');
        exit;
    }

    try {
        $stmt = $conn->prepare(
            'INSERT INTO lessons
                (title, topic, content, objectives, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );

        $stmt->bind_param(
            'ssssi',
            $title,
            $topic,
            $content,
            $objectives,
            $userId
        );

        $stmt->execute();
        $stmt->close();

        $_SESSION['flash_message'] =
            'Lesson created successfully.';

    } catch (Throwable $e) {
        error_log(
            'CodeUp lesson creation error: ' . $e->getMessage()
        );

        $_SESSION['error_message'] =
            'The lesson could not be saved. Please check the database configuration and try again.';
    }

    redirect('teacher/lessons.php');
    exit;
}

// Get this teacher's lessons and shared lessons.
// Assumption: lessons.created_by stores users.id.
// A NULL created_by value represents a shared lesson.
try {
    $stmt = $conn->prepare(
        'SELECT id, title, topic, content, objectives, created_by, created_at
         FROM lessons
         WHERE created_by = ? OR created_by IS NULL
         ORDER BY created_at DESC'
    );

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $lessons = $stmt->get_result();

} catch (Throwable $e) {
    error_log(
        'CodeUp lesson listing error: ' . $e->getMessage()
    );

    http_response_code(500);
    exit(
        'Unable to load lessons. Please check the lessons table and PHP error log.'
    );
}

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <aside class="sidebar">
        <h3>Teacher Panel</h3>

        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a class="active" href="lessons.php">Lessons</a>
            <a href="quizzes.php">Quizzes</a>
            <a href="reports.php">Reports</a>
        </nav>
    </aside>

    <main class="main-panel">

        <div class="page-header">
            <div>
                <p class="eyebrow">Content Creation</p>
                <h1>Manage Lessons</h1>
                <p>Create programming lessons for your students.</p>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <!-- Create Lesson Form -->
        <div class="card">
            <h3>Create New Lesson</h3>

            <form method="POST" class="stack-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= lessonEscape(csrfToken()); ?>"
                >

                <div class="form-group">
                    <label for="title">Lesson Title</label>

                    <input
                        type="text"
                        id="title"
                        name="title"
                        maxlength="255"
                        placeholder="Example: Introduction to Variables"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="topic">Topic</label>

                    <input
                        type="text"
                        id="topic"
                        name="topic"
                        maxlength="255"
                        placeholder="Example: Variables and Data Types"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="content">Lesson Content</label>

                    <textarea
                        id="content"
                        name="content"
                        rows="6"
                        placeholder="Enter the lesson explanation and examples..."
                        required
                    ></textarea>
                </div>

                <div class="form-group">
                    <label for="objectives">Learning Objectives</label>

                    <textarea
                        id="objectives"
                        name="objectives"
                        rows="3"
                        placeholder="What should students learn from this lesson?"
                    ></textarea>
                </div>

                <button
                    type="submit"
                    name="add_lesson"
                    value="1"
                    class="btn btn-primary"
                >
                    Save Lesson
                </button>

            </form>
        </div>

        <!-- Lesson List -->
        <div class="card">
            <h3>Available Lessons</h3>

            <div style="width:100%; overflow-x:auto;">
                <table class="data-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Topic</th>
                            <th>Created</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($lessons->num_rows > 0): ?>

                        <?php while ($lesson = $lessons->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= lessonEscape($lesson['title']); ?>
                                </td>

                                <td>
                                    <?= lessonEscape($lesson['topic']); ?>
                                </td>

                                <td>
                                    <?= lessonEscape(
                                        !empty($lesson['created_at'])
                                            ? formatDate($lesson['created_at'])
                                            : 'Not available'
                                    ); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="3" style="text-align:center; padding:20px;">
                                No lessons found. Create your first lesson above.
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
$stmt->close();
include __DIR__ . '/../includes/footer.php';
?>
