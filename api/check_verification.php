<?php

require dirname(__DIR__) . '/bootstrap.php';

$pendingUserId = (int) ($_SESSION['pending_user_id'] ?? 0);
if ($pendingUserId === 0) {
    json_response(['verified' => false]);
}

$user = db_one('SELECT * FROM users WHERE id = ?', [$pendingUserId]);
if ($user === null) {
    unset($_SESSION['pending_user_id']);
    json_response(['verified' => false]);
}

if (!(int) $user['is_verified']) {
    json_response(['verified' => false]);
}

log_user_in($user);
flash('success', 'Your email is verified. Welcome to ' . app_name() . '!');

json_response(['verified' => true, 'redirect' => 'index.php']);
