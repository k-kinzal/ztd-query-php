<?php

declare(strict_types=1);

namespace SqlCatalog\Cli;

use SqlCatalog\Core\Reporter\CatalogArtifacts;

/**
 * Writes what a reporter produced into a directory.
 *
 * @visibility root
 */
final class ArtifactWriter
{
    /**
     * The files written, in the order they were written.
     *
     * A reporter may name its artifacts with a path rather than a bare name —
     * a site of pages does — so the directory each file is written into is
     * made before the file is.
     *
     * @return list<string>
     * @throws WriteFailureException When the directory or one of the files cannot be written
     */
    public function write(string $directory, CatalogArtifacts $artifacts): array
    {
        $this->prepare($directory);

        $written = [];
        foreach ($artifacts->all() as $name => $contents) {
            $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            $this->prepare(dirname($path));
            if (file_put_contents($path, $contents) === false) {
                throw new WriteFailureException($path, 'the file could not be written');
            }
            $written[] = $path;
        }

        return $written;
    }

    /**
     * Makes sure the directory exists and can be written into.
     *
     * Every reason it cannot be is checked before anything is attempted, so a
     * failure arrives as one explained exception rather than as a warning from
     * whichever call happened to run into it first.
     *
     * @throws WriteFailureException When the directory is missing and cannot be created
     */
    public function prepare(string $directory): void
    {
        if (is_dir($directory)) {
            if (!is_writable($directory)) {
                throw new WriteFailureException($directory, 'the directory cannot be written into');
            }

            return;
        }
        if (file_exists($directory)) {
            throw new WriteFailureException($directory, 'a file of that name is in the way');
        }
        if (!mkdir($directory, 0o777, true) && !is_dir($directory)) {
            throw new WriteFailureException($directory, 'the directory could not be created');
        }
    }
}
