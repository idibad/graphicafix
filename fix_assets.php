<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
$extensions = ['php', 'html', 'js', 'css'];

foreach ($files as $file) {
    if ($file->isDir()) continue;
    
    $ext = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
    if (!in_array(strtolower($ext), $extensions)) continue;
    
    if (strpos($file->getPathname(), 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;
    if (strpos($file->getPathname(), '.git' . DIRECTORY_SEPARATOR) !== false) continue;
    if ($file->getFilename() === 'fix_assets.php') continue;

    $content = file_get_contents($file->getPathname());
    $original = $content;

    $content = str_replace("BASE_URL ?>css/", "BASE_URL ?>assets/css/", $content);
    $content = str_replace("BASE_URL ?>/css/", "BASE_URL ?>assets/css/", $content);
    
    $content = str_replace("BASE_URL ?>js/", "BASE_URL ?>assets/js/", $content);
    $content = str_replace("BASE_URL ?>/js/", "BASE_URL ?>assets/js/", $content);
    
    $content = str_replace("BASE_URL ?>images/", "BASE_URL ?>assets/images/", $content);
    $content = str_replace("BASE_URL ?>/images/", "BASE_URL ?>assets/images/", $content);
    
    $content = str_replace("BASE_URL ?>fonts/", "BASE_URL ?>assets/fonts/", $content);
    $content = str_replace("BASE_URL ?>/fonts/", "BASE_URL ?>assets/fonts/", $content);

    if ($content !== $original) {
        file_put_contents($file->getPathname(), $content);
        echo "Fixed assets in: " . $file->getPathname() . "\n";
    }
}
echo "Done!\n";
