<?php

require dirname(__DIR__) . '/bootstrap.php';

$userId = require_login();
require_post();

$conversation = find_conversation_for_user(input_int($_POST, 'conversation_id'), $userId);
if ($conversation === null) {
    json_error('Conversation not found.', 404);
}

if (input_string($_POST, 'scope') === 'everyone') {
    delete_conversation_everywhere((int) $conversation['id']);
} else {
    db_exec("UPDATE conversations SET {$conversation['my_side']}_deleted = 1 WHERE id = ?", [$conversation['id']]);
}

json_response(['id' => (int) $conversation['id']]);
