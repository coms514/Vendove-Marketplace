<?php

const UPLOAD_MAX_BYTES = 5 * 1024 * 1024;
const UPLOAD_MAX_PIXELS = 40_000_000;
const UPLOAD_ALLOWED_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

class UploadException extends RuntimeException {}

function store_image_bytes(string $bytes, string $folder): string
{
    if ($bytes === '') {
        throw new UploadException('The image is empty.');
    }

    if (strlen($bytes) > UPLOAD_MAX_BYTES) {
        throw new UploadException('Images must be 5 MB or smaller.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if (!isset(UPLOAD_ALLOWED_TYPES[$mime])) {
        throw new UploadException('Only JPG, PNG and WebP images are allowed.');
    }

    $size = @getimagesizefromstring($bytes);
    if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > UPLOAD_MAX_PIXELS) {
        throw new UploadException('That file is not a valid image.');
    }

    $image = @imagecreatefromstring($bytes);
    if ($image === false) {
        throw new UploadException('That image could not be read.');
    }

    $directory = upload_directory($folder);
    $extension = UPLOAD_ALLOWED_TYPES[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $target = $directory . '/' . $filename;

    $saved = match ($mime) {
        'image/png' => imagesavealpha($image, true) && imagepng($image, $target, 6),
        'image/webp' => imagewebp($image, $target, 85),
        default => imagejpeg($image, $target, 88),
    };
    imagedestroy($image);

    if (!$saved) {
        throw new UploadException('The image could not be saved.');
    }

    return 'uploads/' . $folder . '/' . $filename;
}

function store_data_url_image(string $dataUrl, string $folder): string
{
    if (!preg_match('#^data:image/(png|jpeg|webp);base64,([A-Za-z0-9+/=\r\n]+)$#', $dataUrl, $matches)) {
        throw new UploadException('The image data is not valid.');
    }

    $bytes = base64_decode($matches[2], true);
    if ($bytes === false) {
        throw new UploadException('The image data is not valid.');
    }

    return store_image_bytes($bytes, $folder);
}

function store_uploaded_image(array $file, string $folder): string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        throw new UploadException('Images must be 5 MB or smaller.');
    }

    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new UploadException('Upload failed. Try again.');
    }

    if (filesize($file['tmp_name']) > UPLOAD_MAX_BYTES) {
        throw new UploadException('Images must be 5 MB or smaller.');
    }

    return store_image_bytes((string) file_get_contents($file['tmp_name']), $folder);
}

function normalize_files_array(array $files): array
{
    if (!is_array($files['name'] ?? null)) {
        return [$files];
    }

    $normalized = [];
    foreach (array_keys($files['name']) as $index) {
        $normalized[] = [
            'name' => $files['name'][$index],
            'type' => $files['type'][$index],
            'tmp_name' => $files['tmp_name'][$index],
            'error' => $files['error'][$index],
            'size' => $files['size'][$index],
        ];
    }

    return $normalized;
}

function upload_directory(string $folder): string
{
    if (!preg_match('/^[a-z]+$/', $folder)) {
        throw new InvalidArgumentException("invalid upload folder: {$folder}");
    }

    $directory = APP_ROOT . '/uploads/' . $folder;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException("can't create upload folder: {$folder}");
    }

    return $directory;
}

function delete_upload(?string $path): void
{
    $path = ltrim(str_replace('\\', '/', (string) $path), '/');
    // older profile pictures were saved directly in uploads/
    if (!preg_match('#^uploads/[a-z]+/[A-Za-z0-9_.\- ]+$#', $path) && !preg_match('#^uploads/profile_\d+_\d+\.(png|jpe?g)$#', $path)) {
        return;
    }

    $full = APP_ROOT . '/' . $path;
    if (is_file($full)) {
        @unlink($full);
    }
}

function image_manager_state(?string $postedJson, array $existingById): array
{
    if ($postedJson === null) {
        return array_map(
            fn($id, $url) => ['id' => (int) $id, 'url' => image_url($url)],
            array_keys($existingById),
            $existingById,
        );
    }

    $state = [];
    foreach ((array) json_decode($postedJson, true) as $entry) {
        if (isset($entry['id'], $existingById[(int) $entry['id']])) {
            $state[] = ['id' => (int) $entry['id'], 'url' => image_url($existingById[(int) $entry['id']])];
        } elseif (isset($entry['data']) && is_string($entry['data']) && str_starts_with($entry['data'], 'data:image/')) {
            $state[] = ['data' => $entry['data']];
        }
    }

    return $state;
}

function process_image_list(string $json, array $existing, string $folder, int $max): array
{
    $entries = json_decode($json, true);
    $entries = is_array($entries) ? $entries : [];

    if (count($entries) > $max) {
        throw new UploadException("You can add up to {$max} images.");
    }

    $paths = [];
    $created = [];

    try {
        foreach ($entries as $entry) {
            if (isset($entry['id'], $existing[(int) $entry['id']])) {
                $paths[] = $existing[(int) $entry['id']];
            } elseif (isset($entry['data']) && is_string($entry['data'])) {
                $path = store_data_url_image($entry['data'], $folder);
                $created[] = $path;
                $paths[] = $path;
            }
        }
    } catch (Throwable $e) {
        array_map('delete_upload', $created);
        throw $e;
    }

    return [
        'paths' => $paths,
        'created' => $created,
        'removed' => array_values(array_diff(array_values($existing), $paths)),
    ];
}

// runs a db write and deletes the just-stored images if it fails, so uploads/ doesn't collect orphans
function with_upload_cleanup(array $createdPaths, callable $write): mixed
{
    try {
        return $write();
    } catch (Throwable $e) {
        array_map('delete_upload', $createdPaths);
        throw $e;
    }
}
