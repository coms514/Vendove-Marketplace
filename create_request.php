<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
$errors = [];
$form = ['title' => '', 'description' => '', 'price_range' => '', 'condition' => ''];

if (is_post()) {
    verify_csrf();
    [$buyRequest, $errors] = validate_request_input($_POST);
    $form = array_merge($form, array_intersect_key($_POST, $form));

    if (!$errors) {
        try {
            $images = process_image_list((string) ($_POST['images'] ?? ''), [], 'requests', REQUEST_MAX_IMAGES);
            $requestId = with_upload_cleanup($images['created'], fn() => create_request($userId, $buyRequest, $images['paths']));
            flash('success', 'Your request is posted. Sellers can now message you.');
            redirect('request_details.php?id=' . $requestId);
        } catch (UploadException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$imageExisting = image_manager_state(is_post() ? (string) ($_POST['images'] ?? '') : null, []);
$submitLabel = 'Post request';
$cancelUrl = 'requests.php';

$pageTitle = 'Ask to buy';
$pageStyles = ['cropper.min.css'];
$pageScripts = ['cropper.min.js', 'image_manager.js'];
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <div class="page-header page-header--center">
        <div>
            <h1>Ask to buy</h1>
            <p class="muted">Describe what you want and sellers who have it will reach out.</p>
        </div>
    </div>
    <?php require APP_ROOT . '/templates/partials/request_form.php'; ?>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
