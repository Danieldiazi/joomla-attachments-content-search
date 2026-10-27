<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Console;

use Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\FileLock;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class WorkerCommand extends AbstractCommand
{
    protected static $defaultName = 'attachments:index-worker';
    public function __construct(private DatabaseInterface $db) { parent::__construct(); }
    protected function configure(): void
    {
        $this->setHidden(true);
        $this->addArgument('id', InputArgument::REQUIRED, 'Attachment ID');
    }
    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $id = filter_var($input->getArgument('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            return 22;
        }
        $base = rtrim((string) $this->getApplication()->get('tmp_path'), '/\\')
            . '/acs-' . hash('sha256', JPATH_ROOT . $this->db->getPrefix());
        $lock = new FileLock($base . '-worker.lock');
        if (!PluginHelper::isEnabled('finder', 'attachmentscontent')) {
            return 22;
        }
        try {
            $app = Factory::getApplication();
            $app->bootComponent('com_finder');
            $plugin = $app->bootPlugin('attachmentscontent', 'finder');
            $outcome = $plugin->indexQueuedAttachment($id);
            return match ($outcome) {
                'done' => 0,
                'skipped' => 10,
                'empty_text' => 21,
                default => 22,
            };
        } catch (\Throwable $error) {
            // Exception text can contain paths or document text; never echo it.
            return $error->getMessage() === 'ACS_EXTRACTION_FAILED' ? 20 : 22;
        }
    }
}
