<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
$conversationId = input_int($_GET, 'id');
$conversation = null;
$context = null;

if ($conversationId > 0) {
    $conversation = find_conversation_for_user($conversationId, $userId);
    if ($conversation === null) {
        abort(404, 'Conversation not found.');
    }
    if ($conversation['deleted_for_me']) {
        db_exec("UPDATE conversations SET {$conversation['my_side']}_deleted = 0 WHERE id = ?", [$conversationId]);
    }
    mark_conversation_read($conversationId, $userId);
    $context = conversation_context($conversation);
}

$conversations = conversation_list($userId);

$pageTitle = $conversation ? 'Chat with ' . $conversation['other_name'] : 'Messages';
$bodyClass = 'page-chat';
$hideFooter = true;
$pageScripts = ['chat.js'];
require APP_ROOT . '/templates/header.php';
?>
<div class="chat<?= $conversation ? ' chat--open' : '' ?>">
    <aside class="chat__sidebar" aria-label="Conversations">
        <h1 class="chat__sidebar-title">Messages</h1>
        <?php if ($conversations): ?>
            <ul class="chat-list">
                <?php foreach ($conversations as $chat): ?>
                    <li class="chat-list__item<?= (int) $chat['id'] === $conversationId ? ' is-active' : '' ?>" data-conversation-row="<?= (int) $chat['id'] ?>">
                        <a class="chat-list__link" href="messages.php?id=<?= (int) $chat['id'] ?>">
                            <img class="avatar" src="<?= e(avatar_url($chat['other_picture'])) ?>" alt="">
                            <span class="chat-list__text">
                                <span class="chat-list__top">
                                    <strong><?= e($chat['other_name']) ?></strong>
                                    <time class="muted" datetime="<?= e(iso_date($chat['updated_at'])) ?>" data-relative-time><?= e(format_date($chat['updated_at'], 'M j')) ?></time>
                                </span>
                                <span class="chat-list__context">
                                    <span class="tag tag--sm <?= $chat['item_id'] ? '' : 'tag--wanted' ?>"><?= $chat['item_id'] ? 'Item' : 'Request' ?></span>
                                    <?= e($chat['context_title'] ?? 'Removed listing') ?>
                                </span>
                                <span class="chat-list__preview muted">
                                    <?= (int) $chat['last_sender'] === $userId ? 'You: ' : '' ?><?= e($chat['last_message'] ?? 'No messages yet') ?>
                                </span>
                            </span>
                            <?php if ((int) $chat['unread_count'] > 0 && (int) $chat['id'] !== $conversationId): ?>
                                <span class="badge"><?= (int) $chat['unread_count'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <div class="empty-state">
                <h3>No conversations yet</h3>
                <p>Message a seller from any listing, or reply to a buy request.</p>
                <a class="btn btn--primary" href="listings.php">Browse listings</a>
            </div>
        <?php endif; ?>
    </aside>

    <section class="chat__main">
        <?php if ($conversation): ?>
            <div class="chat__thread"
                 data-chat
                 data-conversation-id="<?= $conversationId ?>"
                 data-other-name="<?= e($conversation['other_name']) ?>"
                 data-blocked-by-me="<?= $conversation['blocked_by_me'] ? '1' : '0' ?>"
                 data-blocked-me="<?= $conversation['blocked_me'] ? '1' : '0' ?>"
                 data-max-images="<?= CHAT_MAX_IMAGES ?>">
                <header class="chat__header">
                    <a class="chat__back" href="messages.php" aria-label="Back to conversations"><?= icon('arrow-left') ?></a>
                    <a class="chat__person" href="public_profile.php?user_id=<?= (int) $conversation['other_id'] ?>">
                        <img class="avatar" src="<?= e(avatar_url($conversation['other_picture'])) ?>" alt="">
                        <strong><?= e($conversation['other_name']) ?></strong>
                    </a>
                    <?php if ($context): ?>
                        <a class="chat__context" href="<?= e($context['link']) ?>">
                            <img src="<?= e($context['image']) ?>" alt="">
                            <span>
                                <span class="muted"><?= e($context['label']) ?></span>
                                <strong><?= e($context['title']) ?></strong>
                                <span class="price"><?= e($context['price']) ?></span>
                            </span>
                        </a>
                    <?php endif; ?>
                    <details class="menu">
                        <summary class="icon-button" aria-label="Conversation options"><?= icon('more') ?></summary>
                        <div class="menu__panel">
                            <button type="button" data-block-toggle><?= $conversation['blocked_by_me'] ? 'Unblock ' : 'Block ' ?><?= e($conversation['other_name']) ?></button>
                            <button type="button" data-delete-conversation="me">Delete for me</button>
                            <button type="button" class="danger" data-delete-conversation="everyone">Delete for everyone</button>
                        </div>
                    </details>
                </header>

                <div class="chat__messages" data-chat-messages aria-live="polite">
                    <div class="loading-block"><div class="spinner"></div><span>Loading messages…</span></div>
                </div>

                <div class="chat__notice" data-chat-notice hidden></div>

                <form class="chat__composer" data-chat-form>
                    <div class="chat__attachments" data-chat-previews hidden></div>
                    <div class="chat__composer-row">
                        <label class="icon-button" title="Attach photos">
                            <?= icon('image') ?><span class="visually-hidden">Attach photos</span>
                            <input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden data-chat-files>
                        </label>
                        <textarea rows="1" maxlength="<?= CHAT_MAX_LENGTH ?>" placeholder="Write a message…" aria-label="Message" data-chat-input></textarea>
                        <button class="btn btn--primary chat__send" type="submit" data-chat-send>
                            <?= icon('send') ?><span class="visually-hidden">Send</span>
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <div class="empty-state chat__placeholder">
                <?= icon('message') ?>
                <h2>Select a conversation</h2>
                <p class="muted">Pick a chat on the left to read and reply.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="context-menu" data-message-menu hidden>
    <button type="button" data-message-action="edit">Edit</button>
    <button type="button" data-message-action="delete-me">Delete for me</button>
    <button type="button" class="danger" data-message-action="delete-everyone">Delete for everyone</button>
</div>

<dialog class="dialog" data-edit-dialog>
    <form method="dialog" class="dialog__body">
        <h2 class="dialog__title">Edit message</h2>
        <textarea rows="3" maxlength="<?= CHAT_MAX_LENGTH ?>" data-edit-input></textarea>
        <div class="dialog__actions">
            <button class="btn btn--ghost" value="cancel">Cancel</button>
            <button class="btn btn--primary" value="save">Save</button>
        </div>
    </form>
</dialog>

<dialog class="lightbox" data-lightbox>
    <button type="button" class="lightbox__close" aria-label="Close" data-lightbox-close><?= icon('x') ?></button>
    <img alt="" data-lightbox-image>
</dialog>
<?php require APP_ROOT . '/templates/footer.php'; ?>
