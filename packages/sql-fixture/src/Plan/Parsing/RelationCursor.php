<?php

declare(strict_types=1);

namespace SqlFixture\Plan\Parsing;

use SqlFixture\Plan\PlanSyntaxException;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;

/**
 * Tracks the unread position of one DBML relation statement.
 *
 * @visibility root
 */
final class RelationCursor
{
    private const IDENTIFIER = '/\G(?:`([^`]+)`|"([^"]+)"|([A-Za-z_][A-Za-z0-9_$]*))/';

    /**
     * Opens a statement at its first character.
     */
    public function __construct(public readonly string $source, public int $offset = 0)
    {
    }

    /**
     * Consumes a quoted or unquoted identifier at the current cursor.
     * @throws PlanSyntaxException
     */
    public function readIdentifier(string $expected): string
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
     * Consumes a supported relation operator.
     * @throws PlanSyntaxException
     */
    public function readOperator(): RelationKind
    {
        $character = $this->peek();
        $kind = $character === null ? null : RelationKind::tryFrom($character);

        if ($kind === null) {
            throw PlanSyntaxException::unexpected($this->source, $this->offset, "one of '<', '>' or '-'");
        }

        $this->offset++;

        return $kind;
    }

    /**
     * Consumes an optional endpoint marker when present.
     */
    public function readOptionalMarker(): bool
    {
        if ($this->peek() !== '?') {
            return false;
        }

        $this->offset++;

        return true;
    }

    /**
     * Advances the cursor past whitespace.
     */
    public function skipWhitespace(): void
    {
        while (($character = $this->peek()) !== null && trim($character) === '') {
            $this->offset++;
        }
    }

    /**
     * Rejects trailing input after the relation statement.
     * @throws PlanSyntaxException
     */
    public function expectEnd(): void
    {
        if ($this->offset < strlen($this->source)) {
            throw PlanSyntaxException::unexpected($this->source, $this->offset, 'the end of the relation');
        }
    }

    /**
     * Reads the next character without advancing the cursor.
     */
    public function peek(): ?string
    {
        return $this->source[$this->offset] ?? null;
    }
}
