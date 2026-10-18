<?php

declare(strict_types=1);

namespace Deriver\Deriver\Sources;

/**
 * Captured source contents and their path, with no file access or parsing.
 * @visibility root
 * @example Describing source contents in memory
 *     (new \Deriver\Deriver\Sources\Source('app.php', '<?php'))->path // => 'app.php'
 */
final class Source
{
    /**
     * @param string $path Source path
     * @param string $contents Captured bytes
     * @param bool $declarationsOnly Whether only declarations are available and callable bodies cannot be used
     */
    public function __construct(
        public readonly string $path,
        public readonly string $contents,
        public readonly bool $declarationsOnly = false,
    ) {
    }
}
