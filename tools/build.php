<?php
/** Build one Joomla package ZIP with production dependencies. @license GPL-3.0-or-later */
$root = dirname(__DIR__);
if (!class_exists(ZipArchive::class) || !is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "Run composer install --no-dev first; ext-zip is required.\n");
    exit(1);
}
$dist = $argv[1] ?? $root . '/dist';
if (!is_dir($dist) && !mkdir($dist, 0775, true)) {
    throw new RuntimeException('Cannot create output directory');
}
function addTree(ZipArchive $zip, string $source, string $prefix = ''): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->isFile()) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
            $zip->addFile($file->getPathname(), $prefix . $relative);
        }
    }
}
$children = [];
foreach (['system/attachmentscontentguard', 'finder/attachmentscontent'] as $child) {
    [$group, $name] = explode('/', $child);
    $path = $dist . '/plg_' . $group . '_' . $name . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Cannot create plugin archive');
    }
    addTree($zip, $root . '/plugins/' . $child);
    $zip->addFile($root . '/LICENSE', 'LICENSE');
    if ($group === 'finder') {
        addTree($zip, $root . '/vendor', 'vendor/');
    }
    $zip->close();
    $children[] = $path;
}
$output = $dist . '/pkg_attachmentscontentsearch-0.1.1.zip';
$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Cannot create package archive');
}
$zip->addFile($root . '/package/pkg_attachmentscontentsearch.xml', 'pkg_attachmentscontentsearch.xml');
$zip->addFile($root . '/package/script.php', 'script.php');
$zip->addFile($root . '/README.md', 'README.md');
$zip->addFile($root . '/LICENSE', 'LICENSE');
foreach ($children as $child) {
    $zip->addFile($child, 'packages/' . basename($child));
}
$zip->close();
echo $output . "\n";
