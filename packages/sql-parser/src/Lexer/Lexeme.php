<?php

declare(strict_types=1);

namespace SqlParser\Lexer;

/**
 * A terminal a dialect scanner recognised, before it is numbered against a grammar.
 *
 * @visibility root
 */
final class Lexeme
{
    /**
     * @param string $name Terminal name as the grammar spells it
     * @param string $text Text as written in the SQL
     * @param int $offset Byte offset of the first character
     */
    public function __construct(
        public readonly string $name,
        public readonly string $text,
        public readonly int $offset,
    ) {
    }

    /**
     * Answers the byte offset just past the lexeme.
     *
     * @return int End offset
     */
    public function end(): int
    {
        return $this->offset + strlen($this->text);
    }
}
