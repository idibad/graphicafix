<?php
$dirs = ['admin', 'portal'];

foreach ($dirs as $dir) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/' . $dir));
    foreach ($files as $file) {
        if ($file->isDir() || pathinfo($file->getFilename(), PATHINFO_EXTENSION) !== 'php') continue;
        
        $content = file_get_contents($file->getPathname());
        $original = $content;
        
        $content = str_replace("include 'templates/dashboard_header.php'", "include 'dashboard_header.php'", $content);
        $content = str_replace("include_once 'templates/dashboard_header.php'", "include_once 'dashboard_header.php'", $content);
        $content = str_replace("require 'templates/dashboard_header.php'", "require 'dashboard_header.php'", $content);
        $content = str_replace("require_once 'templates/dashboard_header.php'", "require_once 'dashboard_header.php'", $content);
        
        if ($content !== $original) {
            file_put_contents($file->getPathname(), $content);
            echo "Fixed: " . $file->getPathname() . "\n";
        }
    }
}
echo "Fix complete.\n";
