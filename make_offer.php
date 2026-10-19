<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
$itemId = input_int(is_post() ? $_POST : $_GET, 'item_id');
$item = find_item($itemId);
if ($item === null) {
    abort(404, 'Listing not found.');
}

$error = null;
$amountInput = '';

if (is_post()) {
    verify_csrf();
    $amountInput = input_string($_POST, 'amount', 20);
    $offerAmount = input_decimal($_POST, 'amount');
    $error = validate_offer($item, $userId, $offerAmount);

    if ($error === null) {
        create_offer($itemId, $userId, $offerAmount);
        flash('success', 'Your offer of ' . money($offerAmount) . ' was sent. We will let you know when the seller responds.');
        redirect('item_details.php?id=' . $itemId);
    }
} else {
    // half the price always passes the amount rules, so this only surfaces the other blockers
    // (own listing, sold, offer already pending) before the buyer types anything
    $error = validate_offer($item, $userId, (float) $item['price_usd'] / 2);
}

$minimum = round((float) $item['price_usd'] * OFFER_MIN_RATIO, 2);
$pageTitle = 'Make an offer';
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <a class="back-link" href="item_details.php?id=<?= $itemId ?>"><?= icon('arrow-left') ?> Back to listing</a>

    <div class="card summary form--narrow">
        <img class="summary__image" src="<?= e(image_url($item['primary_image'])) ?>" alt="">
        <div>
            <h1 class="summary__title"><?= e($item['title']) ?></h1>
            <p class="muted">Asking price <strong class="price"><?= e(money($item['price_usd'])) ?></strong> · sold by <?= e($item['username']) ?></p>
        </div>
    </div>

    <form method="post" class="form card form--narrow" data-loading-form>
        <?= csrf_field() ?>
        <input type="hidden" name="item_id" value="<?= $itemId ?>">

        <?php if ($error): ?>
            <div class="alert alert--error"><?= e($error) ?></div>
        <?php endif; ?>

        <label class="field">
            <span class="field__label">Your offer (USD)</span>
            <input type="number" name="amount" min="<?= e($minimum) ?>" max="<?= e((float) $item['price_usd'] - 0.01) ?>" step="0.01" value="<?= e($amountInput) ?>" required autofocus>
            <span class="form-hint">Offers start at <?= e(money($minimum)) ?>. If the seller accepts, you pay this amount at checkout.</span>
        </label>

        <div class="actions">
            <button class="btn btn--primary btn--grow" type="submit">Send offer</button>
            <a class="btn btn--ghost" href="item_details.php?id=<?= $itemId ?>">Cancel</a>
        </div>
    </form>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
