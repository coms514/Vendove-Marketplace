<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
$item = find_owned_item(input_int($_GET, 'id'), $userId);
if ($item === null) {
    abort(404, 'Listing not found.');
}
if (!item_is_available($item)) {
    abort(403, "Sold items can't be edited.");
}

$itemId = (int) $item['id'];
$options = catalog_options();
$existingById = array_column(item_images($itemId), 'image_url', 'id');
$errors = [];
$form = [
    'title' => $item['title'],
    'description' => (string) $item['description'],
    'price' => $item['price_usd'],
    'condition' => (string) $item['condition'],
    'category_id' => (int) $item['category_id'],
    'brand_id' => (int) $item['brand_id'],
    'size_id' => (int) $item['size_id'],
];

if (is_post()) {
    verify_csrf();
    [$listing, $errors] = validate_item_input($_POST);
    $form = array_merge($form, array_intersect_key($_POST, $form));

    if (!$errors) {
        try {
            $images = process_image_list((string) ($_POST['images'] ?? ''), $existingById, 'items', ITEM_MAX_IMAGES);
            if (!$images['paths']) {
                $errors[] = 'Please keep at least one photo.';
            } else {
                with_upload_cleanup($images['created'], fn() => update_item($itemId, $listing, $images['paths']));
                array_map('delete_upload', $images['removed']);
                flash('success', 'Your listing was updated.');
                redirect('item_details.php?id=' . $itemId);
            }
        } catch (UploadException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$imageExisting = image_manager_state(is_post() ? (string) ($_POST['images'] ?? '') : null, $existingById);
$submitLabel = 'Save changes';
$cancelUrl = 'item_details.php?id=' . $itemId;

$pageTitle = 'Edit listing';
$pageStyles = ['cropper.min.css'];
$pageScripts = ['cropper.min.js', 'image_manager.js'];
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <a class="back-link" href="item_details.php?id=<?= $itemId ?>"><?= icon('arrow-left') ?> Back to listing</a>
    <div class="page-header">
        <h1>Edit listing</h1>
    </div>
    <?php require APP_ROOT . '/templates/partials/item_form.php'; ?>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
