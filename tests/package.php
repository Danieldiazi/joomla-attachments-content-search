<?php
$path = $argv[1] ?? dirname(__DIR__) . '/dist/pkg_attachmentscontentsearch-2026.11.2.zip';
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
foreach ($manifest->languages->language as $language) {
    if ($zip->getFromName((string) $manifest->languages['folder'] . '/' . (string) $language) === false) {
        throw new RuntimeException('Package language missing');
    }
}
if ($zip->getFromName('README.en.md') === false) { throw new RuntimeException('English README missing'); }
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
    $manifestName = $id === 'com_attachmentscontentsearch' ? 'attachmentscontentsearch' : $id;
    $pluginManifest = simplexml_load_string($child->getFromName($manifestName . '.xml'));
    if ((string) $pluginManifest->version !== $version) {
        throw new RuntimeException('Child plugin version differs from package');
    }
    foreach (($pluginManifest->files->folder ?? []) as $folder) {
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
    $languages = $id === 'com_attachmentscontentsearch' ? $pluginManifest->administration->languages : $pluginManifest->languages;
    foreach ($languages->language as $language) {
        $folder = (string) $languages['folder'];
        $entry = ($folder !== '' ? $folder . '/' : '') . (string) $language;
        if ($child->getFromName($entry) === false) {
            throw new RuntimeException('Language missing');
        }
    }
    if ($id === 'com_attachmentscontentsearch') {
        foreach (['admin/services/provider.php', 'admin/src/Controller/DisplayController.php', 'admin/src/View/Attachments/HtmlView.php', 'admin/src/Service/Report.php', 'admin/tmpl/attachments/default.php', 'admin/access.xml', 'admin/config.xml'] as $entry) {
            if ($child->getFromName($entry) === false) { throw new RuntimeException('Component file missing: ' . $entry); }
        }
        if ((string) $pluginManifest->administration->menu['link'] !== 'option=com_attachmentscontentsearch') { throw new RuntimeException('Component menu missing'); }
    }
    if ($id === 'mod_attachmentscontent') {
        if ((string) $pluginManifest['client'] !== 'administrator' || $child->getFromName('mod_attachmentscontent.php') === false || $child->getFromName('tmpl/default.php') === false) {
            throw new RuntimeException('Administrator summary module incomplete');
        }
    }
    if ($id === 'attachmentscontent' && $child->getFromName('vendor/smalot/pdfparser/src/Smalot/PdfParser/Parser.php') === false) {
        throw new RuntimeException('PDF dependency missing');
    }
    if ($id === 'attachmentscontentguard') {
        foreach (['src/Console/IndexCommand.php', 'src/Console/WorkerCommand.php', 'src/Queue/Queue.php', 'src/Queue/ProcessRunner.php', 'src/Queue/FileLock.php', 'src/Queue/SelectionSynchronizer.php', 'src/Security/IndexSelection.php'] as $entry) {
            if ($child->getFromName($entry) === false) {
                throw new RuntimeException('Queue dependency missing: ' . $entry);
            }
        }
    }
    $child->close();
}
$zip->close();
echo "PASS: package contains plugins, administrator module, manifests, languages and PDF dependencies.\n";
