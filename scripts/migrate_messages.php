<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/bootstrap.php';

$keys = 0;
foreach (db_all('SELECT conversation_id FROM conversation_keys') as $row) {
    conversation_key((int) $row['conversation_id']);
    $keys++;
}

$reencrypted = 0;
$unreadable = 0;
$rows = db_all("SELECT id, conversation_id, encrypted_message FROM messages WHERE encrypted_message IS NOT NULL AND encrypted_message <> '' AND encrypted_message NOT LIKE 'v2:%'");

foreach ($rows as $row) {
    $plaintext = decrypt_message((int) $row['conversation_id'], $row['encrypted_message']);
    if ($plaintext === null) {
        $unreadable++;
        fwrite(STDERR, "Message {$row['id']} could not be decrypted and was left unchanged.\n");
        continue;
    }

    db_exec('UPDATE messages SET encrypted_message = ? WHERE id = ?', [encrypt_message((int) $row['conversation_id'], $plaintext), $row['id']]);
    $reencrypted++;
}

db_exec(
    "UPDATE conversation_keys k SET k.iv = ''
     WHERE k.iv <> '' AND NOT EXISTS (
         SELECT 1 FROM messages m
         WHERE m.conversation_id = k.conversation_id AND m.encrypted_message IS NOT NULL
           AND m.encrypted_message <> '' AND m.encrypted_message NOT LIKE 'v2:%'
     )",
);

$paths = 0;
foreach (db_all("SELECT id, file_path FROM messages WHERE file_path LIKE '%\"/uploads/%'") as $row) {
    db_exec('UPDATE messages SET file_path = ? WHERE id = ?', [json_encode(message_images($row['file_path']), JSON_UNESCAPED_SLASHES), $row['id']]);
    $paths++;
}

db_exec("UPDATE conversations SET last_message = 'New message' WHERE last_message IS NOT NULL AND BINARY last_message NOT IN ('New message', 'Photo', '[Image]')");
db_exec("UPDATE conversations SET last_message = 'Photo' WHERE last_message = '[Image]'");

echo "Conversation keys wrapped: {$keys}\n";
echo "Messages re-encrypted: {$reencrypted}\n";
echo "Messages left unreadable: {$unreadable}\n";
echo "Image paths fixed: {$paths}\n";
