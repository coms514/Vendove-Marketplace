<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
$item = find_item(input_int($_GET, 'item_id'));
if ($item === null) {
    abort(404, 'Listing not found.');
}
if ((int) $item['user_id'] === $userId) {
    abort(403, 'You cannot buy your own listing.');
}
if (!item_is_available($item)) {
    flash('info', 'Sorry, this item has already been sold.');
    redirect('item_details.php?id=' . (int) $item['id']);
}
if (!payments_enabled()) {
    abort(503, "Payments aren't set up yet.");
}

$quote = checkout_quote($item, $userId);
$paymentError = null;

try {
    $intent = checkout_payment_intent($item, $userId, $quote);
} catch (\Stripe\Exception\ApiErrorException $e) {
    log_exception($e);
    $paymentError = "Couldn't start the payment. Try again in a moment.";
}

$pageTitle = 'Checkout';
$pageScripts = ['https://js.stripe.com/v3/', 'checkout.js'];
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <a class="back-link" href="item_details.php?id=<?= (int) $item['id'] ?>"><?= icon('arrow-left') ?> Back to listing</a>

    <div class="checkout">
        <div class="card checkout__summary">
            <h1>Order summary</h1>
            <div class="summary">
                <img class="summary__image" src="<?= e(image_url($item['primary_image'])) ?>" alt="">
                <div>
                    <p class="summary__title"><?= e($item['title']) ?></p>
                    <p class="muted">Sold by <?= e($item['username']) ?></p>
                </div>
            </div>
            <dl class="totals">
                <div><dt>Listed price</dt><dd><?= e(money($item['price_usd'])) ?></dd></div>
                <?php if ($quote['offer_id']): ?>
                    <div><dt>Accepted offer</dt><dd><?= e(money($quote['amount'])) ?></dd></div>
                <?php endif; ?>
                <div class="totals__total"><dt>Total</dt><dd><?= e(money($quote['amount'])) ?></dd></div>
            </dl>
        </div>

        <div class="card checkout__payment">
            <h2>Payment</h2>
            <?php if ($paymentError): ?>
                <div class="alert alert--error"><?= e($paymentError) ?></div>
            <?php else: ?>
                <form id="payment-form" class="form"
                      data-checkout
                      data-publishable-key="<?= e(env('STRIPE_PUBLISHABLE_KEY')) ?>"
                      data-client-secret="<?= e($intent->client_secret) ?>"
                      data-return-url="<?= e(absolute_url('checkout_complete.php')) ?>">
                    <div class="payment-element" data-payment-element>
                        <div class="loading-block"><div class="spinner"></div><span>Loading secure payment form…</span></div>
                    </div>
                    <div class="alert alert--error" data-payment-error hidden></div>
                    <button class="btn btn--primary btn--block" type="submit" data-payment-submit disabled>
                        Pay <?= e(money($quote['amount'])) ?>
                    </button>
                    <p class="form-hint">Payments are processed securely by Stripe. Your card details never reach our servers.</p>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
