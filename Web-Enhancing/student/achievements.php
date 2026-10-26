
<?php
require __DIR__ . '/../includes/config.php';

requireRole('student');

$pageTitle = 'Achievements | CodeUp';

$user = currentUser();

if (!$user || empty($user['id'])) {
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
$badges = [];
$errorMessage = '';

function achievementEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function achievementIcon(string $name): string
{
    $icons = [
        'First Lesson'    => '📘',
        'Quiz Starter'    => '📝',
        'Perfect Score'   => '🏆',
        'Lesson Master'   => '🎓',
        'Quiz Master'     => '🥇',
        'Coding Beginner' => '💻',
        'Code Champion'   => '🚀',
        'Programming Pro' => '⭐',
    ];

    return $icons[$name] ?? '🏅';
}

try {
    $stmt = db()->prepare(
        'SELECT name, description, earned_at
         FROM badges
         WHERE student_id = ?
         ORDER BY earned_at DESC, name ASC'
    );

    $stmt->bind_param('i', $studentId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($badge = $result->fetch_assoc()) {
        $badges[] = $badge;
    }

    $stmt->close();

} catch (Throwable $exception) {
    error_log(
        'CodeUp achievements error: ' . $exception->getMessage()
    );

    $errorMessage =
        'Unable to load your achievements. Please try again later.';
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.achievement-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
}

.achievement-count {
    font-size: 2.3rem;
    font-weight: 800;
    margin: 8px 0;
}

.achievement-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-top: 24px;
}

.achievement-item {
    text-align: center;
    padding: 28px 20px;
    border: 1px solid #e5e7eb;
    border-radius: 16px;
    background: #fff;
    transition: transform .2s ease, box-shadow .2s ease;
}

.achievement-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
}

.achievement-icon {
    width: 88px;
    height: 88px;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #eff6ff;
    font-size: 42px;
}

.achievement-item h3 {
    margin: 12px 0 8px;
}

.achievement-description {
    color: #64748b;
    line-height: 1.6;
}

.achievement-date {
    font-size: .85rem;
    color: #64748b;
    margin-top: 18px;
}

.achievement-empty {
    text-align: center;
    padding: 44px 20px;
}

.achievement-empty-icon {
    font-size: 60px;
    margin-bottom: 12px;
}

.achievement-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 22px;
}

@media (max-width: 600px) {
    .achievement-grid {
        grid-template-columns: 1fr;
    }

    .achievement-summary {
        align-items: flex-start;
    }
}
</style>

<div class="dashboard-page">

    <aside class="sidebar">
        <h3>Student Panel</h3>

        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="lessons.php">Lessons</a>
            <a href="quizzes.php">Quizzes</a>
            <a href="challenges.php">Coding Challenges</a>
            <a href="achievements.php" class="active">Achievements</a>
            <a href="../settings.php">Settings</a>
        </nav>
    </aside>

    <main class="main-panel">

        <div class="page-header">
            <div>
                <p class="eyebrow">Rewards and Milestones</p>
                <h1>My Achievements</h1>
                <p>
                    Celebrate your progress as you learn programming
                    and complete activities in CodeUp.
                </p>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <?php if ($errorMessage !== ''): ?>

            <div class="card">
                <p><?= achievementEscape($errorMessage); ?></p>
            </div>

        <?php else: ?>

            <div class="card achievement-summary">
                <div>
                    <p class="eyebrow">Your Collection</p>

                    <h2 class="achievement-count">
                        <?= count($badges); ?>
                    </h2>

                    <p>
                        <?= count($badges) === 1
                            ? 'badge earned'
                            : 'badges earned'; ?>
                    </p>
                </div>

                <div aria-hidden="true" style="font-size:54px;">
                    🏆
                </div>
            </div>

            <?php if (empty($badges)): ?>

                <div class="card achievement-empty">
                    <div class="achievement-empty-icon">🏅</div>

                    <h2>No badges yet</h2>

                    <p>
                        Complete lessons, take quizzes, and practice
                        coding to earn your first badge.
                    </p>

                    <div class="achievement-actions">
                        <a href="lessons.php" class="btn btn-primary">
                            Start Learning
                        </a>

                        <a href="quizzes.php" class="btn">
                            Take a Quiz
                        </a>
                    </div>
                </div>

            <?php else: ?>

                <div class="achievement-grid">

                    <?php foreach ($badges as $badge): ?>

                        <?php
                        $badgeName = (string) ($badge['name'] ?? '');
                        $earnedAt = $badge['earned_at'] ?? null;
                        $formattedDate = '';

                        if (is_string($earnedAt) && $earnedAt !== '') {
                            $timestamp = strtotime($earnedAt);

                            if ($timestamp !== false) {
                                $formattedDate = date(
                                    'M d, Y',
                                    $timestamp
                                );
                            }
                        }
                        ?>

                        <article class="achievement-item">

                            <div class="achievement-icon" aria-hidden="true">
                                <?= achievementEscape(
                                    achievementIcon($badgeName)
                                ); ?>
                            </div>

                            <span class="tag">Badge Earned</span>

                            <h3>
                                <?= achievementEscape($badgeName); ?>
                            </h3>

                            <p class="achievement-description">
                                <?= achievementEscape(
                                    $badge['description'] ?? ''
                                ); ?>
                            </p>

                            <?php if ($formattedDate !== ''): ?>
                                <p class="achievement-date">
                                    Earned on
                                    <?= achievementEscape($formattedDate); ?>
                                </p>
                            <?php endif; ?>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        <?php endif; ?>

    </main>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
