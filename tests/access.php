<?php
require __DIR__ . '/bootstrap.php';
$joomla = $argv[1] ?? dirname(__DIR__, 2) . '/joomla-cms';
if (!is_file($joomla . '/libraries/vendor/autoload.php')) {
    throw new RuntimeException('Pass a Joomla 6.2 source checkout with Composer dependencies.');
}
require $joomla . '/libraries/vendor/autoload.php';
require $joomla . '/libraries/loader.php';
JLoader::setup();
JLoader::registerNamespace('Joomla\\CMS', $joomla . '/libraries/src', false, false, 'psr4');
JLoader::registerNamespace('Joomla\\Component\\Finder', $joomla . '/administrator/components/com_finder/src', false, false, 'psr4');
JLoader::registerNamespace('Joomla\\Component\\Finder\\Site', $joomla . '/components/com_finder/src', false, false, 'psr4');
JLoader::registerNamespace('Joomla\\Component\\Finder\\Administrator', $joomla . '/administrator/components/com_finder/src', false, false, 'psr4');

use Danieldiazi\Plugin\System\AttachmentsContentGuard\Security\AccessPolicy;
use Joomla\Database\DatabaseFactory;

$db = new class(['database' => ':memory:', 'prefix' => 't_', 'driver' => 'sqlite']) extends Joomla\Database\Sqlite\SqliteDriver {
    // SQLite PDO rowCount() does not count SELECT rows; emulate the MySQL count contract.
    public function getNumRows() { return count($this->loadRowList()); }
};
function sql(string $statement): void {
    global $db;
    $db->setQuery($statement)->execute();
}
sql("CREATE TABLE t_extensions (type TEXT, folder TEXT, element TEXT, params TEXT)");
sql("INSERT INTO t_extensions VALUES ('plugin', 'finder', 'attachmentscontent', '{}')");
sql('CREATE TABLE t_attachments (id INTEGER PRIMARY KEY, filename TEXT, uri_type TEXT, parent_type TEXT, parent_entity TEXT, parent_id INTEGER, state INTEGER, access INTEGER)');
sql('CREATE TABLE t_content (id INTEGER PRIMARY KEY, catid INTEGER, state INTEGER, access INTEGER, publish_up TEXT, publish_down TEXT)');
sql('CREATE TABLE t_categories (id INTEGER PRIMARY KEY, extension TEXT, lft INTEGER, rgt INTEGER, published INTEGER, access INTEGER)');
sql('CREATE TABLE t_finder_links (link_id INTEGER PRIMARY KEY, url TEXT, access INTEGER)');
sql("INSERT INTO t_categories VALUES (1, 'system', 0, 11, 1, 1), (2, 'com_content', 1, 8, 1, 1), (3, 'com_content', 2, 3, 1, 7), (4, 'com_content', 4, 7, 1, 1), (5, 'com_content', 5, 6, 1, 1)");
sql("INSERT INTO t_content VALUES (10, 2, 1, 1, NULL, NULL), (11, 3, 1, 1, NULL, NULL), (12, 5, 1, 1, NULL, NULL)");
sql("INSERT INTO t_attachments VALUES (1, 'public.docx', 'file', 'com_content', 'article', 10, 1, 1), (2, 'private.pdf', 'file', 'com_content', 'article', 10, 1, 3), (3, 'category.docx', 'file', 'com_content', 'article', 11, 1, 1), (4, 'ancestor.docx', 'file', 'com_content', 'article', 12, 1, 1), (5, 'category.pdf', 'file', 'com_content', 'category', 2, 1, 3)");
foreach (range(1, 5) as $id) {
    sql('INSERT INTO t_finder_links VALUES (' . $id . ', ' . $db->quote(AccessPolicy::URL_PREFIX . $id) . ', 0)');
}
sql("INSERT INTO t_finder_links VALUES (99, 'index.php?option=com_content&view=article&id=10', 1), (100, 'index.php?option=com_attachments&view=attachment&id=2', 1)");
function ids(array $levels, bool $enabled = true): array {
    global $db;
    $condition = AccessPolicy::finderCondition($db, $levels, '2026-10-26 12:00:00', $enabled);
    $query = $db->createQuery()->select('l.link_id')->from('#__finder_links AS l')
        ->where('l.access IN (' . implode(',', array_map('intval', array_merge($levels, [0]))) . ')')
        ->where($condition)->order('l.link_id');
    return array_map('intval', $db->setQuery($query)->loadColumn());
}
check(ids([1]) === [1, 4, 99], 'Guest sees public documents, never private attachment, restricted category or legacy entries');
check(ids([1, 3]) === [1, 2, 4, 5, 99], 'Member sees permitted attachment and category attachment');
check(ids([1, 7]) === [1, 3, 4, 99], 'Non-numeric ACL: category level 7 does not grant attachment level 3');
check(ids([1, 3], false) === [99], 'Disabled component/finder or insecure mode fails closed');
sql('UPDATE t_attachments SET access = 7 WHERE id = 2');
check(ids([1, 3]) === [1, 4, 5, 99], 'Permission revocation takes effect without rebuilding index');
sql('UPDATE t_content SET access = 7 WHERE id = 10');
check(ids([1, 3]) === [4, 5, 99], 'Article ACL is also required');
sql('UPDATE t_content SET access = 1 WHERE id = 10');
sql('UPDATE t_categories SET access = 7 WHERE id = 4');
check(ids([1]) === [1, 99], 'Restricted ancestor category blocks descendant attachments');
sql('UPDATE t_categories SET access = 1, published = 0 WHERE id = 4');
check(ids([1]) === [1, 99], 'Unpublished ancestor blocks descendant attachments');
sql("UPDATE t_content SET publish_up = '2027-01-01 00:00:00' WHERE id = 10");
check(ids([1]) === [99], 'Future article is hidden');
sql("UPDATE t_content SET publish_up = NULL, publish_down = '2025-01-01 00:00:00' WHERE id = 10");
check(ids([1]) === [99], 'Expired article is hidden');
sql('UPDATE t_content SET publish_down = NULL WHERE id = 10');
sql('UPDATE t_attachments SET state = 0 WHERE id = 1');
check(ids([1]) === [99], 'Unpublished attachment hidden immediately');
sql('UPDATE t_attachments SET state = 1 WHERE id = 1');
sql('DELETE FROM t_attachments WHERE id = 1');
check(ids([1]) === [99], 'Deleted attachment leaves no visible stale result');
check(ids([]) === [], 'No authorised levels means no results');

// Load actual Joomla parent classes to catch incompatible method declarations.
$classes = [
    Danieldiazi\Plugin\Finder\AttachmentsContent\Extension\AttachmentsContent::class,
    Danieldiazi\Plugin\System\AttachmentsContentGuard\Extension\Guard::class,
    Danieldiazi\Plugin\System\AttachmentsContentGuard\Model\SearchModel::class,
    Danieldiazi\Plugin\System\AttachmentsContentGuard\Model\SuggestionsModel::class,
    Danieldiazi\Plugin\System\AttachmentsContentGuard\Controller\DisplayController::class,
    Danieldiazi\Plugin\System\AttachmentsContentGuard\Factory\GuardedFactoryProvider::class,
];
foreach ($classes as $class) {
    check(class_exists($class), 'Joomla 6.2 class compatibility: ' . substr($class, strrpos($class, '\\') + 1));
}
$factory = new Danieldiazi\Plugin\System\AttachmentsContentGuard\Factory\GuardedFactory('\\Joomla\\Component\\Finder');
$method = new ReflectionMethod($factory, 'getClassName');
check($method->invoke($factory, 'Model\\SearchModel', 'Site') === $classes[2], 'Finder factory selects guarded search model');
check($method->invoke($factory, 'Model\\SuggestionsModel', 'Site') === $classes[3], 'Finder factory selects guarded suggestions model');
$model = new ReflectionClass($classes[3]);
check($model->newInstanceWithoutConstructor()->getItems() === [], 'Suggestions endpoints reveal no dictionary terms');
echo "$testCount access and integration checks passed.\n";



Joomla\CMS\Factory::$container = new Joomla\DI\Container();
Joomla\CMS\Factory::$container->set(Joomla\Database\DatabaseInterface::class, $db);
Joomla\CMS\Factory::$language = new class { public function getTag() { return "en-GB"; } };
// Exercise the real Joomla SearchModel query, result conversion, totals and paging.
$components = new ReflectionProperty(Joomla\CMS\Component\ComponentHelper::class, 'components');
$components->setValue(null, [
    'com_attachments' => new Joomla\CMS\Component\ComponentRecord(['enabled' => 1, 'params' => '{"secure":1}']),
    'com_languages' => new Joomla\CMS\Component\ComponentRecord(['enabled' => 1, 'params' => '{"site":"es-ES"}']),
]);
(new ReflectionProperty(Joomla\CMS\Plugin\PluginHelper::class, 'plugins'))->setValue(null, [
    (object) ['type' => 'finder', 'name' => 'attachmentscontent', 'params' => '{}'],
    (object) ['type' => 'system', 'name' => 'attachmentscontentguard', 'params' => '{}'],
]);
foreach ([
    'object TEXT', 'state INTEGER DEFAULT 1', 'published INTEGER DEFAULT 1',
    'publish_start_date TEXT', 'publish_end_date TEXT', 'start_date TEXT', 'language TEXT',
] as $column) {
    sql('ALTER TABLE t_finder_links ADD COLUMN ' . $column);
}
sql('CREATE TABLE t_finder_links_terms (link_id INTEGER, term_id INTEGER, weight REAL)');
sql('CREATE TABLE t_finder_terms (term_id INTEGER PRIMARY KEY, links INTEGER)');
sql('CREATE TABLE t_finder_taxonomy_map (link_id INTEGER, node_id INTEGER)');
(new Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\Queue($db))->install();
$db->setQuery("UPDATE t_attachmentscontent_state SET selection_hash = '" . (new Danieldiazi\Plugin\System\AttachmentsContentGuard\Security\IndexSelection())->hash() . "'")->execute();
sql("INSERT INTO t_attachments VALUES (1, 'public.docx', 'file', 'com_content', 'article', 10, 1, 1)");
sql('UPDATE t_attachments SET access = 3 WHERE id = 2');
sql('UPDATE t_categories SET published = 1, access = 1 WHERE id = 4');
foreach ([1, 2, 3, 4, 5, 99, 100] as $id) {
    $result = new Joomla\Component\Finder\Administrator\Indexer\Result();
    $result->title = 'Documento ' . $id;
    $result->route = 'index.php?option=com_attachments&task=download&id=' . $id;
    $result->body = 'pesca sostenible';
    sql('UPDATE t_finder_links SET object = ' . $db->quote(serialize($result)) . ' WHERE link_id = ' . $id);
    sql('INSERT INTO t_finder_links_terms VALUES (' . $id . ', 42, 1)');
}
function searchModel(array $levels, int $offset = 0): object {
    global $db;
    $model = (new ReflectionClass(Danieldiazi\Plugin\System\AttachmentsContentGuard\Model\SearchModel::class))->newInstanceWithoutConstructor();
    $model->setDatabase($db);
    $user = new class extends Joomla\CMS\User\User {
        public array $levels = [];
        public function __construct() {}
        public function getAuthorisedViewLevels() { return $this->levels; }
    };
    $user->id = 123;
    $user->levels = $levels;
    $model->setCurrentUser($user);
    (new ReflectionProperty($model, 'state'))->setValue($model, new Joomla\Registry\Registry());
    (new ReflectionProperty($model, '__state_set'))->setValue($model, true);
    $query = (new ReflectionClass(Joomla\Component\Finder\Administrator\Indexer\Query::class))->newInstanceWithoutConstructor();
    $query->input = 'pesca';
    $query->filters = [];
    $query->empty = false;
    (new ReflectionProperty($model, 'searchquery'))->setValue($model, $query);
    (new ReflectionProperty($model, 'includedTerms'))->setValue($model, [[42]]);
    foreach (['user.groups' => array_merge($levels, [0]), 'list.start' => $offset, 'list.limit' => 1, 'list.ordering' => 'l.link_id', 'list.direction' => 'ASC'] as $key => $value) {
        $model->setState($key, $value);
    }
    return $model;
}
$member = searchModel([1, 3]);
check($member->getTotal() === 5, 'Real Joomla search counts only authorised pesca matches');
$items = $member->getItems();
check(count($items) === 1 && $items[0]->title === 'Documento 1', 'Real Joomla search returns authorised first page');
$guest = searchModel([1], 1);
check($guest->getTotal() === 3 && $guest->getItems()[0]->title === 'Documento 4', 'Guest pagination excludes private documents before paging');
sql('UPDATE t_attachments SET access = 7 WHERE id = 2');
check(searchModel([1, 3])->getTotal() === 4, 'Real search immediately applies a permission revocation');
// A changed indexing scope is applied to real search results before physical cleanup.
sql('UPDATE t_extensions SET params = ' . $db->quote('{"index_access_mode":"selected","index_access_levels":[]}'));
check(searchModel([1, 3])->getTotal() === 1, 'Empty indexing scope hides stale attachment matches before totals and paging');
$downloadQuery = AccessPolicy::attachmentQuery($db, [1, 3], gmdate('Y-m-d H:i:s'));
$downloadQuery->where('a.id = 1');
check((int) $db->setQuery($downloadQuery)->loadResult() === 1, 'Index selection does not revoke an otherwise authorised attachment download');
sql("UPDATE t_extensions SET params = '{}'");
$components->getValue()['com_attachments']->getParams()->set('secure', 0);
check(searchModel([1, 3])->getTotal() === 1, 'Real search hides all document entries when secure downloads are disabled');
echo "$testCount total access and integration checks passed.\n";

// Regression: core Adapter's default state query joins a.catid, which Attachments lacks.
$adapter = (new ReflectionClass(Danieldiazi\Plugin\Finder\AttachmentsContent\Extension\AttachmentsContent::class))->newInstanceWithoutConstructor();
$adapter->setDatabase($db);
$stateQuery = (new ReflectionMethod($adapter, 'getStateQuery'))->invoke($adapter);
check(count($db->setQuery($stateQuery)->loadObjectList()) === 5, 'Attachment state lookup works without an a.catid column');
echo "$testCount total access and integration checks passed.\n";

// Queue integration uses the real plugin/Result/extractor; only the core index writer is replaced.
class QueueTestIndexer extends Joomla\Component\Finder\Administrator\Indexer\Indexer {
    public array $written = [];
    public ?Closure $afterWrite = null;
    public function __construct() {}
    public function index($item, $format = 'html') { $this->written[] = $item; if ($this->afterWrite) { ($this->afterWrite)($item); } return 1; }
    public function remove($linkId, $removeTaxonomies = true) { return true; }
}
$components->getValue()['com_attachments']->getParams()->set('secure', 1);
Joomla\CMS\Factory::$application = new class extends Joomla\CMS\Application\ConsoleApplication {
    public function __construct() {}
    public function triggerEvent($name, $args = []) { return []; }
    public function get($key, $default = null) { return match ($key) { 'log_path' => dirname(__DIR__) . '/build/logs', 'tmp_path' => dirname(__DIR__) . '/build', default => $default }; }
};
Joomla\CMS\Factory::$config = new Joomla\Registry\Registry(['log_path' => dirname(__DIR__) . '/build/logs']);
@mkdir(dirname(__DIR__) . '/build/logs', 0775, true);
foreach (['created TEXT', 'filename_sys TEXT', 'display_name TEXT', 'description TEXT'] as $column) {
    sql('ALTER TABLE t_attachments ADD COLUMN ' . $column);
}
(new ReflectionProperty($adapter, 'db'))->setValue($adapter, $db);
$adapter->setApplication(Joomla\CMS\Factory::$application);
$writer = new QueueTestIndexer();
(new ReflectionProperty($adapter, 'indexer'))->setValue($adapter, $writer);
(new ReflectionProperty($adapter, 'params'))->setValue($adapter, new Joomla\Registry\Registry());
check($adapter->indexQueuedAttachment(9999) === 'skipped', 'Queue removes stale entry for a deleted attachment');
sql("UPDATE t_attachments SET uri_type = 'url' WHERE id = 1");
check($adapter->indexQueuedAttachment(1) === 'skipped', 'Queue skips unsupported attachment without reporting success');
sql("UPDATE t_attachments SET uri_type = 'file', filename_sys = '/not-a-valid-attachment.docx' WHERE id = 1");
try {
    $adapter->indexQueuedAttachment(1);
    check(false, 'Queue reports extraction failures');
} catch (RuntimeException $error) {
    check($error->getMessage() === 'ACS_EXTRACTION_FAILED', 'Queue reports extraction failures rather than silently marking done');
}
@mkdir(JPATH_ROOT . '/attachments', 0775, true);
$file = JPATH_ROOT . '/attachments/queue-test.docx';
$archive = new ZipArchive();
$archive->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$archive->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>pesca personales</w:t></w:r></w:p></w:body></w:document>');
$archive->close();
sql('UPDATE t_attachments SET filename_sys = ' . $db->quote($file) . " WHERE id = 1");
check($adapter->indexQueuedAttachment(1) === 'done' && str_contains($writer->written[0]->body, 'pesca personales'), 'Queue passes extracted DOCX content to the real Joomla Result');
$archive->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$archive->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body/></w:document>');
$archive->close();
check($adapter->indexQueuedAttachment(1) === 'empty_text', 'Queue distinguishes an empty document from a successful index');
check((new ReflectionProperty($adapter, 'queueMode'))->getValue($adapter) === false, 'Queue mode is reset after successful and failed jobs');
@unlink($file);
$command = new Danieldiazi\Plugin\System\AttachmentsContentGuard\Console\IndexCommand($db);
$worker = new Danieldiazi\Plugin\System\AttachmentsContentGuard\Console\WorkerCommand($db);
check($command->getName() === 'attachments:index' && $worker->isHidden(), 'Queue commands are compatible with Joomla Console');
check(isset(Danieldiazi\Plugin\System\AttachmentsContentGuard\Extension\Guard::getSubscribedEvents()['application.before_execute']), 'System guard registers queue commands in the Joomla console event');
echo "$testCount total access and integration checks passed.\n";

sql('UPDATE t_extensions SET params = ' . $db->quote('{"index_access_mode":"selected","index_access_levels":[]}'));
$written = count($writer->written);
check($adapter->indexQueuedAttachment(1) === 'excluded' && count($writer->written) === $written, 'Queue checks the current level selection before reading a document');
$item = (new ReflectionMethod($adapter, 'getItem'))->invoke($adapter, 1);
(new ReflectionMethod($adapter, 'index'))->invoke($adapter, $item);
check(count($writer->written) === $written, 'Normal Finder indexing also excludes unselected levels');

$guard = (new ReflectionClass(Danieldiazi\Plugin\System\AttachmentsContentGuard\Extension\Guard::class))->newInstanceWithoutConstructor();
$guard->setDatabase($db);
$guard->setApplication(Joomla\CMS\Factory::$application);
$scopeSync = new Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\SelectionSynchronizer($db, dirname(__DIR__) . '/build/event-scope.lock');
sql('UPDATE t_extensions SET params = ' . $db->quote('{"index_access_mode":"selected","index_access_levels":[1]}'));
$guard->onExtensionAfterSave(new Joomla\Event\Event('onExtensionAfterSave', ['subject' => (object) ['type' => 'plugin', 'folder' => 'finder', 'element' => 'anotherplugin']]));
check($scopeSync->needsSync(), 'Saving another plugin does not reconcile this extension');
$guard->onExtensionAfterSave(new Joomla\Event\Event('onExtensionAfterSave', ['subject' => (object) ['type' => 'plugin', 'folder' => 'finder', 'element' => 'attachmentscontent']]));
check(!$scopeSync->needsSync() && $db->setQuery('SELECT status FROM t_attachmentscontent_queue WHERE attachment_id = 1')->loadResult() === 'pending', 'Saving the finder plugin reconciles the queue and schedules newly included levels');
$fields = simplexml_load_file(dirname(__DIR__) . '/plugins/finder/attachmentscontent/attachmentscontent.xml');
check((string) $fields->config->fields->fieldset->field[0]['default'] === 'all'
    && (string) $fields->config->fields->fieldset->field[1]['multiple'] === 'true', 'Configuration exposes all-level default and native Joomla multiselect');
echo "$testCount total access and integration checks passed.\n";

// Simulate a settings save during Finder's write, after the final pre-write scope check.
sql("UPDATE t_extensions SET params = '{}'");
$base = dirname(__DIR__) . '/build/acs-' . hash('sha256', JPATH_ROOT . $db->getPrefix());
$scopeSync = new Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\SelectionSynchronizer($db, $base . '-worker.lock');
$scopeSync->synchronize();
$archive->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$archive->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>pesca during scope change</w:t></w:r></w:p></w:body></w:document>');
$archive->close();
$workerLock = new Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\FileLock($base . '-worker.lock');
$writer->afterWrite = static function ($item) use ($db, $scopeSync): void {
    sql('INSERT INTO t_finder_links (link_id, url, access) VALUES (901, ' . $db->quote($item->url) . ', 0)');
    sql('INSERT INTO t_finder_terms VALUES (901, 1)');
    sql('INSERT INTO t_finder_links_terms VALUES (901, 901, 1)');
    sql('UPDATE t_extensions SET params = ' . $db->quote('{"index_access_mode":"selected","index_access_levels":[]}'));
    check(!$scopeSync->synchronize(), 'Saving during a Finder write defers deletion until the worker finishes');
};
check($adapter->indexQueuedAttachment(1) === 'excluded', 'In-flight index result is classified using the new committed selection');
check($db->setQuery('SELECT link_id FROM t_finder_links WHERE link_id = 901')->loadResult() === null
    && !$scopeSync->needsSync(), 'Worker completion removes an excluded entry even if it was written after settings changed');
$writer->afterWrite = null;
unset($workerLock);
@unlink($file);
echo "$testCount total access and integration checks passed.\n";
