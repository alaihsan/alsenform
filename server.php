<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 * Emulate Apache/Nginx mod_rewrite for PHP CLI Built-in Web Server.
 *
 * Static files are answered here without booting Laravel and are tuned for slow,
 * congested school Wi-Fi: pre-compressed gzip / brotli variants, conditional
 * requests (304 Not Modified) and byte ranges so videos also play on iOS.
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

/**
 * Determine whether the client accepts the given content encoding.
 */
$acceptsEncoding = static function (string $encoding): bool {
    foreach (explode(',', strtolower($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')) as $part) {
        $segments = array_map('trim', explode(';', $part));
        if ($segments[0] !== $encoding) {
            continue;
        }

        foreach (array_slice($segments, 1) as $parameter) {
            if (str_starts_with($parameter, 'q=') && (float) substr($parameter, 2) <= 0) {
                return false;
            }
        }

        return true;
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
        'txt' => 'text/plain; charset=utf-8',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf',
        'gz' => 'application/gzip',
        'br' => 'application/octet-stream',
    ];

    $contentType = $mimeTypes[$extension] ?? (mime_content_type($filePath) ?: 'application/octet-stream');
    $modifiedAt = (int) filemtime($filePath);
    $size = (int) filesize($filePath);
    $isCompressible = in_array($extension, ['js', 'mjs', 'css', 'svg', 'json', 'map', 'txt', 'ttf', 'eot'], true);

    // Prefer a pre-compressed variant produced by `npm run build`, otherwise gzip on the fly.
    $body = null;
    $servedPath = $filePath;
    $contentEncoding = null;

    if ($isCompressible) {
        // Only build output has trusted pre-compressed siblings written by `npm run build`.
        foreach (str_starts_with($uri, '/build/') ? ['br' => '.br', 'gzip' => '.gz'] : [] as $encoding => $suffix) {
            $variant = $filePath.$suffix;
            if ($acceptsEncoding($encoding) && is_file($variant) && filemtime($variant) >= $modifiedAt) {
                $servedPath = $variant;
                $contentEncoding = $encoding;
                break;
            }
        }

        if ($contentEncoding === null && $size >= 1024 && $size <= 8 * 1024 * 1024 && $acceptsEncoding('gzip') && function_exists('gzencode')) {
            $compressed = gzencode((string) file_get_contents($filePath), 6);
            if ($compressed !== false) {
                $body = $compressed;
                $contentEncoding = 'gzip';
            }
        }
    }

    $etag = sprintf('"%x-%x%s"', $modifiedAt, $size, $contentEncoding ? '-'.$contentEncoding : '');

    header("Content-Type: {$contentType}");
    header('X-Content-Type-Options: nosniff');
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $modifiedAt).' GMT');

    // Uploaded files (question media, avatars) must never run scripts when opened directly.
    if (str_starts_with($uri, '/storage/')) {
        header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; media-src 'self'; style-src 'unsafe-inline'; sandbox");
    }
    header("ETag: {$etag}");

    // Cache immutable build assets for 1 year, others for 7 days
    if (str_starts_with($uri, '/build/') || str_starts_with($uri, '/fonts/')) {
        header('Cache-Control: public, max-age=31536000, immutable');
    } else {
        header('Cache-Control: public, max-age=604800');
    }

    if ($isCompressible) {
        header('Vary: Accept-Encoding');
    }

    $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
    $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
    $notModified = $ifNoneMatch !== null
        ? in_array($etag, array_map('trim', explode(',', $ifNoneMatch)), true) || trim($ifNoneMatch) === '*'
        : ($ifModifiedSince !== null && ($since = strtotime($ifModifiedSince)) !== false && $since >= $modifiedAt);

    if ($notModified) {
        http_response_code(304);
        exit;
    }

    if ($contentEncoding !== null) {
        header("Content-Encoding: {$contentEncoding}");
        header('Content-Length: '.(string) ($body !== null ? strlen($body) : filesize($servedPath)));

        if ($body !== null) {
            echo $body;
        } else {
            readfile($servedPath);
        }

        exit;
    }

    header('Accept-Ranges: bytes');

    // Byte ranges: required by Safari / iOS to play <video> and lets interrupted downloads resume.
    $range = $_SERVER['HTTP_RANGE'] ?? null;
    $ifRange = $_SERVER['HTTP_IF_RANGE'] ?? null;
    $rangeIsValidForThisFile = $ifRange === null
        || trim($ifRange) === $etag
        || strtotime($ifRange) === $modifiedAt;

    if ($range !== null && $rangeIsValidForThisFile && $size > 0 && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range), $matches) && ($matches[1] !== '' || $matches[2] !== '')) {
        if ($matches[1] === '') {
            $start = max(0, $size - (int) $matches[2]);
            $end = $size - 1;
        } else {
            $start = (int) $matches[1];
            $end = $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1);
        }

        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */{$size}");
            header('Content-Length: 0');
            exit;
        }

        $length = $end - $start + 1;
        http_response_code(206);
        header("Content-Range: bytes {$start}-{$end}/{$size}");
        header('Content-Length: '.(string) $length);

        $handle = fopen($filePath, 'rb');
        if ($handle !== false) {
            fseek($handle, $start);
            while ($length > 0 && ! feof($handle) && connection_status() === CONNECTION_NORMAL) {
                $chunk = fread($handle, (int) min(256 * 1024, $length));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                flush();
                $length -= strlen($chunk);
            }
            fclose($handle);
        }

        exit;
    }

    header('Content-Length: '.(string) $size);
    readfile($filePath);
    exit;
}

require_once $publicPath.'/index.php';
