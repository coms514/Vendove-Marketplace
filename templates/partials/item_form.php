<div class="editor">
    <form method="post" class="form card editor__form" data-loading-form data-item-form>
        <?= csrf_field() ?>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert--error"><?= e($error) ?></div>
        <?php endforeach; ?>

        <label class="field">
            <span class="field__label">Title</span>
            <input type="text" name="title" maxlength="100" value="<?= e($form['title']) ?>" required data-preview-source="title">
        </label>

        <label class="field">
            <span class="field__label">Description</span>
            <textarea name="description" rows="6" maxlength="5000" required data-preview-source="description"><?= e($form['description']) ?></textarea>
        </label>

        <div class="form-row">
            <label class="field">
                <span class="field__label">Price (USD)</span>
                <input type="number" name="price" min="0.01" step="0.01" value="<?= e($form['price']) ?>" required data-preview-source="price">
            </label>
            <label class="field">
                <span class="field__label">Condition</span>
                <select name="condition" required data-preview-source="condition">
                    <option value="">Choose…</option>
                    <?php foreach (ITEM_CONDITIONS as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= $form['condition'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="form-row form-row--3">
            <?php foreach (['category_id' => ['Category', 'categories'], 'brand_id' => ['Brand', 'brands'], 'size_id' => ['Size', 'sizes']] as $field => [$label, $list]): ?>
                <label class="field">
                    <span class="field__label"><?= e($label) ?> <span class="muted">(optional)</span></span>
                    <select name="<?= e($field) ?>">
                        <option value="">None</option>
                        <?php foreach ($options[$list] as $option): ?>
                            <option value="<?= (int) $option['id'] ?>" <?= (int) $form[$field] === (int) $option['id'] ? 'selected' : '' ?>><?= e($option['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="field">
            <span class="field__label">Photos</span>
            <?php $imageMax = ITEM_MAX_IMAGES; ?>
            <?php require APP_ROOT . '/templates/partials/image_manager.php'; ?>
        </div>

        <div class="actions">
            <button class="btn btn--primary btn--grow" type="submit"><?= e($submitLabel) ?></button>
            <a class="btn btn--ghost" href="<?= e($cancelUrl) ?>">Cancel</a>
        </div>
    </form>

    <aside class="editor__preview" aria-label="Preview">
        <p class="eyebrow">Preview</p>
        <div class="card listing-card">
            <div class="listing-card__media">
                <img src="<?= e(IMAGE_PLACEHOLDER) ?>" alt="" data-preview-image>
            </div>
            <div class="listing-card__body">
                <h3 class="listing-card__title" data-preview="title" data-empty="Your item title"></h3>
                <div class="listing-card__meta">
                    <span class="price" data-preview="price" data-empty="$0.00"></span>
                    <span class="tag" data-preview="condition" data-empty="Condition"></span>
                </div>
                <p class="muted preview-description" data-preview="description" data-empty="Your description will appear here."></p>
            </div>
        </div>
    </aside>
</div>
