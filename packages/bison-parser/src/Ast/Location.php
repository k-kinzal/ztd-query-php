<?php

declare(strict_types=1);

namespace BisonParser\Ast;

/**
 * Where a node begins in the grammar file, as a line and column counted from one.
 *
 * @visibility public
 *
 * @example Reading a position
 *     $location = new \BisonParser\Ast\Location(12, 3);
 *     (string) $location // => '12:3'
 */
final class Location
{
    /**
     * @param int $line Line number counted from one
     * @param int $column Byte column counted from one
     */
    public function __construct(
        public readonly int $line,
        public readonly int $column,
    ) {
    }

    /**
     * Writes the position as `line:column`.
     *
     * @return string The position
     */
    public function __toString(): string
    {
        return "{$this->line}:{$this->column}";
    }
}
