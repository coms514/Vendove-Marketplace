<?php

function register_error_handlers(): void
{
    $debug = env_bool('APP_DEBUG');

    error_reporting(E_ALL);
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('log_errors', '1');
    ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

    set_exception_handler(function (Throwable $e) use ($debug): void {
        log_exception($e);

        if (headers_sent()) {
            echo $debug ? '<pre>' . e((string) $e) . '</pre>' : '';
            return;
        }

        if (is_api_request()) {
            json_response(null, 500, $debug ? $e->getMessage() : 'Something went wrong. Try again.');
        }

        http_response_code(500);
        render_error_page(500, 'Something went wrong', $debug ? $e->getMessage() : 'Try again in a moment.');
    });
}

function log_exception(Throwable $e): void
{
    error_log(sprintf(
        "[%s] %s: %s in %s:%d\n%s",
        date('c'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString(),
    ));
}

function is_api_request(): bool
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');

    return str_contains($script, '/api/') || str_ends_with($script, '/stripe_webhook.php');
}

function render_error_page(int $status, string $title, string $message): never
{
    http_response_code($status);
    $pageTitle = $title;
    require APP_ROOT . '/templates/header.php';
    require APP_ROOT . '/templates/partials/error.php';
    require APP_ROOT . '/templates/footer.php';
    exit;
}

function abort(int $status, ?string $message = null): never
{
    $titles = [
        400 => 'Bad request',
        403 => 'Not allowed',
        404 => 'Page not found',
        405 => 'Method not allowed',
        503 => 'Unavailable',
    ];

    $defaults = [
        400 => 'That request was not valid.',
        403 => "You can't do that.",
        404 => "We couldn't find that page.",
        405 => "That action isn't available here.",
        503 => 'This is unavailable right now.',
    ];

    $message ??= $defaults[$status] ?? 'Request failed.';

    if (is_api_request()) {
        json_error($message, $status);
    }

    render_error_page($status, $titles[$status] ?? 'Error', $message);
}
