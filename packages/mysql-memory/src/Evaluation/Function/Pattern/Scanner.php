<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;

/**
 * Reads the characters of a regular expression one at a time, and knows where the last one read stands.
 *
 * A syntax error names the line and the character in that line of the last character read,
 * counting from 1: a carriage return, a line feed that does not follow one, U+0085 and U+2028
 * begin a new line (verified on a live 8.4 server).
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Scanner
{
    /**
     * The index of the next character.
     */
    public int $index = 0;

    /**
     * The line of the last character read.
     */
    public int $line = 1;

    /**
     * The position in its line of the last character read.
     */
    public int $column = 0;

    /**
     * @param list<string> $characters The characters of the expression
     */
    public function __construct(public readonly array $characters)
    {
    }

    /**
     * Answers a scanner over the characters of a UTF-8 expression.
     */
    public static function of(string $pattern): self
    {
        return new self(mb_str_split($pattern, 1, 'UTF-8'));
    }

    /**
     * Answers a character ahead without reading it, or null past the end.
     */
    public function peek(int $ahead = 0): ?string
    {
        return $this->characters[$this->index + $ahead] ?? null;
    }

    /**
     * Reads the next character, or answers null at the end without moving.
     *
     * @phpstan-impure
     */
    public function next(): ?string
    {
        $character = $this->characters[$this->index] ?? null;
        if ($character === null) {
            return null;
        }
        $previous = $this->characters[$this->index - 1] ?? '';
        $this->index++;
        if (in_array($character, ["\r", "\u{85}", "\u{2028}"], true) || ($character === "\n" && $previous !== "\r")) {
            $this->line++;
            $this->column = 0;
        } else {
            $this->column++;
        }

        return $character;
    }

    /**
     * Tells whether every character was read.
     */
    public function done(): bool
    {
        return $this->index >= count($this->characters);
    }

    /**
     * Answers the syntax error at the last character read (ER_REGEXP_RULE_SYNTAX).
     */
    public function syntax(): SqlError
    {
        return DataError::RegexpRuleSyntax->error($this->line, $this->column);
    }
}
