
<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$conn = db();
$user = currentUser();

if (!$user || empty($user['id'])) {
    redirect('index.php');
}

$userId = (int)$user['id'];
$role = strtolower((string)($user['role'] ?? ''));

if (!in_array($role, ['admin', 'teacher', 'student'], true)) {
    redirect('index.php');
}

$pageTitle = 'Settings | CodeUp';

function settingsH($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Load the current account information.
function settingsLoadUser(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare(
        'SELECT id, name, email, role, password
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $result ?: null;
}

$user = settingsLoadUser($conn, $userId);

if (!$user) {
    session_destroy();
    redirect('index.php');
}

$role = strtolower((string)$user['role']);

// Load role-specific profile information.
$student = null;
$teacher = null;

if ($role === 'student') {
    $student = getStudentData($userId);
} elseif ($role === 'teacher') {
    $teacher = getTeacherData($userId);
}

// Process submitted forms.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    /*
     * SAVE ACCOUNT AND PROFILE
     */
    if (isset($_POST['update_settings'])) {
        $name = is_string($_POST['name'] ?? null)
            ? trim($_POST['name'])
            : '';

        $email = is_string($_POST['email'] ?? null)
            ? trim($_POST['email'])
            : '';

        if (
            $name === '' ||
            strlen($name) > 150 ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 150
        ) {
            $_SESSION['error_message'] =
                'Please enter a valid name and email address (maximum 150 characters).';

            redirect('settings.php');
        }

        $studentNumber = '';
        $specialization = '';
        $bio = '';

        if ($role === 'student' && $student) {
            $studentNumber = is_string($_POST['student_id'] ?? null)
                ? trim($_POST['student_id'])
                : '';

            if (strlen($studentNumber) > 50) {
                $_SESSION['error_message'] =
                    'Student ID must not exceed 50 characters.';

                redirect('settings.php');
            }
        }

        if ($role === 'teacher' && $teacher) {
            $specialization = is_string($_POST['specialization'] ?? null)
                ? trim($_POST['specialization'])
                : '';

            $bio = is_string($_POST['bio'] ?? null)
                ? trim($_POST['bio'])
                : '';

            if (strlen($specialization) > 100 || strlen($bio) > 5000) {
                $_SESSION['error_message'] =
                    'Specialization must be at most 100 characters and bio at most 5,000 characters.';

                redirect('settings.php');
            }
        }

        $started = false;

        try {
            $conn->begin_transaction();
            $started = true;

            $stmt = $conn->prepare(
                'UPDATE users SET name = ?, email = ? WHERE id = ?'
            );
            $stmt->bind_param('ssi', $name, $email, $userId);
            $stmt->execute();
            $stmt->close();

            if ($role === 'student' && $student) {
                $stmt = $conn->prepare(
                    'UPDATE students
                     SET student_id = ?
                     WHERE user_id = ?'
                );

                $stmt->bind_param('si', $studentNumber, $userId);
                $stmt->execute();
                $stmt->close();
            }

            if ($role === 'teacher' && $teacher) {
                $stmt = $conn->prepare(
                    'UPDATE teachers
                     SET specialization = ?, bio = ?
                     WHERE user_id = ?'
                );

                $stmt->bind_param(
                    'ssi',
                    $specialization,
                    $bio,
                    $userId
                );

                $stmt->execute();
                $stmt->close();
            }

            $conn->commit();
            $started = false;

            $_SESSION['flash_message'] =
                'Your settings have been saved successfully.';

        } catch (Throwable $e) {
            if ($started) {
                try {
                    $conn->rollback();
                } catch (Throwable $ignored) {
                }
            }

            error_log('CodeUp settings save: ' . $e->getMessage());

            if (
                $e instanceof mysqli_sql_exception &&
                (int)$e->getCode() === 1062
            ) {
                $_SESSION['error_message'] =
                    'That email address or profile ID is already in use.';
            } else {
                $_SESSION['error_message'] =
                    'Unable to save settings. Check your database columns and try again.';
            }
        }

        redirect('settings.php');
    }

    /*
     * CHANGE PASSWORD
     */
    if (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? null;
        $newPassword = $_POST['new_password'] ?? null;
        $confirmPassword = $_POST['confirm_password'] ?? null;

        if (
            !is_string($currentPassword) ||
            !is_string($newPassword) ||
            !is_string($confirmPassword)
        ) {
            $_SESSION['error_message'] = 'Please enter valid passwords.';
            redirect('settings.php');
        }

        if (!password_verify($currentPassword, $user['password'])) {
            $_SESSION['error_message'] =
                'Your current password is incorrect.';
            redirect('settings.php');
        }

        if (strlen($newPassword) < 8 || strlen($newPassword) > 4096) {
            $_SESSION['error_message'] =
                'Your new password must contain at least 8 characters.';
            redirect('settings.php');
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['error_message'] =
                'The new passwords do not match.';
            redirect('settings.php');
        }

        if (password_verify($newPassword, $user['password'])) {
            $_SESSION['error_message'] =
                'Your new password must be different from your current password.';
            redirect('settings.php');
        }

        $started = false;

        try {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $conn->begin_transaction();
            $started = true;

            $stmt = $conn->prepare(
                'UPDATE users SET password = ? WHERE id = ?'
            );

            $stmt->bind_param('si', $hash, $userId);
            $stmt->execute();
            $stmt->close();

            $invalidateCodes = $conn->prepare(
                'UPDATE password_recovery_codes
                 SET used_at = NOW()
                 WHERE user_id = ? AND used_at IS NULL'
            );
            $invalidateCodes->bind_param('i', $userId);
            $invalidateCodes->execute();
            $invalidateCodes->close();

            $conn->commit();
            $started = false;

            session_regenerate_id(true);

            $_SESSION['flash_message'] =
                'Your password has been changed successfully.';

        } catch (Throwable $e) {
            if ($started) {
                try {
                    $conn->rollback();
                } catch (Throwable $ignored) {
                }
            }

            error_log('CodeUp password change: ' . $e->getMessage());

            $_SESSION['error_message'] =
                'Unable to change your password. Please try again.';
        }

        redirect('settings.php');
    }

    /*
     * GENERATE PASSWORD RECOVERY CODE
     */
    if (isset($_POST['generate_recovery_code'])) {
        $started = false;

        try {
            $code = strtoupper(bin2hex(random_bytes(16)));
            $codeHash = hash('sha256', $code);

            $conn->begin_transaction();
            $started = true;

            $stmt = $conn->prepare(
                'DELETE FROM password_recovery_codes WHERE user_id = ?'
            );

            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare(
                'INSERT INTO password_recovery_codes
                    (user_id, code_hash, expires_at)
                 VALUES
                    (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))'
            );

            $stmt->bind_param('is', $userId, $codeHash);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $started = false;

            $_SESSION['new_recovery_code'] = $code;
            $_SESSION['flash_message'] =
                'Recovery code generated. Save it now; it is shown only once.';

        } catch (Throwable $e) {
            if ($started) {
                try {
                    $conn->rollback();
                } catch (Throwable $ignored) {
                }
            }

            error_log('CodeUp recovery code: ' . $e->getMessage());

            $_SESSION['error_message'] =
                'Could not generate a recovery code. Make sure the recovery database table has been created.';
        }

        redirect('settings.php');
    }
}

// Display the newly generated recovery code once.
$recoveryCode = $_SESSION['new_recovery_code'] ?? null;
unset($_SESSION['new_recovery_code']);

// Refresh the profile after a successful update.
$user = settingsLoadUser($conn, $userId) ?? $user;

if ($role === 'student') {
    $student = getStudentData($userId);
} elseif ($role === 'teacher') {
    $teacher = getTeacherData($userId);
}

include __DIR__ . '/includes/header.php';
?>

<div class="dashboard-page">

    <aside class="sidebar">
        <h3><?= settingsH(ucfirst($role)); ?> Panel</h3>

        <nav>
            <?php if ($role === 'admin'): ?>
                <a href="<?= BASE_URL; ?>/admin/dashboard.php">Dashboard</a>
                <a href="<?= BASE_URL; ?>/admin/manage_students.php">Manage Students</a>
                <a href="<?= BASE_URL; ?>/admin/manage_teachers.php">Manage Teachers</a>
                <a href="<?= BASE_URL; ?>/admin/manage_lessons.php">Manage Lessons</a>
                <a href="<?= BASE_URL; ?>/admin/manage_quizzes.php">Manage Quizzes</a>
                <a href="<?= BASE_URL; ?>/admin/backup_database.php">Backup Database</a>

            <?php elseif ($role === 'teacher'): ?>
                <a href="<?= BASE_URL; ?>/teacher/dashboard.php">Dashboard</a>
                <a href="<?= BASE_URL; ?>/teacher/lessons.php">Lessons</a>
                <a href="<?= BASE_URL; ?>/teacher/quizzes.php">Quizzes</a>
                <a href="<?= BASE_URL; ?>/teacher/reports.php">Reports</a>

            <?php else: ?>
                <a href="<?= BASE_URL; ?>/student/dashboard.php">Dashboard</a>
                <a href="<?= BASE_URL; ?>/student/lessons.php">Lessons</a>
                <a href="<?= BASE_URL; ?>/student/quizzes.php">Quizzes</a>
                <a href="<?= BASE_URL; ?>/student/challenges.php">Coding Challenges</a>
            <?php endif; ?>

            <a class="active" href="<?= BASE_URL; ?>/settings.php">Settings</a>
        </nav>
    </aside>

    <main class="main-panel">

        <div class="page-header">
            <div>
                <p class="eyebrow">Account Management</p>
                <h1>Settings</h1>
                <p>Manage your CodeUp account and security.</p>
            </div>
        </div>

        <?php renderFlashMessage(); ?>

        <?php if ($recoveryCode): ?>
            <section class="card">
                <h2>Your Recovery Code</h2>
                <p>
                    Store this code privately. It expires in seven days.
                    Generating a new code invalidates the previous one.
                </p>

                <p>
                    <code style="overflow-wrap:anywhere;">
                        <?= settingsH($recoveryCode); ?>
                    </code>
                </p>

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="navigator.clipboard.writeText(<?= settingsH(
                        json_encode(
                            $recoveryCode,
                            JSON_HEX_TAG |
                            JSON_HEX_APOS |
                            JSON_HEX_QUOT |
                            JSON_HEX_AMP
                        )
                    ); ?>).then(function(){alert('Recovery code copied.');}).catch(function(){alert('Please copy the recovery code manually.');})"
                >
                    Copy Recovery Code
                </button>
            </section>
        <?php endif; ?>

        <!-- ACCOUNT INFORMATION -->
        <section class="card">
            <h2>Account Information</h2>

            <form method="POST" class="stack-form">
                <input type="hidden" name="csrf_token"
                       value="<?= settingsH(csrfToken()); ?>">

                <div class="form-group">
                    <label for="settings-name">Full Name</label>
                    <input
                        id="settings-name"
                        type="text"
                        name="name"
                        maxlength="150"
                        value="<?= settingsH($user['name']); ?>"
                        autocomplete="name"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="settings-email">Email Address</label>
                    <input
                        id="settings-email"
                        type="email"
                        name="email"
                        maxlength="150"
                        value="<?= settingsH($user['email']); ?>"
                        autocomplete="email"
                        required
                    >
                </div>

                <?php if ($role === 'student' && $student): ?>
                    <div class="form-group">
                        <label for="student-id">Student ID</label>
                        <input
                            id="student-id"
                            type="text"
                            name="student_id"
                            maxlength="50"
                            value="<?= settingsH($student['student_id'] ?? ''); ?>"
                        >
                    </div>

                <?php elseif ($role === 'teacher' && $teacher): ?>
                    <div class="form-group">
                        <label for="specialization">Specialization</label>
                        <input
                            id="specialization"
                            type="text"
                            name="specialization"
                            maxlength="100"
                            value="<?= settingsH($teacher['specialization'] ?? ''); ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="bio">About Me</label>
                        <textarea
                            id="bio"
                            name="bio"
                            rows="4"
                            maxlength="5000"
                        ><?= settingsH($teacher['bio'] ?? ''); ?></textarea>
                    </div>
                <?php endif; ?>

                <button
                    type="submit"
                    name="update_settings"
                    value="1"
                    class="btn btn-primary"
                >
                    Save Settings
                </button>
            </form>
        </section>

        <!-- PASSWORD -->
        <section class="card">
            <h2>Change Password</h2>

            <form method="POST" class="stack-form">
                <input type="hidden" name="csrf_token"
                       value="<?= settingsH(csrfToken()); ?>">

                <div class="form-group">
                    <label for="current-password">Current Password</label>
                    <input
                        id="current-password"
                        type="password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="new-password">New Password</label>
                    <input
                        id="new-password"
                        type="password"
                        name="new_password"
                        minlength="8"
                        maxlength="4096"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="confirm-password">Confirm New Password</label>
                    <input
                        id="confirm-password"
                        type="password"
                        name="confirm_password"
                        minlength="8"
                        maxlength="4096"
                        autocomplete="new-password"
                        required
                    >
                </div>

                <button
                    type="submit"
                    name="change_password"
                    value="1"
                    class="btn btn-primary"
                >
                    Change Password
                </button>
            </form>
        </section>

        <!-- RECOVERY -->
        <section class="card">
            <h2>Password Recovery</h2>

            <p>
                Generate a recovery code and keep it somewhere safe.
                The code expires after seven days.
            </p>

            <form method="POST"
                  onsubmit="return confirm('Generate a new recovery code? Your previous code will stop working.');">

                <input type="hidden" name="csrf_token"
                       value="<?= settingsH(csrfToken()); ?>">

                <button
                    type="submit"
                    name="generate_recovery_code"
                    value="1"
                    class="btn btn-secondary"
                >
                    Generate Recovery Code
                </button>
            </form>
        </section>

    </main>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
