<?php
require __DIR__ . '/includes/config.php';

if (isLoggedIn()) {
	redirect(getDashboardTarget());
}

$pageTitle = 'CodeUp | Learn to Code';
include __DIR__ . '/includes/header.php';
?>

<header class="home-nav">
	<a class="home-brand" href="<?= BASE_URL; ?>/" aria-label="CodeUp home">CodeUp<span>.</span></a>
	<nav class="home-nav-actions" aria-label="Main navigation">
		<a href="#learning">Explore</a>
		<a href="register.php" class="btn btn-secondary" data-register-trigger>Create account</a>
		<a href="login.php" class="btn btn-primary" data-login-trigger>Log in</a>
	</nav>
</header>

<main class="landing-page">
	<section class="hero" aria-labelledby="hero-title">
		<div class="hero-content">
			<span class="eyebrow">Your next skill starts here</span>
			<h1 id="hero-title">Make your ideas work with code.</h1>
			<p class="lead">Learn programming one clear step at a time. Build practical skills with guided lessons, quick quizzes, and challenges that reward curiosity.</p>
			<div class="hero-actions">
				<a href="register.php" class="btn btn-light" data-register-trigger>Start learning</a>
				<a href="login.php" class="hero-login-link" data-login-trigger>Already have an account? Log in</a>
			</div>
			<div class="hero-stats" aria-label="Learning highlights">
				<div><strong>Learn</strong><span>at your own pace</span></div>
				<div><strong>Practice</strong><span>with real challenges</span></div>
				<div><strong>Grow</strong><span>one lesson at a time</span></div>
			</div>
		</div>
	</section>

	<section class="feature-grid" id="learning" aria-label="How CodeUp helps you learn">
		<article class="feature-card">
			<span class="feature-number">01</span>
			<h2>Lessons that make sense</h2>
			<p>Follow structured topics that turn unfamiliar concepts into manageable steps.</p>
		</article>
		<article class="feature-card">
			<span class="feature-number">02</span>
			<h2>Practice as you go</h2>
			<p>Check your understanding with quizzes and hands-on coding challenges.</p>
		</article>
		<article class="feature-card">
			<span class="feature-number">03</span>
			<h2>See your progress</h2>
			<p>Keep track of completed lessons and celebrate the skills you build.</p>
		</article>
	</section>
</main>

<dialog class="login-dialog" id="login-dialog" aria-labelledby="login-dialog-title">
	<button type="button" class="login-dialog-close" data-login-close aria-label="Close login dialog">&times;</button>
	<div class="auth-header">
		<span class="eyebrow">Welcome back</span>
		<h2 id="login-dialog-title">Log in to CodeUp</h2>
		<p>Pick up where your learning left off.</p>
	</div>
	<form method="POST" action="<?= BASE_URL; ?>/login.php" class="auth-form">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
		<div class="form-group">
			<label for="home-login-email">Email address</label>
			<input id="home-login-email" type="email" name="email" placeholder="name@example.com" autocomplete="email" required>
		</div>
		<div class="form-group">
			<label for="home-login-password">Password</label>
			<input id="home-login-password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
		</div>
		<button type="submit" class="btn btn-primary btn-block">Log in</button>
	</form>
	<div class="auth-links">
		<a href="register.php" data-register-trigger>Create an account</a>
		<a href="forgot_password.php" data-forgot-trigger>Forgot password?</a>
	</div>
</dialog>

<dialog class="login-dialog" id="forgot-dialog" aria-labelledby="forgot-dialog-title">
	<button type="button" class="login-dialog-close" data-forgot-close aria-label="Close password help dialog">&times;</button>
	<div class="auth-header">
		<span class="eyebrow">Account support</span>
		<h2 id="forgot-dialog-title">Password help</h2>
		<p>Use the recovery code you generated in Settings.</p>
	</div>
	<form method="POST" action="<?= BASE_URL; ?>/forgot_password.php" class="auth-form">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
		<div class="form-group">
			<label for="forgot-recovery-code">Recovery code</label>
			<input id="forgot-recovery-code" type="text" name="recovery_code" minlength="32" maxlength="32" pattern="[A-Fa-f0-9]{32}" autocomplete="off" required>
		</div>
		<div class="form-group">
			<label for="forgot-new-password">New password</label>
			<input id="forgot-new-password" type="password" name="new_password" minlength="8" autocomplete="new-password" required>
		</div>
		<div class="form-group">
			<label for="forgot-confirm-password">Confirm new password</label>
			<input id="forgot-confirm-password" type="password" name="confirm_password" minlength="8" autocomplete="new-password" required>
		</div>
		<button type="submit" name="reset_password" value="1" class="btn btn-primary btn-block">Reset password</button>
	</form>
	<div class="auth-links">
		<button type="button" class="btn btn-primary" data-forgot-close>Back to login</button>
		<a href="settings.php">Generate a recovery code in Settings</a>
	</div>
</dialog>

<dialog class="login-dialog" id="register-dialog" aria-labelledby="register-dialog-title">
	<button type="button" class="login-dialog-close" data-register-close aria-label="Close registration dialog">&times;</button>
	<div class="auth-header">
		<span class="eyebrow">Start learning</span>
		<h2 id="register-dialog-title">Create your account</h2>
		<p>Join CodeUp and start building practical coding skills.</p>
	</div>
	<form method="POST" action="<?= BASE_URL; ?>/register.php" class="auth-form">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
		<div class="form-group">
			<label for="home-register-name">Full name</label>
			<input id="home-register-name" type="text" name="name" placeholder="Enter your full name" autocomplete="name" required>
		</div>
		<div class="form-group">
			<label for="home-register-email">Email address</label>
			<input id="home-register-email" type="email" name="email" placeholder="name@example.com" autocomplete="email" required>
		</div>
		<div class="form-group">
			<label for="home-register-password">Password</label>
			<input id="home-register-password" type="password" name="password" placeholder="Create a password" minlength="8" autocomplete="new-password" required>
		</div>
		<div class="form-group">
			<label for="home-register-confirm-password">Confirm password</label>
			<input id="home-register-confirm-password" type="password" name="confirm_password" placeholder="Repeat your password" minlength="8" autocomplete="new-password" required>
		</div>
		<button type="submit" class="btn btn-primary btn-block">Create account</button>
	</form>
	<div class="auth-links">
		<a href="login.php" data-login-trigger>Already have an account? Log in</a>
	</div>
</dialog>

<?php include __DIR__ . '/includes/footer.php'; ?>
