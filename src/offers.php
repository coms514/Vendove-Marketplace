<?php

const OFFER_MIN_RATIO = 0.25;

function accepted_offer_for(int $itemId, int $buyerId): ?array
{
    return db_one(
        "SELECT * FROM offers WHERE item_id = ? AND user_id = ? AND status = 'accepted' ORDER BY id DESC LIMIT 1",
        [$itemId, $buyerId],
    );
}

function pending_offer_for(int $itemId, int $buyerId): ?array
{
    return db_one(
        "SELECT * FROM offers WHERE item_id = ? AND user_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1",
        [$itemId, $buyerId],
    );
}

function validate_offer(array $item, int $buyerId, ?float $amount): ?string
{
    if ((int) $item['user_id'] === $buyerId) {
        return 'You cannot make an offer on your own listing.';
    }
    if (!item_is_available($item)) {
        return 'This item is no longer available.';
    }
    if (pending_offer_for((int) $item['id'], $buyerId)) {
        return 'You already have an offer waiting for the seller.';
    }
    if (accepted_offer_for((int) $item['id'], $buyerId)) {
        return 'The seller already accepted your offer. You can check out now.';
    }

    $price = (float) $item['price_usd'];
    if ($amount === null || $amount <= 0) {
        return 'Please enter a valid amount.';
    }
    if ($amount < round($price * OFFER_MIN_RATIO, 2)) {
        return 'Your offer must be at least ' . money($price * OFFER_MIN_RATIO) . ' (25% of the price).';
    }
    if ($amount >= $price) {
        return 'Your offer is at or above the asking price. You can buy it right away instead.';
    }

    return null;
}

function create_offer(int $itemId, int $buyerId, float $amount): int
{
    return db_insert(
        "INSERT INTO offers (item_id, user_id, offer_amount, status) VALUES (?, ?, ?, 'pending')",
        [$itemId, $buyerId, $amount],
    );
}

function find_offer_for_seller(int $offerId, int $sellerId): ?array
{
    return db_one(
        'SELECT o.*, i.user_id AS seller_id, i.title, i.status AS item_status, i.active AS item_active
         FROM offers o
         JOIN items i ON i.id = o.item_id
         WHERE o.id = ? AND i.user_id = ?',
        [$offerId, $sellerId],
    );
}

function offers_received(int $sellerId): array
{
    return db_all(
        "SELECT o.id, o.offer_amount, o.created_at, i.id AS item_id, i.title, i.price_usd, i.primary_image,
                u.id AS buyer_id, u.username AS buyer_name, u.profile_picture AS buyer_picture
         FROM offers o
         JOIN items i ON i.id = o.item_id
         JOIN users u ON u.id = o.user_id
         WHERE i.user_id = ? AND o.status = 'pending' AND i.active = 1 AND i.status <> 'sold'
         ORDER BY o.created_at DESC",
        [$sellerId],
    );
}

function offers_made(int $buyerId): array
{
    return db_all(
        "SELECT o.id, o.offer_amount, o.status, o.created_at, i.id AS item_id, i.title, i.price_usd,
                i.primary_image, i.status AS item_status, i.active AS item_active,
                EXISTS (SELECT 1 FROM transactions t WHERE t.item_id = i.id AND t.buyer_id = o.user_id) AS purchased
         FROM offers o
         JOIN items i ON i.id = o.item_id
         WHERE o.user_id = ? AND o.created_at >= NOW() - INTERVAL 60 DAY
         ORDER BY FIELD(o.status, 'accepted', 'pending', 'rejected'), o.created_at DESC",
        [$buyerId],
    );
}

function notification_count(int $userId): int
{
    $received = (int) db_value(
        "SELECT COUNT(*) FROM offers o JOIN items i ON i.id = o.item_id
         WHERE i.user_id = ? AND o.status = 'pending' AND i.active = 1 AND i.status <> 'sold'",
        [$userId],
    );
    $accepted = (int) db_value(
        "SELECT COUNT(*) FROM offers o JOIN items i ON i.id = o.item_id
         WHERE o.user_id = ? AND o.status = 'accepted' AND i.active = 1 AND i.status <> 'sold'",
        [$userId],
    );

    return $received + $accepted;
}
