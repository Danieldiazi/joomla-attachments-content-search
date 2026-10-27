<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Extension;

use Danieldiazi\Plugin\System\AttachmentsContentGuard\Factory\GuardedFactoryProvider;
use Danieldiazi\Plugin\System\AttachmentsContentGuard\Security\AccessPolicy;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

final class Guard extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    public static function getSubscribedEvents(): array
    {
        return [
            'onExtensionAfterSave' => 'onExtensionAfterSave',
            'onAfterInitialise' => 'onAfterInitialise',
            'application.before_execute' => 'registerQueueCommands',
            'onPageCacheSetCaching' => 'onPageCacheSetCaching',
            'onAfterExtensionBoot' => 'onAfterExtensionBoot',
            'onAfterRoute' => ['onAfterRoute', 1000],
        ];
    }

    public function onExtensionAfterSave(Event $event): void
    {
        $record = $event->getArgument('subject');
        if (!$record || ($record->type ?? '') !== 'plugin' || ($record->folder ?? '') !== 'finder'
            || ($record->element ?? '') !== 'attachmentscontent') {
            return;
        }
        $queue = new \Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\Queue($this->getDatabase());
        $queue->install();
        $complete = $this->selectionSynchronizer()->synchronize();
        if (!$complete) {
            $this->getApplication()->enqueueMessage(
                'Attachments Content Search: the new access-level selection is active. Index cleanup will finish after the current file, or on the next administrator request.',
                'notice'
            );
        }
    }

    public function onAfterInitialise(): void
    {
        $app = $this->getApplication();
        if (!$app->isClient('administrator') || !$app->getIdentity() || !$app->getIdentity()->authorise('core.manage', 'com_plugins')) {
            return;
        }
        // Finish cleanup left pending by an interrupted worker or an earlier settings request.
        try {
            $sync = $this->selectionSynchronizer();
            if ($sync->needsSync()) {
                $sync->synchronize();
            }
        } catch (\Throwable $error) {
            $app->enqueueMessage('Attachments Content Search: access-level index cleanup is pending. Check the database and temporary directory, then run attachments:index sync.', 'warning');
        }
    }

    private function selectionSynchronizer(): \Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\SelectionSynchronizer
    {
        $db = $this->getDatabase();
        $base = rtrim((string) $this->getApplication()->get('tmp_path'), '/\\')
            . '/acs-' . hash('sha256', JPATH_ROOT . $db->getPrefix());
        return new \Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\SelectionSynchronizer($db, $base . '-worker.lock');
    }

    public function registerQueueCommands(): void
    {
        $app = $this->getApplication();
        if ($app instanceof \Joomla\Console\Application) {
            $app->addCommand(new \Danieldiazi\Plugin\System\AttachmentsContentGuard\Console\IndexCommand($this->getDatabase()));
            $app->addCommand(new \Danieldiazi\Plugin\System\AttachmentsContentGuard\Console\WorkerCommand($this->getDatabase()));
        }
    }

    public function onPageCacheSetCaching(\Joomla\CMS\Event\PageCache\SetCachingEvent $event): void
    {
        $option = $this->getApplication()->getInput()->getCmd('option');
        if (in_array($option, ['com_finder', 'com_attachments'], true)) {
            $event->addResult(false);
        }
    }

    public function onAfterExtensionBoot(Event $event): void
    {
        if ($this->getApplication()->isClient('site') && $event->getArgument('extensionName') === 'finder') {
            $event->getArgument('container')->registerServiceProvider(new GuardedFactoryProvider('\\Joomla\\Component\\Finder'));
        }
    }

    public function onAfterRoute(): void
    {
        $app = $this->getApplication();
        if (!$app->isClient('site')) {
            return;
        }
        $input = $app->getInput();
        $option = $input->getCmd('option');
        if ($option === 'com_finder') {
            $app->allowCache(false);
            $params = $app->getParams();
            $params->set('show_autosuggest', 0);
            $params->set('show_suggested_query', 0);
            $params->set('show_explained_query', 0);
            $app->setHeader('Cache-Control', 'private, no-store', true);
        }
        if ($option !== 'com_attachments' || !in_array($input->getCmd('task'), ['download', 'display.download'], true)) {
            return;
        }
        $app->allowCache(false);
        $db = $this->getDatabase();
        $recordQuery = $db->createQuery()->select('*')->from('#__attachments')->where('id = ' . $input->getInt('id'));
        $record = $db->setQuery($recordQuery)->loadObject();
        if ($record && ($record->uri_type !== 'file' || $record->parent_type !== 'com_content'
            || !in_array(strtolower(pathinfo($record->filename, PATHINFO_EXTENSION)), ['docx', 'pdf'], true))) {
            return;
        }
        $query = AccessPolicy::attachmentQuery($db, $app->getIdentity()->getAuthorisedViewLevels(), Factory::getDate()->toSql());
        $query->where('a.id = ' . $input->getInt('id'));
        if (!ComponentHelper::getParams('com_attachments')->get('secure', false) || !$db->setQuery($query)->loadResult()) {
            throw new \RuntimeException('Document not available.', 404);
        }
        $app->setHeader('Cache-Control', 'private, no-store', true);
    }
}
