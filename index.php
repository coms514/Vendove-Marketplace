<?php

require __DIR__ . '/bootstrap.php';

$latestItems = db_all(
    "SELECT id, title, price_usd, `condition`, primary_image FROM items
     WHERE active = 1 AND status <> 'sold'
     ORDER BY created_at DESC, id DESC LIMIT 8",
);
$latestRequests = db_all(
    'SELECT r.id, r.title, r.price_range, r.image_url, u.username
     FROM requests r JOIN users u ON u.id = r.user_id
     WHERE r.active = 1
     ORDER BY r.created_at DESC, r.id DESC LIMIT 4',
);

$pageTitle = '';
require APP_ROOT . '/templates/header.php';
?>
<section class="hero">
    <div class="container hero__inner">
        <h1>Buy, sell and find exactly what you are looking for.</h1>
        <p>List what you no longer need, make offers on what you want, or post a request and let sellers come to you.</p>
        <form class="hero__search" action="listings.php" method="get" role="search">
            <input type="search" name="q" placeholder="Search listings" aria-label="Search listings">
            <button class="btn btn--primary" type="submit"><?= icon('search') ?><span>Search</span></button>
        </form>
        <div class="hero__actions">
            <a class="btn btn--ghost" href="create_item.php"><?= icon('plus') ?> Sell an item</a>
            <a class="btn btn--ghost" href="create_request.php">Ask to buy</a>
        </div>
    </div>
</section>

<section class="container section">
    <div class="section__header">
        <h2>Latest listings</h2>
        <a href="listings.php">See all</a>
    </div>
    <?php if ($latestItems): ?>
        <div class="card-grid">
            <?php foreach ($latestItems as $card): ?>
                <?php require APP_ROOT . '/templates/partials/item_card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>No listings yet</h3>
            <p>Be the first to put something up for sale.</p>
            <a class="btn btn--primary" href="create_item.php">Sell an item</a>
        </div>
    <?php endif; ?>
</section>

<section class="container section">
    <div class="section__header">
        <h2>People are looking for</h2>
        <a href="requests.php">See all requests</a>
    </div>
    <?php if ($latestRequests): ?>
        <div class="card-grid">
            <?php foreach ($latestRequests as $card): ?>
                <?php require APP_ROOT . '/templates/partials/request_card.php'; ?>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <h3>No requests yet</h3>
            <p>Can't find what you want? Tell sellers what you are after.</p>
            <a class="btn btn--primary" href="create_request.php">Ask to buy</a>
        </div>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
