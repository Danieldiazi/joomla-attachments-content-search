<?php
/** Build a Joomla package; optional release tag controls every manifest version. @license GPL-3.0-or-later */
$root = dirname(__DIR__);
if (!class_exists(ZipArchive::class) || !is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "Run composer install --no-dev first; ext-zip is required.\n");
    exit(1);
}
$packageSource = $root . '/package/pkg_attachmentscontentsearch.xml';
$defaultVersion = (string) simplexml_load_file($packageSource)->version;
$version = $defaultVersion;
$dist = $root . '/dist';
foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--version=')) {
        $version = preg_replace('/^v/', '', substr($argument, 10));
    } elseif (str_starts_with($argument, '--output-dir=')) {
        $dist = substr($argument, 13);
    } elseif (!str_starts_with($argument, '--')) {
        $dist = $argument; // Retain the original positional output directory.
    } else {
        throw new InvalidArgumentException('Unknown build option: ' . $argument);
    }
}
if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z]+(?:[.-][0-9A-Za-z]+)*)?$/D', $version)) {
    throw new InvalidArgumentException('Version must be a tag such as v1.2.3 or v1.2.3-rc.1');
}
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
function versionedManifest(string $path, string $version): string {
    $xml = new DOMDocument();
    $xml->preserveWhiteSpace = true;
    if (!$xml->load($path, LIBXML_NONET)) {
        throw new RuntimeException('Invalid manifest: ' . basename($path));
    }
    $node = $xml->getElementsByTagName('version')->item(0);
    if (!$node) {
        throw new RuntimeException('Missing manifest version');
    }
    $node->nodeValue = $version;
    return $xml->saveXML();
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
    // Replace the existing manifest entry in the archive; sources stay unchanged.
    $zip->addFromString($name . '.xml', versionedManifest($root . '/plugins/' . $child . '/' . $name . '.xml', $version));
    $zip->addFile($root . '/LICENSE', 'LICENSE');
    if ($group === 'finder') {
        addTree($zip, $root . '/vendor', 'vendor/');
    }
    $zip->close();
    $children[] = $path;
}
$output = $dist . '/pkg_attachmentscontentsearch-' . $version . '.zip';
$zip = new ZipArchive();
if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Cannot create package archive');
}
$zip->addFromString('pkg_attachmentscontentsearch.xml', versionedManifest($packageSource, $version));
$zip->addFile($root . '/package/script.php', 'script.php');
$zip->addFile($root . '/README.md', 'README.md');
$zip->addFile($root . '/LICENSE', 'LICENSE');
foreach ($children as $child) {
    $zip->addFile($child, 'packages/' . basename($child));
}
$zip->close();
echo $output . "\n";

