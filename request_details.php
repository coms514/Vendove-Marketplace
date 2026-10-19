<?php

require __DIR__ . '/bootstrap.php';

$request = find_request(input_int($_GET, 'id'));
if ($request === null) {
    abort(404, 'Request not found.');
}

$userId = current_user_id();
$isOwner = $userId === (int) $request['user_id'];
$images = array_column(request_images((int) $request['id']), 'image_url');
if (!$images) {
    $images = [$request['image_url']];
}

$pageTitle = $request['title'];
require APP_ROOT . '/templates/header.php';
?>
<section class="container section">
    <a class="back-link" href="requests.php"><?= icon('arrow-left') ?> Back to requests</a>

    <div class="detail">
        <div class="gallery" data-gallery>
            <div class="gallery__main">
                <img src="<?= e(image_url($images[0])) ?>" alt="<?= e($request['title']) ?>" data-gallery-main>
            </div>
            <?php if (count($images) > 1): ?>
                <div class="gallery__thumbs">
                    <?php foreach ($images as $index => $image): ?>
                        <button type="button" class="gallery__thumb<?= $index === 0 ? ' is-active' : '' ?>" data-gallery-thumb="<?= e(image_url($image)) ?>">
                            <img src="<?= e(image_url($image)) ?>" alt="Photo <?= $index + 1 ?>" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="detail__info card">
            <div class="detail__header">
                <span class="tag tag--wanted">Wanted</span>
                <h1><?= e($request['title']) ?></h1>
                <div class="detail__price">
                    <span class="price price--lg price--budget"><?= e(format_price_range($request['price_range'])) ?></span>
                    <span class="muted">budget</span>
                </div>
            </div>

            <dl class="specs">
                <div><dt>Condition</dt><dd><?= e(REQUEST_CONDITIONS[$request['condition']] ?? 'Any condition') ?></dd></div>
                <div><dt>Posted</dt><dd><?= e(format_date($request['created_at'])) ?></dd></div>
            </dl>

            <div class="prose"><?= nl2br(e($request['description'])) ?></div>

            <div class="actions">
                <?php if ($isOwner): ?>
                    <a class="btn btn--secondary" href="edit_request.php?id=<?= (int) $request['id'] ?>">Edit request</a>
                    <form action="delete_request.php" method="post" data-confirm="Delete this request? Sellers will no longer see it." data-confirm-title="Delete request">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $request['id'] ?>">
                        <button class="btn btn--danger" type="submit">Delete</button>
                    </form>
                <?php elseif ($userId): ?>
                    <form action="start_conversation.php" method="post" class="btn--grow">
                        <?= csrf_field() ?>
                        <input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>">
                        <button class="btn btn--primary btn--block" type="submit"><?= icon('message') ?> I have this item</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn--primary btn--grow" href="login.php?next=<?= e(urlencode('request_details.php?id=' . (int) $request['id'])) ?>"><?= icon('message') ?> I have this item</a>
                <?php endif; ?>
            </div>

            <a class="seller" href="public_profile.php?user_id=<?= (int) $request['user_id'] ?>">
                <img class="avatar" src="<?= e(avatar_url($request['profile_picture'])) ?>" alt="">
                <span>
                    <strong><?= e($request['username']) ?></strong>
                    <span class="muted">is looking for this</span>
                </span>
            </a>
        </div>
    </div>
</section>
<?php require APP_ROOT . '/templates/footer.php'; ?>
