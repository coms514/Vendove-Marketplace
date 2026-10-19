<?php

require __DIR__ . '/bootstrap.php';

$token = input_string($_GET, 'token', 200);
$user = $token !== '' && ctype_xdigit($token)
    ? db_one('SELECT id, username FROM users WHERE token = ? AND is_verified = 0', [hash_token($token)])
    : null;

if ($user) {
    db_exec('UPDATE users SET is_verified = 1, token = NULL WHERE id = ?', [$user['id']]);
}

$pageTitle = 'Verify email';
$bodyClass = 'page-auth';
require APP_ROOT . '/templates/header.php';
?>
<section class="container auth">
    <div class="card auth__card empty-state">
        <?php if ($user): ?>
            <h1>Email verified</h1>
            <p>Thanks, <?= e($user['username']) ?>. Your account is ready.</p>
            <a class="btn btn--primary" href="<?= current_user() ? 'index.php' : 'login.php' ?>">Continue</a>
        <?php else: ?>
            <h1>Link not valid</h1>
            <p>This verification link is invalid or has already been used. You can request a new one from the login page.</p>
            <a class="btn btn--primary" href="login.php">Go to login</a>
        <?php endif; ?>
    </div>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
