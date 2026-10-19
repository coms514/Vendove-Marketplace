<?php

const ITEM_MAX_IMAGES = 5;
const REQUEST_MAX_IMAGES = 5;
const ITEM_CONDITIONS = ['new' => 'New', 'used' => 'Used'];
const REQUEST_CONDITIONS = ['new' => 'New', 'used' => 'Used', 'any' => 'Any condition'];

function catalog_options(): array
{
    return [
        'categories' => db_all('SELECT id, name FROM categories ORDER BY name'),
        'brands' => db_all('SELECT id, name FROM brands ORDER BY name'),
        'sizes' => db_all('SELECT id, name FROM sizes ORDER BY id'),
    ];
}

function find_item(int $id): ?array
{
    return db_one(
        'SELECT i.*, u.username, u.profile_picture,
                c.name AS category_name, b.name AS brand_name, s.name AS size_name
         FROM items i
         JOIN users u ON u.id = i.user_id
         LEFT JOIN categories c ON c.id = i.category_id
         LEFT JOIN brands b ON b.id = i.brand_id
         LEFT JOIN sizes s ON s.id = i.size_id
         WHERE i.id = ? AND i.active = 1',
        [$id],
    );
}

function find_owned_item(int $id, int $userId): ?array
{
    return db_one('SELECT * FROM items WHERE id = ? AND user_id = ? AND active = 1', [$id, $userId]);
}

function item_images(int $itemId): array
{
    return db_all('SELECT id, image_url FROM item_images WHERE item_id = ? ORDER BY display_order, id', [$itemId]);
}

function item_is_available(array $item): bool
{
    return (int) $item['active'] === 1 && $item['status'] !== 'sold';
}

function validate_item_input(array $input): array
{
    $options = catalog_options();
    $listing = [
        'title' => input_string($input, 'title', 100),
        'description' => input_string($input, 'description', 5000),
        'price' => input_decimal($input, 'price'),
        'condition' => input_string($input, 'condition'),
        'category_id' => input_int($input, 'category_id') ?: null,
        'brand_id' => input_int($input, 'brand_id') ?: null,
        'size_id' => input_int($input, 'size_id') ?: null,
    ];

    $errors = [];
    if ($listing['title'] === '') {
        $errors[] = 'Please enter a title.';
    }
    if ($listing['description'] === '') {
        $errors[] = 'Please enter a description.';
    }
    if ($listing['price'] === null || $listing['price'] <= 0 || $listing['price'] > 99999999) {
        $errors[] = 'Please enter a valid price.';
    }
    if (!isset(ITEM_CONDITIONS[$listing['condition']])) {
        $errors[] = 'Please choose a condition.';
    }
    foreach (['category_id' => 'categories', 'brand_id' => 'brands', 'size_id' => 'sizes'] as $field => $list) {
        if ($listing[$field] !== null && !in_array($listing[$field], array_map('intval', array_column($options[$list], 'id')), true)) {
            $listing[$field] = null;
        }
    }

    return [$listing, $errors];
}

function create_item(int $userId, array $listing, array $imagePaths): int
{
    return db_transaction(function () use ($userId, $listing, $imagePaths) {
        $itemId = db_insert(
            'INSERT INTO items (user_id, title, description, slug, price, price_usd, `condition`, size,
                                category_id, brand_id, size_id, primary_image, active, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'\', ?, ?, ?, ?, 1, \'available\')',
            [
                $userId, $listing['title'], $listing['description'], slugify($listing['title']) . '-' . bin2hex(random_bytes(4)),
                $listing['price'], $listing['price'], $listing['condition'],
                $listing['category_id'], $listing['brand_id'], $listing['size_id'], $imagePaths[0] ?? null,
            ],
        );
        // the final slug needs the id, which only exists after the insert
        db_exec('UPDATE items SET slug = ? WHERE id = ?', [slugify($listing['title']) . '-' . $itemId, $itemId]);
        replace_item_images($itemId, $imagePaths);

        return $itemId;
    });
}

function update_item(int $itemId, array $listing, array $imagePaths): void
{
    db_transaction(function () use ($itemId, $listing, $imagePaths) {
        db_exec(
            'UPDATE items
             SET title = ?, description = ?, slug = ?, price = ?, price_usd = ?, `condition` = ?,
                 category_id = ?, brand_id = ?, size_id = ?, primary_image = ?
             WHERE id = ?',
            [
                $listing['title'], $listing['description'], slugify($listing['title']) . '-' . $itemId,
                $listing['price'], $listing['price'], $listing['condition'],
                $listing['category_id'], $listing['brand_id'], $listing['size_id'], $imagePaths[0] ?? null, $itemId,
            ],
        );
        replace_item_images($itemId, $imagePaths);
    });
}

function replace_item_images(int $itemId, array $paths): void
{
    db_exec('DELETE FROM item_images WHERE item_id = ?', [$itemId]);
    foreach (array_values($paths) as $order => $path) {
        db_exec('INSERT INTO item_images (item_id, image_url, display_order) VALUES (?, ?, ?)', [$itemId, $path, $order]);
    }
}

function search_items(array $filters, int $page, int $perPage = 12): array
{
    $where = ['i.active = 1', "i.status <> 'sold'"];
    $params = [];

    if ($filters['q'] !== '') {
        $where[] = '(i.title LIKE ? OR i.description LIKE ?)';
        $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
        array_push($params, $like, $like);
    }
    if (isset(ITEM_CONDITIONS[$filters['condition']])) {
        $where[] = 'i.`condition` = ?';
        $params[] = $filters['condition'];
    }
    foreach (['category_id', 'brand_id'] as $field) {
        if ($filters[$field] > 0) {
            $where[] = "i.{$field} = ?";
            $params[] = $filters[$field];
        }
    }
    if ($filters['min_price'] !== null) {
        $where[] = 'i.price_usd >= ?';
        $params[] = $filters['min_price'];
    }
    if ($filters['max_price'] !== null) {
        $where[] = 'i.price_usd <= ?';
        $params[] = $filters['max_price'];
    }

    $order = match ($filters['sort']) {
        'price_asc' => 'i.price_usd ASC, i.id DESC',
        'price_desc' => 'i.price_usd DESC, i.id DESC',
        'oldest' => 'i.created_at ASC, i.id ASC',
        default => 'i.created_at DESC, i.id DESC',
    };

    $whereSql = implode(' AND ', $where);
    $total = (int) db_value("SELECT COUNT(*) FROM items i WHERE {$whereSql}", $params);
    $pagination = paginate($total, $perPage, $page);

    $items = db_all(
        "SELECT i.id, i.title, i.price_usd, i.`condition`, i.primary_image, i.created_at
         FROM items i
         WHERE {$whereSql}
         ORDER BY {$order}
         LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
        $params,
    );

    return [$items, $pagination];
}

function find_request(int $id): ?array
{
    return db_one(
        'SELECT r.*, u.username, u.profile_picture
         FROM requests r
         JOIN users u ON u.id = r.user_id
         WHERE r.id = ? AND r.active = 1',
        [$id],
    );
}

function find_owned_request(int $id, int $userId): ?array
{
    return db_one('SELECT * FROM requests WHERE id = ? AND user_id = ? AND active = 1', [$id, $userId]);
}

function request_images(int $requestId): array
{
    return db_all('SELECT id, image_url FROM request_images WHERE request_id = ? ORDER BY display_order, id', [$requestId]);
}

function validate_request_input(array $input): array
{
    $buyRequest = [
        'title' => input_string($input, 'title', 100),
        'description' => input_string($input, 'description', 5000),
        'price_range' => preg_replace('/\s+/', '', input_string($input, 'price_range', 50)),
        'condition' => input_string($input, 'condition'),
    ];
    $buyRequest['price_range'] = str_replace('$', '', $buyRequest['price_range']);

    $errors = [];
    if ($buyRequest['title'] === '') {
        $errors[] = 'Please enter what you are looking for.';
    }
    if ($buyRequest['description'] === '') {
        $errors[] = 'Please add a short description.';
    }
    if (!preg_match('/^\d+(\.\d{1,2})?(-\d+(\.\d{1,2})?)?$/', $buyRequest['price_range'])) {
        $errors[] = 'Enter a budget like 50 or a range like 50-100.';
    } elseif (str_contains($buyRequest['price_range'], '-')) {
        [$low, $high] = array_map('floatval', explode('-', $buyRequest['price_range']));
        if ($low > $high) {
            $errors[] = 'The first number of the budget range must be the lowest.';
        }
    }
    if (!isset(REQUEST_CONDITIONS[$buyRequest['condition']])) {
        $errors[] = 'Please choose a condition.';
    }

    return [$buyRequest, $errors];
}

function create_request(int $userId, array $buyRequest, array $imagePaths): int
{
    return db_transaction(function () use ($userId, $buyRequest, $imagePaths) {
        $requestId = db_insert(
            'INSERT INTO requests (user_id, title, description, price_range, `condition`, image_url, active)
             VALUES (?, ?, ?, ?, ?, ?, 1)',
            [$userId, $buyRequest['title'], $buyRequest['description'], $buyRequest['price_range'], $buyRequest['condition'], $imagePaths[0] ?? null],
        );
        replace_request_images($requestId, $imagePaths);

        return $requestId;
    });
}

function update_request(int $requestId, array $buyRequest, array $imagePaths): void
{
    db_transaction(function () use ($requestId, $buyRequest, $imagePaths) {
        db_exec(
            'UPDATE requests SET title = ?, description = ?, price_range = ?, `condition` = ?, image_url = ? WHERE id = ?',
            [$buyRequest['title'], $buyRequest['description'], $buyRequest['price_range'], $buyRequest['condition'], $imagePaths[0] ?? null, $requestId],
        );
        replace_request_images($requestId, $imagePaths);
    });
}

function replace_request_images(int $requestId, array $paths): void
{
    db_exec('DELETE FROM request_images WHERE request_id = ?', [$requestId]);
    foreach (array_values($paths) as $order => $path) {
        db_exec('INSERT INTO request_images (request_id, image_url, display_order) VALUES (?, ?, ?)', [$requestId, $path, $order]);
    }
}

function search_requests(array $filters, int $page, int $perPage = 12): array
{
    $where = ['r.active = 1'];
    $params = [];

    if ($filters['q'] !== '') {
        $where[] = '(r.title LIKE ? OR r.description LIKE ?)';
        $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
        array_push($params, $like, $like);
    }
    if ($filters['condition'] === 'new' || $filters['condition'] === 'used') {
        $where[] = "(r.`condition` = ? OR r.`condition` = 'any')";
        $params[] = $filters['condition'];
    }

    $budget = 'CAST(SUBSTRING_INDEX(r.price_range, \'-\', 1) AS DECIMAL(12,2))';
    $order = match ($filters['sort']) {
        'price_asc' => "{$budget} ASC, r.id DESC",
        'price_desc' => "{$budget} DESC, r.id DESC",
        'oldest' => 'r.created_at ASC, r.id ASC',
        default => 'r.created_at DESC, r.id DESC',
    };

    $whereSql = implode(' AND ', $where);
    $total = (int) db_value("SELECT COUNT(*) FROM requests r WHERE {$whereSql}", $params);
    $pagination = paginate($total, $perPage, $page);

    $requests = db_all(
        "SELECT r.id, r.title, r.price_range, r.`condition`, r.image_url, r.created_at, u.username
         FROM requests r
         JOIN users u ON u.id = r.user_id
         WHERE {$whereSql}
         ORDER BY {$order}
         LIMIT {$pagination['per_page']} OFFSET {$pagination['offset']}",
        $params,
    );

    return [$requests, $pagination];
}
