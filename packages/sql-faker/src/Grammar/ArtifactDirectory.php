<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use RuntimeException;

/**
 * Answers which directory a generated artifact is written into.
 *
 * A build names the file it wants to produce, not the directory that has to
 * exist first, and a checkout may not carry an empty directory at all. Asking
 * the filesystem to create the directory is therefore part of naming it, and a
 * path that can never become a writable directory is a failure the build has
 * to hear about before it has staged anything.
 *
 * @visibility root
 */
final class ArtifactDirectory
{
    /**
     * Names the directory an artifact is written into, creating it when the filesystem allows.
     *
     * The nearest ancestor that already exists decides the answer: if it is not
     * a writable directory then nothing below it can become one, and reporting
     * that ancestor says why rather than only that the attempt failed.
     *
     * @param string $artifactPath Path of the file that is about to be written
     *
     * @return string Directory that now exists and can be written to
     *
     * @throws RuntimeException When the path cannot become a writable directory
     */
    public function prepared(string $artifactPath): string
    {
        $directory = dirname($artifactPath);

        $existing = $directory;
        while (!file_exists($existing) && dirname($existing) !== $existing) {
            $existing = dirname($existing);
        }
        if (!is_dir($existing) || !is_writable($existing)) {
            throw new RuntimeException("Failed to create {$directory}: {$existing} is not a writable directory");
        }
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("Failed to create {$directory}");
        }

        return $directory;
    }
}
