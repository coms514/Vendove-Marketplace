<?php

require __DIR__ . '/bootstrap.php';

$secret = env('STRIPE_WEBHOOK_SECRET');
if ($secret === null || !payments_enabled()) {
    json_error('Webhook not configured.', 404);
}

try {
    $event = \Stripe\Webhook::constructEvent(
        (string) file_get_contents('php://input'),
        $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '',
        $secret,
    );
} catch (UnexpectedValueException | \Stripe\Exception\SignatureVerificationException $e) {
    json_error('Invalid webhook signature.', 400);
}

// backup for buyers who close the tab before checkout_complete.php runs
if ($event->type === 'payment_intent.succeeded') {
    finalize_purchase($event->data->object);
}

json_response(['received' => true]);
