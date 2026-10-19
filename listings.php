<?php

require __DIR__ . '/bootstrap.php';

$filters = [
    'q' => input_string($_GET, 'q', 100),
    'condition' => input_string($_GET, 'condition'),
    'category_id' => input_int($_GET, 'category_id'),
    'brand_id' => input_int($_GET, 'brand_id'),
    'min_price' => input_decimal($_GET, 'min_price'),
    'max_price' => input_decimal($_GET, 'max_price'),
    'sort' => input_string($_GET, 'sort'),
];
$options = catalog_options();
[$items, $pagination] = search_items($filters, input_int($_GET, 'page', 1));
$hasFilters = $filters['q'] !== '' || $filters['condition'] !== '' || $filters['category_id'] || $filters['brand_id']
    || $filters['min_price'] !== null || $filters['max_price'] !== null;

$sorts = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low'];

$pageTitle = 'Browse listings';
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <div class="page-header">
        <div>
            <h1>Browse listings</h1>
            <p class="muted"><?= $pagination['total'] ?> item<?= $pagination['total'] === 1 ? '' : 's' ?> for sale</p>
        </div>
        <a class="btn btn--primary" href="create_item.php"><?= icon('plus') ?> Sell an item</a>
    </div>

    <form class="filters card" method="get">
        <label class="field filters__search">
            <span class="visually-hidden">Search</span>
            <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search listings">
        </label>
        <label class="field">
            <span class="visually-hidden">Category</span>
            <select name="category_id">
                <option value="">All categories</option>
                <?php foreach ($options['categories'] as $option): ?>
                    <option value="<?= (int) $option['id'] ?>" <?= $filters['category_id'] === (int) $option['id'] ? 'selected' : '' ?>><?= e($option['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span class="visually-hidden">Brand</span>
            <select name="brand_id">
                <option value="">All brands</option>
                <?php foreach ($options['brands'] as $option): ?>
                    <option value="<?= (int) $option['id'] ?>" <?= $filters['brand_id'] === (int) $option['id'] ? 'selected' : '' ?>><?= e($option['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span class="visually-hidden">Condition</span>
            <select name="condition">
                <option value="">Any condition</option>
                <?php foreach (ITEM_CONDITIONS as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['condition'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field">
            <span class="visually-hidden">Minimum price</span>
            <input type="number" name="min_price" min="0" step="0.01" value="<?= e($filters['min_price']) ?>" placeholder="Min $">
        </label>
        <label class="field">
            <span class="visually-hidden">Maximum price</span>
            <input type="number" name="max_price" min="0" step="0.01" value="<?= e($filters['max_price']) ?>" placeholder="Max $">
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
                <a class="btn btn--ghost" href="listings.php">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($items): ?>
        <div class="card-grid">
            <?php foreach ($items as $card): ?>
                <?php require APP_ROOT . '/templates/partials/item_card.php'; ?>
            <?php endforeach; ?>
        </div>
        <?php require APP_ROOT . '/templates/partials/pagination.php'; ?>
    <?php else: ?>
        <div class="empty-state">
            <h3><?= $hasFilters ? 'No listings match your filters' : 'No listings yet' ?></h3>
            <p><?= $hasFilters ? 'Try a different search or clear the filters.' : 'Be the first to put something up for sale.' ?></p>
            <?php if ($hasFilters): ?>
                <a class="btn btn--ghost" href="listings.php">Clear filters</a>
            <?php else: ?>
                <a class="btn btn--primary" href="create_item.php">Sell an item</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
