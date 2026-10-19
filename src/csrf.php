<?php

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_is_valid(): bool
{
    $sent = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

function verify_csrf(): void
{
    if (!csrf_is_valid()) {
        if (is_api_request()) {
            json_error('Session expired. Refresh the page and try again.', 403);
        }

        render_error_page(403, 'Page expired', 'Session expired. Go back, refresh the page and try again.');
    }
}
