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
    }
    public function enqueue(): int
    {
        $added = 0;
        $last = 0;
        // A bounded snapshot; keyset pages avoid loading thousands of records at once.
        $maximum = (int) $this->db->setQuery('SELECT MAX(id) FROM #__attachments')->loadResult();
        do {
            $query = $this->db->createQuery()->select('a.id')->from('#__attachments AS a')
                ->where('a.id > ' . $last)->where('a.id <= ' . $maximum)->order('a.id ASC')->setLimit(500);
            $ids = $this->db->setQuery($query)->loadColumn();
            foreach ($ids as $id) {
                $last = (int) $id;
                $exists = $this->db->setQuery($this->db->createQuery()->select('attachment_id')->from(self::TABLE)
                    ->where('attachment_id = ' . $last))->loadResult();
                if ($exists !== null) {
                    continue;
                }
                $row = (object) ['attachment_id' => $last, 'status' => 'pending', 'attempts' => 0,
                    'error_code' => '', 'updated_at' => gmdate('Y-m-d H:i:s')];
                $this->db->insertObject(self::TABLE, $row);
                $added++;
            }
        } while (count($ids) === 500);
        return $added;
    }
    public function claim(): ?int
    {
        $query = $this->db->createQuery()->select('attachment_id')->from(self::TABLE)
            ->where("status = 'pending'")->order('attachment_id ASC')->setLimit(1);
        $id = $this->db->setQuery($query)->loadResult();
        if ($id === null) {
            return null;
        }
        $this->db->setQuery($this->db->createQuery()->update(self::TABLE)
            ->set("status = 'running'")->set('attempts = attempts + 1')->set("error_code = ''")
            ->set('updated_at = ' . $this->db->quote(gmdate('Y-m-d H:i:s')))
            ->where('attachment_id = ' . (int) $id))->execute();
        return (int) $id;
    }
    public function finish(int $id, string $status, string $error = ''): void
    {
        if (!in_array($status, ['done', 'skipped', 'failed'], true)
            || !in_array($error, ['', 'timeout', 'worker_failed', 'extraction_failed', 'empty_text', 'index_failed'], true)) {
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
        $counts = array_fill_keys(['pending', 'running', 'done', 'skipped', 'failed'], 0);
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
