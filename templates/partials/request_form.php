<form method="post" class="form card form--narrow" data-loading-form>
    <?= csrf_field() ?>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert--error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <label class="field">
        <span class="field__label">What are you looking for?</span>
        <input type="text" name="title" maxlength="100" value="<?= e($form['title']) ?>" required>
    </label>

    <label class="field">
        <span class="field__label">Details</span>
        <textarea name="description" rows="5" maxlength="5000" required><?= e($form['description']) ?></textarea>
        <span class="form-hint">Size, colour, model, anything that helps sellers know they have the right thing.</span>
    </label>

    <div class="form-row">
        <label class="field">
            <span class="field__label">Budget (USD)</span>
            <input type="text" name="price_range" maxlength="30" value="<?= e($form['price_range']) ?>" placeholder="50 or 50-100" required>
        </label>
        <label class="field">
            <span class="field__label">Condition</span>
            <select name="condition" required>
                <option value="">Choose…</option>
                <?php foreach (REQUEST_CONDITIONS as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $form['condition'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <div class="field">
        <span class="field__label">Reference photos <span class="muted">(optional)</span></span>
        <?php $imageMax = REQUEST_MAX_IMAGES; ?>
        <?php require APP_ROOT . '/templates/partials/image_manager.php'; ?>
    </div>

    <div class="actions">
        <button class="btn btn--primary btn--grow" type="submit"><?= e($submitLabel) ?></button>
        <a class="btn btn--ghost" href="<?= e($cancelUrl) ?>">Cancel</a>
    </div>
</form>
