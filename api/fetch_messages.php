<?php

require dirname(__DIR__) . '/bootstrap.php';

$userId = require_login();
require_post();

$conversation = find_conversation_for_user(input_int($_POST, 'conversation_id'), $userId);
if ($conversation === null) {
    json_error('Conversation not found.', 404);
}

mark_conversation_read((int) $conversation['id'], $userId);

json_response([
    'messages' => conversation_messages($conversation, $userId),
    'blocked_by_me' => $conversation['blocked_by_me'],
    'blocked_me' => $conversation['blocked_me'],
]);
