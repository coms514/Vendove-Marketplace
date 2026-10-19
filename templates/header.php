<?php

$layoutUser = null;
$unreadMessages = 0;
$notificationTotal = 0;

// the error page renders this header too, so a db failure here must not throw again
try {
    $layoutUser = current_user();
    if ($layoutUser) {
        $unreadMessages = unread_message_count((int) $layoutUser['id']);
        $notificationTotal = notification_count((int) $layoutUser['id']);
    }
} catch (Throwable $layoutError) {
    log_exception($layoutError);
}

$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$navLinks = [
    'listings.php' => ['Browse', ['listings.php', 'item_details.php', 'make_offer.php', 'checkout.php']],
    'requests.php' => ['Requests', ['requests.php', 'request_details.php']],
    'create_item.php' => ['Sell', ['create_item.php', 'edit_item.php']],
    'create_request.php' => ['Ask to buy', ['create_request.php', 'edit_request.php']],
];
$fullTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' · ' . app_name() : app_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($fullTitle) ?></title>
    <link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
    <?php foreach ($pageStyles ?? [] as $stylesheet): ?>
        <link rel="stylesheet" href="assets/css/<?= e($stylesheet) ?>">
    <?php endforeach; ?>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="brand" href="index.php"><?= e(app_name()) ?></a>

        <button class="nav-toggle" type="button" aria-controls="site-nav" aria-expanded="false" data-nav-toggle>
            <?= icon('menu') ?><span class="visually-hidden">Menu</span>
        </button>

        <nav class="site-nav" id="site-nav" data-nav>
            <ul class="site-nav__links">
                <?php foreach ($navLinks as $href => [$label, $pages]): ?>
                    <li><a href="<?= e($href) ?>" <?= in_array($currentPage, $pages, true) ? 'aria-current="page"' : '' ?>><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>

            <div class="site-nav__user">
                <?php if ($layoutUser): ?>
                    <a class="nav-icon" href="messages.php" <?= $currentPage === 'messages.php' ? 'aria-current="page"' : '' ?>>
                        <?= icon('message') ?><span>Messages</span>
                        <span class="badge" data-badge="messages" <?= $unreadMessages ? '' : 'hidden' ?>><?= $unreadMessages ?></span>
                    </a>
                    <a class="nav-icon" href="notifications.php" <?= $currentPage === 'notifications.php' ? 'aria-current="page"' : '' ?>>
                        <?= icon('bell') ?><span>Notifications</span>
                        <span class="badge" data-badge="notifications" <?= $notificationTotal ? '' : 'hidden' ?>><?= $notificationTotal ?></span>
                    </a>
                    <details class="user-menu">
                        <summary>
                            <img class="avatar avatar--sm" src="<?= e(avatar_url($layoutUser['profile_picture'])) ?>" alt="">
                            <span class="user-menu__name"><?= e($layoutUser['username']) ?></span>
                        </summary>
                        <div class="user-menu__panel">
                            <a href="account.php">My account</a>
                            <a href="transactions.php">My activity</a>
                            <a href="public_profile.php?user_id=<?= (int) $layoutUser['id'] ?>">Public profile</a>
                            <form action="logout.php" method="post">
                                <?= csrf_field() ?>
                                <button type="submit">Log out</button>
                            </form>
                        </div>
                    </details>
                <?php else: ?>
                    <a class="btn btn--ghost btn--sm" href="login.php">Log in</a>
                    <a class="btn btn--primary btn--sm" href="register.php">Sign up</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<main id="main" class="site-main">
    <?php $flashes = take_flashes(); ?>
    <?php if ($flashes): ?>
        <div class="container flash-stack">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert--<?= e($flash['type']) ?>" role="status" data-dismissible>
                    <span><?= e($flash['message']) ?></span>
                    <button type="button" class="alert__close" aria-label="Dismiss" data-dismiss><?= icon('x') ?></button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
