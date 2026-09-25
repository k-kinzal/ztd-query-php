<?php

declare(strict_types=1);

namespace Requirements\Source;

use RuntimeException;

/**
 * Reads a local source document within the 16 MiB limit.
 */
final class LocalFile
{
    /**
     * Reads a file.
     *
     * @param string $path The absolute path
     *
     * @return string The contents; an empty string for a directory
     *
     * @throws RuntimeException When the file cannot be read or exceeds 16 MiB
     */
    public function read(string $path): string
    {
        if (is_dir($path)) {
            return '';
        }
        $content = is_file($path) && is_readable($path) ? file_get_contents($path, false, null, 0, 16777217) : false;
        if ($content === false || strlen($content) > 16777216) {
            throw new RuntimeException('Unreadable resource or 16 MiB size limit exceeded.');
        }
        return $content;
    }
}
