<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\Finder\AttachmentsContent\Extension;

use Danieldiazi\Plugin\Finder\AttachmentsContent\Extraction\TextExtractor;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\QueryInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

final class AttachmentsContent extends Adapter implements SubscriberInterface
{
    use DatabaseAwareTrait;

    private bool $queueMode = false;
    private string $queueOutcome = 'skipped';

    protected $context = 'AttachmentsContent';
    protected $extension = 'com_attachments';
    protected $layout = 'acsattachment';
    protected $type_title = 'Attachment document';
    protected $table = '#__attachments';
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return array_merge(parent::getSubscribedEvents(), [
            'onFinderAfterSave' => 'onFinderAfterSave',
            'onFinderAfterDelete' => 'onFinderAfterDelete',
            'onFinderChangeState' => 'onFinderChangeState',
        ]);
    }

    protected function setup()
    {
        if (!ComponentHelper::isEnabled('com_attachments')
            || !PluginHelper::isEnabled('system', 'attachmentscontentguard')
            || !ComponentHelper::getParams('com_attachments')->get('secure', false)) {
            throw new \RuntimeException('Attachments Content Search requires Attachments secure downloads and the system guard plugin.');
        }
        return true;
    }

    protected function getListQuery($query = null)
    {
        $db = $this->getDatabase();
        $query = $query instanceof QueryInterface ? $query : $db->createQuery();
        // Load unsupported rows too: index() removes their old entries after a format/parent change.
        return $query->select('a.*')->select('a.created AS start_date')
            ->from($db->quoteName($this->table, 'a'));
    }

    /** Attachments has no category foreign key; core's default a.catid join is invalid. */
    protected function getStateQuery()
    {
        return $this->getDatabase()->createQuery()
            ->select('a.id, a.state, a.access')->from('#__attachments AS a');
    }

    public function onStartIndex()
    {
        try {
            return parent::onStartIndex();
        } catch (\Throwable $error) {
            throw new \RuntimeException('Attachments Content Search start: ' . $error->getMessage(), 0, $error);
        }
    }

    public function onBuildIndex()
    {
        try {
            return parent::onBuildIndex();
        } catch (\Throwable $error) {
            throw new \RuntimeException('Attachments Content Search batch: ' . $error->getMessage(), 0, $error);
        }
    }
    /** Index one durable job without Finder's browser batch/session offsets. */
    public function indexQueuedAttachment(int $id): string
    {
        $this->setup();
        $this->queueMode = true;
        $this->queueOutcome = 'skipped';
        try {
            $row = $this->getDatabase()->setQuery($this->getListQuery()->where('a.id = ' . $id))->loadObject();
            if (!$row) {
                $this->remove($id);
                return 'skipped';
            }
            $this->index($this->getItem($id));
            return $this->queueOutcome;
        } finally {
            $this->queueMode = false;
        }
    }

    protected function index(Result $item)
    {
        $id = (int) $item->id;
        if (!$id) {
            return;
        }
        // Remove first so a replacement which fails extraction cannot leave old text searchable.
        $this->remove($id);
        if ($item->uri_type !== 'file' || $item->parent_type !== 'com_content'
            || !in_array($item->parent_entity, ['article', 'category'], true)
            || (int) $item->state !== 1
            || !in_array(strtolower(pathinfo($item->filename, PATHINFO_EXTENSION)), ['docx', 'pdf'], true)) {
            return;
        }
        try {
            $extractor = new TextExtractor(max(1, min(100, (int) $this->params->get('max_file_mb', 20))) * 1048576);
            $text = $extractor->extract((string) $item->filename_sys, (string) $item->filename, JPATH_ROOT . '/attachments');
        } catch (\Throwable $e) {
            Log::addLogger(['text_file' => 'attachments-content-search.php'], Log::ALL, ['attachmentscontent']);
            // Never log extracted text, original filenames, or exception messages containing paths.
            Log::add('Attachment ID ' . $id . ': extraction failed (' . get_class($e) . ').', Log::WARNING, 'attachmentscontent');
            if ($this->queueMode) {
                throw new \RuntimeException('ACS_EXTRACTION_FAILED', 0, $e);
            }
            return;
        }
        if ($text === '') {
            $this->queueOutcome = 'empty_text';
            return;
        }
        // Build a fresh Result: do not serialize the server path or unneeded attachment fields.
        $result = new Result();
        $result->id = $id;
        $result->type_id = $this->type_id;
        $result->layout = 'default';
        $result->url = $this->getUrl($id, $this->extension, $this->layout);
        $result->route = 'index.php?option=com_attachments&task=download&id=' . $id;
        $result->title = htmlspecialchars((string) ($item->display_name ?: $item->filename), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $result->summary = htmlspecialchars((string) $item->description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?: ' ';
        $result->body = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $result->access = 0;
        $result->state = 1;
        $result->language = '*';
        $result->start_date = $item->start_date;
        $result->params = new Registry();
        $result->mime = strtolower(pathinfo($item->filename, PATHINFO_EXTENSION)) === 'pdf'
            ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        $result->addTaxonomy('Type', 'Attachment document');
        $this->indexer->index($result);
        $this->queueOutcome = 'done';
    }

    public function onFinderAfterSave(Event $event): void
    {
        if ($event->getArgument('context') === 'com_attachments.attachment') {
            $subject = $event->getArgument('subject');
            $this->reindex((int) $subject->id);
        }
    }

    public function onFinderAfterDelete(Event $event): void
    {
        if ($event->getArgument('context') === 'com_attachments.attachment') {
            $this->remove((int) $event->getArgument('subject')->id);
        }
    }

    public function onFinderChangeState(Event $event): void
    {
        if ($event->getArgument('context') === 'com_attachments.attachment') {
            foreach ($event->getArgument('pks', []) as $id) {
                $this->reindex((int) $id);
            }
        }
        if ($event->getArgument('context') === 'com_plugins.plugin' && (int) $event->getArgument('value') === 0) {
            $this->pluginDisable($event->getArgument('pks', []));
        }
    }
}
