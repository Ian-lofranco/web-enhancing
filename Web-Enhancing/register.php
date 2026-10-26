<?php
require __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect(getDashboardTarget());
}

$pageTitle = 'Register | CodeUp';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $_SESSION['error_message'] = 'Please complete all required fields.';
    } elseif ($password !== $confirmPassword) {
        $_SESSION['error_message'] = 'Passwords do not match.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $_SESSION['error_message'] = 'Use a password with at least 8 characters.';
    } else {
        try {
            db()->begin_transaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $insertUser = db()->prepare('INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())');
            $role = 'student';
            $insertUser->bind_param('ssss', $name, $email, $hash, $role);
            $insertUser->execute();

            $userId = $insertUser->insert_id;
            $studentId = 'SR-' . str_pad((string)$userId, 4, '0', STR_PAD_LEFT);
            $studentStmt = db()->prepare('INSERT INTO students (user_id, student_id, created_at) VALUES (?, ?, NOW())');
            $studentStmt->bind_param('is', $userId, $studentId);
            $studentStmt->execute();
            db()->commit();

            $_SESSION['flash_message'] = 'Registration successful. You can now login.';
            redirect('index.php?login=1');
        } catch (mysqli_sql_exception $exception) {
            db()->rollback();
            error_log('Student registration failed: ' . $exception->getMessage());
            $_SESSION['error_message'] = (int)$exception->getCode() === 1062
                ? 'This email is already registered.'
                : 'Unable to create your account. Please try again.';
        }
    }

    redirect('index.php?register=1');
}

include __DIR__ . '/includes/header.php';
?>

<main class="auth-page">
    <div class="auth-box">
        <div class="auth-header">
            <h2>Create Student Account</h2>
            <p>Register and begin your programming path.</p>
        </div>

        <?php renderFlashMessage(); ?>

        <form method="POST" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="Enter your full name" required>
            </div>

            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="name@example.com" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Create a password" minlength="8" required>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Repeat your password" minlength="8" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Register</button>
        </form>

        <div class="auth-links">
            <a href="login.php">Already have an account?</a>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
