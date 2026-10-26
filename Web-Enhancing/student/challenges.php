
<?php
require __DIR__ . '/../includes/config.php';
requireRole('student');

$pageTitle = 'Coding Challenges | CodeUp';

// Get the logged-in student's information
$student = getStudentData((int) $_SESSION['user_id']);

if (!$student) {
    $_SESSION['error_message'] = 'Student account not found.';
    redirect('student/dashboard.php');
    exit;
}

// Handle code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_code'])) {
    verifyCsrfToken();

    $challengeId = filter_input(
        INPUT_POST,
        'challenge_id',
        FILTER_VALIDATE_INT
    );

    $code = trim($_POST['code'] ?? '');

    if (!$challengeId || $challengeId <= 0) {
        $_SESSION['error_message'] = 'Invalid coding challenge.';
        redirect('student/challenges.php');
        exit;
    }

    if ($code === '') {
        $_SESSION['error_message'] = 'Please enter your solution before submitting.';
        redirect('student/challenges.php');
        exit;
    }

    try {
        // Check whether the challenge exists
        $challengeStmt = db()->prepare(
            'SELECT id FROM coding_challenges WHERE id = ? LIMIT 1'
        );

        $challengeStmt->bind_param('i', $challengeId);
        $challengeStmt->execute();

        $challengeExists = $challengeStmt->get_result()->fetch_assoc();
        $challengeStmt->close();

        if (!$challengeExists) {
            $_SESSION['error_message'] =
                'Coding challenge not found. Please refresh the page.';
            redirect('student/challenges.php');
            exit;
        }

        // Save the student's submission
        $status = 'Pending Review';
        $score = 0;

        $stmt = db()->prepare(
            'INSERT INTO submissions
            (student_id, challenge_id, code, status, score, submitted_at)
            VALUES (?, ?, ?, ?, ?, NOW())'
        );

        $stmt->bind_param(
            'iissi',
            $student['id'],
            $challengeId,
            $code,
            $status,
            $score
        );

        $stmt->execute();
        $stmt->close();

        $_SESSION['flash_message'] =
            'Your coding solution has been submitted successfully!';

    } catch (mysqli_sql_exception $exception) {
        error_log(
            'Coding challenge submission failed: ' .
            $exception->getMessage()
        );

        $_SESSION['error_message'] =
            'Unable to submit your solution. Please try again.';
    }

    redirect('student/challenges.php');
    exit;
}

// Retrieve all coding challenges
$challenges = db()->query(
    'SELECT id, title, description, difficulty, starter_code
     FROM coding_challenges
     ORDER BY created_at DESC'
);

// Retrieve the student's previous submissions
$submissionsByChallenge = [];

$submissionsStmt = db()->prepare(
    'SELECT challenge_id, status, score, submitted_at
     FROM submissions
     WHERE student_id = ?
     ORDER BY submitted_at DESC'
);

$submissionsStmt->bind_param('i', $student['id']);
$submissionsStmt->execute();

$submissionsResult = $submissionsStmt->get_result();

while ($submission = $submissionsResult->fetch_assoc()) {
    $submissionsByChallenge[$submission['challenge_id']][] = $submission;
}

$submissionsStmt->close();

include __DIR__ . '/../includes/header.php';
?>

<div class="dashboard-page">

    <aside class="sidebar">
        <h3>Student Panel</h3>

        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="lessons.php">Lessons</a>
            <a href="quizzes.php">Quizzes</a>
            <a class="active" href="challenges.php">
                Coding Challenges
            </a>
            <a href="../settings.php">Settings</a>
        </nav>
    </aside>

    <main class="main-panel">

        <div class="page-header">
            <div>
                <p class="eyebrow">Practice</p>
                <h1>Coding Challenges</h1>
                <p>Practice your programming skills by solving challenges.</p>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <?php if ($challenges->num_rows === 0): ?>

            <div class="card">
                <h3>No Coding Challenges Yet</h3>
                <p>
                    There are currently no coding challenges available.
                    Please check again later.
                </p>
            </div>

        <?php else: ?>

            <?php while ($challenge = $challenges->fetch_assoc()): ?>

                <div class="card challenge-card">

                    <div class="challenge-header">
                        <h3>
                            <?= htmlspecialchars(
                                $challenge['title'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </h3>

                        <span class="tag">
                            <?= htmlspecialchars(
                                $challenge['difficulty'] ?? 'Beginner',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </span>
                    </div>

                    <p>
                        <?= nl2br(
                            htmlspecialchars(
                                $challenge['description'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        ); ?>
                    </p>

                    <?php if (!empty($challenge['starter_code'])): ?>
                        <h4>Starter Code</h4>

                        <pre class="code-block"><code><?= htmlspecialchars(
                            $challenge['starter_code'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?></code></pre>
                    <?php endif; ?>

                    <form method="POST" class="stack-form">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                csrfToken(),
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>"
                        >

                        <input
                            type="hidden"
                            name="challenge_id"
                            value="<?= (int) $challenge['id']; ?>"
                        >

                        <label for="code-<?= (int) $challenge['id']; ?>">
                            Your Solution
                        </label>

                        <textarea
                            id="code-<?= (int) $challenge['id']; ?>"
                            name="code"
                            rows="8"
                            maxlength="50000"
                            placeholder="Write your solution here..."
                            required
                        ></textarea>

                        <button
                            type="submit"
                            name="submit_code"
                            value="1"
                            class="btn btn-primary"
                        >
                            Submit Code
                        </button>

                    </form>

                    <?php
                    $challengeSubmissions =
                        $submissionsByChallenge[$challenge['id']] ?? [];
                    ?>

                    <?php if (!empty($challengeSubmissions)): ?>

                        <div class="submission-history">
                            <h4>Your Submissions</h4>

                            <ul class="list-group">

                                <?php foreach (
                                    $challengeSubmissions as $submission
                                ): ?>

                                    <li>
                                        <span>
                                            <?= htmlspecialchars(
                                                $submission['status'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>

                                            &middot;

                                            <?= htmlspecialchars(
                                                formatDate(
                                                    $submission['submitted_at']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ); ?>
                                        </span>

                                        <small>
                                            <?php if (
                                                $submission['status'] === 'Reviewed'
                                            ): ?>
                                                Score:
                                                <?= (int) $submission['score']; ?>%
                                            <?php else: ?>
                                                Awaiting review
                                            <?php endif; ?>
                                        </small>
                                    </li>

                                <?php endforeach; ?>

                            </ul>
                        </div>

                    <?php endif; ?>

                </div>

            <?php endwhile; ?>

        <?php endif; ?>

    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
