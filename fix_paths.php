<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__));
$extensions = ['php'];

foreach ($files as $file) {
    if ($file->isDir()) continue;
    
    $ext = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
    if (!in_array(strtolower($ext), $extensions)) continue;
    
    if (strpos($file->getPathname(), 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;
    if (strpos($file->getPathname(), '.git' . DIRECTORY_SEPARATOR) !== false) continue;
    if ($file->getFilename() === 'fix_paths.php') continue;

    $content = file_get_contents($file->getPathname());
    $original = $content;

    $content = str_replace(
        "\$_SERVER['DOCUMENT_ROOT'] . '/graphicafix/config.php'",
        "\$_SERVER['DOCUMENT_ROOT'] . '/graphicafix/core/config.php'",
        $content
    );
    
    $content = str_replace(
        "\$_SERVER['DOCUMENT_ROOT'] . '/graphicafix/functions.php'",
        "\$_SERVER['DOCUMENT_ROOT'] . '/graphicafix/core/functions.php'",
        $content
    );

    if ($content !== $original) {
        file_put_contents($file->getPathname(), $content);
        echo "Fixed DOCUMENT_ROOT path in: " . $file->getPathname() . "\n";
    }
}
echo "Done!\n";
