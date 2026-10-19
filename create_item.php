<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
$options = catalog_options();
$errors = [];
$form = ['title' => '', 'description' => '', 'price' => '', 'condition' => '', 'category_id' => 0, 'brand_id' => 0, 'size_id' => 0];

if (is_post()) {
    verify_csrf();
    [$listing, $errors] = validate_item_input($_POST);
    $form = array_merge($form, array_intersect_key($_POST, $form));

    if (!$errors) {
        try {
            $images = process_image_list((string) ($_POST['images'] ?? ''), [], 'items', ITEM_MAX_IMAGES);
            if (!$images['paths']) {
                $errors[] = 'Please add at least one photo.';
            } else {
                $itemId = with_upload_cleanup($images['created'], fn() => create_item($userId, $listing, $images['paths']));
                flash('success', 'Your listing is live.');
                redirect('item_details.php?id=' . $itemId);
            }
        } catch (UploadException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$imageExisting = image_manager_state(is_post() ? (string) ($_POST['images'] ?? '') : null, []);
$submitLabel = 'Publish listing';
$cancelUrl = 'listings.php';

$pageTitle = 'Sell an item';
$pageStyles = ['cropper.min.css'];
$pageScripts = ['cropper.min.js', 'image_manager.js'];
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <div class="page-header">
        <div>
            <h1>Sell an item</h1>
            <p class="muted">Good photos and an honest description sell faster.</p>
        </div>
    </div>
    <?php require APP_ROOT . '/templates/partials/item_form.php'; ?>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
