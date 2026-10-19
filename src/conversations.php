<?php

const CHAT_MAX_IMAGES = 3;
const CHAT_MAX_LENGTH = 2000;

function find_conversation_for_user(int $conversationId, int $userId): ?array
{
    $conversation = db_one(
        'SELECT c.*, u.id AS other_id, u.username AS other_name, u.profile_picture AS other_picture
         FROM conversations c
         JOIN users u ON u.id = IF(c.user1_id = ?, c.user2_id, c.user1_id)
         WHERE c.id = ? AND (c.user1_id = ? OR c.user2_id = ?)',
        [$userId, $conversationId, $userId, $userId],
    );

    if ($conversation === null) {
        return null;
    }

    $isUser1 = (int) $conversation['user1_id'] === $userId;
    $conversation['blocked_by_me'] = (bool) ($isUser1 ? $conversation['user1_blocked_user2'] : $conversation['user2_blocked_user1']);
    $conversation['blocked_me'] = (bool) ($isUser1 ? $conversation['user2_blocked_user1'] : $conversation['user1_blocked_user2']);
    $conversation['deleted_for_me'] = (bool) ($isUser1 ? $conversation['user1_deleted'] : $conversation['user2_deleted']);
    $conversation['my_side'] = $isUser1 ? 'user1' : 'user2';

    return $conversation;
}

function conversation_context(array $conversation): ?array
{
    if (!empty($conversation['item_id'])) {
        $item = db_one('SELECT id, title, price_usd, primary_image, status FROM items WHERE id = ?', [$conversation['item_id']]);
        if ($item) {
            return [
                'type' => 'item',
                'label' => $item['status'] === 'sold' ? 'Sold' : 'For sale',
                'title' => $item['title'],
                'price' => money($item['price_usd']),
                'image' => image_url($item['primary_image']),
                'link' => 'item_details.php?id=' . $item['id'],
            ];
        }
    }

    if (!empty($conversation['request_id'])) {
        $request = db_one('SELECT id, title, price_range, image_url FROM requests WHERE id = ?', [$conversation['request_id']]);
        if ($request) {
            return [
                'type' => 'request',
                'label' => 'Wanted',
                'title' => $request['title'],
                'price' => format_price_range($request['price_range']),
                'image' => image_url($request['image_url']),
                'link' => 'request_details.php?id=' . $request['id'],
            ];
        }
    }

    return null;
}

function conversation_list(int $userId): array
{
    return db_all(
        'SELECT c.id, c.item_id, c.request_id, c.last_message, c.last_sender, c.updated_at,
                u.id AS other_id, u.username AS other_name, u.profile_picture AS other_picture,
                COALESCE(i.title, r.title) AS context_title,
                (SELECT COUNT(*) FROM messages m
                 WHERE m.conversation_id = c.id AND m.sender_id <> ? AND m.is_read = 0 AND m.deleted_by_receiver = 0) AS unread_count
         FROM conversations c
         JOIN users u ON u.id = IF(c.user1_id = ?, c.user2_id, c.user1_id)
         LEFT JOIN items i ON i.id = c.item_id
         LEFT JOIN requests r ON r.id = c.request_id
         WHERE (c.user1_id = ? AND c.user1_deleted = 0) OR (c.user2_id = ? AND c.user2_deleted = 0)
         ORDER BY c.updated_at DESC, c.id DESC',
        [$userId, $userId, $userId, $userId],
    );
}

function unread_message_count(int $userId): int
{
    return (int) db_value(
        'SELECT COUNT(*)
         FROM messages m
         JOIN conversations c ON c.id = m.conversation_id
         WHERE m.sender_id <> ? AND m.is_read = 0 AND m.deleted_by_receiver = 0
           AND ((c.user1_id = ? AND c.user1_deleted = 0) OR (c.user2_id = ? AND c.user2_deleted = 0))',
        [$userId, $userId, $userId],
    );
}

function open_conversation(int $userId, string $contextType, int $contextId): int
{
    if ($contextType === 'item') {
        $owner = db_value('SELECT user_id FROM items WHERE id = ? AND active = 1', [$contextId]);
        $column = 'item_id';
    } else {
        $owner = db_value('SELECT user_id FROM requests WHERE id = ? AND active = 1', [$contextId]);
        $column = 'request_id';
    }

    if ($owner === null) {
        throw new DomainException('This listing is no longer available.');
    }

    $otherId = (int) $owner;
    if ($otherId === $userId) {
        throw new DomainException('You cannot start a conversation with yourself.');
    }

    $existing = db_one(
        "SELECT id, user1_id FROM conversations
         WHERE {$column} = ? AND ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?))
         LIMIT 1",
        [$contextId, $userId, $otherId, $otherId, $userId],
    );

    if ($existing) {
        $side = (int) $existing['user1_id'] === $userId ? 'user1_deleted' : 'user2_deleted';
        db_exec("UPDATE conversations SET {$side} = 0 WHERE id = ?", [$existing['id']]);

        return (int) $existing['id'];
    }

    $conversationId = db_insert(
        "INSERT INTO conversations (user1_id, user2_id, {$column}) VALUES (?, ?, ?)",
        [$userId, $otherId, $contextId],
    );
    conversation_key($conversationId);

    return $conversationId;
}

function message_images(?string $json): array
{
    $paths = json_decode((string) $json, true);
    if (!is_array($paths)) {
        return [];
    }

    return array_values(array_filter(array_map(fn($path) => ltrim((string) $path, '/'), $paths)));
}

function conversation_messages(array $conversation, int $userId): array
{
    $stored = db_all('SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at, id', [$conversation['id']]);
    $messages = [];

    foreach ($stored as $message) {
        $mine = (int) $message['sender_id'] === $userId;
        if (($mine && $message['deleted_by_sender']) || (!$mine && $message['deleted_by_receiver'])) {
            continue;
        }

        $text = null;
        if ($message['encrypted_message'] !== null && $message['encrypted_message'] !== '') {
            $text = decrypt_message((int) $conversation['id'], $message['encrypted_message']) ?? 'This message could not be decrypted.';
        }

        $messages[] = [
            'id' => (int) $message['id'],
            'mine' => $mine,
            'text' => $text,
            'images' => message_images($message['file_path']),
            'edited' => (bool) $message['edited'],
            'read' => (bool) $message['is_read'],
            'sent_at' => iso_date($message['created_at']),
        ];
    }

    return $messages;
}

function mark_conversation_read(int $conversationId, int $userId): void
{
    db_exec(
        'UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id <> ? AND is_read = 0',
        [$conversationId, $userId],
    );
}

function refresh_conversation_preview(int $conversationId): void
{
    $last = db_one(
        'SELECT sender_id, encrypted_message, file_path FROM messages
         WHERE conversation_id = ? AND NOT (deleted_by_sender = 1 AND deleted_by_receiver = 1)
         ORDER BY created_at DESC, id DESC LIMIT 1',
        [$conversationId],
    );

    if ($last === null) {
        db_exec('UPDATE conversations SET last_message = NULL, last_sender = NULL WHERE id = ?', [$conversationId]);
        return;
    }

    // bodies are encrypted, so the list preview never shows message text
    $preview = !empty($last['encrypted_message']) ? 'New message' : 'Photo';
    db_exec(
        'UPDATE conversations SET last_message = ?, last_sender = ? WHERE id = ?',
        [$preview, $last['sender_id'], $conversationId],
    );
}

function delete_conversation_everywhere(int $conversationId): void
{
    $attachments = db_all('SELECT file_path FROM messages WHERE conversation_id = ? AND file_path IS NOT NULL', [$conversationId]);

    db_transaction(function () use ($conversationId) {
        db_exec('DELETE FROM messages WHERE conversation_id = ?', [$conversationId]);
        db_exec('DELETE FROM conversation_keys WHERE conversation_id = ?', [$conversationId]);
        db_exec('DELETE FROM conversations WHERE id = ?', [$conversationId]);
    });

    foreach ($attachments as $message) {
        array_map('delete_upload', message_images($message['file_path']));
    }
}
