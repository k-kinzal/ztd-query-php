<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

/**
 * Normalizes local paths so a citation and the source URI can be compared.
 */
final class LocalPath
{
    /**
     * Resolves ".", ".." and repeated separators without touching the file system.
     *
     * @param string $path The path, with / or \\ separators
     *
     * @return string The path segments joined by /, without leading or trailing separators
     */
    public static function normalize(string $path): string
    {
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '' && $part !== '.') {
                $parts[] = $part;
            }
        }
        return implode('/', $parts);
    }
}
