<?php
define('_JEXEC', 1);
define('JPATH_ROOT', dirname(__DIR__) . '/build/test-site');
define('JPATH_SITE', JPATH_ROOT);
define('JPATH_ADMINISTRATOR', JPATH_ROOT . '/administrator');
define('JPATH_LIBRARIES', JPATH_ROOT . '/libraries');
define('JPATH_CACHE', JPATH_ROOT . '/cache');
define('JVERSION', '6.2.0');
require dirname(__DIR__) . '/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Danieldiazi\\Plugin\\Finder\\AttachmentsContent\\' => '/plugins/finder/attachmentscontent/src/',
        'Danieldiazi\\Plugin\\System\\AttachmentsContentGuard\\' => '/plugins/system/attachmentscontentguard/src/',
    ];
    foreach ($prefixes as $prefix => $path) {
        if (str_starts_with($class, $prefix)) {
            $file = dirname(__DIR__) . $path . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    }
});
$testCount = 0;
function check(bool $condition, string $label): void {
    global $testCount;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    $testCount++;
    echo "PASS: $label\n";
}
function rejects(callable $action, string $label): void {
    try {
        $action();
    } catch (RuntimeException $e) {
        check(true, $label);
        return;
    }
    check(false, $label);
}
