<?php

const AVATAR_PLACEHOLDER = 'assets/img/avatar.svg';
const IMAGE_PLACEHOLDER = 'assets/img/placeholder.svg';

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_name(): string
{
    return env('APP_NAME', 'Vendove');
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function absolute_url(string $path = ''): string
{
    $base = env('APP_URL');

    if ($base === null) {
        $scheme = is_https() ? 'https' : 'http';
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if (str_ends_with($dir, '/api')) {
            $dir = substr($dir, 0, -4);
        }
        $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
    }

    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['SERVER_PORT'] ?? null) == 443
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function require_post(): void
{
    if (!is_post()) {
        abort(405);
    }

    verify_csrf();
}

function input_string(array $source, string $key, int $maxLength = 0): string
{
    $value = $source[$key] ?? '';
    $value = is_string($value) ? trim($value) : '';

    return $maxLength > 0 ? mb_substr($value, 0, $maxLength) : $value;
}

function input_int(array $source, string $key, int $default = 0): int
{
    $value = $source[$key] ?? null;

    return is_scalar($value) && preg_match('/^-?\d+$/', (string) $value) ? (int) $value : $default;
}

function input_choice(array $source, string $key, array $allowed): string
{
    $value = input_string($source, $key);

    return in_array($value, $allowed, true) ? $value : '';
}

function input_decimal(array $source, string $key): ?float
{
    $value = $source[$key] ?? null;
    if (!is_scalar($value) || !preg_match('/^\d+(\.\d{1,2})?$/', trim((string) $value))) {
        return null;
    }

    return (float) $value;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function json_response(mixed $payload = null, int $status = 200, ?string $error = null): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    echo json_encode([
        'success' => $status < 400 && $error === null,
        'data' => $payload,
        'error' => $error,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $message, int $status = 400): never
{
    json_response(null, $status, $message);
}

function money(mixed $amount): string
{
    return '$' . number_format((float) $amount, 2);
}

function format_price_range(?string $range): string
{
    if ($range === null || $range === '') {
        return 'Open budget';
    }

    $parts = explode('-', $range, 2);
    if (count($parts) === 2) {
        return money($parts[0]) . ' – ' . money($parts[1]);
    }

    return money($parts[0]);
}

function format_date(?string $value, string $format = 'M j, Y'): string
{
    return $value ? date($format, strtotime($value)) : '';
}

function iso_date(?string $value): string
{
    return $value ? date('c', strtotime($value)) : '';
}

function image_url(?string $path, string $fallback = IMAGE_PLACEHOLDER): string
{
    $path = ltrim(trim((string) $path), '/');

    return $path !== '' ? $path : $fallback;
}

function avatar_url(?string $path): string
{
    return image_url($path, AVATAR_PLACEHOLDER);
}

function safe_redirect_target(?string $target, string $default = 'index.php'): string
{
    $target = (string) $target;

    return preg_match('/^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$/', $target) ? $target : $default;
}

function current_query(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    $query = array_filter($query, fn($value) => $value !== '' && $value !== null);

    return http_build_query($query);
}

function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);

    return [
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'total' => $total,
    ];
}

function slugify(string $text): string
{
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $text), '-'));

    return $slug !== '' ? mb_substr($slug, 0, 200) : 'item';
}

function icon(string $name): string
{
    $paths = [
        'message' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
        'send' => '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
        'menu' => '<path d="M3 12h18M3 6h18M3 18h18"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'arrow-left' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'more' => '<circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/>',
        'x' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
    ];

    return '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
        . ($paths[$name] ?? '') . '</svg>';
}
