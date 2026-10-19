<?php

require dirname(__DIR__) . '/bootstrap.php';

$userId = require_login();
require_post();

$conversation = find_conversation_for_user(input_int($_POST, 'conversation_id'), $userId);
if ($conversation === null) {
    json_error('Conversation not found.', 404);
}

$blocked = input_int($_POST, 'blocked') === 1;
$column = $conversation['my_side'] === 'user1' ? 'user1_blocked_user2' : 'user2_blocked_user1';

db_exec("UPDATE conversations SET {$column} = ? WHERE id = ?", [$blocked ? 1 : 0, $conversation['id']]);

json_response(['blocked' => $blocked]);
