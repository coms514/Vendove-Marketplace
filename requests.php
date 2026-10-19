<?php

require __DIR__ . '/bootstrap.php';

$filters = [
    'q' => input_string($_GET, 'q', 100),
    'condition' => input_string($_GET, 'condition'),
    'sort' => input_string($_GET, 'sort'),
];
[$requests, $pagination] = search_requests($filters, input_int($_GET, 'page', 1));
$hasFilters = $filters['q'] !== '' || $filters['condition'] !== '';

$sorts = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'price_asc' => 'Budget: low to high', 'price_desc' => 'Budget: high to low'];

$pageTitle = 'Buy requests';
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <div class="page-header">
        <div>
            <h1>Buy requests</h1>
            <p class="muted">Have something on this list? Message the buyer.</p>
        </div>
        <a class="btn btn--primary" href="create_request.php"><?= icon('plus') ?> Ask to buy</a>
    </div>

    <form class="filters card" method="get">
        <label class="field filters__search">
            <span class="visually-hidden">Search</span>
            <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search requests">
        </label>
        <label class="field">
            <span class="visually-hidden">Condition</span>
            <select name="condition">
                <option value="">Any condition</option>
                <option value="new" <?= $filters['condition'] === 'new' ? 'selected' : '' ?>>Accepts new</option>
                <option value="used" <?= $filters['condition'] === 'used' ? 'selected' : '' ?>>Accepts used</option>
            </select>
        </label>
        <label class="field">
            <span class="visually-hidden">Sort</span>
            <select name="sort">
                <?php foreach ($sorts as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filters__actions">
            <button class="btn btn--primary" type="submit">Apply</button>
            <?php if ($hasFilters): ?>
                <a class="btn btn--ghost" href="requests.php">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($requests): ?>
        <div class="card-grid">
            <?php foreach ($requests as $card): ?>
                <?php require APP_ROOT . '/templates/partials/request_card.php'; ?>
            <?php endforeach; ?>
        </div>
        <?php require APP_ROOT . '/templates/partials/pagination.php'; ?>
    <?php else: ?>
        <div class="empty-state">
            <h3><?= $hasFilters ? 'No requests match your search' : 'No requests yet' ?></h3>
            <p><?= $hasFilters ? 'Try different words or clear the filters.' : 'Looking for something? Post the first request.' ?></p>
            <a class="btn btn--primary" href="create_request.php">Ask to buy</a>
        </div>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
