<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
$extensions = ['php', 'html', 'js', 'css'];

foreach ($files as $file) {
    if ($file->isDir()) continue;
    
    $ext = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
    if (!in_array(strtolower($ext), $extensions)) continue;
    
    // Don't modify vendor, git, or the refactor script itself
    if (strpos($file->getPathname(), 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;
    if (strpos($file->getPathname(), '.git' . DIRECTORY_SEPARATOR) !== false) continue;
    if ($file->getFilename() === 'refactor.php') continue;

    $content = file_get_contents($file->getPathname());
    $original = $content;

    // 1. Templates Replacement
    $content = preg_replace("/(include|require|include_once|require_once)\s*[\(]?\s*['\"](header\.php)['\"]\s*[\)]?/", "$1 'templates/$2'", $content);
    $content = preg_replace("/(include|require|include_once|require_once)\s*[\(]?\s*['\"](footer\.php)['\"]\s*[\)]?/", "$1 'templates/$2'", $content);
    $content = preg_replace("/(include|require|include_once|require_once)\s*[\(]?\s*['\"](dashboard_header\.php)['\"]\s*[\)]?/", "$1 'templates/$2'", $content);
    $content = preg_replace("/(include|require|include_once|require_once)\s*[\(]?\s*['\"](default\.php)['\"]\s*[\)]?/", "$1 'templates/$2'", $content);

    // 2. Core Replacement
    $content = preg_replace("/(include|require|include_once|require_once)\s*[\(]?\s*['\"](config\.php)['\"]\s*[\)]?/", "$1 'core/$2'", $content);
    $content = preg_replace("/(include|require|include_once|require_once)\s*[\(]?\s*['\"]\.\.\/(config\.php)['\"]\s*[\)]?/", "$1 '../core/$2'", $content);
    
    $content = preg_replace("/(include|require|include_once|require_once)\s*[\(]?\s*['\"](functions\.php)['\"]\s*[\)]?/", "$1 'core/$2'", $content);
    $content = preg_replace("/(include|require|include_once|require_once)\s*[\(]?\s*['\"]\.\.\/(functions\.php)['\"]\s*[\)]?/", "$1 '../core/$2'", $content);

    // 3. Assets Replacement (very careful with quotes to not ruin existing http links)
    // Matches href="css/...", src="js/...", src="images/...", url('images/...')
    $content = preg_replace("/(['\"])\/?css\//i", "$1assets/css/", $content);
    $content = preg_replace("/(['\"])\/?js\//i", "$1assets/js/", $content);
    $content = preg_replace("/(['\"])\/?images\//i", "$1assets/images/", $content);
    $content = preg_replace("/(['\"])\/?fonts\//i", "$1assets/fonts/", $content);
    $content = preg_replace("/url\(\s*['\"]?images\//i", "url('assets/images/", $content);
    $content = preg_replace("/url\(\s*['\"]?fonts\//i", "url('assets/fonts/", $content);

    // Edge case: if we accidentally double-replaced assets/assets/
    $content = str_replace("assets/assets/", "assets/", $content);

    // 4. API / Callbacks Replacement in JS/URLs
    $content = str_replace("/safepay_callback.php", "/core/safepay_callback.php", $content);
    $content = str_replace("/jazzcash_callback.php", "/core/jazzcash_callback.php", $content);
    $content = str_replace("'check_discount.php'", "'api/check_discount.php'", $content);
    $content = str_replace("\"check_discount.php\"", "\"api/check_discount.php\"", $content);
    $content = str_replace("'get_csrf_token.php'", "'api/get_csrf_token.php'", $content);
    $content = str_replace("\"get_csrf_token.php\"", "\"api/get_csrf_token.php\"", $content);
    $content = str_replace("'subscribe_handler.php'", "'api/subscribe_handler.php'", $content);
    $content = str_replace("\"subscribe_handler.php\"", "\"api/subscribe_handler.php\"", $content);

    if ($content !== $original) {
        file_put_contents($file->getPathname(), $content);
        echo "Updated: " . $file->getPathname() . "\n";
    }
}
echo "Done!\n";
