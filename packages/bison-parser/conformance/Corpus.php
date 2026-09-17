<?php

declare(strict_types=1);

namespace Conformance;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Finds the grammar files under the directories given.
 */
final class Corpus
{
    /**
     * Lists every `.y` and `.yy` file under the roots, in a stable order.
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
                if ($entry instanceof SplFileInfo && in_array($entry->getExtension(), ['y', 'yy'], true)) {
                    $files[] = $entry->getPathname();
                }
            }
        }
        sort($files);

        return $files;
    }
}
