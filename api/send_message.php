<?php

require dirname(__DIR__) . '/bootstrap.php';

$userId = require_login();
require_post();

$conversation = find_conversation_for_user(input_int($_POST, 'conversation_id'), $userId);
if ($conversation === null) {
    json_error('Conversation not found.', 404);
}
if ($conversation['blocked_by_me']) {
    json_error('Unblock this user to send messages.', 403);
}
if ($conversation['blocked_me']) {
    json_error('This user is not accepting messages from you.', 403);
}

$body = input_string($_POST, 'body');
if (mb_strlen($body) > CHAT_MAX_LENGTH) {
    json_error('Messages can be up to ' . CHAT_MAX_LENGTH . ' characters.', 422);
}

$photos = isset($_FILES['images']) ? normalize_files_array($_FILES['images']) : [];
$photos = array_values(array_filter($photos, fn($photo) => ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE));
if (count($photos) > CHAT_MAX_IMAGES) {
    json_error('You can attach up to ' . CHAT_MAX_IMAGES . ' photos per message.', 422);
}
if ($body === '' && !$photos) {
    json_error('Write a message or attach a photo.', 422);
}

$paths = [];
try {
    foreach ($photos as $photo) {
        $paths[] = store_uploaded_image($photo, 'chat');
    }
} catch (UploadException $e) {
    array_map('delete_upload', $paths);
    json_error($e->getMessage(), 422);
}

$conversationId = (int) $conversation['id'];

$messageId = with_upload_cleanup($paths, fn() => db_insert(
    'INSERT INTO messages (conversation_id, sender_id, encrypted_message, file_path, created_at) VALUES (?, ?, ?, ?, NOW())',
    [
        $conversationId,
        $userId,
        $body !== '' ? encrypt_message($conversationId, $body) : null,
        $paths ? json_encode($paths, JSON_UNESCAPED_SLASHES) : null,
    ],
));

db_exec(
    'UPDATE conversations SET last_message = ?, last_sender = ?, updated_at = NOW(), user1_deleted = 0, user2_deleted = 0 WHERE id = ?',
    [$body !== '' ? 'New message' : 'Photo', $userId, $conversationId],
);

json_response(['id' => $messageId], 201);
