<?php
require __DIR__ . '/bootstrap.php';
$joomla = $argv[1] ?? dirname(__DIR__, 2) . '/joomla-cms';
require $joomla . '/libraries/vendor/autoload.php';
require dirname(__DIR__) . '/components/com_attachmentscontentsearch/admin/src/Service/Report.php';
use Danieldiazi\Component\AttachmentsContentSearch\Administrator\Service\Report;
$file = tempnam(sys_get_temp_dir(), 'acs-report-');
$db = new Joomla\Database\Sqlite\SqliteDriver(['database' => $file, 'prefix' => 'r_', 'driver' => 'sqlite']);
function reportSql(string $sql): void { global $db; $db->setQuery($sql)->execute(); }
try {
    reportSql('CREATE TABLE #__attachments (id INTEGER PRIMARY KEY, filename TEXT, access INTEGER, parent_type TEXT, parent_entity TEXT, parent_id INTEGER, uri_type TEXT)');
    reportSql('CREATE TABLE #__extensions (type TEXT, folder TEXT, element TEXT, params TEXT)');
    reportSql('CREATE TABLE #__finder_links (url TEXT)');
    reportSql('CREATE TABLE #__viewlevels (id INTEGER PRIMARY KEY, title TEXT, ordering INTEGER)');
    reportSql('CREATE TABLE #__content (id INTEGER PRIMARY KEY, title TEXT, access INTEGER, catid INTEGER, language TEXT)');
    reportSql('CREATE TABLE #__categories (id INTEGER PRIMARY KEY, title TEXT, access INTEGER, language TEXT, extension TEXT)');
    reportSql("INSERT INTO #__extensions VALUES ('plugin','finder','attachmentscontent','{}')");
    reportSql("INSERT INTO #__viewlevels VALUES (1,'Público',1),(3,'Registrados',2)");
    reportSql("INSERT INTO #__content VALUES (10,'Memoria anual',3,20,'*')");
    reportSql("INSERT INTO #__categories VALUES (20,'Informes',3,'*','com_content')");
    reportSql("INSERT INTO #__attachments VALUES (1,'informe.pdf',1,'com_content','article',10,'file'),(2,'datos.ODT',3,'com_content','category',20,'file'),(3,'viejo.doc',1,'com_content','article',10,'file'),(4,'externo.pdf',1,'com_content','article',10,'url')");
    $url = 'index.php?option=com_attachments&view=acsattachment&id=1';
    reportSql('INSERT INTO #__finder_links VALUES (' . $db->quote($url) . ')');
    // Duplicate Finder rows must not inflate attachment counts.
    reportSql('INSERT INTO #__finder_links VALUES (' . $db->quote($url) . ')');
    $before = $db->getTableList();
    $data = Report::load($db);
    check((int) $data['count'] === 2 && !$data['hasQueue'], 'Report works without a queue and excludes unsupported files');
    check((int) $data['summary'][1]->indexed_count === 1, 'Index counts use actual Finder entries without duplicates');
    check($data['rows'][0]->article_title === 'Memoria anual' && (int) $data['rows'][0]->article_access === 3, 'Attachment and article access remain separate');
    $compact = Report::load($db, [], false);
    check($compact['total'] === 2 && $compact['indexedTotal'] === 1, 'Compact summary reports indexed and unindexed totals');
    check($before === $db->getTableList(), 'Reading either report creates no tables');
    check((int) Report::load($db, ['search' => 'Memoria'])['count'] === 1, 'Search finds article titles');
    check((int) Report::load($db, ['search' => 'Informes'])['count'] === 2, 'Search finds category titles including article categories');
    check((int) Report::load($db, ['search' => "' OR 1=1 --"])['count'] === 0, 'Search treats SQL fragments as literal text');
    check((int) Report::load($db, ['search' => '%'])['count'] === 0, 'Search treats wildcard characters literally');
    check((int) Report::load($db, ['state' => 'indexed'])['count'] === 1 && (int) Report::load($db, ['state' => 'unindexed'])['count'] === 1, 'Index state filters are complementary');
    check((int) Report::load($db, ['level' => 3, 'kind' => 'category', 'parent' => 20])['count'] === 1, 'Level, type and parent ID filters combine');
    check((int) Report::load($db, ['state' => 'failed'])['count'] === 0, 'Queue filters return no matches when the queue is absent');
    reportSql('CREATE TABLE #__attachmentscontent_queue (attachment_id INTEGER PRIMARY KEY, status TEXT)');
    reportSql("INSERT INTO #__attachmentscontent_queue VALUES (1,'pending'),(2,'failed')");
    check((int) Report::load($db, ['state' => 'failed'])['rows'][0]->id === 2, 'Queue failure filter uses stored state');
    $data = Report::load($db, ['state' => 'pending']);
    check((int) $data['count'] === 1 && (int) $data['rows'][0]->indexed_count === 1, 'Indexed attachments may also await an update');
    reportSql('UPDATE #__extensions SET params = ' . $db->quote('{"index_access_mode":"selected","index_access_levels":[3]}'));
    $data = Report::load($db, ['state' => 'excluded']);
    check((int) $data['count'] === 1 && !$data['allowed'](1) && $data['allowed'](3), 'Excluded level filter follows current plugin configuration');
    for ($id = 5; $id <= 35; $id++) { reportSql("INSERT INTO #__attachments VALUES ($id,'extra.pdf',1,'com_content','article',10,'file')"); }
    $data = Report::load($db, ['page' => 999]);
    check($data['pages'] === 2 && $data['page'] === 2 && count($data['rows']) === 8, 'Pagination clamps out of range pages without losing rows');
    $user = new class { public array $denied = []; public function authorise($action, $asset): bool { return !in_array($asset, $this->denied, true); } };
    check(Report::canView($user), 'Authorised administrators can view the report');
    foreach (['com_attachmentscontentsearch','com_finder','com_content'] as $asset) { $user->denied = [$asset]; check(!Report::canView($user), 'Missing management permission denies report: ' . $asset); }
} finally { $db->disconnect(); @unlink($file); }
echo "$testCount report checks passed.\n";
