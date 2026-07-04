<?php
/**
 * Router for PHP Built-in Development Server
 * Mimics .htaccess URL rewriting (removes .php extension)
 * Usage: php -S localhost:8000 router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Serve static files (css, js, images, fonts, etc.) directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // Let PHP serve static assets as-is
}

// Try appending .php and serving that file
$phpFile = __DIR__ . rtrim($uri, '/') . '.php';
if (file_exists($phpFile)) {
    require $phpFile;
    return true;
}

// Try index.php inside a directory
$indexFile = __DIR__ . rtrim($uri, '/') . '/index.php';
if (file_exists($indexFile)) {
    require $indexFile;
    return true;
}

// 404 fallback
if (file_exists(__DIR__ . '/404.php')) {
    http_response_code(404);
    require __DIR__ . '/404.php';
} else {
    http_response_code(404);
    echo "<h1>404 Not Found</h1>";
}
return true;
