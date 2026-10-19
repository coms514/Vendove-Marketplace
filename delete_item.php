<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
require_post();

$item = find_owned_item(input_int($_POST, 'id'), $userId);
if ($item === null) {
    abort(404, 'Listing not found.');
}

db_transaction(function () use ($item) {
    db_exec('UPDATE items SET active = 0 WHERE id = ?', [$item['id']]);
    db_exec("UPDATE offers SET status = 'rejected' WHERE item_id = ? AND status IN ('pending', 'accepted')", [$item['id']]);
});

flash('success', 'Your listing was deleted.');
redirect('transactions.php');
