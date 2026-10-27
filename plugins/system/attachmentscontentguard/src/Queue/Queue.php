<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue;

use Joomla\Database\DatabaseInterface;

/** Durable metadata only; caller holds the installation-wide supervisor lock for mutations. */
final class Queue
{
    private const TABLE = '#__attachmentscontent_queue';
    public function __construct(private DatabaseInterface $db) {}
    public function install(): void
    {
        $table = $this->db->quoteName(self::TABLE);
        $mysql = $this->db->getServerType() === 'mysql';
        $index = $mysql ? ', INDEX acs_queue_status (status, attachment_id)' : '';
        $this->db->setQuery("CREATE TABLE IF NOT EXISTS $table (
            attachment_id INTEGER NOT NULL PRIMARY KEY,
            status VARCHAR(16) NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            error_code VARCHAR(40) NOT NULL DEFAULT '',
            updated_at VARCHAR(20) NOT NULL
            $index
        )")->execute();
        if (!$mysql) {
            $name = $this->db->quoteName($this->db->getPrefix() . 'acs_queue_status');
            $this->db->setQuery("CREATE INDEX IF NOT EXISTS $name ON $table (status, attachment_id)")->execute();
        }
        $this->db->setQuery('CREATE TABLE IF NOT EXISTS ' . $this->db->quoteName('#__attachmentscontent_state')
            . ' (id INTEGER NOT NULL PRIMARY KEY, selection_hash VARCHAR(64) NOT NULL)')->execute();
        if ($this->db->setQuery('SELECT id FROM #__attachmentscontent_state WHERE id = 1')->loadResult() === null) {
            try {
                $row = (object) ['id' => 1, 'selection_hash' => ''];
                $this->db->insertObject('#__attachmentscontent_state', $row);
            } catch (\Throwable $error) {
                if ($this->db->setQuery('SELECT id FROM #__attachmentscontent_state WHERE id = 1')->loadResult() === null) {
                    throw $error;
                }
            }
        }
    }
    public function enqueue(?\Danieldiazi\Plugin\System\AttachmentsContentGuard\Security\IndexSelection $selection = null): int
    {
        $selection ??= new \Danieldiazi\Plugin\System\AttachmentsContentGuard\Security\IndexSelection();
        // Set-based insertion: no document content or ID list is loaded into PHP memory.
        $select = $this->db->createQuery()->select("a.id, 'pending', 0, '', " . $this->db->quote(gmdate('Y-m-d H:i:s')))
            ->from('#__attachments AS a')->leftJoin(self::TABLE . ' AS q ON q.attachment_id = a.id')
            ->where($selection->condition())->where('q.attachment_id IS NULL');
        $this->db->setQuery('INSERT INTO ' . $this->db->quoteName(self::TABLE)
            . ' (attachment_id, status, attempts, error_code, updated_at) ' . $select)->execute();
        return $this->db->getAffectedRows();
    }
    public function syncSelection(\Danieldiazi\Plugin\System\AttachmentsContentGuard\Security\IndexSelection $selection): void
    {
        $allowed = $this->db->createQuery()->select('1')->from('#__attachments AS a')
            ->where('a.id = ' . $this->db->quoteName(self::TABLE) . '.attachment_id')->where($selection->condition());
        $now = $this->db->quote(gmdate('Y-m-d H:i:s'));
        $this->db->setQuery($this->db->createQuery()->update(self::TABLE)
            ->set("status = 'excluded'")->set("error_code = 'access_excluded'")->set('updated_at = ' . $now)
            ->where("status <> 'excluded'")->where('NOT EXISTS (' . $allowed . ')'))->execute();
        $this->db->setQuery($this->db->createQuery()->update(self::TABLE)
            ->set("status = 'pending'")->set("error_code = ''")->set('updated_at = ' . $now)
            ->where("status = 'excluded'")->where('EXISTS (' . $allowed . ')'))->execute();
        $this->enqueue($selection);
    }
    public function claim(): ?int
    {
        do {
            $query = $this->db->createQuery()->select('attachment_id')->from(self::TABLE)
                ->where("status = 'pending'")->order('attachment_id ASC')->setLimit(1);
            $id = $this->db->setQuery($query)->loadResult();
            if ($id === null) {
                return null;
            }
            $this->db->setQuery($this->db->createQuery()->update(self::TABLE)
                ->set("status = 'running'")->set('attempts = attempts + 1')->set("error_code = ''")
                ->set('updated_at = ' . $this->db->quote(gmdate('Y-m-d H:i:s')))
                ->where('attachment_id = ' . (int) $id)->where("status = 'pending'"))->execute();
        } while ($this->db->getAffectedRows() !== 1);
        return (int) $id;
    }
    public function finish(int $id, string $status, string $error = ''): void
    {
        if (!in_array($status, ['done', 'skipped', 'failed', 'excluded'], true)
            || !in_array($error, ['', 'timeout', 'worker_failed', 'extraction_failed', 'empty_text', 'index_failed', 'access_excluded'], true)) {
            throw new \InvalidArgumentException('Invalid queue outcome');
        }
        $this->db->setQuery($this->db->createQuery()->update(self::TABLE)
            ->set('status = ' . $this->db->quote($status))->set('error_code = ' . $this->db->quote($error))
            ->set('updated_at = ' . $this->db->quote(gmdate('Y-m-d H:i:s')))
            ->where('attachment_id = ' . $id)->where("status = 'running'"))->execute();
    }
    public function recoverInterrupted(): void
    {
        $this->transition(['running'], 'failed', 'interrupted');
    }
    public function retry(): void
    {
        $this->transition(['failed'], 'pending');
    }
    public function refresh(): void
    {
        $this->transition(['done', 'skipped'], 'pending');
    }
    private function transition(array $from, string $to, string $error = ''): void
    {
        $this->db->setQuery($this->db->createQuery()->update(self::TABLE)
            ->set('status = ' . $this->db->quote($to))->set('error_code = ' . $this->db->quote($error))
            ->set('updated_at = ' . $this->db->quote(gmdate('Y-m-d H:i:s')))
            ->where('status IN (' . implode(',', array_map($this->db->quote(...), $from)) . ')'))->execute();
    }
    public function counts(): array
    {
        $counts = array_fill_keys(['pending', 'running', 'done', 'skipped', 'failed', 'excluded'], 0);
        $rows = $this->db->setQuery($this->db->createQuery()->select('status, COUNT(*) AS total')
            ->from(self::TABLE)->group('status'))->loadObjectList();
        foreach ($rows as $row) {
            $counts[$row->status] = (int) $row->total;
        }
        return $counts;
    }
    public function failures(int $after = 0): array
    {
        return $this->db->setQuery($this->db->createQuery()
            ->select('attachment_id, attempts, error_code, updated_at')->from(self::TABLE)
            ->where("status = 'failed'")->where('attachment_id > ' . max(0, $after))
            ->order('attachment_id ASC')->setLimit(100))->loadAssocList();
    }
}
