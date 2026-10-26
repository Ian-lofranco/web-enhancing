<?php
// This file is kept as a helper for custom functions used across the system.
// It also makes it easier for beginners to understand where shared logic lives.

function getDashboardTarget()
{
    if (!isLoggedIn()) {
        return 'login.php';
    }

    $user = currentUser();
    if (!$user) {
        return 'login.php';
    }

    switch ($user['role']) {
        case 'admin':
            return 'admin/dashboard.php';
        case 'teacher':
            return 'teacher/dashboard.php';
        case 'student':
        default:
            return 'student/dashboard.php';
    }
}

function renderFlashMessage()
{
    $messages = [];
    if (isset($_SESSION['flash_message'])) {
        $messages[] = ['success', (string)$_SESSION['flash_message']];
        unset($_SESSION['flash_message']);
    }

    if (isset($_SESSION['error_message'])) {
        $messages[] = ['error', (string)$_SESSION['error_message']];
        unset($_SESSION['error_message']);
    }

    if (!$messages) {
        return;
    }

    static $noticeId = 0;
    foreach ($messages as [$type, $message]) {
        $noticeId++;
        $isError = $type === 'error';
        $class = $isError ? 'notice-dialog-error' : 'notice-dialog-success';
        $label = $isError ? 'Error' : 'Success';
        echo '<dialog class="notice-dialog ' . $class . '" data-notice-dialog aria-labelledby="notice-title-' . $noticeId . '" aria-describedby="notice-message-' . $noticeId . '">';
        echo '<div class="notice-copy"><strong id="notice-title-' . $noticeId . '">' . $label . '</strong><p id="notice-message-' . $noticeId . '">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></div>';
        echo '</dialog>';
    }
}

function verifyCsrfToken()
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !hash_equals(csrfToken(), $submittedToken)) {
        http_response_code(403);
        exit('Your session expired. Reload the page and try again.');
    }
}
