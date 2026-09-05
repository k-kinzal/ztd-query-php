<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Bison\Lexer;

/**
 * One lexeme read out of a Bison grammar file.
 *
 * The offset is kept alongside the value because the parser reports failures
 * against the grammar source, and a token that has been read is otherwise no
 * longer locatable in the file it came from.
 */
final class BisonToken
{
    /**
     * @param BisonTokenType $type Which lexeme was recognised
     * @param string|int $value The text it spelled, or its numeric value for a number
     * @param int $offset Where the lexeme starts in the grammar source
     */
    public function __construct(
        public readonly BisonTokenType $type,
        public readonly string|int $value,
        public readonly int $offset,
    ) {
    }

    /**
     * Reads the value as text.
     *
     * @return string The value, with a number rendered as its digits
     */
    public function asString(): string
    {
        return is_string($this->value) ? $this->value : (string) $this->value;
    }

    /**
     * Reads the value as a number.
     *
     * @return int The value, with text that names no number read as zero
     */
    public function asInt(): int
    {
        return is_int($this->value) ? $this->value : (int) $this->value;
    }
}
