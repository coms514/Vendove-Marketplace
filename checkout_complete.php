<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
$intentId = input_string($_GET, 'payment_intent', 100);

if (!preg_match('/^pi_[A-Za-z0-9]+$/', $intentId) || !payments_enabled()) {
    abort(404);
}

try {
    $intent = stripe()->paymentIntents->retrieve($intentId);
} catch (\Stripe\Exception\ApiErrorException $e) {
    log_exception($e);
    abort(404, 'Payment not found.');
}

if ((int) ($intent->metadata['buyer_id'] ?? 0) !== $userId) {
    abort(403);
}

$status = finalize_purchase($intent);
$itemId = (int) $intent->metadata['item_id'];
$item = db_one('SELECT id, title, primary_image FROM items WHERE id = ?', [$itemId]);

$outcomes = [
    'completed' => ['Payment received', 'Thanks for your purchase! The seller has been notified. You can message them to arrange delivery.', 'success'],
    'processing' => ['Payment processing', 'Your payment is being processed. We will mark the item as yours as soon as it clears.', 'info'],
    'refunded' => ['Item no longer available', 'Someone completed their purchase just before you. Your payment was refunded in full.', 'error'],
    'failed' => ['Payment not completed', 'Your payment did not go through. You have not been charged.', 'error'],
];
[$title, $text, $tone] = $outcomes[$status] ?? $outcomes['failed'];

$pageTitle = $title;
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <div class="card empty-state empty-state--page form--narrow">
        <span class="status-icon status-icon--<?= e($tone) ?>"><?= icon($tone === 'success' ? 'check' : 'x') ?></span>
        <h1><?= e($title) ?></h1>
        <?php if ($item): ?>
            <p class="summary__title"><?= e($item['title']) ?> · <?= e(money($intent->amount / 100)) ?></p>
        <?php endif; ?>
        <p><?= e($text) ?></p>
        <div class="actions actions--center">
            <?php if ($status === 'completed'): ?>
                <a class="btn btn--primary" href="transactions.php">View my purchases</a>
                <form action="start_conversation.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="item_id" value="<?= $itemId ?>">
                    <button class="btn btn--ghost" type="submit"><?= icon('message') ?> Message seller</button>
                </form>
            <?php elseif ($status === 'failed'): ?>
                <a class="btn btn--primary" href="checkout.php?item_id=<?= $itemId ?>">Try again</a>
            <?php else: ?>
                <a class="btn btn--primary" href="listings.php">Keep browsing</a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
