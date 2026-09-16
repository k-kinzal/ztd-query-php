<?php

declare(strict_types=1);

namespace SqlParser\Lexer;

/**
 * One terminal as the lexer read it from the SQL text.
 *
 * The end marker and other tokens a lexer synthesises have no text and stand
 * at the offset where the lexer produced them.
 *
 * @visibility public
 *
 * @example Reading a token's position
 *     $token = new \SqlParser\Lexer\Token(7, 'SELECT_SYM', 'SELECT', 0);
 *     $token->end() // => 6
 */
final class Token
{
    /**
     * @param int $symbol Terminal number in the grammar's symbol table
     * @param string $name Terminal name as the grammar spells it
     * @param string $text Text as written in the SQL
     * @param int $offset Byte offset of the first character
     */
    public function __construct(
        public readonly int $symbol,
        public readonly string $name,
        public readonly string $text,
        public readonly int $offset,
    ) {
    }

    /**
     * Answers the byte offset just past the token.
     *
     * @return int End offset
     */
    public function end(): int
    {
        return $this->offset + strlen($this->text);
    }

    /**
     * Reports whether the token is a given terminal.
     *
     * @param string $name Terminal name
     *
     * @return bool True when the names match
     */
    public function is(string $name): bool
    {
        return $this->name === $name;
    }
}
