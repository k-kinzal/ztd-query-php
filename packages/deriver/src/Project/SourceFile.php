<?php

declare(strict_types=1);

namespace Deriver\Project;

/**
 * A captured source file, read as data and never included.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Project\SourceFile('app.php', '<?php'))->path // => 'app.php'
 */
final class SourceFile
{
    /**
     * @param string $path path
     * @param string $contents contents
     * @param bool $declarationsOnly Whether callable bodies are unavailable and only declarations are trusted
     */
    public function __construct(
        public readonly string $path,
        public readonly string $contents,
        public readonly bool $declarationsOnly = false,
    ) {
    }
}
