<a class="card card--link listing-card" href="item_details.php?id=<?= (int) $card['id'] ?>">
    <div class="listing-card__media">
        <img src="<?= e(image_url($card['primary_image'] ?? null)) ?>" alt="" loading="lazy">
        <?php if (($card['status'] ?? '') === 'sold'): ?>
            <span class="tag tag--sold">Sold</span>
        <?php endif; ?>
    </div>
    <div class="listing-card__body">
        <h3 class="listing-card__title"><?= e($card['title']) ?></h3>
        <div class="listing-card__meta">
            <span class="price"><?= e(money($card['price_usd'])) ?></span>
            <?php if (!empty($card['condition'])): ?>
                <span class="tag"><?= e(ITEM_CONDITIONS[$card['condition']] ?? ucfirst($card['condition'])) ?></span>
            <?php endif; ?>
        </div>
    </div>
</a>
