<?php

declare(strict_types=1);

namespace SqlParser\Lexer;

/**
 * One terminal as the lexer read it from the SQL text.
 *
 * A token carries the whitespace and comments written before it, so a run of
 * tokens spells the text it was read from, byte for byte. The end marker and
 * other tokens a lexer synthesises have no text and stand at the offset where
 * the lexer produced them, which is how the trivia before them still has an
 * owner.
 *
 * @visibility public
 *
 * @example Reading a token's position
 *     $token = new \SqlParser\Lexer\Token(7, 'SELECT_SYM', 'SELECT', 0);
 *     $token->end() // => 6
 * @example Writing a token back as it was written
 *     $token = new \SqlParser\Lexer\Token(7, 'NUM', '1', 9, '   ');
 *     $token->toString() // => '   1'
 */
final class Token
{
    /**
     * @param int $symbol Terminal number in the grammar's symbol table
     * @param string $name Terminal name as the grammar spells it
     * @param string $text Text as written in the SQL
     * @param int $offset Byte offset of the first character
     * @param string $leading Whitespace and comments written before the token
     */
    public function __construct(
        public readonly int $symbol,
        public readonly string $name,
        public readonly string $text,
        public readonly int $offset,
        public readonly string $leading = '',
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

    /**
     * Answers the text the token was read from, the trivia before it included.
     *
     * @return string The trivia written before the token followed by its text
     */
    public function toString(): string
    {
        return $this->leading . $this->text;
    }
}
