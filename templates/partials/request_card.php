<a class="card card--link listing-card" href="request_details.php?id=<?= (int) $card['id'] ?>">
    <div class="listing-card__media">
        <img src="<?= e(image_url($card['image_url'] ?? null)) ?>" alt="" loading="lazy">
        <span class="tag tag--wanted">Wanted</span>
    </div>
    <div class="listing-card__body">
        <h3 class="listing-card__title"><?= e($card['title']) ?></h3>
        <div class="listing-card__meta">
            <span class="price price--budget"><?= e(format_price_range($card['price_range'])) ?></span>
            <?php if (!empty($card['username'])): ?>
                <span class="muted">by <?= e($card['username']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</a>
