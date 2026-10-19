<?php

require __DIR__ . '/bootstrap.php';

require_guest();

$errors = [];
$login = '';
$unverifiedUser = null;
$next = safe_redirect_target($_GET['next'] ?? $_POST['next'] ?? null);

if (is_post()) {
    verify_csrf();
    $login = input_string($_POST, 'login', 100);
    $action = $_POST['action'] ?? 'login';

    if ($action === 'resend') {
        $user = $login !== '' ? find_user_by_login($login) : null;
        if ($user && !(int) $user['is_verified']) {
            send_verification_email($user, issue_verification_token((int) $user['id']));
        }
        flash('success', 'If that account is waiting for verification, we have sent a new link.');
        redirect('login.php');
    }

    $password = (string) ($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $errors[] = 'Please enter your username or email and your password.';
    } else {
        $user = attempt_login($login, $password);

        if ($user === null) {
            $errors[] = 'Those details do not match an account.';
        } elseif (!(int) $user['is_verified']) {
            $errors[] = 'Please verify your email address before logging in.';
            $unverifiedUser = $user;
        } else {
            log_user_in($user);
            flash('success', 'Welcome back, ' . $user['username'] . '!');
            redirect($next);
        }
    }
}

$pageTitle = 'Log in';
$bodyClass = 'page-auth';
require APP_ROOT . '/templates/header.php';
?>
<section class="container auth">
    <div class="card auth__card">
        <h1>Log in</h1>
        <p class="muted">Welcome back to <?= e(app_name()) ?>.</p>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert--error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <?php if ($unverifiedUser): ?>
            <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resend">
                <input type="hidden" name="login" value="<?= e($login) ?>">
                <button class="btn btn--ghost btn--sm" type="submit">Send a new verification link</button>
            </form>
        <?php endif; ?>

        <form method="post" class="form" data-loading-form>
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">

            <label class="field">
                <span class="field__label">Username or email</span>
                <input type="text" name="login" value="<?= e($login) ?>" autocomplete="username" required autofocus>
            </label>

            <label class="field">
                <span class="field__label">Password</span>
                <span class="password-field">
                    <input type="password" name="password" autocomplete="current-password" required>
                    <button type="button" class="password-field__toggle" data-password-toggle aria-label="Show password"><?= icon('eye') ?></button>
                </span>
            </label>

            <button class="btn btn--primary btn--block" type="submit">Log in</button>
        </form>

        <p class="auth__switch">New here? <a href="register.php">Create an account</a></p>
    </div>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
