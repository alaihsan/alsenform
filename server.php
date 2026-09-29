<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 * Emulate Apache/Nginx mod_rewrite for PHP CLI Built-in Web Server.
 */
$publicPath = realpath(__DIR__.'/public');
$publicStoragePath = realpath(__DIR__.'/storage/app/public');

$uri = rawurldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

$filePath = realpath($publicPath.'/'.ltrim($uri, '/'));

/**
 * Determine whether a resolved file remains inside an explicitly public directory.
 */
$isPublicFile = static function (string $path) use ($publicPath, $publicStoragePath): bool {
    foreach (array_filter([$publicPath, $publicStoragePath]) as $allowedPath) {
        if ($path === $allowedPath || str_starts_with($path, $allowedPath.DIRECTORY_SEPARATOR)) {
            return true;
        }
    }

    return false;
};

// If the requested URI exists as a static file in public/, stream it directly with proper MIME type
if ($uri !== '/' && $filePath !== false && is_file($filePath) && $isPublicFile($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    $mimeTypes = [
        'js' => 'application/javascript; charset=utf-8',
        'mjs' => 'application/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'json' => 'application/json',
        'map' => 'application/json',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf',
    ];

    $contentType = $mimeTypes[$extension] ?? (mime_content_type($filePath) ?: 'application/octet-stream');

    header("Content-Type: {$contentType}");
    header('Content-Length: '.(string) filesize($filePath));

    // Cache immutable build assets for 1 year, others for 7 days
    if (str_starts_with($uri, '/build/') || str_starts_with($uri, '/fonts/')) {
        header('Cache-Control: public, max-age=31536000, immutable');
    } else {
        header('Cache-Control: public, max-age=604800');
    }

    readfile($filePath);
    exit;
}

require_once $publicPath.'/index.php';
