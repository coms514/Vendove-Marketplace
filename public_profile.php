<?php

require __DIR__ . '/bootstrap.php';

$profile = db_one('SELECT id, username, profile_picture, bio, created_at FROM users WHERE id = ? AND is_verified = 1', [input_int($_GET, 'user_id')]);
if ($profile === null) {
    abort(404, 'User not found.');
}

$listings = db_all(
    "SELECT id, title, price_usd, `condition`, primary_image, status FROM items
     WHERE user_id = ? AND active = 1 AND status <> 'sold' ORDER BY created_at DESC",
    [$profile['id']],
);
$requests = db_all(
    'SELECT id, title, price_range, image_url FROM requests WHERE user_id = ? AND active = 1 ORDER BY created_at DESC',
    [$profile['id']],
);
$soldCount = (int) db_value('SELECT COUNT(*) FROM transactions WHERE seller_id = ?', [$profile['id']]);
$isMe = current_user_id() === (int) $profile['id'];

$pageTitle = $profile['username'];
require APP_ROOT . '/templates/header.php';
?>
<section class="profile-hero">
    <div class="container profile-hero__inner">
        <img class="avatar avatar--xl" src="<?= e(avatar_url($profile['profile_picture'])) ?>" alt="">
        <div>
            <h1><?= e($profile['username']) ?></h1>
            <?php if (trim((string) $profile['bio']) !== ''): ?>
                <p class="profile-hero__bio"><?= nl2br(e($profile['bio'])) ?></p>
            <?php endif; ?>
            <p class="muted">
                <?= count($listings) ?> listing<?= count($listings) === 1 ? '' : 's' ?> ·
                <?= $soldCount ?> sold ·
                Joined <?= e(format_date($profile['created_at'], 'F Y')) ?>
            </p>
            <?php if ($isMe): ?>
                <a class="btn btn--ghost btn--sm" href="account.php">Edit profile</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="container section">
    <h2>Listings</h2>
    <?php if ($listings): ?>
        <div class="card-grid">
            <?php foreach ($listings as $card): ?>
                <?php require APP_ROOT . '/templates/partials/item_card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="empty-inline"><?= e($profile['username']) ?> has nothing for sale right now.</p>
    <?php endif; ?>
</section>

<?php if ($requests): ?>
    <section class="container section">
        <h2>Looking for</h2>
        <div class="card-grid">
            <?php foreach ($requests as $card): ?>
                <?php require APP_ROOT . '/templates/partials/request_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php require APP_ROOT . '/templates/footer.php'; ?>
