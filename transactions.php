<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();

// buyers pay the accepted offer when there is one, otherwise the listed price
$paidAmount = "COALESCE((SELECT o.offer_amount FROM offers o
                         WHERE o.item_id = t.item_id AND o.user_id = t.buyer_id AND o.status = 'accepted'
                         ORDER BY o.id DESC LIMIT 1), i.price_usd)";

$listings = db_all(
    "SELECT id, title, price_usd, `condition`, primary_image, status FROM items
     WHERE user_id = ? AND active = 1 AND status <> 'sold' ORDER BY created_at DESC",
    [$userId],
);
$requests = db_all(
    'SELECT id, title, price_range, image_url FROM requests WHERE user_id = ? AND active = 1 ORDER BY created_at DESC',
    [$userId],
);
$bought = db_all(
    "SELECT i.id, i.title, i.primary_image, t.transaction_date, {$paidAmount} AS paid, u.username AS other_name
     FROM transactions t JOIN items i ON i.id = t.item_id JOIN users u ON u.id = t.seller_id
     WHERE t.buyer_id = ? ORDER BY t.transaction_date DESC",
    [$userId],
);
$sold = db_all(
    "SELECT i.id, i.title, i.primary_image, t.transaction_date, {$paidAmount} AS paid, u.username AS other_name
     FROM transactions t JOIN items i ON i.id = t.item_id JOIN users u ON u.id = t.buyer_id
     WHERE t.seller_id = ? ORDER BY t.transaction_date DESC",
    [$userId],
);

$pageTitle = 'My activity';
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <div class="page-header">
        <h1>My activity</h1>
    </div>

    <div class="stack">
        <section>
            <div class="section__header">
                <h2>Active listings</h2>
                <a href="create_item.php">Sell an item</a>
            </div>
            <?php if ($listings): ?>
                <div class="card-grid">
                    <?php foreach ($listings as $card): ?>
                        <?php require APP_ROOT . '/templates/partials/item_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-inline">You have no active listings.</p>
            <?php endif; ?>
        </section>

        <section>
            <div class="section__header">
                <h2>Active buy requests</h2>
                <a href="create_request.php">Ask to buy</a>
            </div>
            <?php if ($requests): ?>
                <div class="card-grid">
                    <?php foreach ($requests as $card): ?>
                        <?php require APP_ROOT . '/templates/partials/request_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-inline">You have no active requests.</p>
            <?php endif; ?>
        </section>

        <?php foreach (['Purchases' => [$bought, 'from', 'You have not bought anything yet.'], 'Sales' => [$sold, 'to', 'You have not sold anything yet.']] as $heading => [$deals, $preposition, $emptyText]): ?>
            <section class="panel">
                <h2 class="panel__title"><?= e($heading) ?></h2>
                <?php if ($deals): ?>
                    <ul class="list">
                        <?php foreach ($deals as $deal): ?>
                            <li class="list__row">
                                <img class="list__thumb" src="<?= e(image_url($deal['primary_image'])) ?>" alt="">
                                <div class="list__main">
                                    <a class="list__title" href="item_details.php?id=<?= (int) $deal['id'] ?>"><?= e($deal['title']) ?></a>
                                    <p class="muted"><?= e(money($deal['paid'])) ?> <?= e($preposition) ?> <?= e($deal['other_name']) ?> · <?= e(format_date($deal['transaction_date'])) ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="empty-inline"><?= e($emptyText) ?></p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
