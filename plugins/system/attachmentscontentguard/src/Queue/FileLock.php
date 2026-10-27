<?php
/** @license GPL-3.0-or-later */
namespace Danieldiazi\Plugin\System\AttachmentsContentGuard\Queue;

final class FileLock
{
    private $handle;
    public function __construct(string $path)
    {
        $this->handle = fopen($path, 'c');
        if (!$this->handle || !flock($this->handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($this->handle)) {
                fclose($this->handle);
            }
            $this->handle = null;
            throw new \RuntimeException('Another queue process is active, or the lock directory is not writable.');
        }
    }
    public function __destruct()
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
    }
}
