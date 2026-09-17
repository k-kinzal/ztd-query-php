<?php

declare(strict_types=1);

namespace LemonParser\Ast;

/**
 * A position in a grammar file, counted from one.
 *
 * Lemon reports line numbers; the column is added so that a tool can point
 * at the exact character. Lines blanked by the preprocessor keep their
 * newlines, so positions in the tree are positions in the original file.
 *
 * @visibility public
 *
 * @example Reading where a rule begins
 *     $file = (new \LemonParser\Parser())->parse("%token_prefix TK_\nexpr ::= expr PLUS expr.\n");
 *     (string) $file->rules()[0]->location // => "2:1"
 */
final class Location
{
    /**
     * @param int $line Line number, counted from one
     * @param int $column Column number, counted from one
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
