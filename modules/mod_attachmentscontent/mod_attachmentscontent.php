<?php
/** @license GPL-3.0-or-later */
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
$app = Factory::getApplication();
$user = $app->getIdentity();
if (!$app->isClient('administrator') || !$user->authorise('core.manage', 'com_finder') || !$user->authorise('core.manage', 'com_content')) { return; }
$db = Factory::getContainer()->get(DatabaseInterface::class);
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$key = 'acs_' . (int) $module->id;
$level = $app->getInput()->getInt($key . '_level', -1);
$page = max(1, $app->getInput()->getInt($key . '_page', 1));
$size = 25;
try {
    $tables = $db->getTableList();
    $hasQueue = in_array($db->getPrefix() . 'attachmentscontent_queue', $tables, true);
    $raw = $db->setQuery("SELECT params FROM #__extensions WHERE type = 'plugin' AND folder = 'finder' AND element = 'attachmentscontent'")->loadResult();
    $selection = new Registry((string) $raw);
    $selected = array_map('intval', (array) $selection->get('index_access_levels', []));
    $allowed = static fn($id) => $raw !== null && ($selection->get('index_access_mode', 'all') === 'all' || in_array((int) $id, $selected, true));
    $url = $db->createQuery()->concatenate([$db->quote('index.php?option=com_attachments&view=acsattachment&id='), 'a.id']);
    $indexed = 'EXISTS (SELECT 1 FROM #__finder_links AS l WHERE l.url = ' . $url . ')';
    $base = $db->createQuery()->from('#__attachments AS a')
        ->where("a.parent_type = 'com_content'")->where("a.parent_entity IN ('article', 'category')")
        ->where("a.uri_type = 'file'")->where("(LOWER(a.filename) LIKE '%.pdf' OR LOWER(a.filename) LIKE '%.docx')");
    if ($hasQueue) { $base->leftJoin('#__attachmentscontent_queue AS q ON q.attachment_id = a.id'); }
    $status = $hasQueue ? 'q.status' : 'NULL';
    $summaryQuery = clone $base;
    $summaryQuery->select('a.access, COUNT(*) AS total, SUM(CASE WHEN ' . $indexed . ' THEN 1 ELSE 0 END) AS indexed_count');
    foreach (['pending', 'running', 'failed', 'skipped'] as $state) {
        $summaryQuery->select('SUM(CASE WHEN ' . $status . ' = ' . $db->quote($state) . ' THEN 1 ELSE 0 END) AS ' . $state);
    }
    $summary = $db->setQuery($summaryQuery->group('a.access'))->loadObjectList('access');
    $levels = $db->setQuery('SELECT id, title FROM #__viewlevels ORDER BY ordering, id')->loadObjectList('id');
    foreach ($summary as $id => $row) { if (!isset($levels[$id])) { $levels[$id] = (object) ['id' => $id, 'title' => 'Nivel #' . $id]; } }
    $filtered = clone $base;
    if ($level >= 0) { $filtered->where('a.access = ' . $level); }
    $count = $db->setQuery((clone $filtered)->select('COUNT(*)'))->loadResult();
    $pages = max(1, (int) ceil($count / $size)); $page = min($page, $pages);
    $rows = $db->setQuery($filtered->select('a.id, a.filename, a.access, a.parent_entity, a.parent_id, ar.title AS article_title, ar.access AS article_access, ar.catid, ar.language AS article_language, c.title AS category_title, c.access AS category_access, c.language AS category_language')
        ->select('CASE WHEN ' . $indexed . ' THEN 1 ELSE 0 END AS indexed_count')->select($status . ' AS queue_status')
        ->leftJoin("#__content AS ar ON a.parent_entity = 'article' AND ar.id = a.parent_id")
        ->leftJoin("#__categories AS c ON c.extension = 'com_content' AND c.id = CASE WHEN a.parent_entity = 'article' THEN ar.catid ELSE a.parent_id END")
        ->order('a.access, a.parent_id, a.id'), ($page - 1) * $size, $size)->loadObjectList();
} catch (\Throwable $error) {
    echo '<p role="status">No se pudo consultar el resumen. Comprueba que Attachments y Búsqueda inteligente están instalados.</p>'; return;
}
$link = static function (int $targetLevel, int $targetPage = 1) use ($key) {
    $uri = clone \Joomla\CMS\Uri\Uri::getInstance();
    $uri->setVar($key . '_level', $targetLevel); $uri->setVar($key . '_page', $targetPage);
    return $uri->toString();
};
require ModuleHelper::getLayoutPath('mod_attachmentscontent', 'default');
