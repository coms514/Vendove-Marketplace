<?php

use Stripe\PaymentIntent;
use Stripe\StripeClient;

function payments_enabled(): bool
{
    return env('STRIPE_SECRET_KEY') !== null && env('STRIPE_PUBLISHABLE_KEY') !== null;
}

function stripe(): StripeClient
{
    static $client = null;

    return $client ??= new StripeClient(env('STRIPE_SECRET_KEY'));
}

function stripe_currency(): string
{
    return strtolower(env('STRIPE_CURRENCY', 'usd'));
}

function checkout_quote(array $item, int $buyerId): array
{
    $offer = accepted_offer_for((int) $item['id'], $buyerId);
    $amount = $offer ? (float) $offer['offer_amount'] : (float) $item['price_usd'];

    return [
        'amount' => $amount,
        'amount_cents' => (int) round($amount * 100),
        'offer_id' => $offer ? (int) $offer['id'] : null,
    ];
}

function checkout_payment_intent(array $item, int $buyerId, array $quote): PaymentIntent
{
    $sessionKey = 'checkout_' . $item['id'];
    $existingId = $_SESSION[$sessionKey] ?? null;

    // reuse the open intent so a refresh or a second tab doesn't create another one
    if ($existingId) {
        try {
            $intent = stripe()->paymentIntents->retrieve($existingId);
            $reusable = in_array($intent->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true);
            if ($reusable && $intent->amount === $quote['amount_cents'] && (int) $intent->metadata['buyer_id'] === $buyerId) {
                return $intent;
            }
            if ($reusable) {
                stripe()->paymentIntents->cancel($intent->id);
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            log_exception($e);
        }
    }

    $intent = stripe()->paymentIntents->create([
        'amount' => $quote['amount_cents'],
        'currency' => stripe_currency(),
        'automatic_payment_methods' => ['enabled' => true],
        'description' => mb_substr($item['title'], 0, 200),
        'metadata' => [
            'item_id' => (string) $item['id'],
            'buyer_id' => (string) $buyerId,
            'seller_id' => (string) $item['user_id'],
            'offer_id' => (string) ($quote['offer_id'] ?? ''),
        ],
    ]);

    $_SESSION[$sessionKey] = $intent->id;

    return $intent;
}

function finalize_purchase(PaymentIntent $intent): string
{
    if ($intent->status !== 'succeeded') {
        return $intent->status === 'processing' ? 'processing' : 'failed';
    }

    $itemId = (int) ($intent->metadata['item_id'] ?? 0);
    $buyerId = (int) ($intent->metadata['buyer_id'] ?? 0);
    $sellerId = (int) ($intent->metadata['seller_id'] ?? 0);

    if ($itemId === 0 || $buyerId === 0) {
        error_log("payment intent {$intent->id} missing item or buyer metadata");
        return 'failed';
    }

    $outcome = db_transaction(function () use ($intent, $itemId, $buyerId, $sellerId) {
        if (db_value('SELECT 1 FROM transactions WHERE payment_id = ?', [$intent->id])) {
            return 'completed';
        }

        // someone else bought it (or it changed hands) while this buyer was paying, so refund
        $item = db_one('SELECT id, user_id, status FROM items WHERE id = ? FOR UPDATE', [$itemId]);
        if ($item === null || $item['status'] === 'sold' || (int) $item['user_id'] !== $sellerId) {
            return 'refund';
        }

        db_exec(
            'INSERT INTO transactions (buyer_id, seller_id, item_id, payment_id, transaction_date) VALUES (?, ?, ?, ?, NOW())',
            [$buyerId, $sellerId, $itemId, $intent->id],
        );
        db_exec("UPDATE items SET status = 'sold' WHERE id = ?", [$itemId]);
        db_exec(
            "UPDATE offers SET status = 'rejected' WHERE item_id = ? AND status = 'pending'",
            [$itemId],
        );

        return 'completed';
    });

    if ($outcome === 'refund') {
        try {
            stripe()->refunds->create(['payment_intent' => $intent->id]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            log_exception($e);
        }

        return 'refunded';
    }

    unset($_SESSION['checkout_' . $itemId]);

    return $outcome;
}
