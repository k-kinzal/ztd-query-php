<?php

declare(strict_types=1);

namespace SqlCatalog\Source;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Finds the PHP files under the paths a run was pointed at.
 *
 * @visibility root
 */
final class SourceScanner
{
    /**
     * The directory names skipped unless a path names one directly.
     */
    public const SKIPPED = ['vendor', 'node_modules', '.git', 'build'];

    private string $root;

    /**
     * @var list<string>
     */
    private array $excluded;

    /**
     * @param string $root The directory reported paths are relative to
     * @param list<string> $excluded Patterns, matched against the reported path, to leave out
     */
    public function __construct(string $root, array $excluded = [])
    {
        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
        $this->excluded = $excluded;
    }

    /**
     * The files under the given paths, in a stable order.
     *
     * @param list<string> $paths
     * @return list<SourceFile>
     * @throws SourceScanException When a path does not exist or cannot be read
     */
    public function scan(array $paths): array
    {
        $found = [];
        foreach ($paths as $path) {
            foreach ($this->scanOne($path) as $file) {
                $found[$file->path] = $file;
            }
        }
        ksort($found);

        return array_values($found);
    }

    /**
     * The files under one path.
     *
     * @return list<SourceFile>
     * @throws SourceScanException When the path does not exist or cannot be read
     */
    public function scanOne(string $path): array
    {
        if (is_file($path)) {
            $file = $this->read($path);

            return $file === null ? [] : [$file];
        }
        if (!is_dir($path)) {
            throw new SourceScanException($path, 'no such file or directory');
        }

        $files = [];
        foreach ($this->walk($path) as $found) {
            $file = $this->read($found);
            if ($file !== null) {
                $files[] = $file;
            }
        }

        return $files;
    }

    /**
     * Every PHP file under a directory, skipping the directories a scan never wants.
     *
     * @return list<string>
     */
    public function walk(string $directory): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
                static fn (SplFileInfo $entry): bool => !$entry->isDir()
                    || !in_array($entry->getFilename(), self::SKIPPED, true),
            ),
        );

        $paths = [];
        foreach ($iterator as $entry) {
            if ($entry instanceof SplFileInfo && $entry->isFile() && $entry->getExtension() === 'php') {
                $paths[] = $entry->getPathname();
            }
        }
        sort($paths);

        return $paths;
    }

    /**
     * The file read and named relative to the root, or null when it is excluded.
     *
     * @throws SourceScanException When the file cannot be read
     */
    public function read(string $path): ?SourceFile
    {
        $reported = $this->relative($path);
        if ($this->isExcluded($reported)) {
            return null;
        }
        $code = @file_get_contents($path);
        if ($code === false) {
            throw new SourceScanException($path, 'the file could not be read');
        }

        return new SourceFile($reported, $code);
    }

    /**
     * Whether a reported path matches one of the exclusion patterns.
     */
    public function isExcluded(string $reported): bool
    {
        foreach ($this->excluded as $pattern) {
            if (fnmatch($pattern, $reported) || str_starts_with($reported, rtrim($pattern, '/') . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The path as the catalog reports it.
     */
    public function relative(string $path): string
    {
        $absolute = realpath($path);
        $target = $absolute === false ? $path : $absolute;
        $root = realpath($this->root);
        $prefix = ($root === false ? $this->root : $root) . DIRECTORY_SEPARATOR;

        return str_starts_with($target, $prefix) ? substr($target, strlen($prefix)) : $target;
    }
}
