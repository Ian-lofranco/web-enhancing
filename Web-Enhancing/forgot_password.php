<?php
require __DIR__ . '/includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    verifyCsrfToken();
    $submittedCode = $_POST['recovery_code'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $recoveryCode = is_string($submittedCode) ? strtoupper(trim($submittedCode)) : '';

    if (!is_string($newPassword) || !is_string($confirmPassword) || !preg_match('/^[A-F0-9]{32}$/', $recoveryCode)) {
        $_SESSION['error_message'] = 'The recovery code is invalid, expired, or already used.';
        redirect('index.php?login=1&forgot=1');
    }
    if (strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
        $_SESSION['error_message'] = 'Use matching passwords with at least 8 characters.';
        redirect('index.php?login=1&forgot=1');
    }

    try {
        db()->begin_transaction();
        $codeHash = hash('sha256', $recoveryCode);
        $lookup = db()->prepare('SELECT id, user_id FROM password_recovery_codes WHERE code_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE');
        $lookup->bind_param('s', $codeHash);
        $lookup->execute();
        $recovery = $lookup->get_result()->fetch_assoc();

        if (!$recovery) {
            db()->rollback();
            $_SESSION['error_message'] = 'The recovery code is invalid, expired, or already used.';
            redirect('index.php?login=1&forgot=1');
        }

        $userId = (int)$recovery['user_id'];
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updatePassword = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
        $updatePassword->bind_param('si', $passwordHash, $userId);
        $updatePassword->execute();

        $consumeCodes = db()->prepare('UPDATE password_recovery_codes SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL');
        $consumeCodes->bind_param('i', $userId);
        $consumeCodes->execute();
        db()->commit();

        $_SESSION['flash_message'] = 'Password reset successfully. Sign in with your new password.';
        redirect('index.php?login=1');
    } catch (mysqli_sql_exception $exception) {
        db()->rollback();
        error_log('Password recovery failed: ' . $exception->getMessage());
        $_SESSION['error_message'] = 'Unable to reset the password. Please try again.';
        redirect('index.php?login=1&forgot=1');
    }
}

$pageTitle = 'Forgot Password | CodeUp';
include __DIR__ . '/includes/header.php';
?>

<main class="auth-page">
    <div class="auth-box">
        <div class="auth-header">
            <h2>Password Help</h2>
            <p>Enter a recovery code you generated earlier in Settings.</p>
        </div>

        <?php renderFlashMessage(); ?>

        <form method="POST" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="recovery-code">Recovery code</label>
                <input id="recovery-code" type="text" name="recovery_code" minlength="32" maxlength="32" pattern="[A-Fa-f0-9]{32}" autocomplete="off" required>
            </div>
            <div class="form-group">
                <label for="recovery-new-password">New password</label>
                <input id="recovery-new-password" type="password" name="new_password" minlength="8" autocomplete="new-password" required>
            </div>
            <div class="form-group">
                <label for="recovery-confirm-password">Confirm new password</label>
                <input id="recovery-confirm-password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
            </div>
            <button type="submit" name="reset_password" value="1" class="btn btn-primary btn-block">Reset password</button>
        </form>

        <div class="auth-links">
            <a href="index.php?login=1">Back to login</a>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
