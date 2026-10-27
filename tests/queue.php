<?php
require __DIR__ . '/bootstrap.php';
$joomla = $argv[1] ?? dirname(__DIR__, 2) . '/joomla-cms';
require $joomla . '/libraries/vendor/autoload.php';

use Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\Queue;
use Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\FileLock;
use Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue\ProcessRunner;

$file = tempnam(sys_get_temp_dir(), 'acs-queue-');
$db = new Joomla\Database\Sqlite\SqliteDriver(['database' => $file, 'prefix' => 't_', 'driver' => 'sqlite']);
try {
    $db->setQuery('CREATE TABLE t_attachments (id INTEGER PRIMARY KEY)')->execute();
    $db->transactionStart();
    for ($i = 1; $i <= 1005; $i++) {
        $db->setQuery('INSERT INTO t_attachments VALUES (' . $i . ')')->execute();
    }
    $db->transactionCommit();
    $queue = new Queue($db);
    $queue->install();
    check($queue->enqueue() === 1005, 'Enqueue more than two keyset pages without losing records');
    check($queue->enqueue() === 0, 'Repeated enqueue does not duplicate jobs');
    check($queue->counts()['pending'] === 1005, 'Persist initial progress');
    $id = $queue->claim();
    check($id === 1, 'Claim jobs in a stable order');
    $queue->finish($id, 'done');
    check($queue->claim() === 2, 'Claim next job');
    unset($queue);
    $db->disconnect();
    $db = new Joomla\Database\Sqlite\SqliteDriver(['database' => $file, 'prefix' => 't_', 'driver' => 'sqlite']);
    $queue = new Queue($db);
    $queue->install();
    check($queue->counts()['done'] === 1 && $queue->counts()['running'] === 1, 'Progress survives a new database connection');
    $queue->recoverInterrupted();
    check($queue->failures()[0]['error_code'] === 'interrupted', 'Interrupted job is identified without silently skipping it');
    check($queue->claim() === 3, 'Resume pending jobs without redoing completed jobs');
    $queue->finish(3, 'failed', 'timeout');
    check(count($queue->failures()) === 2, 'Failure report preserves both interrupted and timed-out jobs');
    check(count($queue->failures(2)) === 1, 'Failure report supports keyset paging');
    $queue->retry();
    check($queue->claim() === 2, 'Only explicit retry returns failed jobs to pending');
    $queue->finish(2, 'skipped');
    $queue->refresh();
    check($queue->claim() === 1, 'Explicit refresh reindexes completed jobs');
    $queue->finish(1, 'done');
    $columns = $db->getTableColumns('t_attachmentscontent_queue');
    check(!isset($columns['text']) && !isset($columns['filename']), 'Queue metadata does not duplicate extracted text or filenames');
    $lock = new FileLock($file . '.lock');
    rejects(fn() => new FileLock($file . '.lock'), 'Concurrent supervisor/worker is rejected');
    unset($lock);
    $lock = new FileLock($file . '.lock');
    unset($lock);
    check(true, 'OS releases the lock for a resumed invocation');

    $runner = new ProcessRunner();
    check($runner->run([PHP_BINARY, '-r', 'exit(0);'], 5) === ['done', ''], 'Successful child is recorded');
    check($runner->run([PHP_BINARY, '-r', 'exit(10);'], 5) === ['skipped', ''], 'Unsupported child is distinguished from failure');
    check($runner->run([PHP_BINARY, '-r', 'exit(20);'], 5) === ['failed', 'extraction_failed'], 'Extraction failure has a stable safe code');
    check($runner->run([PHP_BINARY, '-r', 'exit(21);'], 5) === ['failed', 'empty_text'], 'Empty or scanned document is visible in failure report');
    check($runner->run([PHP_BINARY, '-r', 'exit(22);'], 5) === ['failed', 'index_failed'], 'Indexing failure is recorded');
    check($runner->run([PHP_BINARY, '-r', 'undefined_acs_function();'], 5) === ['failed', 'worker_failed'], 'Fatal child error cannot crash the supervisor');
    check($runner->run([PHP_BINARY, '-d', 'memory_limit=16M', '-r', '$x = str_repeat("x", 128 * 1024 * 1024);'], 5) === ['failed', 'worker_failed'], 'Child memory exhaustion cannot exhaust supervisor memory');
    check($runner->run([PHP_BINARY, '-r', 'sleep(3);'], 0.1) === ['failed', 'timeout'], 'Hung child is terminated using wall-clock timeout');
    check($runner->run([PHP_BINARY, '-r', 'fwrite(STDOUT, str_repeat("x", 131072)); fwrite(STDERR, str_repeat("y", 131072));'], 5) === ['done', ''], 'Worker output is drained without retaining sensitive data');
    check($runner->run([PHP_BINARY, '-r', 'exit(0);'], 5) === ['done', ''], 'Supervisor continues after fatal error, memory exhaustion and timeout');
} finally {
    $db->disconnect();
    @unlink($file);
    @unlink($file . '.lock');
}
echo "$testCount queue and process-isolation checks passed.\n";
