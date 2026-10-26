<?php
require __DIR__ . '/../includes/config.php';
requireRole('admin');

$pageTitle = 'Manage Quizzes | CodeUp';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_quiz'])) {
    verifyCsrfToken();
    $title = trim($_POST['title'] ?? '');
    $lessonId = (int)($_POST['lesson_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');
    $options = [
        'A' => trim($_POST['option_a'] ?? ''),
        'B' => trim($_POST['option_b'] ?? ''),
        'C' => trim($_POST['option_c'] ?? ''),
        'D' => trim($_POST['option_d'] ?? ''),
    ];
    $correct = $_POST['correct_option'] ?? '';

    if ($title !== '' && $lessonId > 0 && $question !== '' && !in_array('', $options, true) && isset($options[$correct])) {
        try {
            db()->begin_transaction();
            $quizStmt = db()->prepare('INSERT INTO quizzes (title, lesson_id, created_at) VALUES (?, ?, NOW())');
            $quizStmt->bind_param('si', $title, $lessonId);
            $quizStmt->execute();
            $quizId = $quizStmt->insert_id;

            $questionStmt = db()->prepare('INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
            $questionStmt->bind_param('issssss', $quizId, $question, $options['A'], $options['B'], $options['C'], $options['D'], $correct);
            $questionStmt->execute();
            db()->commit();

            $_SESSION['flash_message'] = 'Quiz created successfully.';
            redirect('admin/manage_quizzes.php');
        } catch (mysqli_sql_exception $exception) {
            db()->rollback();
            error_log('Admin quiz creation failed: ' . $exception->getMessage());
            $_SESSION['error_message'] = 'The quiz could not be saved. Check the selected lesson and try again.';
        }
    } else {
        $_SESSION['error_message'] = 'Complete every quiz and question field before saving.';
    }
}

$lessons = db()->query('SELECT id, title FROM lessons ORDER BY title');
$quizzes = db()->query('SELECT q.id, q.title, l.title AS lesson_title, q.created_at, COUNT(question.id) AS question_count FROM quizzes q LEFT JOIN lessons l ON l.id = q.lesson_id LEFT JOIN questions question ON question.quiz_id = q.id GROUP BY q.id, q.title, l.title, q.created_at ORDER BY q.created_at DESC');

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">
    <aside class="sidebar">
        <h3>Admin Panel</h3>
        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="manage_students.php">Manage Students</a>
            <a href="manage_teachers.php">Manage Teachers</a>
            <a href="manage_lessons.php">Manage Lessons</a>
            <a class="active" href="manage_quizzes.php">Manage Quizzes</a>
            <a href="backup_database.php">Backup Database</a>
        </nav>
    </aside>

    <main class="main-panel">
        <div class="page-header">
            <div>
                <p class="eyebrow">Assessment</p>
                <h1>Quiz Management</h1>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <div class="card">
            <h3>Create Quiz</h3>
            <form method="POST" class="stack-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="quiz-title">Quiz Title</label>
                    <input id="quiz-title" type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label for="quiz-lesson">Lesson</label>
                    <select id="quiz-lesson" name="lesson_id" required>
                        <option value="" disabled selected>Select a lesson</option>
                        <?php while ($lesson = $lessons->fetch_assoc()): ?>
                            <option value="<?= (int)$lesson['id']; ?>"><?= htmlspecialchars($lesson['title']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quiz-question">Question</label>
                    <textarea id="quiz-question" name="question" rows="3" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="quiz-option-a">Option A</label><input id="quiz-option-a" type="text" name="option_a" required></div>
                    <div class="form-group"><label for="quiz-option-b">Option B</label><input id="quiz-option-b" type="text" name="option_b" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label for="quiz-option-c">Option C</label><input id="quiz-option-c" type="text" name="option_c" required></div>
                    <div class="form-group"><label for="quiz-option-d">Option D</label><input id="quiz-option-d" type="text" name="option_d" required></div>
                </div>
                <div class="form-group">
                    <label for="quiz-correct-option">Correct Option</label>
                    <select id="quiz-correct-option" name="correct_option" required>
                        <option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option>
                    </select>
                </div>
                <button type="submit" name="create_quiz" value="1" class="btn btn-primary">Save Quiz</button>
            </form>
        </div>

        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Quiz</th>
                        <th>Lesson</th>
                        <th>Questions</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($quiz = $quizzes->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($quiz['title']); ?></td>
                            <td><?= htmlspecialchars($quiz['lesson_title'] ?? 'General'); ?></td>
                            <td><?= (int)$quiz['question_count']; ?></td>
                            <td><?= formatDate($quiz['created_at']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
