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
 * A token built for a tree that is being rewritten, or carried over into one
 * from somewhere else, stands nowhere in the text under it: it is detached,
 * and carries a negative offset to say so.
 *
 * @visibility public
 *
 * @example Reading a token's position
 *     $token = new \SqlParser\Lexer\Token(7, 'SELECT_SYM', 'SELECT', 0);
 *     $token->end() // => 6
 * @example Writing a token back as it was written
 *     $token = new \SqlParser\Lexer\Token(7, 'NUM', '1', 9, '   ');
 *     $token->toString() // => '   1'
 * @example Marking a token as standing nowhere in the text
 *     $token = (new \SqlParser\Lexer\Token(7, 'SELECT_SYM', 'SELECT', 0))->detached();
 *     $token->isDetached() // => true
 */
final class Token
{
    /**
     * Offset of a token that stands nowhere in a text.
     */
    public const DETACHED = -1;

    /**
     * @param int $symbol Terminal number in the grammar's symbol table
     * @param string $name Terminal name as the grammar spells it
     * @param string $text Text as written in the SQL
     * @param int $offset Byte offset of the first character, or `Token::DETACHED`
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
     * Reports whether the token stands nowhere in a text.
     *
     * @return bool True when the token was built or carried over rather than read
     */
    public function isDetached(): bool
    {
        return $this->offset < 0;
    }

    /**
     * Answers the same token, standing nowhere in a text.
     *
     * A token grafted into a tree parsed from another text keeps an offset
     * that means nothing there, and two such tokens can read as though they
     * touched when they never did. Detaching one says that it stands nowhere.
     *
     * @return self The token, detached
     */
    public function detached(): self
    {
        return $this->isDetached() ? $this : new self($this->symbol, $this->name, $this->text, self::DETACHED);
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
