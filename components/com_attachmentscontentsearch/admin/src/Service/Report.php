<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Component\AttachmentsContentSearch\Administrator\Service;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
defined('_JEXEC') or die;
final class Report
{
    public static function canView($user): bool
    {
        return $user->authorise('core.manage', 'com_attachmentscontentsearch')
            && $user->authorise('core.manage', 'com_finder') && $user->authorise('core.manage', 'com_content');
    }
    public static function load(DatabaseInterface $db, array $filters = [], bool $details = true): array
    {
        $level = (int) ($filters['level'] ?? -1);
        $page = max(1, (int) ($filters['page'] ?? 1)); $size = 25;
        $search = mb_substr(trim((string) ($filters['search'] ?? '')), 0, 200);
        $state = (string) ($filters['state'] ?? ''); $kind = (string) ($filters['kind'] ?? '');
        $parent = max(0, (int) ($filters['parent'] ?? 0));
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
        foreach (['pending', 'running', 'failed', 'skipped'] as $queueState) {
            $summaryQuery->select('SUM(CASE WHEN ' . $status . ' = ' . $db->quote($queueState) . ' THEN 1 ELSE 0 END) AS ' . $queueState);
        }
        $summary = $db->setQuery($summaryQuery->group('a.access'))->loadObjectList('access');
        $levels = $db->setQuery('SELECT id, title FROM #__viewlevels ORDER BY ordering, id')->loadObjectList('id');
        foreach ($summary as $id => $row) { if (!isset($levels[$id])) { $levels[$id] = (object) ['id' => $id, 'title' => null]; } }
        if (!$details) {
            $indexedTotal = array_sum(array_map(static fn($row) => (int) $row->indexed_count, $summary));
            $total = array_sum(array_map(static fn($row) => (int) $row->total, $summary));
            return compact('levels', 'allowed', 'indexedTotal', 'total');
        }
        $filtered = clone $base;
        $filtered->leftJoin("#__content AS ar ON a.parent_entity = 'article' AND ar.id = a.parent_id")
            ->leftJoin("#__categories AS c ON c.extension = 'com_content' AND c.id = CASE WHEN a.parent_entity = 'article' THEN ar.catid ELSE a.parent_id END");
        if ($search !== '') {
            $term = $db->quote('%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%');
            $filtered->where('(a.filename LIKE ' . $term . " ESCAPE '!' OR ar.title LIKE " . $term . " ESCAPE '!' OR c.title LIKE " . $term . " ESCAPE '!')");
        }
        if (in_array($kind, ['article', 'category'], true)) { $filtered->where('a.parent_entity = ' . $db->quote($kind)); }
        if ($parent > 0) { $filtered->where('a.parent_id = ' . $parent); }
        if ($state === 'indexed') { $filtered->where($indexed); }
        elseif ($state === 'unindexed') { $filtered->where('NOT ' . $indexed); }
        elseif ($state === 'excluded') {
            $filtered->where($selection->get('index_access_mode', 'all') === 'all' ? '1 = 0' : ($selected ? 'a.access NOT IN (' . implode(',', $selected) . ')' : '1 = 1'));
        }
        elseif (in_array($state, ['pending', 'running', 'failed', 'skipped'], true)) {
            $filtered->where($hasQueue ? 'q.status = ' . $db->quote($state) : '1 = 0');
        }
        if ($level >= 0) { $filtered->where('a.access = ' . $level); }
        $count = $db->setQuery((clone $filtered)->select('COUNT(*)'))->loadResult();
        $pages = max(1, (int) ceil($count / $size)); $page = min($page, $pages);
        $rows = $db->setQuery($filtered->select('a.id, a.filename, a.access, a.parent_entity, a.parent_id, ar.title AS article_title, ar.access AS article_access, ar.catid, ar.language AS article_language, c.title AS category_title, c.access AS category_access, c.language AS category_language')
            ->select('CASE WHEN ' . $indexed . ' THEN 1 ELSE 0 END AS indexed_count')->select($status . ' AS queue_status')
            ->order('a.access, a.parent_id, a.id'), ($page - 1) * $size, $size)->loadObjectList();

        return compact('level', 'page', 'pages', 'count', 'rows', 'levels', 'summary', 'allowed', 'hasQueue', 'search', 'state', 'kind', 'parent');
    }
}
