<?php

require __DIR__ . '/bootstrap.php';

require_post();

destroy_session();
start_session();
session_regenerate_id(true);

flash('success', 'You have been logged out.');
redirect('login.php');
