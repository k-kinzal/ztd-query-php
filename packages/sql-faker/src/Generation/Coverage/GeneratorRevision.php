<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Coverage;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Identifies development implementations by source contents, never by dev-main alone.
 *
 * @visibility root
 */
final class GeneratorRevision
{
    private static ?string $digest = null;

    /**
     * Hashes implementation files once per process, outside per-input generation.
     */
    public static function current(): string
    {
        if (self::$digest !== null) {
            return self::$digest;
        }
        $root = dirname(__DIR__, 2);
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[$file->getPathname()] = hash_file('sha256', $file->getPathname());
            }
        }
        ksort($files);
        self::$digest = hash('sha256', implode('', array_values($files)));
        return self::$digest;
    }
}
