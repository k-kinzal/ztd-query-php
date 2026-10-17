<?php

declare(strict_types=1);

namespace Tests\Fake;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Owns temporary source files for file discovery and public analyzer tests.
 */
final class SourceDirectory
{
    /**
     * Absolute directory removed with this fixture.
     */
    public readonly string $path;

    /**
     * Creates an isolated directory for source fixtures.
     * @throws RuntimeException If the directory cannot be created or resolved
     */
    public function __construct()
    {
        $path = tempnam(sys_get_temp_dir(), 'deriver-files-');
        if ($path === false || !unlink($path) || !mkdir($path)) {
            throw new RuntimeException('Cannot create source fixture directory.');
        }
        $absolute = realpath($path);
        if ($absolute === false) {
            throw new RuntimeException('Cannot resolve source fixture directory.');
        }
        $this->path = str_replace('\\', '/', $absolute);
    }

    /**
     * Writes a source fixture and returns its absolute path.
     * @throws RuntimeException If the parent or file cannot be written
     */
    public function write(string $name, string $contents): string
    {
        $path = $this->path . '/' . $name;
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0777, true)) {
            throw new RuntimeException('Cannot create source fixture parent.');
        }
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException('Cannot write source fixture.');
        }
        return $path;
    }

    /**
     * Removes owned files, links and directories after a test finishes.
     */
    public function __destruct()
    {
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($entries as $entry) {
            if (!$entry instanceof SplFileInfo) {
                continue;
            }
            if ($entry->isDir() && !$entry->isLink()) {
                rmdir($entry->getPathname());
            } else {
                unlink($entry->getPathname());
            }
        }
        rmdir($this->path);
    }
}
