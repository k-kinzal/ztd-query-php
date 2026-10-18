<?php

declare(strict_types=1);

namespace Deriver\Deriver\Sources;

use Deriver\Exception\CollectorException;

/**
 * Reads explicitly selected local files without executing their contents.
 * @visibility root
 * @example Collecting an empty selection
 *     count((new \Deriver\Deriver\Sources\Collector())->collect([])) // => 0
 */
final class Collector
{
    /**
     * @param list<string> $files Local file paths already selected by the caller
     * @return Sources Captured contents in the supplied order
     * @throws CollectorException If a path is unreadable, uses a stream wrapper, or is repeated
     */
    public function collect(array $files): Sources
    {
        $sources = [];
        $seen = [];
        foreach ($files as $path) {
            if (str_contains($path, '://') || !is_file($path) || !is_readable($path)) {
                throw new CollectorException('Source must be a readable local file: ' . $path);
            }
            $normalized = Path::normalize($path);
            if (isset($seen[$normalized])) {
                throw new CollectorException('Source paths must be nonempty and unique: ' . $normalized);
            }
            $seen[$normalized] = true;
            set_error_handler(static function (int $severity, string $message) use ($path): never {
                throw new CollectorException('Cannot capture source ' . $path . ': ' . $message);
            });
            try {
                $contents = file_get_contents($path);
            } finally {
                restore_error_handler();
            }
            if ($contents === false) {
                throw new CollectorException('Cannot capture source: ' . $path);
            }
            $sources[] = new Source($normalized, $contents);
        }
        return new Sources($sources);
    }
}
