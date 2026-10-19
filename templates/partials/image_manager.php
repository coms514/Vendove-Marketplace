<div class="image-manager" data-image-manager data-max="<?= (int) $imageMax ?>"
     data-existing="<?= e(json_encode($imageExisting ?? [], JSON_UNESCAPED_SLASHES)) ?>">
    <input type="hidden" name="images" data-image-input>
    <input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden data-image-file>

    <div class="image-manager__grid" data-image-grid></div>

    <button type="button" class="dropzone" data-image-add>
        <?= icon('image') ?>
        <span><strong>Add photos</strong> · up to <?= (int) $imageMax ?>, JPG, PNG or WebP</span>
    </button>
    <p class="form-hint">Drag photos to reorder. The first photo is the cover. Click a photo to crop it.</p>
</div>

<dialog class="dialog dialog--wide" data-crop-dialog>
    <div class="dialog__body">
        <h2 class="dialog__title">Crop photo</h2>
        <div class="crop-area"><img alt="" data-crop-image></div>
        <div class="dialog__actions">
            <button type="button" class="btn btn--ghost" data-crop-cancel>Cancel</button>
            <button type="button" class="btn btn--primary" data-crop-apply>Apply crop</button>
        </div>
    </div>
</dialog>
