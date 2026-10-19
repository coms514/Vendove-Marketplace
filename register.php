<?php

require __DIR__ . '/bootstrap.php';

require_guest();

$errors = [];
$username = '';
$email = '';
$pendingUserId = (int) ($_SESSION['pending_user_id'] ?? 0);

if (is_post()) {
    verify_csrf();
    $username = input_string($_POST, 'username', 50);
    $email = strtolower(input_string($_POST, 'email', 100));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $username)) {
        $errors[] = 'Usernames need 3 to 30 letters, numbers, dots, dashes or underscores.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Passwords need at least 8 characters.';
    } elseif ($password !== $confirm) {
        $errors[] = 'The two passwords do not match.';
    }

    if (!$errors && db_value('SELECT 1 FROM users WHERE username = ? OR email = ?', [$username, $email])) {
        $errors[] = 'That username or email is already in use.';
    }

    if (!$errors) {
        $userId = db_insert(
            "INSERT INTO users (username, email, password, profile_picture, bio, top_size, bottom_size, shoe_size, gender, is_verified)
             VALUES (?, ?, ?, '', '', '', '', '', '', 0)",
            [$username, $email, password_hash($password, PASSWORD_DEFAULT)],
        );

        $token = issue_verification_token($userId);
        $sent = send_verification_email(['id' => $userId, 'username' => $username, 'email' => $email], $token);

        session_regenerate_id(true);
        $_SESSION['pending_user_id'] = $userId;

        if (!$sent) {
            flash('error', 'Your account was created, but the verification email could not be sent. Use "Send a new verification link" on the login page.');
        }
        redirect('register.php');
    }
}

$pendingUser = $pendingUserId ? db_one('SELECT id, email, is_verified FROM users WHERE id = ?', [$pendingUserId]) : null;

$pageTitle = 'Create account';
$bodyClass = 'page-auth';
$pageScripts = $pendingUser ? ['verification.js'] : [];
require APP_ROOT . '/templates/header.php';
?>
<section class="container auth">
    <div class="card auth__card">
        <?php if ($pendingUser): ?>
            <div class="empty-state" data-verification-poll>
                <div class="spinner" aria-hidden="true"></div>
                <h1>Check your inbox</h1>
                <p>We sent a verification link to <strong><?= e($pendingUser['email']) ?></strong>. This page will continue automatically once you click it.</p>
                <a class="btn btn--ghost" href="login.php">Go to login</a>
            </div>
        <?php else: ?>
            <h1>Create your account</h1>
            <p class="muted">Buy, sell and ask for what you need.</p>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert--error"><?= e($error) ?></div>
            <?php endforeach; ?>

            <form method="post" class="form" data-loading-form>
                <?= csrf_field() ?>

                <label class="field">
                    <span class="field__label">Username</span>
                    <input type="text" name="username" value="<?= e($username) ?>" autocomplete="username" pattern="[A-Za-z0-9_.\-]{3,30}" required>
                </label>

                <label class="field">
                    <span class="field__label">Email</span>
                    <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required>
                </label>

                <label class="field">
                    <span class="field__label">Password</span>
                    <span class="password-field">
                        <input type="password" name="password" autocomplete="new-password" minlength="8" required>
                        <button type="button" class="password-field__toggle" data-password-toggle aria-label="Show password"><?= icon('eye') ?></button>
                    </span>
                    <span class="form-hint">At least 8 characters.</span>
                </label>

                <label class="field">
                    <span class="field__label">Confirm password</span>
                    <span class="password-field">
                        <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" required>
                        <button type="button" class="password-field__toggle" data-password-toggle aria-label="Show password"><?= icon('eye') ?></button>
                    </span>
                </label>

                <button class="btn btn--primary btn--block" type="submit">Create account</button>
            </form>

            <p class="auth__switch">Already have an account? <a href="login.php">Log in</a></p>
        <?php endif; ?>
    </div>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
