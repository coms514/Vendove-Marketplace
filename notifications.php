<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
$received = offers_received($userId);
$made = offers_made($userId);
$unreadChats = array_values(array_filter(conversation_list($userId), fn($chat) => (int) $chat['unread_count'] > 0));

$statusLabels = ['pending' => 'Waiting for seller', 'accepted' => 'Accepted', 'rejected' => 'Declined'];

$pageTitle = 'Notifications';
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <div class="page-header">
        <h1>Notifications</h1>
    </div>

    <div class="stack">
        <section class="panel">
            <h2 class="panel__title">Offers on your listings</h2>
            <?php if ($received): ?>
                <ul class="list">
                    <?php foreach ($received as $offer): ?>
                        <li class="list__row">
                            <img class="list__thumb" src="<?= e(image_url($offer['primary_image'])) ?>" alt="">
                            <div class="list__main">
                                <a class="list__title" href="item_details.php?id=<?= (int) $offer['item_id'] ?>"><?= e($offer['title']) ?></a>
                                <p class="muted">
                                    <a href="public_profile.php?user_id=<?= (int) $offer['buyer_id'] ?>"><?= e($offer['buyer_name']) ?></a>
                                    offered <strong class="price"><?= e(money($offer['offer_amount'])) ?></strong>
                                    (asking <?= e(money($offer['price_usd'])) ?>) · <?= e(format_date($offer['created_at'], 'M j, g:i a')) ?>
                                </p>
                            </div>
                            <form class="list__actions" action="handle_offer.php" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="offer_id" value="<?= (int) $offer['id'] ?>">
                                <button class="btn btn--primary btn--sm" name="action" value="accept">Accept</button>
                                <button class="btn btn--ghost btn--sm" name="action" value="decline">Decline</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty-inline">No offers waiting for you.</p>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2 class="panel__title">Your offers</h2>
            <?php if ($made): ?>
                <ul class="list">
                    <?php foreach ($made as $offer): ?>
                        <?php $canPay = $offer['status'] === 'accepted' && (int) $offer['item_active'] && $offer['item_status'] !== 'sold'; ?>
                        <li class="list__row">
                            <img class="list__thumb" src="<?= e(image_url($offer['primary_image'])) ?>" alt="">
                            <div class="list__main">
                                <a class="list__title" href="item_details.php?id=<?= (int) $offer['item_id'] ?>"><?= e($offer['title']) ?></a>
                                <p class="muted">
                                    You offered <strong class="price"><?= e(money($offer['offer_amount'])) ?></strong> ·
                                    <span class="status status--<?= e($offer['status']) ?>"><?= e($offer['purchased'] ? 'Purchased' : ($statusLabels[$offer['status']] ?? $offer['status'])) ?></span>
                                </p>
                            </div>
                            <?php if ($canPay): ?>
                                <div class="list__actions">
                                    <a class="btn btn--primary btn--sm" href="checkout.php?item_id=<?= (int) $offer['item_id'] ?>">Pay <?= e(money($offer['offer_amount'])) ?></a>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty-inline">You have not made any offers recently.</p>
            <?php endif; ?>
        </section>

        <section class="panel">
            <h2 class="panel__title">Unread messages</h2>
            <?php if ($unreadChats): ?>
                <ul class="list">
                    <?php foreach ($unreadChats as $chat): ?>
                        <li class="list__row">
                            <img class="avatar" src="<?= e(avatar_url($chat['other_picture'])) ?>" alt="">
                            <div class="list__main">
                                <a class="list__title" href="messages.php?id=<?= (int) $chat['id'] ?>"><?= e($chat['other_name']) ?></a>
                                <p class="muted"><?= (int) $chat['unread_count'] ?> new · <?= e($chat['context_title'] ?? 'Conversation') ?></p>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty-inline">You are all caught up.</p>
            <?php endif; ?>
        </section>
    </div>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
