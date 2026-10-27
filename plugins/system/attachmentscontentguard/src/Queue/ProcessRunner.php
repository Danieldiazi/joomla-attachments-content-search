<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue;

/** One OS process per attachment. Output is discarded by the OS, never stored as document metadata. */
final class ProcessRunner
{
    public function run(array $command, float $timeout): array
    {
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('Queue processing requires proc_open in CLI PHP.');
        }
        $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $process = proc_open($command, [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new \RuntimeException('Cannot start PHP worker.');
        }
        $start = microtime(true);
        $timedOut = false;
        while (true) {
            $state = proc_get_status($process);
            if (!$state['running']) {
                break;
            }
            if (microtime(true) - $start >= $timeout) {
                $timedOut = true;
                proc_terminate($process);
                usleep(100000);
                $state = proc_get_status($process);
                if ($state['running']) {
                    proc_terminate($process, 9);
                }
                // Never claim the next job while a worker is still alive.
                $deadline = microtime(true) + 2;
                do {
                    usleep(20000);
                    $state = proc_get_status($process);
                } while ($state['running'] && microtime(true) < $deadline);
                if ($state['running']) {
                    throw new \RuntimeException('Worker could not be stopped; stop it before resuming.');
                }
                break;
            }
            usleep(20000);
        }
        $code = $state['exitcode'];
        $closed = proc_close($process);
        if ($code < 0) {
            $code = $closed;
        }
        if ($timedOut) {
            return ['failed', 'timeout'];
        }
        return match ($code) {
            0 => ['done', ''],
            10 => ['skipped', ''],
            11 => ['excluded', 'access_excluded'],
            20 => ['failed', 'extraction_failed'],
            21 => ['failed', 'empty_text'],
            22 => ['failed', 'index_failed'],
            default => ['failed', 'worker_failed'],
        };
    }
}
