<?php

declare(strict_types=1);

namespace Deriver\Deriver\Sources;

/**
 * Lexical source path identities shared by collection, indexing and selectors.
 * @visibility root
 */
final class Path
{
    /**
     * Normalizes separators and dot components without accessing the filesystem.
     */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..' && $parts !== [] && end($parts) !== '..') {
                array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }
        return (str_starts_with($path, '/') ? '/' : '') . implode('/', $parts);
    }
}
