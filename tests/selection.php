<?php
require __DIR__ . '/bootstrap.php';
$joomla = $argv[1] ?? dirname(__DIR__, 2) . '/joomla-cms';
require $joomla . '/libraries/vendor/autoload.php';

use Danieldiazi\Plugin\System\AttachmentsContentGuard\Security\IndexSelection;
use Danieldiazi\Plugin\System\AttachmentsContentGuard\Security\AccessPolicy;
use Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\Queue;
use Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\FileLock;
use Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\SelectionSynchronizer;
use Joomla\Registry\Registry;

check(IndexSelection::fromParams(new Registry())->allows(7), 'Existing installations default to all levels');
$selected = IndexSelection::fromParams(new Registry(['index_access_mode' => 'selected', 'index_access_levels' => ['7', 3, '3']]));
check($selected->allows(3) && $selected->allows(7) && !$selected->allows(1), 'Selected levels are a set, not a numeric hierarchy');
check($selected->hash() === IndexSelection::fromParams(new Registry(['index_access_mode' => 'selected', 'index_access_levels' => [3, 7]]))->hash(), 'Selection hash ignores order and duplicates');
check(!IndexSelection::fromParams(new Registry(['index_access_mode' => 'selected']))->allows(1), 'Selected mode with an empty list includes nothing');
check(!IndexSelection::fromParams(new Registry(['index_access_mode' => 'invalid', 'index_access_levels' => ['1 OR 1=1', -1, 0]]))->allows(1), 'Invalid mode and untrusted IDs fail closed');
try { $selected->condition('a;DELETE'); check(false, 'SQL aliases cannot inject conditions'); }
catch (InvalidArgumentException $error) { check(true, 'SQL aliases cannot inject conditions'); }

$file = tempnam(sys_get_temp_dir(), 'acs-scope-');
$prefix = 'acs_' . bin2hex(random_bytes(4)) . '_';
$mysql = ($argv[2] ?? '') === 'mysql';
$db = $mysql
    ? new Joomla\Database\Mysqli\MysqliDriver(['host' => '127.0.0.1', 'user' => 'root',
        'password' => getenv('ACS_MYSQL_PASSWORD') ?: '', 'database' => 'acs_test', 'prefix' => $prefix])
    : new Joomla\Database\Sqlite\SqliteDriver(['database' => $file, 'prefix' => $prefix, 'driver' => 'sqlite']);
function scopeSql(string $query): void { global $db; $db->setQuery($query)->execute(); }
function saveScope(string $mode, array $levels = []): void {
    global $db;
    $params = json_encode(['index_access_mode' => $mode, 'index_access_levels' => $levels], JSON_THROW_ON_ERROR);
    scopeSql('UPDATE #__extensions SET params = ' . $db->quote($params));
}
function scopeRows(string $table): array {
    global $db;
    return array_map('intval', $db->setQuery('SELECT link_id FROM ' . $table . ' ORDER BY link_id')->loadColumn());
}
$tables = ['attachments', 'extensions', 'finder_links', 'finder_terms', 'finder_links_terms', 'finder_taxonomy_map', 'attachmentscontent_queue', 'attachmentscontent_state'];
try {
    scopeSql('CREATE TABLE #__attachments (id INTEGER PRIMARY KEY, access INTEGER)');
    scopeSql('CREATE TABLE #__extensions (type VARCHAR(20), folder VARCHAR(20), element VARCHAR(40), params TEXT)');
    scopeSql("INSERT INTO #__extensions VALUES ('plugin', 'finder', 'attachmentscontent', '{}')");
    scopeSql('CREATE TABLE #__finder_links (link_id INTEGER PRIMARY KEY, url VARCHAR(255))');
    scopeSql('CREATE TABLE #__finder_terms (term_id INTEGER PRIMARY KEY, links INTEGER)');
    scopeSql('CREATE TABLE #__finder_links_terms (link_id INTEGER, term_id INTEGER)');
    scopeSql('CREATE TABLE #__finder_taxonomy_map (link_id INTEGER, node_id INTEGER)');
    scopeSql('INSERT INTO #__attachments VALUES (1, 1), (2, 3), (3, 7), (4, 3), (5, 1)');
    foreach ([101 => 1, 102 => 2, 103 => 3, 104 => 4, 105 => 999] as $link => $id) {
        scopeSql('INSERT INTO #__finder_links VALUES (' . $link . ', ' . $db->quote(AccessPolicy::URL_PREFIX . $id) . ')');
        scopeSql('INSERT INTO #__finder_taxonomy_map VALUES (' . $link . ', 1)');
    }
    scopeSql("INSERT INTO #__finder_links VALUES (106, 'index.php?option=com_content&id=10')");
    scopeSql('INSERT INTO #__finder_taxonomy_map VALUES (106, 2)');
    scopeSql('INSERT INTO #__finder_terms VALUES (1, 3), (2, 1), (3, 2), (4, 1), (5, 1)');
    scopeSql('INSERT INTO #__finder_links_terms VALUES (101, 1), (102, 1), (106, 1), (103, 2), (102, 3), (104, 3), (105, 4), (106, 5)');
    $queue = new Queue($db);
    $queue->install();
    $queue->enqueue();
    $queue->finish($queue->claim(), 'done'); // 1
    $queue->finish($queue->claim(), 'done'); // 2
    $queue->finish($queue->claim(), 'failed', 'timeout'); // 3
    check($queue->claim() === 4, 'Fixture has an in-flight job');
    $queue->finish($queue->claim(), 'done'); // 5
    $sync = new SelectionSynchronizer($db, $file . '.lock');
    saveScope('selected', [3]);
    check($sync->needsSync(), 'Changed committed configuration requires reconciliation');
    $lock = new FileLock($file . '.lock');
    check(!$sync->synchronize(), 'Settings save defers physical deletion while a file is processing');
    $counts = $queue->counts();
    check($counts['excluded'] === 3 && $counts['running'] === 1 && $counts['done'] === 1, 'Settings immediately exclude completed, failed and pending jobs outside the scope');
    $queue->finish(1, 'done');
    check($queue->counts()['excluded'] === 3, 'A late worker cannot overwrite an excluded job');
    check(count(scopeRows('#__finder_links')) === 6, 'Deferred cleanup retains physical entries until the worker releases its lock');
    unset($lock);
    check($sync->synchronize(), 'Cleanup resumes after the active file ends');
    check(scopeRows('#__finder_links') === [102, 104, 106], 'Only excluded attachment entries are removed; unrelated adapters remain');
    check(scopeRows('#__finder_taxonomy_map') === [102, 104, 106], 'Excluded taxonomy mappings are removed');
    check(scopeRows('#__finder_links_terms') === [102, 102, 104, 106, 106], 'Excluded term mappings are removed');
    check((int) $db->setQuery('SELECT links FROM #__finder_terms WHERE term_id = 1')->loadResult() === 2, 'Shared term counts retain documents from other levels and adapters');
    check($db->setQuery('SELECT term_id FROM #__finder_terms WHERE term_id IN (2,4)')->loadColumn() === [], 'Terms used only by excluded documents are deleted');
    check(!$sync->needsSync(), 'Successful reconciliation records the selection hash');
    saveScope('selected', [1, 3]);
    $sync->synchronize();
    $pending = array_map('intval', $db->setQuery("SELECT attachment_id FROM #__attachmentscontent_queue WHERE status = 'pending' ORDER BY attachment_id")->loadColumn());
    check($pending === [1, 5], 'Newly included levels become pending without restarting still-included completed jobs');
    check($queue->counts()['excluded'] === 1, 'Unselected level stays excluded');
    scopeSql('INSERT INTO #__attachments VALUES (6, 7), (7, 3)');
    check($queue->enqueue(IndexSelection::load($db)) === 1, 'Enqueue adds only new attachments in included levels');

    // More than two cleanup chunks; these stale links no longer have an attachment.
    scopeSql('INSERT INTO #__finder_terms VALUES (98, 250)');
    for ($i = 2000; $i < 2250; $i++) {
        scopeSql('INSERT INTO #__finder_links VALUES (' . $i . ', ' . $db->quote(AccessPolicy::URL_PREFIX . $i) . ')');
        scopeSql('INSERT INTO #__finder_links_terms VALUES (' . $i . ', 98)');
        scopeSql('INSERT INTO #__finder_taxonomy_map VALUES (' . $i . ', 1)');
    }
    $sync->synchronize(false, true);
    check(scopeRows('#__finder_links') === [102, 104, 106], 'Cleanup handles multiple bounded chunks and deleted attachments');
    check($db->setQuery('SELECT links FROM #__finder_terms WHERE term_id = 98')->loadResult() === null, 'Chunked cleanup removes orphaned terms without keeping document text');
    saveScope('selected');
    $sync->synchronize();
    check(scopeRows('#__finder_links') === [106] && $queue->counts()['pending'] === 0, 'An empty selection clears attachment entries and leaves no pending attachment jobs');
    saveScope('all');
    $sync->synchronize();
    check($queue->counts()['pending'] === 7 && $queue->counts()['excluded'] === 0, 'Returning to all levels schedules previously excluded files');
    check($queue->claim() === 1, 'Reincluded jobs can be resumed normally');
    $queue->finish(1, 'done');
    check((int) $db->setQuery('SELECT attempts FROM #__attachmentscontent_queue WHERE attachment_id = 1')->loadResult() === 2, 'Selection changes preserve attempt history');
    saveScope('selected', [3]);
    $sync->synchronize();
    $queue->retry();
    $queue->refresh();
    $queue->syncSelection(IndexSelection::load($db));
    check($queue->counts()['excluded'] === 4, 'Retry and refresh do not resurrect excluded levels');
} finally {
    foreach (array_reverse($tables) as $table) {
        $db->setQuery('DROP TABLE IF EXISTS ' . $db->quoteName($prefix . $table))->execute();
    }
    $db->disconnect();
    @unlink($file);
    @unlink($file . '.lock');
}
echo "$testCount access-level selection and reconciliation checks passed (" . ($mysql ? 'MySQL' : 'SQLite') . ").\n";
