<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
require_post();

$itemId = input_int($_POST, 'item_id');
$requestId = input_int($_POST, 'request_id');
$back = $itemId ? 'item_details.php?id=' . $itemId : ($requestId ? 'request_details.php?id=' . $requestId : 'index.php');

try {
    $conversationId = $itemId
        ? open_conversation($userId, 'item', $itemId)
        : open_conversation($userId, 'request', $requestId);
} catch (DomainException $e) {
    flash('error', $e->getMessage());
    redirect($back);
}

redirect('messages.php?id=' . $conversationId);
