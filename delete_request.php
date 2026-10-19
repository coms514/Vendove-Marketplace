<?php

require __DIR__ . '/bootstrap.php';

$userId = require_login();
require_post();

$request = find_owned_request(input_int($_POST, 'id'), $userId);
if ($request === null) {
    abort(404, 'Request not found.');
}

db_exec('UPDATE requests SET active = 0 WHERE id = ?', [$request['id']]);

flash('success', 'Your request was deleted.');
redirect('transactions.php');
