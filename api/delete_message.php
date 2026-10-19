<?php

require dirname(__DIR__) . '/bootstrap.php';

$userId = require_login();
require_post();

$scope = input_string($_POST, 'scope') === 'everyone' ? 'everyone' : 'me';

$message = db_one(
    'SELECT m.id, m.conversation_id, m.sender_id, m.file_path
     FROM messages m
     JOIN conversations c ON c.id = m.conversation_id
     WHERE m.id = ? AND (c.user1_id = ? OR c.user2_id = ?)',
    [input_int($_POST, 'message_id'), $userId, $userId],
);

if ($message === null) {
    json_error('Message not found.', 404);
}

$isSender = (int) $message['sender_id'] === $userId;

if ($scope === 'everyone') {
    if (!$isSender) {
        json_error('You can only delete your own messages for everyone.', 403);
    }
    db_exec('DELETE FROM messages WHERE id = ?', [$message['id']]);
    array_map('delete_upload', message_images($message['file_path']));
} else {
    $column = $isSender ? 'deleted_by_sender' : 'deleted_by_receiver';
    db_exec("UPDATE messages SET {$column} = 1 WHERE id = ?", [$message['id']]);
}

refresh_conversation_preview((int) $message['conversation_id']);

json_response(['id' => (int) $message['id']]);
