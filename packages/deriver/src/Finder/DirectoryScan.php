<?php

declare(strict_types=1);

namespace Deriver\Finder;

use Generator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Enumerates regular files while pruning excluded and linked entries.
 * @visibility root
 */
final class DirectoryScan
{
    /**
     * @param string $directory Absolute local root
     * @param list<PathPattern> $includes Root-relative include patterns
     * @param list<PathPattern> $excludes Root-relative exclude patterns
     * @return Generator<int, string, null, void> Selected paths without reading file contents
     */
    public function files(string $directory, array $includes, array $excludes): Generator
    {
        $prefix = rtrim($directory, '/') . '/';
        $entries = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            static function (SplFileInfo $file) use ($prefix, $excludes): bool {
                $relative = substr(str_replace('\\', '/', $file->getPathname()), strlen($prefix));
                return !$file->isLink()
                    && !PathPattern::matchesAny($excludes, $relative)
                    && (!$file->isDir() || !PathPattern::matchesAny($excludes, $relative . '/'));
            },
        );
        foreach (new RecursiveIteratorIterator($entries) as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            if ($includes === [] || PathPattern::matchesAny($includes, substr($path, strlen($prefix)))) {
                yield $path;
            }
        }
    }
}
