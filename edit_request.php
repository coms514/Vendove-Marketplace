<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
$request = find_owned_request(input_int($_GET, 'id'), $userId);
if ($request === null) {
    abort(404, 'Request not found.');
}

$requestId = (int) $request['id'];
$existingById = array_column(request_images($requestId), 'image_url', 'id');
$errors = [];
$form = [
    'title' => $request['title'],
    'description' => (string) $request['description'],
    'price_range' => (string) $request['price_range'],
    'condition' => (string) $request['condition'],
];

if (is_post()) {
    verify_csrf();
    [$buyRequest, $errors] = validate_request_input($_POST);
    $form = array_merge($form, array_intersect_key($_POST, $form));

    if (!$errors) {
        try {
            $images = process_image_list((string) ($_POST['images'] ?? ''), $existingById, 'requests', REQUEST_MAX_IMAGES);
            with_upload_cleanup($images['created'], fn() => update_request($requestId, $buyRequest, $images['paths']));
            array_map('delete_upload', $images['removed']);
            flash('success', 'Your request was updated.');
            redirect('request_details.php?id=' . $requestId);
        } catch (UploadException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$imageExisting = image_manager_state(is_post() ? (string) ($_POST['images'] ?? '') : null, $existingById);
$submitLabel = 'Save changes';
$cancelUrl = 'request_details.php?id=' . $requestId;

$pageTitle = 'Edit request';
$pageStyles = ['cropper.min.css'];
$pageScripts = ['cropper.min.js', 'image_manager.js'];
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <a class="back-link" href="request_details.php?id=<?= $requestId ?>"><?= icon('arrow-left') ?> Back to request</a>
    <div class="page-header page-header--center">
        <h1>Edit request</h1>
    </div>
    <?php require APP_ROOT . '/templates/partials/request_form.php'; ?>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
