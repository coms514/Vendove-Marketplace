<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
require_post();

$offer = find_offer_for_seller(input_int($_POST, 'offer_id'), $userId);
$action = input_string($_POST, 'action');

if ($offer === null) {
    abort(404, 'Offer not found.');
}

if ($offer['status'] !== 'pending') {
    flash('info', 'You already answered this offer.');
    redirect('notifications.php');
}

if (!(int) $offer['item_active'] || $offer['item_status'] === 'sold') {
    flash('error', 'This item is no longer available.');
    redirect('notifications.php');
}

if ($action === 'accept') {
    db_exec("UPDATE offers SET status = 'accepted' WHERE id = ? AND status = 'pending'", [$offer['id']]);
    flash('success', 'Offer accepted. The buyer can now check out at ' . money($offer['offer_amount']) . '.');
} elseif ($action === 'decline') {
    db_exec("UPDATE offers SET status = 'rejected' WHERE id = ? AND status = 'pending'", [$offer['id']]);
    flash('success', 'Offer declined.');
} else {
    abort(400);
}

redirect('notifications.php');
