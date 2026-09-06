<?php

declare(strict_types=1);

namespace SqlFaker\Coverage;

/**
 * Owns atomic snapshot replacement and rejects a second writer for the same key.
 *
 * @visibility root
 */
final class CoverageSnapshotStore
{
    /**
     * @var resource|null
     */
    private $lock = null;
    /**
     * Snapshot filename for this compatibility key.
     */
    public readonly string $path;

    /**
     * Locks a compatibility key before reading or generating anything.
     *
     * @throws CoverageException When storage or the exclusive lock is unavailable
     */
    public function __construct(string $directory, string $key)
    {
        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new CoverageException('Cannot create coverage directory: ' . $directory);
        }
        if (!is_writable($directory)) {
            throw new CoverageException('Coverage directory is not writable: ' . $directory);
        }
        $this->path = $directory . '/' . $key . '.json';
        $lock = @fopen($directory . '/' . $key . '.lock', 'c');
        if ($lock === false) {
            throw new CoverageException('Cannot open coverage writer lock.');
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new CoverageException('Another coverage writer owns this compatibility key.');
        }
        $this->lock = $lock;
    }

    /**
     * Releases the writer without implicitly flushing or masking an original failure.
     */
    public function __destruct()
    {
        if (isset($this->lock)) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
        }
    }

    /**
     * Reads only this key's snapshot, never a fuzzer corpus.
     *
     * @throws CoverageException When an existing snapshot cannot be read
     */
    public function read(): ?string
    {
        if (!file_exists($this->path)) {
            return null;
        }
        $json = @file_get_contents($this->path);
        if ($json === false) {
            throw new CoverageException('Cannot read coverage snapshot: ' . $this->path);
        }
        return $json;
    }

    /**
     * Replaces a complete file within the same filesystem; a failed write keeps history intact.
     *
     * @throws CoverageException When the checkpoint cannot be persisted
     */
    public function write(string $json): void
    {
        $temporary = @tempnam(dirname($this->path), '.coverage-');
        if ($temporary === false) {
            throw new CoverageException('Cannot create coverage checkpoint temporary file.');
        }
        try {
            if (dirname($temporary) !== realpath(dirname($this->path))) {
                throw new CoverageException('Checkpoint temporary file is outside the snapshot filesystem.');
            }
            if (@file_put_contents($temporary, $json) !== strlen($json)) {
                throw new CoverageException('Cannot write complete coverage checkpoint.');
            }
            if (!@rename($temporary, $this->path)) {
                throw new CoverageException('Cannot atomically replace coverage checkpoint.');
            }
        } finally {
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
    }
}
