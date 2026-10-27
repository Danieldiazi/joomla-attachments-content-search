<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Console;

use Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\FileLock;
use Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\ProcessRunner;
use Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\Queue;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class IndexCommand extends AbstractCommand
{
    protected static $defaultName = 'attachments:index';
    public function __construct(private DatabaseInterface $db) { parent::__construct(); }
    protected function configure(): void
    {
        $this->setDescription('Persistent attachment indexing queue: enqueue, run, status, failures, retry, refresh.');
        $this->addArgument('action', InputArgument::OPTIONAL, 'Queue action', 'status');
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum files per invocation', '100');
        $this->addOption('timeout', null, InputOption::VALUE_REQUIRED, 'Wall-clock seconds per file', '60');
        $this->addOption('after', null, InputOption::VALUE_REQUIRED, 'Failures after attachment ID (100 per page)', '0');
    }
    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $action = $input->getArgument('action');
        if (!in_array($action, ['enqueue', 'run', 'status', 'failures', 'retry', 'refresh'], true)) {
            throw new \InvalidArgumentException('Unknown queue action.');
        }
        $limit = filter_var($input->getOption('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        $timeout = filter_var($input->getOption('timeout'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 3600]]);
        if ($limit === false || $timeout === false) {
            throw new \InvalidArgumentException('Use --limit=1..10000 and --timeout=1..3600.');
        }
        if (!PluginHelper::isEnabled('finder', 'attachmentscontent')) {
            throw new \RuntimeException('Enable Smart Search - Attachments Content Search first.');
        }
        $queue = new Queue($this->db);
        $base = rtrim((string) $this->getApplication()->get('tmp_path'), '/\\')
            . '/acs-' . hash('sha256', JPATH_ROOT . $this->db->getPrefix());
        // Never unlink lock files: replacing a locked inode breaks mutual exclusion.
        $supervisor = in_array($action, ['status', 'failures'], true) ? null : new FileLock($base . '-supervisor.lock');
        $queue->install();
        if ($action === 'status') {
            $output->writeln(json_encode($queue->counts(), JSON_THROW_ON_ERROR));
            return 0;
        }
        if ($action === 'failures') {
            foreach ($queue->failures(max(0, (int) $input->getOption('after'))) as $row) {
                $output->writeln(json_encode($row, JSON_THROW_ON_ERROR));
            }
            return 0;
        }
        // An orphan child may still be working after its supervisor was interrupted.
        $workerProbe = new FileLock($base . '-worker.lock');
        unset($workerProbe);
        $queue->recoverInterrupted();
        switch ($action) {
            case 'enqueue':
                $output->writeln('Added: ' . $queue->enqueue());
                break;
            case 'retry':
                $queue->retry();
                break;
            case 'refresh':
                $queue->refresh();
                $output->writeln('Added: ' . $queue->enqueue());
                break;
            case 'run':
                $runner = new ProcessRunner();
                for ($n = 0; $n < $limit; $n++) {
                    $id = $queue->claim();
                    if ($id === null) {
                        break;
                    }
                    [$status, $error] = $runner->run([
                        PHP_BINARY, '-d', 'memory_limit=' . ini_get('memory_limit'),
                        JPATH_ROOT . '/cli/joomla.php', 'attachments:index-worker', (string) $id,
                    ], $timeout);
                    $queue->finish($id, $status, $error);
                    $output->writeln('ID ' . $id . ': ' . $status . ($error ? ' (' . $error . ')' : ''));
                }
                break;
        }
        $counts = $queue->counts();
        $output->writeln(json_encode($counts, JSON_THROW_ON_ERROR));
        return $counts['failed'] > 0 ? 1 : 0;
    }
}
