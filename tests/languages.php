<?php
/** Language catalogue and manifest coverage. @license GPL-3.0-or-later */
$root = dirname(__DIR__);
$catalogues = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $path = str_replace('\\', '/', $file->getPathname());
    if (!preg_match('~/language/(en-GB|es-ES)/[^/]+\\.ini$~', $path, $match)
        || preg_match('~/(vendor|dist|build)/~', $path)) { continue; }
    $values = parse_ini_file($path, false, INI_SCANNER_RAW);
    if ($values === false || !$values) { throw new RuntimeException('Invalid catalogue: ' . $path); }
    $catalogues[$path] = $values;
}
foreach ($catalogues as $path => $values) {
    if (!str_contains($path, '/en-GB/')) { continue; }
    $translated = $catalogues[str_replace('/en-GB/', '/es-ES/', $path)] ?? [];
    $keys = array_keys($values); $otherKeys = array_keys($translated);
    sort($keys); sort($otherKeys);
    if ($keys !== $otherKeys) { throw new RuntimeException('Language keys differ: ' . $path); }
    foreach ($values as $key => $value) {
        if (trim($value) === '' || trim($translated[$key]) === '') { throw new RuntimeException('Empty translation: ' . $key); }
        preg_match_all('/%(?:[0-9]+\\$)?[sd]/', $value, $first);
        preg_match_all('/%(?:[0-9]+\\$)?[sd]/', $translated[$key], $second);
        if ($first[0] !== $second[0]) { throw new RuntimeException('Placeholders differ: ' . $key); }
    }
}
$all = [];
foreach ($catalogues as $path => $values) { if (str_contains($path, '/en-GB/')) { $all += $values; } }
foreach (['components', 'modules', 'plugins', 'package'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (!in_array($file->getExtension(), ['php', 'xml'], true)) { continue; }
        $text = file_get_contents($file->getPathname());
        preg_match_all('/[\x27"]((?:COM_ATTACHMENTSCONTENTSEARCH|MOD_ATTACHMENTSCONTENT|PKG_ATTACHMENTSCONTENTSEARCH|PLG_FINDER_ATTACHMENTSCONTENT|PLG_SYSTEM_ATTACHMENTSCONTENTGUARD)(?:_[A-Z0-9_]+)?)[\x27"]/', $text, $used);
        foreach ($used[1] as $key) { if (!isset($all[$key])) { throw new RuntimeException('Missing translation: ' . $key); } }
    }
}
$manifests = [
    'components/com_attachmentscontentsearch/attachmentscontentsearch.xml',
    'modules/mod_attachmentscontent/mod_attachmentscontent.xml',
    'plugins/finder/attachmentscontent/attachmentscontent.xml',
    'plugins/system/attachmentscontentguard/attachmentscontentguard.xml',
    'package/pkg_attachmentscontentsearch.xml',
];
foreach ($manifests as $path) {
    $xml = simplexml_load_file($root . '/' . $path);
    $languages = isset($xml->administration) ? $xml->administration->languages : $xml->languages;
    foreach ($languages->language as $language) {
        $source = dirname($root . '/' . $path) . '/' . (string) $languages['folder'];
        $source = rtrim($source, '/') . '/' . (string) $language;
        if (!is_file($source)) { throw new RuntimeException('Manifest language missing: ' . $source); }
    }
    if (count($languages->language) < 2) { throw new RuntimeException('Missing manifest languages: ' . $path); }
}
echo "PASS: English/Spanish keys, placeholders, references and manifest sources.\n";
