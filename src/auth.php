<?php

const PROFILE_GENDERS = ['Male', 'Female', 'Other'];
const PROFILE_CLOTHING_SIZES = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
const PROFILE_SHOE_SIZES = [
    'US 3.5', 'US 4', 'US 4.5', 'US 5', 'US 5.5', 'US 6', 'US 6.5', 'US 7', 'US 7.5', 'US 8',
    'US 8.5', 'US 9', 'US 9.5', 'US 10', 'US 10.5', 'US 11', 'US 11.5', 'US 12', 'US 13', 'US 14',
];
const PROFILE_COUNTRY_CODES = ['+1' => 'US / Canada +1', '+44' => 'UK +44', '+33' => 'France +33', '+61' => 'Australia +61'];

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user(): ?array
{
    static $user = false;

    if ($user === false) {
        $id = current_user_id();
        $user = $id ? db_one('SELECT * FROM users WHERE id = ?', [$id]) : null;

        if ($id && $user === null) {
            destroy_session();
            start_session();
        }
    }

    return $user;
}

function require_login(): int
{
    if (current_user() === null) {
        if (is_api_request()) {
            json_error('Please log in to continue.', 401);
        }

        flash('info', 'Please log in to continue.');
        $target = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
        if (!is_post() && !empty($_SERVER['QUERY_STRING'])) {
            $target .= '?' . $_SERVER['QUERY_STRING'];
        }
        redirect('login.php?next=' . urlencode($target));
    }

    return (int) current_user_id();
}

function require_guest(): void
{
    if (current_user() !== null) {
        redirect('index.php');
    }
}

function log_user_in(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['pending_user_id'], $_SESSION['csrf_token']);
    $_SESSION['user_id'] = (int) $user['id'];
}

function find_user_by_login(string $login): ?array
{
    $column = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

    return db_one("SELECT * FROM users WHERE {$column} = ? LIMIT 1", [$login]);
}

function attempt_login(string $login, string $password): ?array
{
    $user = find_user_by_login($login);

    if ($user === null || !password_verify($password, $user['password'])) {
        return null;
    }

    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    return $user;
}

function hash_token(string $token): string
{
    return hash('sha256', $token);
}

function issue_verification_token(int $userId): string
{
    $token = bin2hex(random_bytes(32));
    db_exec('UPDATE users SET token = ? WHERE id = ?', [hash_token($token), $userId]);

    return $token;
}

function send_verification_email(array $user, string $token): bool
{
    $link = absolute_url('verify.php?token=' . urlencode($token));
    $name = e($user['username']);
    $brand = e(app_name());
    $href = e($link);

    $html = <<<HTML
        <div style="font-family:Arial,sans-serif;background:#f4f4f7;padding:24px">
          <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;padding:32px;text-align:center">
            <h1 style="color:#1a1625;margin:0 0 12px">Welcome to {$brand}, {$name}!</h1>
            <p style="color:#555">Please confirm your email address to finish setting up your account.</p>
            <a href="{$href}" style="display:inline-block;margin-top:16px;padding:12px 24px;background:#7c3aed;color:#fff;border-radius:8px;text-decoration:none">Verify email</a>
            <p style="color:#999;font-size:12px;margin-top:24px">If you did not create an account, you can ignore this email.</p>
          </div>
        </div>
        HTML;

    $text = 'Welcome to ' . app_name() . ", {$user['username']}!\n\nConfirm your email address: {$link}\n";

    return send_mail($user['email'], $user['username'], 'Confirm your email address', $html, $text);
}
