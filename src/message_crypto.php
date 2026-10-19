<?php

const CIPHER = 'aes-256-gcm';
const CIPHER_PREFIX = 'v2:';
const NONCE_BYTES = 12;
const TAG_BYTES = 16;

function app_key(): string
{
    static $key = null;

    if ($key === null) {
        $raw = base64_decode((string) env('APP_KEY'), true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException('APP_KEY must be a base64-encoded 32-byte key');
        }
        $key = $raw;
    }

    return $key;
}

function aead_encrypt(string $plaintext, string $key, string $context): string
{
    $nonce = random_bytes(NONCE_BYTES);
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $context, TAG_BYTES);

    if ($ciphertext === false) {
        throw new RuntimeException('encryption failed');
    }

    return CIPHER_PREFIX . base64_encode($nonce . $tag . $ciphertext);
}

function aead_decrypt(string $payload, string $key, string $context): ?string
{
    if (!str_starts_with($payload, CIPHER_PREFIX)) {
        return null;
    }

    $raw = base64_decode(substr($payload, strlen(CIPHER_PREFIX)), true);
    if ($raw === false || strlen($raw) < NONCE_BYTES + TAG_BYTES) {
        return null;
    }

    $nonce = substr($raw, 0, NONCE_BYTES);
    $tag = substr($raw, NONCE_BYTES, TAG_BYTES);
    $ciphertext = substr($raw, NONCE_BYTES + TAG_BYTES);
    $plaintext = openssl_decrypt($ciphertext, CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $context);

    return $plaintext === false ? null : $plaintext;
}

function conversation_key(int $conversationId): array
{
    static $cache = [];

    if (isset($cache[$conversationId])) {
        return $cache[$conversationId];
    }

    $context = 'conversation-key:' . $conversationId;
    $stored = db_one('SELECT enc_key, iv FROM conversation_keys WHERE conversation_id = ?', [$conversationId]);

    if ($stored === null) {
        $key = random_bytes(32);
        db_exec(
            'INSERT INTO conversation_keys (conversation_id, enc_key, iv) VALUES (?, ?, \'\')',
            [$conversationId, aead_encrypt($key, app_key(), $context)],
        );

        return $cache[$conversationId] = ['key' => $key, 'legacy_iv' => null];
    }

    // pre-v2 keys were plain base64 with one CBC iv per conversation. the iv is kept so old
    // messages stay readable until scripts/migrate_messages.php re-encrypts them
    $legacyIv = $stored['iv'] !== '' ? base64_decode($stored['iv'], true) : null;

    if (str_starts_with($stored['enc_key'], CIPHER_PREFIX)) {
        $key = aead_decrypt($stored['enc_key'], app_key(), $context);
        if ($key === null) {
            throw new RuntimeException("can't unwrap key for conversation {$conversationId} (APP_KEY changed?)");
        }
    } else {
        $key = base64_decode($stored['enc_key'], true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException("key for conversation {$conversationId} is corrupt");
        }
        db_exec(
            'UPDATE conversation_keys SET enc_key = ? WHERE conversation_id = ?',
            [aead_encrypt($key, app_key(), $context), $conversationId],
        );
    }

    return $cache[$conversationId] = ['key' => $key, 'legacy_iv' => $legacyIv ?: null];
}

function encrypt_message(int $conversationId, string $plaintext): string
{
    return aead_encrypt($plaintext, conversation_key($conversationId)['key'], 'message:' . $conversationId);
}

function decrypt_message(int $conversationId, string $payload): ?string
{
    $key = conversation_key($conversationId);

    if (str_starts_with($payload, CIPHER_PREFIX)) {
        return aead_decrypt($payload, $key['key'], 'message:' . $conversationId);
    }

    if ($key['legacy_iv'] === null) {
        return null;
    }

    $raw = base64_decode($payload, true);
    $plaintext = $raw === false ? false : openssl_decrypt($raw, 'aes-256-cbc', $key['key'], OPENSSL_RAW_DATA, $key['legacy_iv']);

    return $plaintext === false ? null : $plaintext;
}
