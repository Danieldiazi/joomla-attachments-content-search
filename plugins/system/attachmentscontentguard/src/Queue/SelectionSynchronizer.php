<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue;

use Danieldiazi\Plugin\System\AttachmentsContentGuard\Security\IndexSelection;
use Danieldiazi\Plugin\System\AttachmentsContentGuard\Security\AccessPolicy;
use Joomla\Database\DatabaseInterface;

/** Metadata-only reconciliation; never extracts documents in a settings-save request. */
final class SelectionSynchronizer
{
    public function __construct(private DatabaseInterface $db, private string $workerLock) {}
    public function needsSync(): bool
    {
        $hash = $this->db->setQuery('SELECT selection_hash FROM #__attachmentscontent_state WHERE id = 1')->loadResult();
        return $hash !== IndexSelection::load($this->db)->hash();
    }
    public function synchronize(bool $ownsWorkerLock = false, bool $force = false): bool
    {
        $selection = IndexSelection::load($this->db);
        $saved = $this->db->setQuery('SELECT selection_hash FROM #__attachmentscontent_state WHERE id = 1')->loadResult();
        if (!$force && $saved === $selection->hash()) {
            return true;
        }
        $queue = new Queue($this->db);
        // Also update running jobs: finish() cannot overwrite an excluded/requeued job.
        $queue->syncSelection($selection);
        $lock = null;
        if (!$ownsWorkerLock) {
            try {
                $lock = new FileLock($this->workerLock);
            } catch (\RuntimeException $error) {
                // Current searches use the new scope immediately. The active worker/supervisor
                // or the next administrator request completes physical cleanup.
                return false;
            }
        }
        $this->purgeExcluded($selection);
        // If settings changed during cleanup, do not mark the newer selection as reconciled.
        if (IndexSelection::load($this->db)->hash() !== $selection->hash()) {
            return false;
        }
        $queue->syncSelection($selection);
        $this->db->setQuery($this->db->createQuery()->update('#__attachmentscontent_state')
            ->set('selection_hash = ' . $this->db->quote($selection->hash()))->where('id = 1'))->execute();
        return true;
    }
    private function purgeExcluded(IndexSelection $selection): void
    {
        $allowed = $this->db->createQuery()->select('a.id')->from('#__attachments AS a')
            ->where($selection->condition())
            ->where('l.url = ' . $this->db->createQuery()->concatenate([$this->db->quote(AccessPolicy::URL_PREFIX), 'a.id']));
        do {
            $query = $this->db->createQuery()->select('l.link_id')->from('#__finder_links AS l')
                ->where('l.url LIKE ' . $this->db->quote(AccessPolicy::URL_PREFIX . '%'))
                ->where('NOT EXISTS (' . $allowed . ')')->order('l.link_id ASC')->setLimit(100);
            $ids = array_map('intval', $this->db->setQuery($query)->loadColumn());
            if (!$ids) {
                break;
            }
            $list = implode(',', $ids);
            $this->db->transactionStart();
            try {
                // Maintain shared term counts, including terms also used by other adapters.
                $term = $this->db->quoteName('#__finder_terms') . '.term_id';
                $count = '(SELECT COUNT(*) FROM #__finder_links_terms AS m WHERE m.term_id = ' . $term
                    . ' AND m.link_id IN (' . $list . '))';
                $this->db->setQuery($this->db->createQuery()->update('#__finder_terms')
                    ->set($this->db->quoteName('links') . ' = ' . $this->db->quoteName('links') . ' - ' . $count)
                    ->where('EXISTS (SELECT 1 FROM #__finder_links_terms AS m WHERE m.term_id = ' . $term
                        . ' AND m.link_id IN (' . $list . '))'))->execute();
                foreach (['#__finder_links_terms', '#__finder_taxonomy_map', '#__finder_links'] as $table) {
                    $this->db->setQuery($this->db->createQuery()->delete($table)->where('link_id IN (' . $list . ')'))->execute();
                }
                $this->db->setQuery($this->db->createQuery()->delete('#__finder_terms')
                    ->where($this->db->quoteName('links') . ' <= 0'))->execute();
                $this->db->transactionCommit();
            } catch (\Throwable $error) {
                $this->db->transactionRollback();
                throw $error;
            }
        } while (count($ids) === 100);
    }
}
