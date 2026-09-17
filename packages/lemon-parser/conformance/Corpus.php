<?php

declare(strict_types=1);

namespace Conformance;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Finds the grammar files under the directories given, and the defines to read each with.
 *
 * A file `NAME.defines` next to `NAME.y` lists one set of defines per
 * line, names separated by spaces; an empty line is the set with no
 * defines. Without such a file a grammar is read with no defines.
 */
final class Corpus
{
    /**
     * Lists every `.y` file under the roots, in a stable order.
     *
     * @param list<string> $roots Directories or files
     *
     * @return list<string> The grammar files
     */
    public function files(array $roots): array
    {
        $files = [];
        foreach ($roots as $root) {
            if (is_file($root)) {
                $files[] = $root;
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $entry) {
                if ($entry instanceof SplFileInfo && $entry->getExtension() === 'y') {
                    $files[] = $entry->getPathname();
                }
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Lists the sets of defines a grammar file is read with.
     *
     * @param string $path The grammar file
     *
     * @return list<list<string>> The sets, at least one
     */
    public function defineSets(string $path): array
    {
        $sidecar = preg_replace('/\.y$/', '.defines', $path) ?? $path;
        if (!is_file($sidecar)) {
            return [[]];
        }
        $sets = [];
        foreach (explode("\n", rtrim((string) file_get_contents($sidecar), "\n")) as $line) {
            $sets[] = array_values(array_filter(explode(' ', trim($line)), static fn (string $name): bool => $name !== ''));
        }

        return $sets;
    }
}
