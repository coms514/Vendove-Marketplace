<?php

require dirname(__DIR__) . '/bootstrap.php';

$userId = require_login();
require_post();

$body = input_string($_POST, 'body');
if ($body === '') {
    json_error('A message cannot be empty.', 422);
}
if (mb_strlen($body) > CHAT_MAX_LENGTH) {
    json_error('Messages can be up to ' . CHAT_MAX_LENGTH . ' characters.', 422);
}

$message = db_one(
    'SELECT m.id, m.conversation_id, m.encrypted_message
     FROM messages m
     JOIN conversations c ON c.id = m.conversation_id
     WHERE m.id = ? AND m.sender_id = ? AND m.deleted_by_sender = 0 AND (c.user1_id = ? OR c.user2_id = ?)',
    [input_int($_POST, 'message_id'), $userId, $userId, $userId],
);

if ($message === null) {
    json_error('Message not found.', 404);
}
if ($message['encrypted_message'] === null) {
    json_error('Photos cannot be edited.', 422);
}

db_exec(
    'UPDATE messages SET encrypted_message = ?, edited = 1 WHERE id = ?',
    [encrypt_message((int) $message['conversation_id'], $body), $message['id']],
);

json_response(['id' => (int) $message['id']]);
