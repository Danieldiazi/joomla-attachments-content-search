<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Security;

use Joomla\Database\DatabaseInterface;

final class AccessPolicy
{
    public const URL_PREFIX = 'index.php?option=com_attachments&view=acsattachment&id=';

    /** Current ACL and publication rules, including all ancestor categories. */
    public static function attachmentQuery(DatabaseInterface $db, array $levels, string $now): object
    {
        $levels = implode(',', array_unique(array_filter(array_map('intval', $levels), static fn(int $v): bool => $v > 0))) ?: '0';
        $date = $db->quote($now);
        return $db->createQuery()->select('a.id')->from('#__attachments AS a')
            ->leftJoin('#__content AS ar ON a.parent_entity = ' . $db->quote('article') . ' AND ar.id = a.parent_id')
            ->innerJoin('#__categories AS c ON c.id = CASE WHEN a.parent_entity = ' . $db->quote('article') . ' THEN ar.catid ELSE a.parent_id END')
            ->where('a.uri_type = ' . $db->quote('file'))
            ->where('a.parent_type = ' . $db->quote('com_content'))
            ->where('a.state = 1')->where('a.access IN (' . $levels . ')')
            ->where('c.extension = ' . $db->quote('com_content'))
            ->where('c.published = 1')->where('c.access IN (' . $levels . ')')
            ->where('(a.parent_entity = ' . $db->quote('category') . ' OR (a.parent_entity = ' . $db->quote('article')
                . ' AND ar.state = 1 AND ar.access IN (' . $levels . ')'
                . ' AND (ar.publish_up IS NULL OR ar.publish_up = ' . $db->quote('0000-00-00 00:00:00') . ' OR ar.publish_up <= ' . $date . ')'
                . ' AND (ar.publish_down IS NULL OR ar.publish_down = ' . $db->quote('0000-00-00 00:00:00') . ' OR ar.publish_down >= ' . $date . ')))')
            ->where('NOT EXISTS (SELECT 1 FROM #__categories AS ancestor WHERE ancestor.lft <= c.lft AND ancestor.rgt >= c.rgt'
                . ' AND ancestor.id <> 1 AND (ancestor.published <> 1 OR ancestor.access NOT IN (' . $levels . ')))');
    }

    public static function finderCondition(DatabaseInterface $db, array $levels, string $now, bool $enabled): string
    {
        $prefix = $db->quote(self::URL_PREFIX . '%');
        $legacy = $db->quote('index.php?option=com_attachments&view=attachment&id=%');
        $normal = '(l.access <> 0 AND l.url NOT LIKE ' . $prefix . ' AND l.url NOT LIKE ' . $legacy . ')';
        if (!$enabled) {
            return $normal;
        }
        $query = self::attachmentQuery($db, $levels, $now);
        $query->where('l.url = ' . $db->createQuery()->concatenate([$db->quote(self::URL_PREFIX), 'a.id']));
        $query->where('(LOWER(a.filename) LIKE ' . $db->quote('%.docx') . ' OR LOWER(a.filename) LIKE ' . $db->quote('%.pdf') . ')');
        return '(' . $normal . ' OR (l.access = 0 AND l.url LIKE ' . $prefix . ' AND EXISTS (' . $query . ')))';
    }
}
