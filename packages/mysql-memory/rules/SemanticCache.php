<?php

declare(strict_types=1);

namespace Rules;

use PHPStan\Analyser\ResultCache\ResultCacheMetaExtension;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Invalidates the coverage rule when dependency models or generator declarations change.
 *
 * @visibility Rules
 */
final class SemanticCache implements ResultCacheMetaExtension
{
    /**
     * Identifies the additional dependency set to PHPStan.
     */
    public function getKey(): string
    {
        return 'mysql-memory-semantic-generators';
    }

    /**
     * Includes file names so additions and removals invalidate a previously successful run.
     *
     * @throws RuntimeException When a model or generator file cannot be hashed
     */
    public function getHash(): string
    {
        $files = [];
        foreach (['vendor/k-kinzal/sql-semantics/src', 'vendor/k-kinzal/sql-semantics-mysql/src', 'tests/Generator'] as $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/' . $directory)) as $file) {
                if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                    $hash = hash_file('sha256', $file->getPathname());
                    if ($hash === false) {
                        throw new RuntimeException('Cannot hash ' . $file->getPathname());
                    }
                    $files[$file->getPathname()] = $hash;
                }
            }
        }
        ksort($files);
        return hash('sha256', serialize($files));
    }
}
