<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'CodeUp'; ?></title>
    <link rel="stylesheet" href="<?= BASE_URL; ?>/assets/css/style.css">
    <script src="<?= BASE_URL; ?>/assets/js/app.js" defer></script>
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <div class="topbar">
        <div class="brand-box">
            <strong>CodeUp</strong>
        </div>
        <div class="topbar-actions">
            <span>Welcome, <?= htmlspecialchars(currentUser()['name'] ?? 'User'); ?></span>
            <a href="<?= BASE_URL; ?>/settings.php" class="btn btn-outline">Settings</a>
            <a href="<?= BASE_URL; ?>/logout.php" class="btn btn-outline" data-logout-trigger>Logout</a>
        </div>
    </div>
    <dialog class="logout-dialog" id="logout-dialog" aria-labelledby="logout-dialog-title" aria-describedby="logout-dialog-description">
        <div class="logout-dialog-mark" aria-hidden="true">?</div>
        <p class="logout-dialog-label">End your session</p>
        <h2 id="logout-dialog-title">Log out?</h2>
        <p id="logout-dialog-description">You will need to sign in again to continue.</p>
        <div class="logout-dialog-actions">
            <button type="button" class="btn btn-secondary" data-logout-cancel>Stay signed in</button>
            <a href="<?= BASE_URL; ?>/logout.php" class="btn btn-danger">Log out</a>
        </div>
    </dialog>
    <?php endif; ?>

    <?php renderFlashMessage(); ?>

    <div class="page-shell">
