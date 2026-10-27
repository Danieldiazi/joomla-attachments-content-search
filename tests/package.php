<?php
$path = $argv[1] ?? dirname(__DIR__) . '/dist/pkg_attachmentscontentsearch-0.2.0.zip';
$zip = new ZipArchive();
if ($zip->open($path) !== true) {
    throw new RuntimeException('Cannot open installer');
}
$manifest = simplexml_load_string($zip->getFromName('pkg_attachmentscontentsearch.xml'));
if ((string) $manifest['type'] !== 'package') {
    throw new RuntimeException('Invalid package manifest');
}
$version = (string) $manifest->version;
if (basename($path) !== 'pkg_attachmentscontentsearch-' . $version . '.zip') {
    throw new RuntimeException('Package filename and manifest versions differ');
}
$temp = dirname(__DIR__) . '/build/package-test';
@mkdir($temp, 0775, true);
foreach ($manifest->files->file as $file) {
    $bytes = $zip->getFromName('packages/' . (string) $file);
    if ($bytes === false) {
        throw new RuntimeException('Missing child ZIP');
    }
    $childPath = $temp . '/' . (string) $file;
    file_put_contents($childPath, $bytes);
    $child = new ZipArchive();
    if ($child->open($childPath) !== true) {
        throw new RuntimeException('Invalid child ZIP');
    }
    $id = (string) $file['id'];
    $pluginManifest = simplexml_load_string($child->getFromName($id . '.xml'));
    if ((string) $pluginManifest->version !== $version) {
        throw new RuntimeException('Child plugin version differs from package');
    }
    foreach ($pluginManifest->files->folder as $folder) {
        $found = false;
        for ($i = 0; $i < $child->numFiles; $i++) {
            if (str_starts_with($child->getNameIndex($i), (string) $folder . '/')) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new RuntimeException('Manifest folder missing: ' . $folder);
        }
    }
    foreach ($pluginManifest->languages->language as $language) {
        if ($child->getFromName((string) $language) === false) {
            throw new RuntimeException('Language missing');
        }
    }
    if ($id === 'attachmentscontent' && $child->getFromName('vendor/smalot/pdfparser/src/Smalot/PdfParser/Parser.php') === false) {
        throw new RuntimeException('PDF dependency missing');
    }
    if ($id === 'attachmentscontentguard') {
        foreach (['src/Console/IndexCommand.php', 'src/Console/WorkerCommand.php', 'src/Queue/Queue.php', 'src/Queue/ProcessRunner.php', 'src/Queue/FileLock.php'] as $entry) {
            if ($child->getFromName($entry) === false) {
                throw new RuntimeException('Queue dependency missing: ' . $entry);
            }
        }
    }
    $child->close();
}
$zip->close();
echo "PASS: package contains both plugins, manifests, languages and PDF dependencies.\n";
