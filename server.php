<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 * Emulate Apache/Nginx mod_rewrite for PHP CLI Built-in Web Server.
 */
$publicPath = __DIR__.'/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// If file exists in public/ (e.g. static assets, images, vite bundles), serve it directly
if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
