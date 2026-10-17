<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin\Storage;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Registry\Registry;

/**
 * Resolves datafile names against the emulator's tablespace files.
 *
 * Both slash spellings separate directories. Undo paths reject relative directories
 * before the common filename checks. Circular references, filename length, extension
 * and location diagnostics accumulate in that order. Names differing only by leading dot directories
 * refer to one file. No host filesystem is consulted. Verified against isolated MySQL
 * servers through CREATE TABLESPACE and SHOW WARNINGS.
 *
 * @visibility MySqlMemory
 */
final class DatafileChecks
{
    /**
     * Answers the engine path without discarding the original spelling used by diagnostics.
     */
    public function path(string $file): string
    {
        return str_replace('\\', '/', explode("\0", $file, 2)[0]);
    }

    /**
     * Answers a file identity relative to the virtual data directory.
     */
    public function identity(string $file): string
    {
        $path = $this->path($file);
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return $path;
    }

    /**
     * Accumulates the filename and location problems in observation order.
     *
     * @return list<string>
     */
    public function reasons(string $file, string $extension, Registry $registry): array
    {
        $path = $this->path($file);
        $slash = strrpos($path, '/');
        $base = $slash === false ? $path : substr($path, $slash + 1);
        $reasons = [];
        $undo = $extension === '.ibu';
        if ($undo && $slash !== false && !str_starts_with($path, '/')) {
            $reasons[] = 'The ADD DATAFILE filepath for an UNDO TABLESPACE cannot be a relative path.';
        }
        if (str_contains($path, '/../') || str_ends_with($path, '/..')) {
            $reasons[] = 'The ADD DATAFILE filepath cannot contain circular directory references.';
        }
        if (strlen($base) <= strlen($extension)) {
            $reasons[] = 'The ADD DATAFILE filepath does not have a proper filename.';
        }
        if (!str_ends_with($base, $extension)) {
            $reasons[] = "The ADD DATAFILE filepath must end with '" . $extension . "'.";
        }
        if (str_contains($path, ':')) {
            array_push($reasons, SchemaError::WrongFileName->message($path), "Invalid use of ':'.");
        } else {
            $identity = $this->identity($file);
            array_push($reasons, ...$this->location($identity, $undo));
            if ($identity === '' && $path !== '') {
                $reasons[] = 'The ADD DATAFILE filepath already exists.';
            }
            foreach ($registry->tablespaces as $tablespace) {
                if ($this->identity($tablespace->file) === $identity) {
                    $reasons[] = 'The ADD DATAFILE filepath already exists.';
                    break;
                }
            }
        }

        return $reasons;
    }

    /**
     * Describes a normalized location in the virtual data directory.
     *
     * @return list<string>
     */
    public function location(string $identity, bool $undo): array
    {
        if (str_starts_with($identity, '/') || str_starts_with($identity, '../')) {
            $directory = !in_array(dirname($identity), ['/', '..'], true) ? ['The directory does not exist or is incorrect.'] : [];
            return [...$directory, $undo ? 'The UNDO DATAFILE location must be in a known directory.' : 'The DATAFILE location must be in a known directory.'];
        }
        if (str_contains($identity, '/')) {
            return $undo ? ['The directory does not exist or is incorrect.'] : ['The directory does not exist or is incorrect.', 'The DATAFILE location cannot be under the datadir.'];
        }
        return [];
    }

}
