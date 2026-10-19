<?php

require dirname(__DIR__) . '/bootstrap.php';

$userId = require_login();

json_response([
    'messages' => unread_message_count($userId),
    'notifications' => notification_count($userId),
]);
