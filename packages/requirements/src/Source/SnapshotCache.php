<?php

declare(strict_types=1);

namespace Requirements\Source;

use RuntimeException;

/**
 * Stores a verified source document as its pinned local snapshot.
 *
 * The document is written to a temporary file beside the snapshot and renamed into place, so
 * a snapshot is never left half written.
 */
final class SnapshotCache
{
    /**
     * Writes a snapshot, creating its directory.
     *
     * @param string $path The absolute snapshot path
     * @param string $content The verified document
     *
     * @throws RuntimeException When the directory, the temporary file or the snapshot cannot be written
     */
    public function write(string $path, string $content): void
    {
        $parent = dirname($path);
        if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
            throw new RuntimeException("Cannot create source cache directory: $parent");
        }
        $temporary = tempnam($parent, '.source-');
        if ($temporary === false) {
            throw new RuntimeException("Cannot create source cache: $path");
        }
        try {
            if (file_put_contents($temporary, $content) === false || !rename($temporary, $path)) {
                throw new RuntimeException("Cannot populate source cache: $path");
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
