<?php

define('APP_ROOT', __DIR__);

require APP_ROOT . '/vendor/autoload.php';
require APP_ROOT . '/src/env.php';

load_env(APP_ROOT . '/.env');

require APP_ROOT . '/src/errors.php';
require APP_ROOT . '/src/db.php';
require APP_ROOT . '/src/helpers.php';
require APP_ROOT . '/src/session.php';
require APP_ROOT . '/src/auth.php';
require APP_ROOT . '/src/csrf.php';
require APP_ROOT . '/src/uploads.php';
require APP_ROOT . '/src/mailer.php';
require APP_ROOT . '/src/catalog.php';
require APP_ROOT . '/src/offers.php';
require APP_ROOT . '/src/conversations.php';
require APP_ROOT . '/src/message_crypto.php';
require APP_ROOT . '/src/payments.php';

date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));

register_error_handlers();
start_session();
