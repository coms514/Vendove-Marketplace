<?php

require __DIR__ . '/bootstrap.php';

$item = find_item(input_int($_GET, 'id'));
if ($item === null) {
    abort(404, 'Listing not found.');
}

$userId = current_user_id();
$isOwner = $userId === (int) $item['user_id'];
$available = item_is_available($item);
$images = item_images((int) $item['id']);
if (!$images) {
    $images = [['id' => 0, 'image_url' => $item['primary_image']]];
}

$acceptedOffer = $userId && !$isOwner ? accepted_offer_for((int) $item['id'], $userId) : null;
$pendingOffer = $userId && !$isOwner ? pending_offer_for((int) $item['id'], $userId) : null;

$pageTitle = $item['title'];
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <a class="back-link" href="listings.php"><?= icon('arrow-left') ?> Back to listings</a>

    <div class="detail">
        <div class="gallery" data-gallery>
            <div class="gallery__main">
                <img src="<?= e(image_url($images[0]['image_url'])) ?>" alt="<?= e($item['title']) ?>" data-gallery-main>
            </div>
            <?php if (count($images) > 1): ?>
                <div class="gallery__thumbs">
                    <?php foreach ($images as $index => $image): ?>
                        <button type="button" class="gallery__thumb<?= $index === 0 ? ' is-active' : '' ?>" data-gallery-thumb="<?= e(image_url($image['image_url'])) ?>">
                            <img src="<?= e(image_url($image['image_url'])) ?>" alt="Photo <?= $index + 1 ?>" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="detail__info card">
            <div class="detail__header">
                <h1><?= e($item['title']) ?></h1>
                <div class="detail__price">
                    <span class="price price--lg"><?= e(money($item['price_usd'])) ?></span>
                    <span class="tag"><?= e(ITEM_CONDITIONS[$item['condition']] ?? ucfirst((string) $item['condition'])) ?></span>
                    <?php if (!$available): ?>
                        <span class="tag tag--sold">Sold</span>
                    <?php endif; ?>
                </div>
            </div>

            <dl class="specs">
                <div><dt>Category</dt><dd><?= e($item['category_name'] ?? 'Uncategorized') ?></dd></div>
                <div><dt>Brand</dt><dd><?= e($item['brand_name'] ?? 'No brand') ?></dd></div>
                <div><dt>Size</dt><dd><?= e($item['size_name'] ?? 'N/A') ?></dd></div>
                <div><dt>Listed</dt><dd><?= e(format_date($item['created_at'])) ?></dd></div>
            </dl>

            <div class="prose"><?= nl2br(e($item['description'])) ?></div>

            <?php if ($isOwner): ?>
                <div class="actions">
                    <?php if ($available): ?>
                        <a class="btn btn--secondary" href="edit_item.php?id=<?= (int) $item['id'] ?>">Edit listing</a>
                    <?php endif; ?>
                    <form action="delete_item.php" method="post" data-confirm="Delete this listing? It will disappear from the marketplace." data-confirm-title="Delete listing">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <button class="btn btn--danger" type="submit">Delete</button>
                    </form>
                </div>
            <?php elseif ($available): ?>
                <?php if ($acceptedOffer): ?>
                    <div class="alert alert--success">The seller accepted your offer of <?= e(money($acceptedOffer['offer_amount'])) ?>.</div>
                <?php elseif ($pendingOffer): ?>
                    <div class="alert alert--info">Your offer of <?= e(money($pendingOffer['offer_amount'])) ?> is waiting for the seller.</div>
                <?php endif; ?>

                <div class="actions">
                    <a class="btn btn--primary btn--grow" href="checkout.php?item_id=<?= (int) $item['id'] ?>">
                        Buy now<?= $acceptedOffer ? ' for ' . e(money($acceptedOffer['offer_amount'])) : '' ?>
                    </a>
                    <?php if (!$acceptedOffer && !$pendingOffer): ?>
                        <a class="btn btn--secondary" href="make_offer.php?item_id=<?= (int) $item['id'] ?>">Make offer</a>
                    <?php endif; ?>
                    <?php if ($userId): ?>
                        <form action="start_conversation.php" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                            <button class="btn btn--ghost" type="submit"><?= icon('message') ?> Message</button>
                        </form>
                    <?php else: ?>
                        <a class="btn btn--ghost" href="login.php?next=<?= e(urlencode('item_details.php?id=' . (int) $item['id'])) ?>"><?= icon('message') ?> Message</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="alert alert--info">This item has been sold.</div>
            <?php endif; ?>

            <a class="seller" href="public_profile.php?user_id=<?= (int) $item['user_id'] ?>">
                <img class="avatar" src="<?= e(avatar_url($item['profile_picture'])) ?>" alt="">
                <span>
                    <strong><?= e($item['username']) ?></strong>
                    <span class="muted">View seller profile</span>
                </span>
            </a>
        </div>
    </div>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
