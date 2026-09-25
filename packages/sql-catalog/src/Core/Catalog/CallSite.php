<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Catalog;

/**
 * Where in the analyzed source a statement is issued.
 *
 * @visibility root
 */
final class CallSite
{
    /**
     * The sink reported for a database call whose receiver could not be identified.
     */
    public const UNMATCHED = 'unmatched';

    /**
     * @param string $file The path the statement was found in, relative to the analysis root
     * @param int $line The line the call is written on
     * @param string $function The enclosing function, as `Class::method`, `function` or `{main}`
     * @param string $sink The identifier of the database call that was matched
     */
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly string $function,
        public readonly string $sink,
    ) {
    }

    /**
     * The site written the way an editor jumps to it.
     */
    public function display(): string
    {
        return $this->file . ':' . $this->line;
    }
}
