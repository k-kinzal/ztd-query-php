<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Syntax;

use SqlFixture\Plan\PlanSyntaxException;

/**
 * Holds a place in one plan statement while it is being read.
 *
 * Reading a statement is a walk through it, and where the walk has got to is
 * one fact that every step both consults and moves. Giving that fact an owner
 * is what lets the steps be written as separate readers rather than as parts
 * of one procedure that share a field.
 */
final class PlanCursor
{
    private const IDENTIFIER = '/\G(?:`([^`]+)`|"([^"]+)"|([A-Za-z_][A-Za-z0-9_$]*))/';

    private int $offset = 0;

    /**
     * @param string $source Statement being read
     */
    public function __construct(private readonly string $source)
    {
    }

    /**
     * Answers the statement being read, for an error that has to quote it.
     *
     * @return string The statement as it was given
     */
    public function source(): string
    {
        return $this->source;
    }

    /**
     * Answers how far into the statement the walk has got.
     *
     * @return int Offset of the next character
     */
    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Answers the next character without moving past it.
     *
     * @return string|null The character, or null at the end of the statement
     */
    public function peek(): ?string
    {
        return $this->source[$this->offset] ?? null;
    }

    /**
     * Moves past the next character when it is the one expected.
     *
     * @param string $character Character to accept
     *
     * @return bool True when it was there and the walk moved past it
     */
    public function accept(string $character): bool
    {
        if ($this->peek() !== $character) {
            return false;
        }
        $this->offset++;

        return true;
    }

    /**
     * Moves past any run of whitespace.
     */
    public function skipWhitespace(): void
    {
        while (($character = $this->peek()) !== null && trim($character) === '') {
            $this->offset++;
        }
    }

    /**
     * Reads an identifier, quoted with backticks or double quotes or written bare.
     *
     * @param string $expected What the caller was looking for, for the error message
     *
     * @return string The identifier, without its quotes
     *
     * @throws PlanSyntaxException When no identifier is written here
     */
    public function takeIdentifier(string $expected): string
    {
        if (preg_match(self::IDENTIFIER, $this->source, $matches, 0, $this->offset) !== 1) {
            throw PlanSyntaxException::unexpected($this->source, $this->offset, $expected);
        }

        $this->offset += strlen($matches[0]);
        foreach ([1, 2, 3] as $group) {
            if (isset($matches[$group]) && $matches[$group] !== '') {
                return $matches[$group];
            }
        }

        throw PlanSyntaxException::unexpected($this->source, $this->offset, $expected);
    }

    /**
     * Refuses anything written after the statement has been read.
     *
     * @throws PlanSyntaxException When the statement carries more than was read
     */
    public function expectEnd(): void
    {
        if ($this->offset < strlen($this->source)) {
            throw PlanSyntaxException::unexpected($this->source, $this->offset, 'the end of the relation');
        }
    }

    /**
     * Reports the character that was not expected here.
     *
     * @param string $expected What the caller was looking for
     *
     * @return PlanSyntaxException Exception naming the place and what was wanted
     */
    public function unexpected(string $expected): PlanSyntaxException
    {
        return PlanSyntaxException::unexpected($this->source, $this->offset, $expected);
    }
}
