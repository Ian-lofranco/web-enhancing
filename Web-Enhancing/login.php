<?php
require __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
    redirect(getDashboardTarget());
}

$pageTitle = 'Login | CodeUp';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $_SESSION['error_message'] = 'Please enter both email and password.';
        redirect('index.php?login=1');
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['flash_message'] = 'Welcome back!';
            redirect(getDashboardTarget());
        } else {
            $_SESSION['error_message'] = 'Invalid email or password.';
            redirect('index.php?login=1');
        }
    }
}

include __DIR__ . '/includes/header.php';
?>

<main class="auth-page">
    <a href="index.php" style="display:inline-block; padding:10px 18px; background:#2563eb; color:#fff; text-decoration:none; border-radius:8px; font-size:14px; font-weight:600;">← Back to Home</a>
    <div class="auth-box">
        <div class="auth-header">
            <h2>Welcome Back</h2>
            <p>Sign in to continue your coding journey.</p>
        </div>

        <?php renderFlashMessage(); ?>

        <form method="POST" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="name@example.com" required>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Login</button>
        </form>

        <div class="auth-links">
            <a href="index.php?register=1">Create an account</a>
            <a href="index.php?login=1&amp;forgot=1">Forgot password?</a>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
